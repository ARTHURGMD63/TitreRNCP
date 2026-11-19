<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/sports.php';

/**
 * La date d'un squad créé depuis le site (api/create_squad.php) : insérée
 * telle quelle avant l'audit, passée ou invalide (ETU-03, ETU-06).
 */
final class SquadTest extends TestCase
{
    private int $maintenant;

    protected function setUp(): void
    {
        $this->maintenant = (int) strtotime('2026-11-19 12:00:00');
    }

    public function testUneDateAVenirEstNormalisee(): void
    {
        $this->assertSame('2026-12-01 18:30:00', squadDateValide('2026-12-01T18:30', $this->maintenant));
        $this->assertSame('2026-12-01 18:30:00', squadDateValide('2026-12-01 18:30:00', $this->maintenant));
    }

    public function testUneDatePasseeOuPresenteEstRefusee(): void
    {
        $this->assertNull(squadDateValide('2026-11-18 20:00', $this->maintenant));
        $this->assertNull(squadDateValide('2026-11-19 12:00', $this->maintenant));
    }

    public function testUneDateTropLointaineEstRefusee(): void
    {
        $this->assertNull(squadDateValide('2028-01-01 12:00', $this->maintenant));
        $this->assertNotNull(squadDateValide('2027-11-01 12:00', $this->maintenant));
    }

    public function testUneSaisieIllisibleEstRefusee(): void
    {
        foreach (['', 'demain', '2026-13-45 99:99', "2026-12-01 18:30'; DROP TABLE squads", '1700000000'] as $saisie) {
            $this->assertNull(squadDateValide($saisie, $this->maintenant), $saisie);
        }
    }

    public function testLesNiveauxSontUneListeFermee(): void
    {
        $this->assertSame(['tous', 'debutant', 'inter', 'avance'], niveauxSport());
    }
}
