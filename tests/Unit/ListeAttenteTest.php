<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/liste_attente.php';

/**
 * Parrainage de la liste d'attente (ETU-12) : codes imprévisibles, anciens
 * codes encore acceptés pour les inscriptions qui n'ont que ceux-là, bonus
 * plafonné.
 */
final class ListeAttenteTest extends TestCase
{
    public function testUnCodeAleatoireALaBonneForme(): void
    {
        $code = codeParrainageAleatoire();
        $this->assertMatchesRegularExpression('/^LK[2-9A-HJ-NP-Z]{10}$/', $code);
        $this->assertNotSame($code, codeParrainageAleatoire());
    }

    public function testLeBonusEstPlafonne(): void
    {
        $this->assertSame(475, positionAffichee(500, 1));
        $this->assertSame(500 - LISTE_ATTENTE_FILLEULS_MAX * 25, positionAffichee(500, LISTE_ATTENTE_FILLEULS_MAX));
        $this->assertSame(500 - LISTE_ATTENTE_FILLEULS_MAX * 25, positionAffichee(500, 1000), 'plus de filleuls ne rapportent plus rien');
        $this->assertSame(1, positionAffichee(30, 5), 'jamais avant la première place');
    }

    public function testLesCodesAleatoiresEtLesAnciensSeResolvent(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE liste_attente (id INTEGER PRIMARY KEY, email TEXT, parrain_id INTEGER, code TEXT)');
        $pdo->exec("INSERT INTO liste_attente (id, email, code) VALUES (7, 'ancien@x.fr', NULL), (8, 'nouveau@x.fr', 'LKABCDEFGHJK')");

        $this->assertSame(8, idListeAttenteDepuisCode($pdo, 'lkabcdefghjk'));
        $this->assertSame(7, idListeAttenteDepuisCode($pdo, codeDepuisId(7)), 'un lien déjà partagé reste valable');
        $this->assertNull(idListeAttenteDepuisCode($pdo, codeDepuisId(8)), 'un id deviné ne désigne plus une inscription récente');
        $this->assertNull(idListeAttenteDepuisCode($pdo, codeDepuisId(9)));
        $this->assertNull(idListeAttenteDepuisCode($pdo, ''));
    }
}
