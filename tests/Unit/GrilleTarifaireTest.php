<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * La grille tarifaire Outsee (docs/Grille_Tarifaire_Outsee.xlsx) : abonnements,
 * quotas d'événements, flash, mises en avant, posts sponsorisés.
 */
final class GrilleTarifaireTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../includes/capacites.php';
        require_once __DIR__ . '/../../includes/sponsoring.php';
    }

    public function testLesAbonnementsSuiventLaGrille(): void
    {
        $o = crmOffres();
        $g = grilleCapacites();

        $attendu = [
            // code => [prix, événements/mois, flash/mois, mises en avant offertes]
            'bde'       => [25.99, 4,  0, 0],
            'fondateur' => [39,    6,  1, 0],
            'club'      => [49.99, 12, 0, 0],
            'essentiel' => [49.99, 6,  0, 0],
            'premium'   => [69.99, 15, 1, 1],
        ];
        foreach ($attendu as $code => [$prix, $events, $flash, $mea]) {
            $this->assertSame($prix, $o[$code]['tarif'], "prix $code");
            $this->assertSame($events, $g[$code]['evenements_par_mois'], "événements $code");
            $this->assertSame($flash, $g[$code]['flash_par_mois'], "flash $code");
            $this->assertSame($mea, $g[$code]['mises_en_avant_offertes'], "mises en avant $code");
        }
    }

    public function testLeFondateurADesEvenementsEnPlusPendantSesTroisPremiersMois(): void
    {
        $this->assertSame(8, grilleCapacites()['fondateur']['evenements_debut']);
    }

    public function testLesPostsSponsorisesSuiventLaGrille(): void
    {
        $f = formulesSponsoring();
        $this->assertSame(['boost24', 'top7'], array_keys($f));
        $this->assertSame(70.0, $f['boost24']['tarif']);
        $this->assertSame(48, $f['boost24']['heures']);
        $this->assertSame(1, $f['boost24']['notifications']);
        $this->assertSame(350.0, $f['top7']['tarif']);
        $this->assertSame(168, $f['top7']['heures']);
        $this->assertSame(3, $f['top7']['notifications']);
    }

    public function testUneFormuleRetireeNEstPlusVendueMaisResteLisible(): void
    {
        $this->assertFalse(formuleSponsoringValide('premium30'));
        $this->assertSame('Premium — 129 €', libelleSponsoring('premium30'));
    }

    public function testLeSupplementEstUnPrixUniqueEtPositif(): void
    {
        $this->assertGreaterThan(0.0, PRIX_EVENEMENT_SUPPLEMENTAIRE);
    }
}
