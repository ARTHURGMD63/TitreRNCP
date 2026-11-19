<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/session.php';

/**
 * Une session web ouverte avec un ancien mot de passe se ferme (audit SEC-05).
 *
 * Avant, changer ou réinitialiser son mot de passe ne supprimait que les
 * jetons de l'application : celui qui tenait une session web volée la
 * gardait trente jours.
 */
final class SessionIdentifiantsTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, password TEXT, type TEXT)');
        $this->pdo->exec("INSERT INTO users (id, password, type) VALUES (7, 'hachage-1', 'partenaire')");
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testSansCompteConnecteRienAVerifier(): void
    {
        $this->assertTrue(sessionIdentifiantsAJour($this->pdo));
    }

    public function testLaSessionDuMemeMotDePasseResteOuverte(): void
    {
        $_SESSION = ['user_id' => 7, 'identifiants' => empreinteIdentifiants('hachage-1')];

        $this->assertTrue(sessionIdentifiantsAJour($this->pdo));
    }

    public function testUnMotDePasseChangeFermeLaSession(): void
    {
        $_SESSION = ['user_id' => 7, 'identifiants' => empreinteIdentifiants('hachage-1')];
        $this->pdo->exec("UPDATE users SET password = 'hachage-2' WHERE id = 7");

        $this->assertFalse(sessionIdentifiantsAJour($this->pdo));
    }

    public function testUneAncienneSessionRecoitLEmpreinteDuMoment(): void
    {
        $_SESSION = ['user_id' => 7];

        $this->assertTrue(sessionIdentifiantsAJour($this->pdo));
        $this->assertSame(empreinteIdentifiants('hachage-1'), $_SESSION['identifiants']);

        $this->pdo->exec("UPDATE users SET password = 'hachage-2' WHERE id = 7");
        $this->assertFalse(sessionIdentifiantsAJour($this->pdo));
    }

    public function testUnCompteSupprimeOuDisparuFermeLaSession(): void
    {
        $_SESSION = ['user_id' => 7, 'identifiants' => empreinteIdentifiants('hachage-1')];
        $this->pdo->exec("UPDATE users SET type = 'supprime' WHERE id = 7");
        $this->assertFalse(sessionIdentifiantsAJour($this->pdo));

        $_SESSION = ['user_id' => 99];
        $this->assertFalse(sessionIdentifiantsAJour($this->pdo));
    }

    public function testLeControleEstBrancheSurLesPagesEtLaConnexion(): void
    {
        $racine = __DIR__ . '/../..';

        $this->assertStringContainsString('sessionIdentifiantsAJour(', (string) file_get_contents($racine . '/includes/auth_check.php'));
        $this->assertStringContainsString('sessionIdentifiantsAJour(', (string) file_get_contents($racine . '/api/live.php'));
        $this->assertStringContainsString("\$_SESSION['identifiants'] = empreinteIdentifiants(", (string) file_get_contents($racine . '/auth/login.php'));
    }

    public function testLEmpreinteNeContientPasLeHachage(): void
    {
        $hachage = '$2y$12$abcdefghijklmnopqrstuuvwxyzABCDEFGHIJKLMNOPQRSTUVWX';

        $this->assertStringNotContainsString($hachage, empreinteIdentifiants($hachage));
        $this->assertSame(32, strlen(empreinteIdentifiants($hachage)));
    }
}
