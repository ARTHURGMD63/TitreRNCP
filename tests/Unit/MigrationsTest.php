<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Le découpage des scripts de migration.
 *
 * C'est la pièce qui peut casser en silence : mal découpée, une migration
 * s'exécute à moitié, laisse le schéma dans un état intermédiaire, et l'erreur
 * remonte plusieurs instructions plus loin. Les cas testés ici sont ceux que
 * les migrations du dépôt contiennent réellement — DELIMITER pour les
 * procédures de la v7, point-virgule dans une chaîne, commentaires.
 */
final class MigrationsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('decouperSql')) {
            require_once __DIR__ . '/../../includes/migrations.php';
        }
    }

    public function testDecoupeSurLePointVirgule(): void
    {
        $this->assertSame(
            ['SELECT 1', 'SELECT 2'],
            decouperSql('SELECT 1; SELECT 2;')
        );
    }

    public function testUneInstructionSansPointVirguleFinalCompteQuandMeme(): void
    {
        $this->assertSame(['SELECT 1'], decouperSql('SELECT 1'));
    }

    public function testLesInstructionsVidesSontIgnorees(): void
    {
        // Deux points-virgules qui se suivent, ou un fichier qui se termine par
        // un saut de ligne : PDO refuserait une instruction vide.
        $this->assertSame(['SELECT 1'], decouperSql(";;\nSELECT 1;\n\n;"));
    }

    public function testUnPointVirguleDansUneChaineNeCoupePas(): void
    {
        $sql = "INSERT INTO t (c) VALUES ('a;b'); SELECT 2;";
        $this->assertSame(
            ["INSERT INTO t (c) VALUES ('a;b')", 'SELECT 2'],
            decouperSql($sql)
        );
    }

    public function testUnQuoteDoubleDansUneChaineNeLaFermePas(): void
    {
        $sql = "SELECT 'aujourd''hui; demain'; SELECT 2;";
        $this->assertSame(
            ["SELECT 'aujourd''hui; demain'", 'SELECT 2'],
            decouperSql($sql)
        );
    }

    public function testUnQuoteEchappeParAntislashNeFermePas(): void
    {
        $sql = "SELECT 'a\\'; b'; SELECT 2;";
        $this->assertCount(2, decouperSql($sql));
    }

    public function testUnPointVirguleEnCommentaireNeCoupePas(): void
    {
        $sql = "-- un commentaire ; avec un point-virgule\nSELECT 1;";
        $this->assertSame(['SELECT 1'], decouperSql($sql));
    }

    public function testDeuxTiretsCollesNeSontPasUnCommentaire(): void
    {
        // « -- » n'ouvre un commentaire que suivi d'un blanc : sinon « 5--3 »
        // serait avalé jusqu'à la fin de la ligne.
        $this->assertSame(['SELECT 5--3'], decouperSql('SELECT 5--3;'));
    }

    public function testUnCommentaireEnBlocEstRetire(): void
    {
        $this->assertSame(['SELECT 1'], decouperSql("/* rien ; ici */ SELECT 1;"));
    }

    public function testDelimiterProtegeLeCorpsDUneProcedure(): void
    {
        // Le cas de la migration v7. Sans la prise en charge de DELIMITER, la
        // procédure serait coupée à son premier point-virgule interne et le
        // serveur recevrait un fragment invalide.
        $sql = <<<'SQL'
        DELIMITER //
        CREATE PROCEDURE ajouter_index(IN p_table VARCHAR(64))
        BEGIN
            SET @sql = 'x';
            PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
        END //
        DELIMITER ;
        CALL ajouter_index('users');
        SQL;

        $instructions = decouperSql($sql);

        $this->assertCount(2, $instructions);
        $this->assertStringStartsWith('CREATE PROCEDURE', $instructions[0]);
        $this->assertStringContainsString('DEALLOCATE PREPARE st;', $instructions[0]);
        $this->assertSame("CALL ajouter_index('users')", $instructions[1]);
    }

    public function testLaVersionSeLitDansLeNomDuFichier(): void
    {
        $this->assertSame('v12', versionMigration('db_migrations_v12.sql'));
        $this->assertSame('v4', versionMigration('/chemin/absolu/db_migrations_v4.sql'));
        $this->assertNull(versionMigration('db_setup.sql'));
        $this->assertNull(versionMigration('db_migrations_vXX.sql'));
    }

    public function testLesMigrationsSontTrieesEnOrdreNumerique(): void
    {
        // Le piège : un tri alphabétique place v10 avant v9, et la v10
        // s'appliquerait sur un schéma qui n'a pas encore reçu la v9.
        $versions = array_keys(listerMigrations());

        $this->assertNotEmpty($versions, 'Aucune migration trouvée à la racine du projet.');
        $this->assertSame('v4', $versions[0]);

        $numeros = array_map(static fn(string $v): int => (int) substr($v, 1), $versions);
        $triees  = $numeros;
        sort($triees);
        $this->assertSame($triees, $numeros, 'Les migrations ne sont pas dans l’ordre numérique.');
    }

    public function testChaqueMigrationDuDepotSeDecoupeSansResteVide(): void
    {
        // Filet large : si un futur fichier de migration contient une
        // construction que le découpage ne sait pas lire, il produira zéro
        // instruction, et c'est ici qu'on l'apprend plutôt qu'en production.
        foreach (listerMigrations() as $version => $chemin) {
            $instructions = decouperSql((string) file_get_contents($chemin));

            $this->assertNotEmpty(
                $instructions,
                "La migration $version ne produit aucune instruction exécutable."
            );
            foreach ($instructions as $instruction) {
                $this->assertNotSame('', trim($instruction));
            }
        }
    }
}
