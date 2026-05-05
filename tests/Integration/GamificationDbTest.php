<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Tests d'intégration de la gamification avec une vraie BDD SQLite en mémoire.
 * Vérifie l'attribution automatique des badges via checkBadges().
 */
final class GamificationDbTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec("
            CREATE TABLE users (id INTEGER PRIMARY KEY, prenom TEXT);
            CREATE TABLE inscriptions (id INTEGER PRIMARY KEY, user_id INTEGER, statut TEXT);
            CREATE TABLE squad_membres (id INTEGER PRIMARY KEY, user_id INTEGER);
            CREATE TABLE follows_users (id INTEGER PRIMARY KEY, follower_id INTEGER, followed_id INTEGER);
            CREATE TABLE avis (id INTEGER PRIMARY KEY, user_id INTEGER, evenement_id INTEGER, note INTEGER);
            CREATE TABLE economies (id INTEGER PRIMARY KEY, user_id INTEGER, montant REAL);
            CREATE TABLE badges (code TEXT PRIMARY KEY, nom TEXT);
            CREATE TABLE user_badges (user_id INTEGER, badge_code TEXT, unlocked_at TEXT, PRIMARY KEY(user_id, badge_code));
        ");
        $this->pdo->exec("INSERT INTO users (id, prenom) VALUES (1, 'Test')");
        foreach (['first_event','five_events','ten_events','first_squad','five_squads','first_follow','reviewer','saver_50'] as $b) {
            $this->pdo->exec("INSERT INTO badges (code, nom) VALUES ('{$b}', '{$b}')");
        }

        if (!function_exists('getUserStats')) {
            require_once __DIR__ . '/../../includes/gamification.php';
        }
    }

    public function testGetUserStatsReturnsZerosForNewUser(): void
    {
        $stats = getUserStats($this->pdo, 1);
        $this->assertSame(0, $stats['events']);
        $this->assertSame(0, $stats['squads']);
        $this->assertSame(0, $stats['follows']);
        $this->assertSame(0, $stats['avis']);
    }

    public function testCheckBadgesUnlocksFirstEvent(): void
    {
        $this->pdo->exec("INSERT INTO inscriptions (user_id, statut) VALUES (1, 'inscrit')");
        $unlocked = checkBadges($this->pdo, 1);
        $this->assertContains('first_event', $unlocked);
    }

    public function testCheckBadgesIsIdempotent(): void
    {
        $this->pdo->exec("INSERT INTO inscriptions (user_id, statut) VALUES (1, 'inscrit')");
        $first = checkBadges($this->pdo, 1);
        $second = checkBadges($this->pdo, 1);
        $this->assertContains('first_event', $first);
        $this->assertNotContains('first_event', $second, 'Un badge ne doit pas être ré-attribué');
    }

    public function testCheckBadgesIgnoresAnnulees(): void
    {
        $this->pdo->exec("INSERT INTO inscriptions (user_id, statut) VALUES (1, 'annule')");
        $stats = getUserStats($this->pdo, 1);
        $this->assertSame(0, $stats['events']);
    }

    public function testFiveEventsUnlocksTwoBadgesAtOnce(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->pdo->exec("INSERT INTO inscriptions (user_id, statut) VALUES (1, 'inscrit')");
        }
        $unlocked = checkBadges($this->pdo, 1);
        $this->assertContains('first_event', $unlocked);
        $this->assertContains('five_events', $unlocked);
    }
}
