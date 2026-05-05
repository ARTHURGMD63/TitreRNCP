<?php
/**
 * Helpers gamification : calcul XP, niveau, et attribution automatique des badges.
 * Appeler checkBadges($pdo, $uid) après chaque action majeure (inscription, squad, follow, avis).
 */

function getUserStats(PDO $pdo, int $uid): array {
    $stats = [];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE user_id=? AND statut != 'annule'");
    $stmt->execute([$uid]);
    $stats['events'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM squad_membres WHERE user_id=?");
    $stmt->execute([$uid]);
    $stats['squads'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows_users WHERE follower_id=?");
    $stmt->execute([$uid]);
    $stats['follows'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM avis WHERE user_id=?");
    $stmt->execute([$uid]);
    $stats['avis'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM economies WHERE user_id=?");
    $stmt->execute([$uid]);
    $stats['economies'] = (float)$stmt->fetchColumn();

    return $stats;
}

/** XP = 15 × events + 10 × squads + 5 × follows + 8 × avis */
function getXp(array $stats): int {
    return $stats['events']*15 + $stats['squads']*10 + $stats['follows']*5 + $stats['avis']*8;
}

/** Niveau basé sur sqrt(XP/50) */
function getLevel(int $xp): int {
    return max(1, (int)floor(sqrt($xp / 50)) + 1);
}

function xpForLevel(int $level): int {
    return ($level - 1) ** 2 * 50;
}

function checkBadges(PDO $pdo, int $uid): array {
    $stats = getUserStats($pdo, $uid);
    $unlocked = [];
    $map = [
        'first_event'   => $stats['events']    >= 1,
        'five_events'   => $stats['events']    >= 5,
        'ten_events'    => $stats['events']    >= 10,
        'first_squad'   => $stats['squads']    >= 1,
        'five_squads'   => $stats['squads']    >= 5,
        'first_follow'  => $stats['follows']   >= 1,
        'reviewer'      => $stats['avis']      >= 1,
        'saver_50'      => $stats['economies'] >= 50,
    ];
    foreach ($map as $code => $cond) {
        if ($cond) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO user_badges (user_id, badge_code) VALUES (?,?)");
            $stmt->execute([$uid, $code]);
            if ($stmt->rowCount() > 0) $unlocked[] = $code;
        }
    }
    return $unlocked;
}

function getUserBadges(PDO $pdo, int $uid): array {
    $stmt = $pdo->prepare("
        SELECT b.*, ub.unlocked_at
        FROM user_badges ub
        JOIN badges b ON b.code = ub.badge_code
        WHERE ub.user_id = ?
        ORDER BY ub.unlocked_at DESC
    ");
    $stmt->execute([$uid]);
    return $stmt->fetchAll();
}

function getAllBadges(PDO $pdo): array {
    return $pdo->query("SELECT * FROM badges")->fetchAll();
}
