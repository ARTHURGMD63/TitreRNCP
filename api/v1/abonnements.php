<?php
/**
 * GET /api/v1/abonnements.php?type=abonnes|abonnements&q=&p= — les listes
 * ouvertes depuis les deux compteurs de l'écran Moi.
 *
 * La requête d'abonnements.php : les deux compteurs toujours calculés (ils
 * étiquettent les deux onglets), la recherche sur nom, prénom et école, les
 * blocages écartés, trente par page en cumulé, et mon propre lien vers
 * chaque personne pour pouvoir m'abonner en retour.
 *
 * La fenêtre « Inviter un ami » du hub lit la même liste (type=abonnements),
 * comme hubAbonnements() côté site.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/social.php';

apiExigerMethode('GET');

$moi = apiEtudiant($pdo);
$uid = (int) $moi['id'];

$vue = ($_GET['type'] ?? 'abonnements') === 'abonnes' ? 'abonnes' : 'abonnements';
$q   = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');

$stmt = $pdo->prepare("SELECT COUNT(*) FROM follows_users WHERE followed_id = ? AND statut = 'accepted'");
$stmt->execute([$uid]);
$nbAbonnes = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM follows_users WHERE follower_id = ? AND statut = 'accepted'");
$stmt->execute([$uid]);
$nbAbonnements = (int) $stmt->fetchColumn();

$colAutre = $vue === 'abonnes' ? 'f.follower_id' : 'f.followed_id';
$colMoi   = $vue === 'abonnes' ? 'f.followed_id' : 'f.follower_id';

$sql = "SELECT u.id, u.prenom, u.nom, u.ecole, u.promo, u.photo, f.created_at,
               (SELECT fm.statut FROM follows_users fm
                WHERE fm.follower_id = ? AND fm.followed_id = u.id) AS follow_statut
        FROM follows_users f
        JOIN users u ON u.id = $colAutre
        WHERE $colMoi = ? AND f.statut = 'accepted' AND u.type = 'etudiant'";
$params = [$uid, $uid];

[$sqlBlock, $paramsBlock] = blockedFilterSql($pdo, $uid, 'u.id');
$sql   .= $sqlBlock;
$params = array_merge($params, $paramsBlock);

if ($q) {
    $sql .= ' AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.ecole LIKE ?)';
    $terme = '%' . addcslashes($q, '%_') . '%';
    $params[] = $terme; $params[] = $terme; $params[] = $terme;
}

$parPage = 30;
$page    = max(1, (int) ($_GET['p'] ?? 1));
$limite  = $parPage * $page;
$sql    .= ' ORDER BY f.created_at DESC, u.prenom ASC LIMIT ' . ($limite + 1);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$personnes = $stmt->fetchAll();

$encore = count($personnes) > $limite;
if ($encore) {
    array_pop($personnes);
}

apiReponse([
    'success'        => true,
    'type'           => $vue,
    'nb_abonnes'     => $nbAbonnes,
    'nb_abonnements' => $nbAbonnements,
    'personnes'      => array_map(static fn (array $p): array => apiPersonne($p) + [
        'etat_suivi' => in_array($p['follow_statut'] ?? null, ['pending', 'accepted'], true) ? (string) $p['follow_statut'] : 'none',
    ], $personnes),
    'pagination'     => [
        'page'       => $page,
        'a_suivre'   => $encore,
        'cumulative' => true,
        'restants'   => max(0, ($vue === 'abonnes' ? $nbAbonnes : $nbAbonnements) - count($personnes)),
    ],
]);
