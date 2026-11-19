<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/profil_etudiant.php';

/**
 * École et promo du profil étudiant (ETU-16) : bornées à la taille des
 * colonnes, promo prise dans la liste, une ancienne valeur tolérée tant
 * qu'elle ne change pas.
 */
final class ProfilEtudiantTest extends TestCase
{
    public function testLaListeDesPromosEstCelleDuMobile(): void
    {
        $mobile = (string) @file_get_contents(__DIR__ . '/../../mobile/src/catalogue.ts');
        if ($mobile === '') {
            $this->markTestSkipped('mobile/src/catalogue.ts absent');
        }
        $this->assertStringContainsString(
            "export const PROMOS = ['" . implode("', '", promosDisponibles()) . "'];",
            $mobile
        );
    }

    public function testUneEcoleEstResserreeEtBornee(): void
    {
        $this->assertSame('SIGMA Clermont', ecoleNormalisee("  SIGMA   Clermont \n"));
        $this->assertSame('', ecoleNormalisee('   '));
        $this->assertSame(str_repeat('é', ECOLE_LONGUEUR_MAX), ecoleNormalisee(str_repeat('é', ECOLE_LONGUEUR_MAX)));
        $this->assertNull(ecoleNormalisee(str_repeat('a', ECOLE_LONGUEUR_MAX + 1)));
    }

    public function testUnePromoDoitVenirDeLaListe(): void
    {
        $this->assertTrue(promoAcceptee(''));
        $this->assertTrue(promoAcceptee('M2'));
        $this->assertFalse(promoAcceptee('m2'));
        $this->assertFalse(promoAcceptee('Master 2 info'));
    }

    public function testUneAnciennePromoInchangeeResteAcceptee(): void
    {
        $this->assertTrue(promoAcceptee('DUT2', 'DUT2'));
        $this->assertFalse(promoAcceptee('DUT3', 'DUT2'));
        $this->assertFalse(promoAcceptee(str_repeat('x', 40), str_repeat('x', 40)));
    }
}
