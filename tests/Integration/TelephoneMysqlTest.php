<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Codes SMS de vérification du numéro (v43), joués contre MySQL/MariaDB :
 * ces fonctions parlent MySQL (DATE_ADD … INTERVAL). Même réglage que
 * CompteMysqlTest (TEST_MYSQL_DSN, schéma db_setup.sql déjà posé).
 */
final class TelephoneMysqlTest extends TestCase
{
    private PDO $pdo;

    private const NUMERO = '+33699999901';

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

        require_once __DIR__ . '/../../includes/telephone.php';
        $this->nettoyer();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->nettoyer();
        }
    }

    private function nettoyer(): void
    {
        $this->pdo->prepare('DELETE FROM verifications_telephone WHERE telephone = ?')->execute([self::NUMERO]);
        $this->pdo->prepare('DELETE FROM users WHERE telephone = ?')->execute([self::NUMERO]);
    }

    public function testLeBonCodeEstAccepteSansEtreConsomme(): void
    {
        $code = telephoneCodeDemander($this->pdo, self::NUMERO);
        $this->assertIsString($code);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $this->assertTrue(telephoneCodeVerifier($this->pdo, self::NUMERO, $code));
        // Toujours valable : l'inscription peut encore échouer après la vérification.
        $this->assertTrue(telephoneCodeVerifier($this->pdo, self::NUMERO, $code));

        telephoneCodeConsommer($this->pdo, self::NUMERO);
        $this->assertFalse(telephoneCodeVerifier($this->pdo, self::NUMERO, $code));
    }

    public function testUnSecondCodeAvantUneMinuteEstRefuse(): void
    {
        $this->assertNotNull(telephoneCodeDemander($this->pdo, self::NUMERO));
        $this->assertNull(telephoneCodeDemander($this->pdo, self::NUMERO));
    }

    public function testCinqErreursDetruisentLeCode(): void
    {
        $code = (string) telephoneCodeDemander($this->pdo, self::NUMERO);
        $faux = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < TELEPHONE_CODE_ESSAIS_MAX; $i++) {
            $this->assertFalse(telephoneCodeVerifier($this->pdo, self::NUMERO, $faux));
        }
        // Le bon code ne passe plus : il faut en redemander un.
        $this->assertFalse(telephoneCodeVerifier($this->pdo, self::NUMERO, $code));
    }

    public function testUnCodeExpireEstRefuse(): void
    {
        $code = (string) telephoneCodeDemander($this->pdo, self::NUMERO);
        $this->pdo->prepare('UPDATE verifications_telephone SET expire_le = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE telephone = ?')
            ->execute([self::NUMERO]);

        $this->assertFalse(telephoneCodeVerifier($this->pdo, self::NUMERO, $code));
    }

    public function testUnNumeroNeSertQuAUnCompte(): void
    {
        $this->assertFalse(telephonePris($this->pdo, self::NUMERO));

        $this->pdo->prepare("INSERT INTO users (nom, prenom, email, telephone, password, type) VALUES ('T', 'T', ?, ?, 'x', 'etudiant')")
            ->execute(['tel-' . bin2hex(random_bytes(4)) . '@test.invalid', self::NUMERO]);
        $id = (int) $this->pdo->lastInsertId();

        $this->assertTrue(telephonePris($this->pdo, self::NUMERO));
        $this->assertFalse(telephonePris($this->pdo, self::NUMERO, $id), 'son propre numéro ne compte pas');

        $this->expectException(\PDOException::class);
        $this->pdo->prepare("INSERT INTO users (nom, prenom, email, telephone, password, type) VALUES ('U', 'U', ?, ?, 'x', 'etudiant')")
            ->execute(['tel-' . bin2hex(random_bytes(4)) . '@test.invalid', self::NUMERO]);
    }
}
