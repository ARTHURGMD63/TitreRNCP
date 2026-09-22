<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Les deux fichiers d'installation décrivent-ils toujours la même base ?
 *
 * Ce test existe à cause d'une panne réelle. `db_setup.sql` — celui que le
 * README désigne, avec la mention « une seule importation suffit » — et
 * `install_mutualise.sql` se maintenaient à la main, chacun de son côté. Ils
 * ont divergé de quatre tables : `crm_clients`, `crm_interactions`,
 * `finance_mouvements` et `rappels_envoyes`. Toute installation faite en
 * suivant le README produisait donc un back-office qui tombait en erreur au
 * premier clic, et personne ne pouvait le savoir avant d'y arriver.
 *
 * Aucune base n'est nécessaire : on lit les fichiers. Le test tourne donc en
 * intégration continue, où il n'y a que pdo_sqlite.
 */
final class SchemaTest extends TestCase
{
    private string $racine;

    protected function setUp(): void
    {
        $this->racine = dirname(__DIR__, 2);
    }

    /** @return list<string> les tables créées par un fichier SQL */
    private function tablesDe(string $fichier): array
    {
        $sql = (string) file_get_contents($this->racine . '/' . $fichier);
        preg_match_all(
            '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-z_][a-z0-9_]*)`?/i',
            $sql,
            $m
        );

        $tables = array_map('strtolower', $m[1]);
        sort($tables);

        return array_values(array_unique($tables));
    }

    public function testLesDeuxFichiersDInstallationCreentLesMemesTables(): void
    {
        $setup    = $this->tablesDe('db_setup.sql');
        $mutualise = $this->tablesDe('install_mutualise.sql');

        $this->assertSame(
            $mutualise,
            $setup,
            "db_setup.sql et install_mutualise.sql ne créent plus les mêmes tables. "
            . "Manquantes dans db_setup.sql : " . implode(', ', array_diff($mutualise, $setup)) . ". "
            . "Manquantes dans install_mutualise.sql : " . implode(', ', array_diff($setup, $mutualise)) . "."
        );
    }

    public function testToutesLesTablesInterrogeesParLeCodeExistent(): void
    {
        $declarees = $this->tablesDe('db_setup.sql');
        $utilisees = $this->tablesReferenceesParLeCode();

        $absentes = array_values(array_diff($utilisees, $declarees));

        $this->assertSame(
            [],
            $absentes,
            "Le code interroge des tables qu'aucune installation ne crée : "
            . implode(', ', $absentes) . ". "
            . "C'est exactement la panne que ce test existe pour empêcher."
        );
    }

    public function testLeSuiviDesMigrationsEstAmorce(): void
    {
        // Sans cet amorçage, `php outils/migrer.php` proposerait de rejouer
        // sur une base neuve des migrations déjà intégrées — et échouerait sur
        // la v4, qui n'est pas rejouable.
        foreach (['db_setup.sql', 'install_mutualise.sql'] as $fichier) {
            $sql = (string) file_get_contents($this->racine . '/' . $fichier);

            $this->assertStringContainsString(
                'schema_migrations',
                $sql,
                "$fichier ne crée pas la table de suivi des migrations."
            );

            $versions = $this->versionsSurLeDisque();
            foreach ($versions as $version) {
                $this->assertMatchesRegularExpression(
                    "/\('" . preg_quote($version, '/') . "'\)/",
                    $sql,
                    "$fichier n'enregistre pas la migration $version comme appliquée, "
                    . "alors que son contenu y est intégré."
                );
            }
        }
    }

    /** @return list<string> les versions des fichiers db_migrations_v*.sql */
    private function versionsSurLeDisque(): array
    {
        $versions = [];
        foreach (glob($this->racine . '/db_migrations_v*.sql') ?: [] as $chemin) {
            if (preg_match('/db_migrations_(v\d+)\.sql$/i', basename($chemin), $m)) {
                $versions[] = strtolower($m[1]);
            }
        }

        return $versions;
    }

    /**
     * Les tables que le code PHP interroge réellement.
     *
     * On ne lit QUE les chaînes littérales du code, extraites par l'analyseur
     * de PHP lui-même. Une recherche sur le fichier entier ramassait les
     * commentaires et le HTML : « <!-- Join Button --> » donnait une table
     * « button », « LEFT JOIN to catch deleted events » une table « to », et
     * « Re-populate $event from POST » une table « post ». Autant de fausses
     * alertes qui auraient fini par faire ignorer ce test.
     *
     * @return list<string>
     */
    private function tablesReferenceesParLeCode(): array
    {
        $trouvees = [];

        foreach ($this->fichiersPhp() as $chemin) {
            foreach ($this->chainesLitterales((string) file_get_contents($chemin)) as $chaine) {
                // « ON DUPLICATE KEY UPDATE revision = … » et « FOR UPDATE » :
                // le mot UPDATE y est suivi d'une colonne, pas d'une table.
                $sql = preg_replace(
                    '/\bON\s+DUPLICATE\s+KEY\s+UPDATE\b|\bFOR\s+UPDATE\b/i',
                    ' ',
                    $chaine
                ) ?? $chaine;

                preg_match_all(
                    '/\b(?:FROM|JOIN|INSERT\s+(?:IGNORE\s+)?INTO|UPDATE)\s+`?([a-z_][a-z0-9_]*)`?/i',
                    $sql,
                    $m
                );
                foreach ($m[1] as $nom) {
                    $trouvees[strtolower($nom)] = true;
                }
            }
        }

        // Ramassés au passage sans être des tables de l'application : le SELECT
        // d'une dérivée, et la table de suivi que migrer.php crée lui-même.
        unset(
            $trouvees['select'], $trouvees['dual'], $trouvees['information_schema'],
            $trouvees['schema_migrations']
        );

        $tables = array_keys($trouvees);
        sort($tables);

        return $tables;
    }

    /**
     * Les chaînes littérales d'un fichier PHP, commentaires et HTML exclus.
     *
     * @return list<string>
     */
    private function chainesLitterales(string $source): array
    {
        $chaines = [];

        foreach (token_get_all($source) as $jeton) {
            if (!is_array($jeton)) {
                continue;
            }
            // T_ENCAPSED_AND_WHITESPACE : le texte d'une chaîne à guillemets
            // qui contient une variable, comme "SELECT … FROM users WHERE $x".
            if (in_array($jeton[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                $chaines[] = $jeton[1];
            }
        }

        return $chaines;
    }

    /** @return list<string> */
    private function fichiersPhp(): array
    {
        $fichiers = [];
        foreach (['includes', 'api', 'auth', 'admin', 'partenaire', 'cron', 'outils'] as $dossier) {
            foreach (glob($this->racine . "/$dossier/*.php") ?: [] as $chemin) {
                $fichiers[] = $chemin;
            }
        }
        foreach (glob($this->racine . '/*.php') ?: [] as $chemin) {
            $fichiers[] = $chemin;
        }

        return $fichiers;
    }
}
