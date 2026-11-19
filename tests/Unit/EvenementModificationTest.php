<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/evenements_modification.php';

/**
 * Modifier un événement déjà en ligne : permis, sauf s'il est terminé,
 * s'il serait redaté dans le passé, ou avec moins de places que de pass
 * pris. Un changement de date ou de lieu se dit aux inscrits.
 */
final class EvenementModificationTest extends TestCase
{
    private function ev(string $debut, ?string $fin = null): array
    {
        return ['date_heure' => $debut, 'date_fin' => $fin, 'lieu' => 'Place de Jaude'];
    }

    private function dans(int $secondes): string
    {
        return date('Y-m-d H:i:s', time() + $secondes);
    }

    public function testUnEvenementAVenirSeModifie(): void
    {
        $this->assertSame([], evenementModificationRefus($this->ev($this->dans(86400)), $this->dans(2 * 86400), null, 50, 12));
    }

    public function testUnEvenementEnCoursSeModifie(): void
    {
        $ev = $this->ev($this->dans(-3600), $this->dans(3 * 3600));
        $this->assertSame([], evenementModificationRefus($ev, $ev['date_heure'], $ev['date_fin'], 50, 12));
    }

    public function testUnEvenementTermineNeSeModifiePlus(): void
    {
        $refus = evenementModificationRefus($this->ev($this->dans(-3 * 86400)), $this->dans(7 * 86400), null, 50, 0);
        $this->assertCount(1, $refus);
        $this->assertStringStartsWith('Cet événement est terminé', $refus[0]);
    }

    public function testOnNeRedatePasDansLePasse(): void
    {
        $refus = evenementModificationRefus($this->ev($this->dans(86400)), $this->dans(-2 * 86400), null, 50, 3);
        $this->assertStringContainsString('déjà terminé', $refus[0] ?? '');
    }

    public function testPasMoinsDePlacesQueDePassPris(): void
    {
        $this->assertStringContainsString('5 inscrits', evenementModificationRefus($this->ev($this->dans(86400)), $this->dans(86400), null, 4, 5)[0] ?? '');
        $this->assertSame([], evenementModificationRefus($this->ev($this->dans(86400)), $this->dans(86400), null, 5, 5));
    }

    public function testChangementsDitsAuxInscrits(): void
    {
        $avant = ['date_heure' => '2026-11-28 20:00:21', 'lieu' => 'Place de Jaude'];

        $this->assertSame([], evenementChangementsPourInscrits($avant, ['date_heure' => '2026-11-28 20:00:00', 'lieu' => ' place de  jaude ']),
            'mêmes minute et lieu (casse, espaces) : rien à dire');
        $this->assertSame(['Nouvelle date : sam. 28 nov. à 21h30.'],
            evenementChangementsPourInscrits($avant, ['date_heure' => '2026-11-28 21:30:00', 'lieu' => 'Place de Jaude']));
        $this->assertSame(['Nouveau lieu : Rue des Gras.'],
            evenementChangementsPourInscrits($avant, ['date_heure' => '2026-11-28 20:00:00', 'lieu' => 'Rue des Gras']));
    }

    public function testLesDeuxCheminsDeModificationAppliquentLaRegle(): void
    {
        $racine = dirname(__DIR__, 2);
        foreach (['api/v1/partenaire_evenement.php', 'includes/evenement_pro.php'] as $f) {
            $code = (string) file_get_contents("$racine/$f");
            $this->assertStringContainsString('evenementModificationRefus(', $code, $f);
            $this->assertStringContainsString('evenementPrevenirInscrits(', $code, $f);
        }
    }
}
