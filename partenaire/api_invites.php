<?php
/**
 * GET /partenaire/api_invites.php?event_id=&q= — inscrits pas encore
 * check-in, pour la validation manuelle du scan (voir partenaire/api_scan.php,
 * qui accepte `inscription_id` pour ça). Reprend api/v1/partenaire_invites.php
 * à l'identique côté données ; lecture seule, donc pas de protegerEcritureApi()
 * — la session suffit.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/uploads.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'partenaire') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$uid     = (int) $_SESSION['user_id'];
$eventId = (int) ($_GET['event_id'] ?? 0);
$q       = trim((string) ($_GET['q'] ?? ''));

if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Événement manquant']);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT e.id FROM evenements e
     JOIN etablissements et ON et.id = e.etablissement_id
    WHERE e.id = ? AND et.user_id = ?"
);
$stmt->execute([$eventId, $uid]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Événement non autorisé']);
    exit;
}

$sql = "SELECT i.id AS inscription_id, u.prenom, u.nom, u.ecole, u.promo, u.photo
          FROM inscriptions i
          JOIN users u ON u.id = i.user_id
         WHERE i.evenement_id = ? AND i.statut = 'inscrit'";
$params = [$eventId];

if ($q !== '') {
    $sql .= " AND (u.prenom LIKE ? OR u.nom LIKE ?)";
    $terme = '%' . addcslashes($q, '%_') . '%';
    array_push($params, $terme, $terme);
}

$sql .= " ORDER BY u.prenom, u.nom LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode([
    'success' => true,
    'invites' => array_map(
        static function (array $i): array {
            return [
                'inscription_id' => (int) $i['inscription_id'],
                'prenom'         => $i['prenom'],
                'nom'            => $i['nom'],
                'ecole'          => $i['ecole'],
                'promo'          => $i['promo'],
                'photo_url'      => !empty($i['photo']) && is_file(avatarDir() . '/' . $i['photo'])
                    ? avatarUrl($i['photo'])
                    : null,
            ];
        },
        $stmt->fetchAll()
    ),
]);
