<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Codes de réinitialisation et suppression de compte, joués contre une vraie
 * base MySQL/MariaDB.
 *
 * Pourquoi pas SQLite comme les autres tests d'intégration : ces fonctions
 * parlent MySQL (DATE_SUB, DATE_ADD … INTERVAL, FOR UPDATE), et surtout ce
 * qu'elles garantissent — qu'un compte supprimé ne laisse rien derrière lui —
 * dépend du schéma réel : clés étrangères, ENUM, cascades. Un schéma simulé
 * validerait le test, pas la suppression.
 *
 * La base doit déjà contenir le schéma (db_setup.sql). Le test se saute sans
 * TEST_MYSQL_DSN ; l'intégration continue la fournit (service MariaDB).
 *
 *   TEST_MYSQL_DSN="mysql:host=127.0.0.1;port=3306;dbname=linkee_test;charset=utf8mb4"
 *   TEST_MYSQL_USER=root   TEST_MYSQL_PASS=
 */
final class CompteMysqlTest extends TestCase
{
    private PDO $pdo;

    /** @var list<int> comptes créés par le test, retirés à la fin */
    private array $comptes = [];

    /** @var list<int> */
    private array $etablissements = [];

    /** @var list<string> */
    private array $emails = [];

    protected function setUp(): void
    {
        $dsn = getenv('TEST_MYSQL_DSN');
        if (!is_string($dsn) || $dsn === '') {
            $this->markTestSkipped('TEST_MYSQL_DSN absent : test joué contre MySQL en intégration continue.');
        }

        $this->pdo = new PDO($dsn, (string) getenv('TEST_MYSQL_USER'), (string) getenv('TEST_MYSQL_PASS'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        require_once __DIR__ . '/../../includes/compte.php';
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo)) {
            return; // test sauté
        }
        foreach ($this->emails as $email) {
            $this->pdo->prepare('DELETE FROM password_reset_codes WHERE email = ?')->execute([$email]);
            $this->pdo->prepare('DELETE FROM liste_attente WHERE email = ?')->execute([$email]);
        }
        foreach ($this->etablissements as $id) {
            $this->pdo->prepare('DELETE FROM etablissements WHERE id = ?')->execute([$id]); // cascade : événements, inscriptions
        }
        foreach ($this->comptes as $id) {
            $this->pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        }
    }

    private function creerCompte(string $type = 'etudiant'): int
    {
        $email = 'essai-' . bin2hex(random_bytes(6)) . '@linkee.test';
        $this->pdo->prepare(
            "INSERT INTO users (nom, prenom, email, password, ecole, ville, photo, date_naissance, interests, type)
             VALUES ('Essai', 'Prenom', ?, ?, 'UCA', 'Lyon', 'photo.jpg', '2002-04-12', 'sport,musique', ?)"
        )->execute([$email, password_hash('ancienmdp1', PASSWORD_DEFAULT), $type]);

        $id = (int) $this->pdo->lastInsertId();
        $this->comptes[] = $id;
        $this->emails[]  = $email;

        return $id;
    }

    private function email(int $id): string
    {
        return (string) $this->pdo->query("SELECT email FROM users WHERE id = $id")->fetchColumn();
    }

    private function compter(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    // ─── Code de réinitialisation ───────────────────────────────────────────

    public function testUnCompteInconnuNeRecoitPasDeCode(): void
    {
        $this->assertNull(codeReinitialisationCreer($this->pdo, 'personne-' . bin2hex(random_bytes(4)) . '@linkee.test'));
    }

    public function testLeCodeSeVerifieSeConsommeEtDeconnecteTousLesAppareils(): void
    {
        $id    = $this->creerCompte();
        $email = $this->email($id);
        $this->pdo->prepare('INSERT INTO api_tokens (user_id, token_hash, expire_le) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY))')
            ->execute([$id, hash('sha256', 'jeton-' . $id)]);

        $code = codeReinitialisationCreer($this->pdo, $email);
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $code);
        $this->assertSame(
            0,
            $this->compter('SELECT COUNT(*) FROM password_reset_codes WHERE email = ? AND code_hash = ?', [$email, $code]),
            'Le code ne doit jamais être stocké en clair.'
        );

        $this->assertTrue(codeReinitialisationVerifier($this->pdo, $email, (string) $code));
        $this->assertTrue(codeReinitialisationVerifier($this->pdo, $email, (string) $code), 'Vérifier ne consomme pas.');

        codeReinitialisationAppliquer($this->pdo, $id, $email, 'nouveaumdp99');

        $hash = (string) $this->pdo->query("SELECT password FROM users WHERE id = $id")->fetchColumn();
        $this->assertTrue(password_verify('nouveaumdp99', $hash));
        $this->assertFalse(password_verify('ancienmdp1', $hash));
        $this->assertSame(0, $this->compter('SELECT COUNT(*) FROM password_reset_codes WHERE email = ?', [$email]), 'code consommé');
        $this->assertSame(0, $this->compter('SELECT COUNT(*) FROM api_tokens WHERE user_id = ?', [$id]), 'jetons révoqués');
        $this->assertFalse(codeReinitialisationVerifier($this->pdo, $email, (string) $code), 'Un code ne sert qu’une fois.');
    }

    public function testUnNouveauCodeNeSeDemandePasAvantLeDelai(): void
    {
        $email = $this->email($this->creerCompte());

        $this->assertNotNull(codeReinitialisationCreer($this->pdo, $email));
        $this->assertNull(
            codeReinitialisationCreer($this->pdo, $email),
            'Redemander tout de suite remettrait le compteur d’essais à zéro : c’est ce que le délai interdit.'
        );
    }

    public function testCinqEssaisFauxDetruisentLeCode(): void
    {
        $email = $this->email($this->creerCompte());
        $code  = (string) codeReinitialisationCreer($this->pdo, $email);
        $faux  = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < CODE_REINIT_ESSAIS_MAX; $i++) {
            $this->assertFalse(codeReinitialisationVerifier($this->pdo, $email, $faux));
        }

        $this->assertSame(0, $this->compter('SELECT COUNT(*) FROM password_reset_codes WHERE email = ?', [$email]));
        $this->assertFalse(codeReinitialisationVerifier($this->pdo, $email, $code), 'Même le bon code ne passe plus.');
    }

    public function testUnCodeExpireNeServPlus(): void
    {
        $email = $this->email($this->creerCompte());
        $code  = (string) codeReinitialisationCreer($this->pdo, $email);
        $this->pdo->prepare('UPDATE password_reset_codes SET expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE email = ?')->execute([$email]);

        $this->assertFalse(codeReinitialisationVerifier($this->pdo, $email, $code));
    }

    // ─── Suppression de compte ──────────────────────────────────────────────

    /** @param string $quand une expression SQL de date, écrite par le test lui-même */
    private function creerEvenement(int $etablissement, string $quand): int
    {
        $this->pdo->prepare(
            "INSERT INTO evenements (etablissement_id, titre, type, date_heure, quota) VALUES (?, 'Soirée', 'bar', $quand, 50)"
        )->execute([$etablissement]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array{0:int,1:int,2:int} compte supprimé, autre étudiant, événement à venir */
    private function monterUnCompteBienRempli(): array
    {
        $uid   = $this->creerCompte();
        $autre = $this->creerCompte();
        $pro   = $this->creerCompte('partenaire');

        $this->pdo->prepare("INSERT INTO etablissements (user_id, nom, type) VALUES (?, 'Bar d essai', 'bar')")->execute([$pro]);
        $etab = (int) $this->pdo->lastInsertId();
        $this->etablissements[] = $etab;

        $futur = $this->creerEvenement($etab, 'DATE_ADD(NOW(), INTERVAL 3 DAY)');
        $passe = $this->creerEvenement($etab, 'DATE_SUB(NOW(), INTERVAL 3 DAY)');

        $ins = $this->pdo->prepare('INSERT INTO inscriptions (user_id, evenement_id, qr_code, statut) VALUES (?, ?, ?, ?)');
        $ins->execute([$uid, $futur, bin2hex(random_bytes(8)), 'inscrit']);
        $ins->execute([$uid, $passe, bin2hex(random_bytes(8)), 'checkin']);
        $ins->execute([$autre, $futur, bin2hex(random_bytes(8)), 'inscrit']);

        $this->pdo->prepare("INSERT INTO follows_users (follower_id, followed_id, statut) VALUES (?, ?, 'accepted'), (?, ?, 'accepted')")
            ->execute([$autre, $uid, $uid, $autre]);
        $this->pdo->prepare('INSERT INTO user_blocks (blocker_id, blocked_id) VALUES (?, ?)')->execute([$autre, $uid]);
        $this->pdo->prepare('INSERT INTO user_interets (user_id, interet) VALUES (?, ?)')->execute([$uid, 'sport']);
        $this->pdo->prepare("INSERT INTO squads (createur_id, type, titre, date_heure, quota) VALUES (?, 'running', 'Run', DATE_ADD(NOW(), INTERVAL 2 DAY), 5)")
            ->execute([$uid]);
        $squad = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO squad_membres (squad_id, user_id) VALUES (?, ?)')->execute([$squad, $autre]);
        $this->pdo->prepare('INSERT INTO liste_attente (email) VALUES (?)')->execute([$this->email($uid)]);
        $this->pdo->prepare('INSERT INTO api_tokens (user_id, token_hash, expire_le) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY))')
            ->execute([$uid, hash('sha256', 'jeton-' . $uid)]);

        return [$uid, $autre, $futur];
    }

    public function testLaSuppressionVideLIdentiteEtEffaceCeQuiAppartientAuCompte(): void
    {
        [$uid, $autre, $futur] = $this->monterUnCompteBienRempli();
        $emailAvant = $this->email($uid);

        $restes = compteSupprimerEtudiant($this->pdo, $uid);

        $ligne = $this->pdo->query("SELECT * FROM users WHERE id = $uid")->fetch();
        $this->assertSame('supprime', $ligne['type']);
        $this->assertSame(COMPTE_SUPPRIME_PRENOM, $ligne['prenom']);
        $this->assertSame(COMPTE_SUPPRIME_NOM, $ligne['nom']);
        $this->assertSame(compteAdresseSupprimee($uid), $ligne['email']);
        foreach (['ville', 'photo', 'date_naissance', 'interests', 'cgu_acceptees_le'] as $colonne) {
            $this->assertNull($ligne[$colonne], "users.$colonne doit être vidé");
        }
        // École et promotion restent : le registre RGPD le prévoit, pour que les
        // statistiques par école déjà vendues aux partenaires ne bougent pas.
        $this->assertSame('UCA', $ligne['ecole']);
        $this->assertFalse(password_verify('ancienmdp1', (string) $ligne['password']));

        $this->assertSame(0, $this->compter('SELECT COUNT(*) FROM api_tokens WHERE user_id = ?', [$uid]));
        $this->assertSame(0, $this->compter('SELECT COUNT(*) FROM follows_users WHERE follower_id = ? OR followed_id = ?', [$uid, $uid]));
        $this->assertSame(0, $this->compter('SELECT COUNT(*) FROM user_blocks WHERE blocker_id = ? OR blocked_id = ?', [$uid, $uid]));
        $this->assertSame(0, $this->compter('SELECT COUNT(*) FROM user_interets WHERE user_id = ?', [$uid]));
        $this->assertSame(0, $this->compter('SELECT COUNT(*) FROM squads WHERE createur_id = ?', [$uid]));
        $this->assertSame(0, $this->compter('SELECT COUNT(*) FROM squad_membres WHERE user_id = ?', [$autre]), 'les membres de sa squad la quittent avec elle');
        $this->assertSame(0, $this->compter('SELECT COUNT(*) FROM liste_attente WHERE email = ?', [$emailAvant]));

        $this->assertSame('photo.jpg', $restes['avatar']);
        $this->assertSame([$futur], $restes['evenements']);
    }

    public function testLesPassAVenirSontAnnulesEtLesPresencesPasseesGardees(): void
    {
        [$uid, $autre, $futur] = $this->monterUnCompteBienRempli();

        compteSupprimerEtudiant($this->pdo, $uid);

        $this->assertSame(
            'annule',
            (string) $this->pdo->query("SELECT statut FROM inscriptions WHERE user_id = $uid AND evenement_id = $futur")->fetchColumn(),
            'la place est libérée'
        );
        $this->assertSame(
            1,
            $this->compter("SELECT COUNT(*) FROM inscriptions WHERE user_id = ? AND statut = 'checkin'", [$uid]),
            'la présence passée alimente toujours les statistiques'
        );
        $this->assertSame(
            'inscrit',
            (string) $this->pdo->query("SELECT statut FROM inscriptions WHERE user_id = $autre AND evenement_id = $futur")->fetchColumn(),
            'les autres inscrits ne sont pas touchés'
        );
    }

    public function testUnCompteSupprimeNeSeConnecteNiNeRecoitDeCode(): void
    {
        [$uid] = $this->monterUnCompteBienRempli();
        $emailAvant = $this->email($uid);

        compteSupprimerEtudiant($this->pdo, $uid);

        $this->assertNull(compteParEmail($this->pdo, compteAdresseSupprimee($uid)));
        $this->assertNull(compteParEmail($this->pdo, $emailAvant), 'l’ancienne adresse ne désigne plus personne');
        $this->assertNull(codeReinitialisationCreer($this->pdo, compteAdresseSupprimee($uid)));
    }

    public function testSeulUnEtudiantSeSupprimeParCetteVoie(): void
    {
        $pro = $this->creerCompte('partenaire');

        $this->expectException(\RuntimeException::class);
        compteSupprimerEtudiant($this->pdo, $pro);
    }

    public function testUneSuppressionRateeNeLaisseRienAMoitie(): void
    {
        [$uid] = $this->monterUnCompteBienRempli();

        // Une table volontairement absente fait échouer la transaction à mi-chemin.
        $this->pdo->exec('RENAME TABLE user_badges TO user_badges_absente');
        try {
            try {
                compteSupprimerEtudiant($this->pdo, $uid);
                $this->fail('La suppression aurait dû échouer.');
            } catch (\PDOException) {
                // attendu
            }

            $this->assertSame('etudiant', (string) $this->pdo->query("SELECT type FROM users WHERE id = $uid")->fetchColumn());
            $this->assertSame(1, $this->compter('SELECT COUNT(*) FROM squads WHERE createur_id = ?', [$uid]), 'rien n’a été effacé');
            $this->assertSame(1, $this->compter('SELECT COUNT(*) FROM api_tokens WHERE user_id = ?', [$uid]));
        } finally {
            $this->pdo->exec('RENAME TABLE user_badges_absente TO user_badges');
        }
    }
}
