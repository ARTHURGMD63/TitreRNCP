<?php
/**
 * GET /api/v1/classement.php?portee=amis|ecole|ville — le classement XP.
 *
 * Même calcul que classement.php : la liste globale mise en cache une minute
 * (classementGlobal), dérivée en mémoire pour le périmètre demandé, avec l'XP
 * du lecteur en direct. Les deux cas vides du site sont signalés tels quels
 * (`sans_ecole`, `sans_amis`) pour que l'application affiche les mêmes
 * messages.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/gamification.php';
require_once __DIR__ . '/../../includes/social.php';

apiExigerMethode('GET');

$moi = apiEtudiant($pdo);
$uid = (int) $moi['id'];

$portees = ['amis' => 'Amis', 'ecole' => 'Mon école', 'ville' => 'Clermont'];
$portee  = array_key_exists($_GET['portee'] ?? '', $portees) ? (string) $_GET['portee'] : 'amis';

$stmt = $pdo->prepare('SELECT prenom, nom, ecole FROM users WHERE id = ?');
$stmt->execute([$uid]);
$ligneMoi = $stmt->fetch();
$monEcole = trim((string) ($ligneMoi['ecole'] ?? ''));

$criteres = ['exclus' => blockedIds($pdo, $uid)];
if ($portee === 'amis') {
    $stmt = $pdo->prepare("SELECT followed_id FROM follows_users WHERE follower_id = ? AND statut = 'accepted'");
    $stmt->execute([$uid]);
    $criteres['ids'] = array_merge([$uid], array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
} elseif ($portee === 'ecole' && $monEcole !== '') {
    $criteres['ecole'] = $monEcole;
}

$monXp     = getXp(getUserStats($pdo, $uid));
$sansEcole = $portee === 'ecole' && $monEcole === '';

$resultat = $sansEcole
    ? ['lignes' => [], 'moi' => null, 'devant' => null]
    : classementPourEtudiant(classementGlobal($pdo), $criteres,
        ['id' => $uid, 'prenom' => (string) $ligneMoi['prenom'], 'nom' => (string) $ligneMoi['nom'], 'ecole' => $monEcole], $monXp, 50);

$ligne = static fn (array $l): array => [
    'id'        => (int) $l['id'],
    'prenom'    => (string) $l['prenom'],
    'nom'       => (string) ($l['nom'] ?? ''),
    'photo_url' => apiPhotoUrl(isset($l['photo']) ? (string) $l['photo'] : null),
    'rang'      => (int) $l['rang'],
    'xp'        => (int) $l['xp'],
    'moi'       => (int) $l['id'] === $uid,
];

apiReponse([
    'success'    => true,
    'portee'     => $portee,
    'portees'    => array_map(static fn (string $code, string $libelle): array => ['code' => $code, 'libelle' => $libelle],
        array_keys($portees), array_values($portees)),
    'mon_xp'     => $monXp,
    'sans_ecole' => $sansEcole,
    'sans_amis'  => !$sansEcole && count($resultat['lignes']) <= 1 && $portee === 'amis',
    'lignes'     => array_map($ligne, $resultat['lignes']),
    'moi'        => $resultat['moi'] !== null ? $ligne($resultat['moi'] + ['id' => $uid]) : null,
    'devant'     => $resultat['devant'] !== null ? [
        'prenom' => (string) $resultat['devant']['prenom'],
        'ecart'  => (int) $resultat['devant']['xp'] - $monXp,
    ] : null,
]);
