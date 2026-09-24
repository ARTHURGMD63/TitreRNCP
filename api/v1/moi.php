<?php
/**
 * GET /api/v1/moi.php — tout ce que l'écran « Moi » affiche.
 *
 * Les lectures de profil.php, dans le même ordre : identité, centres
 * d'intérêt, compteurs d'abonnés, tuiles (sorties, squads, économies), puis
 * la carte XP — niveau, jauge, résumé, badges obtenus et à débloquer. Les
 * badges sont vérifiés avant d'être lus, comme le fait la page du site.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/gamification.php';

apiExigerMethode('GET');

$moi = apiEtudiant($pdo);
$uid = (int) $moi['id'];

// Colonnes nommées : un SELECT * emporterait le hachage du mot de passe.
$stmt = $pdo->prepare('SELECT id, nom, prenom, email, ecole, promo, photo, interests, type, created_at FROM users WHERE id=?');
$stmt->execute([$uid]);
$u = $stmt->fetch();

$compter = static function (string $sql) use ($pdo, $uid): float {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uid]);
    return (float) $stmt->fetchColumn();
};

$nbInscriptions = (int) $compter("SELECT COUNT(*) FROM inscriptions WHERE user_id=? AND statut != 'annule'");
$ecoTotal       = $compter('SELECT COALESCE(SUM(montant),0) FROM economies WHERE user_id=?');
$nbSquads       = (int) $compter('SELECT COUNT(*) FROM squad_membres WHERE user_id=?');
$nbFollowing    = (int) $compter("SELECT COUNT(*) FROM follows_users WHERE follower_id=? AND statut='accepted'");
$nbFollowers    = (int) $compter("SELECT COUNT(*) FROM follows_users WHERE followed_id=? AND statut='accepted'");

checkBadges($pdo, $uid);
$stats     = getUserStats($pdo, $uid);
$xp        = getXp($stats);
$niveau    = getLevel($xp);
$xpNiveau  = xpForLevel($niveau);
$xpSuivant = xpForLevel($niveau + 1);
$progres   = max(0, min(100, ($xp - $xpNiveau) / max(1, $xpSuivant - $xpNiveau) * 100));

$obtenus = array_column(getUserBadges($pdo, $uid), 'code');
$badge   = static fn (array $b): array => [
    'code'        => (string) $b['code'],
    'nom'         => (string) $b['nom'],
    'description' => (string) ($b['description'] ?? ''),
    'icon'        => (string) ($b['icon'] ?? 'trophee'),
    'couleur'     => (string) ($b['couleur'] ?? 'var(--lime)'),
];
$tous = getAllBadges($pdo);

apiReponse([
    'success' => true,
    'moi'     => apiProfil($u) + [
        // Même ordre que le sélecteur : filtrerInterets() range selon le catalogue.
        'interets'       => filtrerInterets(interetsDepuisTexte($u['interests'] ?? null)),
        'photo_url'      => apiPhotoUrl($u['photo'] ?? null),
        'depuis'         => dateFr((string) $u['created_at'], 'M Y'),
        'nb_abonnes'     => $nbFollowers,
        'nb_abonnements' => $nbFollowing,
        'nb_sorties'     => $nbInscriptions,
        'nb_squads'      => $nbSquads,
        'economies'      => $ecoTotal,
    ],
    'xp' => [
        'total'     => $xp,
        'niveau'    => $niveau,
        'suivant'   => $xpSuivant,
        'progres'   => round($progres, 1),
        'stats'     => [
            'events'  => (int) $stats['events'],
            'squads'  => (int) $stats['squads'],
            'follows' => (int) $stats['follows'],
            'avis'    => (int) $stats['avis'],
        ],
        'obtenus'    => array_values(array_map($badge, array_filter($tous, static fn (array $b): bool => in_array($b['code'], $obtenus, true)))),
        'a_debloquer' => array_values(array_map($badge, array_filter($tous, static fn (array $b): bool => !in_array($b['code'], $obtenus, true)))),
    ],
    // Les listes des deux sélecteurs de profil.php.
    'choix' => [
        'ecoles'   => ['UCA', 'SIGMA Clermont', 'INP Ingénieurs', 'IFSI', 'Autre'],
        'promos'   => ['L1', 'L2', 'L3', 'M1', 'M2', 'BUT1', 'BUT2', 'BUT3'],
        'interets' => interetsDisponibles(),
    ],
]);
