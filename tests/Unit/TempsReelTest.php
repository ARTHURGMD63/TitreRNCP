<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Le flux de révisions.
 *
 * Toute la mécanique temps réel repose sur une empreinte : si elle change
 * alors que rien n'a bougé, mille onglets retéléchargent leurs données pour
 * rien ; si elle ne change pas alors que quelque chose a bougé, l'écran ment.
 * Les deux erreurs sont silencieuses, d'où ces tests.
 */
final class TempsReelTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../includes/temps_reel.php';
    }

    public function testLesNomsDeCanauxSontPrefixes(): void
    {
        $this->assertSame('event:42', canalEvenement(42));
        $this->assertSame('user:7', canalUtilisateur(7));
        $this->assertSame('squad:3', canalSquad(3));
    }

    public function testMemeEtatDonneMemeEmpreinte(): void
    {
        $etat = ['global' => 4, 'user:7' => 12];
        $this->assertSame(fluxSignature($etat), fluxSignature($etat));
    }

    public function testLOrdreDesCanauxNeChangeRien(): void
    {
        // `?ev=1,2` et `?ev=2,1` décrivent le même écran. Sans le tri par clé
        // dans fluxSignature(), changer l'ordre des paramètres d'URL
        // invaliderait le cache du navigateur à chaque interrogation.
        $this->assertSame(
            fluxSignature(['event:1' => 3, 'event:2' => 5]),
            fluxSignature(['event:2' => 5, 'event:1' => 3])
        );
    }

    public function testUneSeuleRevisionQuiBougeChangeLEmpreinte(): void
    {
        $avant = fluxSignature(['global' => 1, 'event:9' => 7]);
        $apres = fluxSignature(['global' => 1, 'event:9' => 8]);
        $this->assertNotSame($avant, $apres);
    }

    public function testUnPerimetreDifferentDonneUneEmpreinteDifferente(): void
    {
        // Ajouter une carte à l'écran doit provoquer un rechargement : sinon
        // la nouvelle soirée s'afficherait avec un compteur jamais renseigné.
        $this->assertNotSame(
            fluxSignature(['global' => 1]),
            fluxSignature(['global' => 1, 'event:2' => 0])
        );
    }

    public function testLEmpreinteEstCourteEtUtilisableCommeEtag(): void
    {
        $signature = fluxSignature(['global' => 1, 'user:3' => 99]);
        $this->assertSame(16, strlen($signature));
        // Un ETag voyage dans un en-tête HTTP : pas de guillemet, pas
        // d'espace, rien qui demanderait un échappement.
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $signature);
    }
}
