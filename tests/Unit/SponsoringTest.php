<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Coup de projecteur et Top du fil : prix, durées, fenêtre réelle.
 */
final class SponsoringTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../includes/sponsoring.php';
    }

    public function testLaFenetreCourtDepuisLAchatPourUnEvenementLointain(): void
    {
        $maintenant = strtotime('2027-03-01 10:00:00');
        $fin = finSponsoring('boost24', '2027-04-15 21:00:00', $maintenant);
        $this->assertSame('2027-03-03 10:00:00', $fin, '48 h à partir de l\'achat');

        $fin = finSponsoring('top7', '2027-04-15 21:00:00', $maintenant);
        $this->assertSame('2027-03-08 10:00:00', $fin, '7 jours à partir de l\'achat');
    }

    public function testLaFenetreSArreteALaFinDeLEvenementPasAuDebut(): void
    {
        $maintenant = strtotime('2027-03-01 10:00:00');
        // Soirée à 20 h, sans heure de fin : 12 h par défaut, donc 8 h du lendemain.
        $this->assertSame('2027-03-02 08:00:00', finSponsoring('top7', '2027-03-01 20:00:00', $maintenant));
        // Avec une fin saisie, c'est elle.
        $this->assertSame('2027-03-02 02:00:00', finSponsoring('top7', '2027-03-01 20:00:00', $maintenant, '2027-03-02 02:00:00'));
    }

    public function testUnEvenementFiniNeSePousseEnAvantPas(): void
    {
        $maintenant = strtotime('2027-03-05 10:00:00');
        $this->assertNull(finSponsoring('boost24', '2027-03-01 20:00:00', $maintenant));
        $this->assertNull(finSponsoring('inconnue', '2027-03-10 20:00:00', $maintenant));
    }

    public function testLaMiseEnAvantOfferteNeCouvreQueLeCoupDeProjecteur(): void
    {
        $this->assertSame(['boost24'], formulesSponsoringOffrables());
    }

    public function testChaqueFormuleAUnePrixUneDureeEtDesNotifications(): void
    {
        foreach (formulesSponsoring() as $code => $f) {
            $this->assertGreaterThan(0.0, $f['tarif'], $code);
            $this->assertGreaterThan(0, $f['heures'], $code);
            $this->assertGreaterThan(0, $f['notifications'], $code);
        }
        $this->assertGreaterThan(tarifSponsoring('boost24'), tarifSponsoring('top7'));
    }

    public function testLeDeroulDUneMiseEnAvantEnCours(): void
    {
        $maintenant = strtotime('2027-03-01 22:00:00');
        $c = sponsoringChrono([
            'is_sponsorise' => 1, 'sponsor_formule' => 'boost24',
            'sponsor_debut' => '2027-03-01 10:00:00', 'sponsor_jusqu_au' => '2027-03-03 10:00:00',
        ], $maintenant);

        $this->assertNotNull($c);
        $this->assertTrue($c['actif']);
        $this->assertFalse($c['terminee']);
        $this->assertSame('2027-03-01 10:00:00', $c['debut']);
        $this->assertSame(36 * 3600, $c['restant_s'], 'il reste 36 h sur 48 h');
        $this->assertSame(48 * 3600, $c['duree_s']);
        $this->assertSame(25, $c['ecoule_pct'], '12 h écoulées sur 48 h');
    }

    public function testUneMiseEnAvantTermineeNAPlusDeTempsRestant(): void
    {
        $c = sponsoringChrono([
            'is_sponsorise' => 1, 'sponsor_formule' => 'boost24',
            'sponsor_debut' => '2027-03-01 10:00:00', 'sponsor_jusqu_au' => '2027-03-03 10:00:00',
        ], strtotime('2027-03-05 00:00:00'));

        $this->assertFalse($c['actif']);
        $this->assertTrue($c['terminee']);
        $this->assertSame(0, $c['restant_s']);
        $this->assertSame(100, $c['ecoule_pct']);
    }

    public function testUneMiseEnAvantAncienneSansDebutNeMontreQueSaFin(): void
    {
        $c = sponsoringChrono([
            'is_sponsorise' => 1, 'sponsor_formule' => 'boost24', 'sponsor_debut' => null,
            'sponsor_jusqu_au' => '2027-03-03 10:00:00',
        ], strtotime('2027-03-02 10:00:00'));

        $this->assertNull($c['debut']);
        $this->assertNull($c['duree_s']);
        $this->assertNull($c['ecoule_pct']);
        $this->assertSame(24 * 3600, $c['restant_s']);
    }

    public function testUnEvenementSansMiseEnAvantNaPasDeDeroule(): void
    {
        $this->assertNull(sponsoringChrono(['is_sponsorise' => 0, 'sponsor_jusqu_au' => '2027-03-03 10:00:00']));
    }

    public function testLaDureeSeLitEnClair(): void
    {
        $this->assertSame('1 j 23 h 05 min', dureeLisible(86400 + 23 * 3600 + 5 * 60));
        $this->assertSame('4 h 12 min', dureeLisible(4 * 3600 + 12 * 60));
        $this->assertSame('8 min', dureeLisible(8 * 60 + 30));
        $this->assertSame('2 j', dureeLisible(48 * 3600), 'les unités à zéro disparaissent');
        $this->assertSame('1 j 6 h', dureeLisible(30 * 3600));
        $this->assertSame('3 h', dureeLisible(3 * 3600));
        $this->assertSame("moins d'une minute", dureeLisible(42));
        $this->assertSame("moins d'une minute", dureeLisible(-5));
    }
}
