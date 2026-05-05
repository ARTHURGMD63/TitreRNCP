<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests des fonctions pures de gamification (XP, niveau, seuils).
 * Ne touche pas à la BDD : on teste uniquement les calculs.
 */
final class GamificationTest extends TestCase
{
    public function testGetXpReturnsZeroForEmptyStats(): void
    {
        $stats = ['events' => 0, 'squads' => 0, 'follows' => 0, 'avis' => 0, 'economies' => 0];
        $this->assertSame(0, getXp($stats));
    }

    public function testGetXpEvent(): void
    {
        $stats = ['events' => 1, 'squads' => 0, 'follows' => 0, 'avis' => 0, 'economies' => 0];
        $this->assertSame(15, getXp($stats));
    }

    public function testGetXpSquad(): void
    {
        $stats = ['events' => 0, 'squads' => 1, 'follows' => 0, 'avis' => 0, 'economies' => 0];
        $this->assertSame(10, getXp($stats));
    }

    public function testGetXpFollow(): void
    {
        $stats = ['events' => 0, 'squads' => 0, 'follows' => 1, 'avis' => 0, 'economies' => 0];
        $this->assertSame(5, getXp($stats));
    }

    public function testGetXpAvis(): void
    {
        $stats = ['events' => 0, 'squads' => 0, 'follows' => 0, 'avis' => 1, 'economies' => 0];
        $this->assertSame(8, getXp($stats));
    }

    public function testGetXpCombined(): void
    {
        // 3 events (45) + 2 squads (20) + 4 follows (20) + 1 avis (8) = 93
        $stats = ['events' => 3, 'squads' => 2, 'follows' => 4, 'avis' => 1, 'economies' => 0];
        $this->assertSame(93, getXp($stats));
    }

    public function testGetLevelMinimumIsOne(): void
    {
        $this->assertSame(1, getLevel(0));
        $this->assertSame(1, getLevel(10));
        $this->assertSame(1, getLevel(49));
    }

    public function testGetLevelTwoAtFiftyXp(): void
    {
        $this->assertSame(2, getLevel(50));
        $this->assertSame(2, getLevel(100));
    }

    public function testGetLevelGrowsWithSqrt(): void
    {
        // 200 XP -> sqrt(200/50)=2 -> level 3
        $this->assertSame(3, getLevel(200));
        // 450 XP -> sqrt(9)=3 -> level 4
        $this->assertSame(4, getLevel(450));
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function levelThresholdsProvider(): array
    {
        return [
            'level 1' => [1, 0],
            'level 2' => [2, 50],
            'level 3' => [3, 200],
            'level 4' => [4, 450],
            'level 5' => [5, 800],
            'level 10' => [10, 4050],
        ];
    }

    #[DataProvider('levelThresholdsProvider')]
    public function testXpForLevelMatchesFormula(int $level, int $expectedXp): void
    {
        $this->assertSame($expectedXp, xpForLevel($level));
    }

    public function testXpForLevelAndGetLevelAreInverseAtBoundary(): void
    {
        for ($lvl = 2; $lvl <= 8; $lvl++) {
            $threshold = xpForLevel($lvl);
            $this->assertSame(
                $lvl,
                getLevel($threshold),
                "À {$threshold} XP on doit être niveau {$lvl}"
            );
        }
    }

    public function testGetLevelIsMonotonic(): void
    {
        $previous = 1;
        for ($xp = 0; $xp <= 5000; $xp += 50) {
            $current = getLevel($xp);
            $this->assertGreaterThanOrEqual($previous, $current);
            $previous = $current;
        }
    }
}
