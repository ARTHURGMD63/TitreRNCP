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
}
