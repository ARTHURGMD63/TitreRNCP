<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/kpi.php';

/**
 * PAY-19 : statistiques des établissements et de l'administration.
 */
final class StatistiquesProTest extends TestCase
{
    public function testLeTauxDePresenceNeCompteQueLesSoireesPasseesDeLaPeriode(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE evenements (id INTEGER PRIMARY KEY, date_heure TEXT)');
        $pdo->exec('CREATE TABLE inscriptions (id INTEGER PRIMARY KEY, evenement_id INTEGER, statut TEXT)');
        $hier   = date('Y-m-d H:i:s', time() - 86400);
        $demain = date('Y-m-d H:i:s', time() + 86400);
        $vieux  = date('Y-m-d H:i:s', time() - 90 * 86400);
        $pdo->exec("INSERT INTO evenements (id, date_heure) VALUES (1, '$hier'), (2, '$demain'), (3, '$vieux')");
        $pdo->exec("INSERT INTO inscriptions (evenement_id, statut) VALUES
            (1, 'checkin'), (1, 'inscrit'), (1, 'annule'),
            (2, 'inscrit'), (3, 'inscrit')");

        // Soirée 1 seule : 1 présent sur 2 inscrits non annulés.
        $this->assertSame(50.0, tauxPresenceGlobal($pdo, 30));
    }

    public function testLesRequetesCorrigeesSontEnPlace(): void
    {
        $racine = dirname(__DIR__, 2);
        $kpi    = (string) file_get_contents("$racine/includes/kpi.php");
        $this->assertStringContainsString("statut = 'checkin' AND checkin_le >= DATE_SUB(NOW(), INTERVAL 7 DAY)", $kpi);

        $tableau = (string) file_get_contents("$racine/api/v1/partenaire_dashboard.php");
        $this->assertMatchesRegularExpression("/GROUP BY u\.ecole/", $tableau);
        $this->assertStringContainsString("i.statut <> 'annule' AND u.ecole IS NOT NULL", $tableau);
    }
}
