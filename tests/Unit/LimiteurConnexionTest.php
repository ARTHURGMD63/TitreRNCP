<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/security.php';

/**
 * SQLite en mémoire qui comprend la seule tournure MySQL du limiteur,
 * DATE_SUB(NOW(), INTERVAL ? SECOND).
 */
final class PdoLimiteur extends PDO
{
    public function __construct()
    {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->exec("CREATE TABLE login_attempts (ip TEXT, email TEXT, attempted_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    }

    /**
     * @param array<int,mixed> $options
     */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $query = str_replace('DATE_SUB(NOW(), INTERVAL ? SECOND)', "datetime('now', '-' || ? || ' seconds')", $query);

        return parent::prepare($query, $options);
    }
}

/**
 * Le limiteur de connexion ne doit pas servir à verrouiller un compte à
 * distance (audit SEC-06) : le blocage porte sur le couple (compte, IP), et
 * le plafond par compte, toutes adresses confondues, est bien plus haut.
 */
final class LimiteurConnexionTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PdoLimiteur();
    }

    private function echecs(string $ip, string $email, int $nombre): void
    {
        for ($i = 0; $i < $nombre; $i++) {
            recordLoginAttempt($this->pdo, $ip, $email);
        }
    }

    public function testLAdresseDeLAttaquantEstBloqueeSurCeCompte(): void
    {
        $this->echecs('198.51.100.1', 'bar@exemple.fr', 5);

        $this->assertTrue(isRateLimited($this->pdo, '198.51.100.1', 5, 900, 'bar@exemple.fr'));
    }

    public function testLaVictimeSeConnecteEncoreDepuisSonAdresse(): void
    {
        // Avant : dix échecs depuis deux adresses bloquaient le compte pour tous.
        $this->echecs('198.51.100.1', 'bar@exemple.fr', 5);
        $this->echecs('198.51.100.2', 'bar@exemple.fr', 5);

        $this->assertFalse(isRateLimited($this->pdo, '203.0.113.50', 5, 900, 'bar@exemple.fr'));
    }

    public function testUneForceBruteRepartieFinitParEtreArretee(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->echecs('198.51.100.' . $i, 'bar@exemple.fr', 5);
        }

        $this->assertTrue(isRateLimited($this->pdo, '203.0.113.50', 5, 900, 'bar@exemple.fr'));
    }

    public function testLesCodesDeDoubleAuthentificationGardentUnPlafondBas(): void
    {
        $this->echecs('198.51.100.1', 'totp:3', 5);
        $this->echecs('198.51.100.2', 'totp:3', 5);

        $this->assertTrue(isRateLimited($this->pdo, '203.0.113.50', 5, 900, 'totp:3', 10));
    }

    public function testLesAutresActionsNeComptentPasDansLeCompteurDeConnexion(): void
    {
        for ($i = 0; $i < 60; $i++) {
            enregistrerTentative($this->pdo, 'oubli', '198.51.100.1', 'bar@exemple.fr');
        }

        $this->assertFalse(isRateLimited($this->pdo, '198.51.100.1', 5, 900, 'bar@exemple.fr'));
    }
}
