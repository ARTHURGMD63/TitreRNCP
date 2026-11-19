<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Âge minimum des événements et numéro de billet (v44).
 */
final class AgeMinimumTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../includes/age_minimum.php';
        require_once __DIR__ . '/../../includes/billet.php';
        require_once __DIR__ . '/../../includes/textes_legaux.php';
    }

    public function testLaSaisieEstRameneeEntre18Et99(): void
    {
        $this->assertSame(18, ageMinimumNormalise(null));
        $this->assertSame(18, ageMinimumNormalise(''));
        $this->assertSame(18, ageMinimumNormalise('abc'));
        $this->assertSame(18, ageMinimumNormalise(16), 'Outsee reste réservé aux majeurs');
        $this->assertSame(21, ageMinimumNormalise('21'));
        $this->assertSame(99, ageMinimumNormalise(150));
    }

    public function testLAgeSeCompteLeJourDeLEvenement(): void
    {
        // 21 ans le 10 décembre 2026.
        $naissance = '2005-12-10';
        $this->assertSame(20, ageALaDate($naissance, '2026-12-09 23:00:00'));
        $this->assertSame(21, ageALaDate($naissance, '2026-12-10 22:00:00'));

        $this->assertFalse(ageMinimumAtteint($naissance, '2026-12-09 23:00:00', 21));
        $this->assertTrue(ageMinimumAtteint($naissance, '2026-12-10 22:00:00', 21), 'réservable avant son anniversaire si la soirée a lieu après');
    }

    public function testUneDateDeNaissanceInconnueNEstPasUnRefus(): void
    {
        $this->assertNull(ageALaDate(null, '2026-12-10'));
        $this->assertNull(ageALaDate('n importe quoi', '2026-12-10'));
        $this->assertTrue(ageMinimumAtteint(null, '2026-12-10', 21));
    }

    public function testLeMessageDeRefus(): void
    {
        $this->assertSame('Cet événement est réservé aux 21 ans et plus.', ageMinimumMessage(21));
    }

    public function testLeNumeroDeBilletRelieLIdentifiantEtLeQr(): void
    {
        $this->assertSame('OS-000123-3FA2', numeroBillet(123, '3fa29c1b' . str_repeat('0', 56)));
        $this->assertSame('OS-1234567-ABCD', numeroBillet(1234567, 'abcd'));
        $this->assertSame('OS-000042', numeroBillet(42, ''));
        // Un code neuf (pass annulé puis repris) change le numéro.
        $this->assertNotSame(numeroBillet(7, 'aaaa'), numeroBillet(7, 'bbbb'));
    }

    public function testLesTextesLegauxAnnoncentLAgeEtLeRefusALEntree(): void
    {
        $textes = textesLegaux();
        $cgu = (string) json_encode($textes['cgu'], JSON_UNESCAPED_UNICODE);
        $cgv = (string) json_encode($textes['cgv'], JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('âge minimum', $cgu);
        $this->assertStringContainsString("Outsee n'en est pas responsable", $cgu);
        $this->assertStringContainsString('billet électronique nominatif', $cgu);
        $this->assertStringContainsString("fixe l'âge minimum", $cgv);
    }
}
