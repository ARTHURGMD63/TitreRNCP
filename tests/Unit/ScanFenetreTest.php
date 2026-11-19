<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/evenements_temps.php';

/**
 * PAY-17 : une entrée ne se valide que dans la fenêtre de l'événement, et
 * chaque validation garde son mode (scan ou manuel) et son auteur.
 */
final class ScanFenetreTest extends TestCase
{
    private function t(string $quand): int
    {
        return (int) strtotime($quand);
    }

    public function testLaFenetreSOuvreDeuxHeuresAvant(): void
    {
        $debut = '2026-11-20 22:00:00';
        $this->assertFalse(evenementScanOuvert($debut, null, $this->t('2026-11-20 19:59:00')));
        $this->assertTrue(evenementScanOuvert($debut, null, $this->t('2026-11-20 20:00:00')));
        $this->assertTrue(evenementScanOuvert($debut, null, $this->t('2026-11-20 23:30:00')));
    }

    public function testLaFenetreSeFermeDeuxHeuresApresLaFin(): void
    {
        $debut = '2026-11-20 22:00:00';
        $fin   = '2026-11-21 03:00:00';
        $this->assertTrue(evenementScanOuvert($debut, $fin, $this->t('2026-11-21 05:00:00')));
        $this->assertFalse(evenementScanOuvert($debut, $fin, $this->t('2026-11-21 05:01:00')));
        // Sans fin déclarée : la fenêtre par défaut, plus la marge.
        $limite = $this->t($debut) + (EVENEMENT_DUREE_PAR_DEFAUT_HEURES + SCAN_FERMETURE_APRES_HEURES) * 3600;
        $this->assertTrue(evenementScanOuvert($debut, null, $limite));
        $this->assertFalse(evenementScanOuvert($debut, null, $limite + 1));
    }

    public function testLeMessageDitSiCestTropTotOuTropTard(): void
    {
        $debut = '2026-11-20 22:00:00';
        $this->assertStringContainsString('s’ouvrent', evenementScanMessageFerme($debut, null, $this->t('2026-11-19 12:00:00')));
        $this->assertStringContainsString('terminé', evenementScanMessageFerme($debut, null, $this->t('2026-12-20 12:00:00')));
    }

    public function testLesDeuxPointsDeScanAppliquentLaFenetreEtJournalisent(): void
    {
        $racine = dirname(__DIR__, 2);
        foreach (['api/v1/partenaire_scan.php', 'partenaire/api_scan.php'] as $f) {
            $code = (string) file_get_contents("$racine/$f");
            $this->assertStringContainsString('evenementScanOuvert(', $code, $f);
            $this->assertStringContainsString('checkin_mode = ?, checkin_par = ?', $code, $f);
            $this->assertStringContainsString("? 'manuel' : 'scan'", $code, $f);
        }
    }
}
