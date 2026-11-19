<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/evenements_suppression.php';

/**
 * PAY-10 / DB-01 : un événement qui engage quelqu'un (pass non annulé,
 * paiement encaissé) ne se supprime plus ; les autres, comme avant.
 */
final class EvenementSuppressionTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('
            CREATE TABLE evenements (id INTEGER PRIMARY KEY, etablissement_id INTEGER);
            CREATE TABLE inscriptions (id INTEGER PRIMARY KEY, evenement_id INTEGER, statut TEXT);
            CREATE TABLE paiements_evenements (id INTEGER PRIMARY KEY, evenement_id INTEGER, statut TEXT);
            INSERT INTO evenements (id, etablissement_id) VALUES (1, 10), (2, 10), (3, 10), (4, 20);
            INSERT INTO inscriptions (evenement_id, statut) VALUES (1, \'inscrit\'), (2, \'annule\'), (3, \'annule\');
            INSERT INTO paiements_evenements (evenement_id, statut) VALUES (3, \'paye\'), (2, \'expire\');
        ');
    }

    public function testLaRegleSeule(): void
    {
        $this->assertNull(evenementSuppressionRefus(0, 0));
        $this->assertSame('inscrits', evenementSuppressionRefus(3, 0)['code'] ?? null);
        $this->assertSame('inscrits', evenementSuppressionRefus(1, 1)['code'] ?? null);
        $this->assertSame('paiement', evenementSuppressionRefus(0, 1)['code'] ?? null);
    }

    public function testUnEvenementAvecUnPassNeSeSupprimePas(): void
    {
        $r = evenementSupprimer($this->pdo, 1, 10);
        $this->assertFalse($r['ok']);
        $this->assertSame('inscrits', $r['code']);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM evenements WHERE id = 1')->fetchColumn());
    }

    public function testUnEvenementPayeNeSeSupprimePas(): void
    {
        $this->assertSame('paiement', evenementSupprimer($this->pdo, 3, 10)['code']);
    }

    public function testSansInscritNiPaiementLaSuppressionPasse(): void
    {
        $r = evenementSupprimer($this->pdo, 2, 10);
        $this->assertTrue($r['ok']);
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM evenements WHERE id = 2')->fetchColumn());
    }

    public function testOnNeSupprimePasLEvenementDUnAutre(): void
    {
        $this->assertSame('introuvable', evenementSupprimer($this->pdo, 4, 10)['code']);
        $this->assertTrue(evenementSupprimer($this->pdo, 4)['ok'], 'l’administrateur n’est pas borné à un établissement');
    }

    public function testPlusAucunDeleteDirectDansLesPointsDeSuppression(): void
    {
        $racine = dirname(__DIR__, 2);
        foreach (['api/v1/partenaire_evenements.php', 'partenaire/evenements.php', 'api/admin_supprimer_evenement.php'] as $f) {
            $code = (string) file_get_contents("$racine/$f");
            $this->assertStringNotContainsString('DELETE FROM evenements', $code, $f);
            $this->assertStringContainsString('evenementSupprimer(', $code, $f);
        }
    }
}
