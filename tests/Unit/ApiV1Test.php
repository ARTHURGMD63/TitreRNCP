<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * L'API de l'application mobile.
 *
 * Même intention qu'`ApiProtectionTest` pour le site : ces tests ne
 * démontrent pas que les points existants sont corrects — cela a été vérifié
 * en conditions réelles, requête par requête, contre le serveur. Ils
 * garantissent que **le prochain ne pourra pas être ajouté sans sa garde**.
 *
 * C'est d'autant plus utile ici que l'API mobile n'a pas de filet : le site
 * protège ses écritures par un jeton CSRF et une origine, et un oubli s'y
 * voit tout de suite dans un navigateur. Un point d'API sans authentification,
 * lui, répond simplement 200 à tout le monde.
 */
final class ApiV1Test extends TestCase
{
    private const DOSSIER = __DIR__ . '/../../api/v1';

    /**
     * Le code d'un fichier, commentaires retirés.
     *
     * Un test qui cherche un motif dans le source attrape aussi ce qu'en
     * disent les commentaires. Celui qui interdit `rand()` échouait sur la
     * ligne qui explique précisément pourquoi on ne l'emploie pas — un test
     * rouge sur du code juste, ce qui est la pire espèce : on finit par le
     * désactiver, et il ne garde plus rien.
     *
     * token_get_all() sépare ce que PHP exécute de ce qu'il ignore, là où une
     * expression régulière sur des `//` se tromperait dès qu'un slash apparaît
     * dans une chaîne.
     */
    private function code(string $chemin): string
    {
        $source = (string) file_get_contents($chemin);
        $out    = '';

        foreach (token_get_all($source) as $jeton) {
            if (is_array($jeton) && in_array($jeton[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $out .= is_array($jeton) ? $jeton[1] : $jeton;
        }

        return $out;
    }

    /** @return list<string> Les points d'entrée, hors socle. */
    private function points(): array
    {
        $out = [];
        foreach (glob(self::DOSSIER . '/*.php') ?: [] as $chemin) {
            if (basename($chemin) !== '_socle.php') {
                $out[] = $chemin;
            }
        }

        return $out;
    }

    public function testIlYADesPointsAVerifier(): void
    {
        // Un glob qui ne renvoie rien ferait passer tous les tests suivants
        // sans rien vérifier — l'échec le plus silencieux qui soit.
        $this->assertNotEmpty($this->points(), 'aucun point trouvé dans api/v1');
    }

    /**
     * Chaque point exige un compte, sauf ceux qui n'en ont pas besoin.
     *
     * `login.php` établit l'authentification, il ne peut pas l'exiger.
     * `logout.php` lit le jeton directement pour le révoquer.
     */
    public function testChaquePointExigeUnCompte(): void
    {
        $exemptes = ['login.php', 'logout.php'];
        $fautifs  = [];

        foreach ($this->points() as $chemin) {
            $nom = basename($chemin);
            if (in_array($nom, $exemptes, true)) {
                continue;
            }
            $source = $this->code($chemin);
            if (!str_contains($source, 'apiUtilisateur(') && !str_contains($source, 'apiEtudiant(')) {
                $fautifs[] = $nom;
            }
        }

        $this->assertSame([], $fautifs, 'points sans appel à apiUtilisateur()/apiEtudiant() : '
            . implode(', ', $fautifs));
    }

    /**
     * La garde vient AVANT la première requête SQL.
     *
     * Une authentification vérifiée après coup laisse la requête partir : même
     * si la réponse est ensuite refusée, la base a travaillé, et une erreur SQL
     * ou un temps de réponse suffisent parfois à renseigner qui insiste.
     */
    public function testLaGardeArriveAvantLaPremiereRequete(): void
    {
        $fautifs = [];

        foreach ($this->points() as $chemin) {
            $nom = basename($chemin);
            if (in_array($nom, ['login.php', 'logout.php'], true)) {
                continue;
            }
            $source = $this->code($chemin);

            $garde = min(array_filter([
                strpos($source, 'apiUtilisateur(') ?: PHP_INT_MAX,
                strpos($source, 'apiEtudiant(') ?: PHP_INT_MAX,
            ]));

            foreach (['$pdo->prepare(', '$pdo->query(', '$pdo->exec('] as $appel) {
                $pos = strpos($source, $appel);
                if ($pos !== false && $pos < $garde) {
                    $fautifs[] = $nom;
                    break;
                }
            }
        }

        $this->assertSame([], $fautifs, 'requête SQL avant la garde dans : ' . implode(', ', $fautifs));
    }

    /** Chaque point borne sa méthode HTTP. */
    public function testChaquePointBorneSaMethode(): void
    {
        $fautifs = [];

        foreach ($this->points() as $chemin) {
            $source = $this->code($chemin);
            if (!str_contains($source, 'apiExigerMethode(')) {
                $fautifs[] = basename($chemin);
            }
        }

        $this->assertSame([], $fautifs, 'sans apiExigerMethode() : ' . implode(', ', $fautifs));
    }

    /**
     * Aucun point ne renvoie la colonne `password`.
     *
     * Le risque n'est pas d'écrire `'password' => …` volontairement, mais de
     * passer une ligne de `users` issue d'un `SELECT *` à json_encode(). D'où
     * apiProfil(), qui choisit explicitement ce qui sort.
     */
    public function testAucunPointNeRenvoieLeMotDePasse(): void
    {
        foreach ($this->points() as $chemin) {
            $source = $this->code($chemin);

            $this->assertStringNotContainsString(
                "'password' =>",
                $source,
                basename($chemin) . ' place « password » dans une réponse'
            );
            // Un SELECT * sur users finirait par emporter le hachage.
            $this->assertDoesNotMatchRegularExpression(
                '/SELECT\s+\*\s+FROM\s+users/i',
                $source,
                basename($chemin) . ' fait un SELECT * sur users'
            );
        }
    }

    public function testLeSocleNeLaissePasFuirLesErreursDansLeJson(): void
    {
        // Un avertissement PHP affiché se glisse avant le JSON et le rend
        // inanalysable. L'application signale alors « réponse invalide »,
        // ce qui ne dit rien de la cause réelle.
        $socle = (string) file_get_contents(self::DOSSIER . '/_socle.php');

        $this->assertStringContainsString("ini_set('display_errors', '0')", $socle);
        $this->assertStringContainsString("ini_set('log_errors', '1')", $socle);
    }

    public function testLeJetonEstRangeSousFormeDEmpreinte(): void
    {
        $api = $this->code(__DIR__ . '/../../includes/api.php');

        // Le jeton en clair ne doit jamais atteindre une requête.
        $this->assertStringContainsString("hash('sha256'", $api);
        $this->assertStringNotContainsString('token_hash = ?\', [$jeton]', $api);

        foreach (['apiCreerJeton', 'apiUtilisateur', 'apiRevoquerJeton'] as $fonction) {
            $this->assertStringContainsString($fonction, $api);
        }
    }

    public function testLeJetonEstTireAuHasardCryptographique(): void
    {
        $api = $this->code(__DIR__ . '/../../includes/api.php');

        $this->assertStringContainsString('random_bytes(32)', $api);
        // rand() et mt_rand() sont prédictibles : un jeton devinable vaut un
        // mot de passe devinable.
        $this->assertDoesNotMatchRegularExpression('/\b(mt_)?rand\s*\(/', $api);
    }

    public function testLaConnexionEstSoumiseAuMemeLimiteurQueLeSite(): void
    {
        // Sans cela, l'API devient le chemin non protégé pour essayer des mots
        // de passe en rafale pendant que le formulaire web compte ses cinq
        // tentatives.
        $login = $this->code(self::DOSSIER . '/login.php');

        $this->assertStringContainsString('isRateLimited(', $login);
        $this->assertStringContainsString('recordLoginAttempt(', $login);
        $this->assertStringContainsString('clearLoginAttempts(', $login);
    }

    public function testLaConnexionNeDistinguePasCompteInconnuEtMauvaisMotDePasse(): void
    {
        $login = $this->code(self::DOSSIER . '/login.php');

        // Un seul message, et une vérification de mot de passe exécutée même
        // quand le compte n'existe pas : sinon le temps de réponse trahit
        // quelles adresses sont inscrites.
        $this->assertSame(
            1,
            substr_count($login, "apiErreur('Identifiants incorrects'"),
            'la connexion doit répondre la même chose dans les deux cas'
        );
        $this->assertStringContainsString('password_verify(', $login);
    }

    public function testLaMigrationDesJetonsExiste(): void
    {
        $migration = (string) file_get_contents(__DIR__ . '/../../db_migrations_v16.sql');

        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS api_tokens', $migration);
        $this->assertStringContainsString('token_hash CHAR(64)', $migration);
        // Sans expiration, une session mobile volée reste ouverte à vie.
        $this->assertStringContainsString('expire_le', $migration);
    }
}
