<?php
/**
 * GET /api/v1/culture_evenement.php?id=3 — la fiche d'un événement culturel.
 *
 * La carte du fil ne rend que les prénoms des amis inscrits ; la fiche les
 * rend avec leur avatar, comme evenement.php pour les soirées.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/culture.php';

apiExigerMethode('GET');

$u   = apiEtudiant($pdo);
$uid = (int) $u['id'];
$id  = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    apiErreur('Identifiant manquant', 422, 'id_manquant');
}

$c = cultureEvenement($pdo, $uid, $id);
if (!$c) {
    apiErreur('Cet événement n’existe plus', 404, 'introuvable');
}

$stmt = $pdo->prepare("
    SELECT u.id, u.prenom, u.nom, u.photo
      FROM follows_users fu
      JOIN culture_inscriptions ci ON ci.user_id = fu.followed_id
      JOIN users u ON u.id = fu.followed_id
     WHERE fu.follower_id = ? AND fu.statut = 'accepted' AND ci.evenement_id = ?
     ORDER BY ci.created_at
");
$stmt->execute([$uid, $id]);
$amis = $stmt->fetchAll();

$fiche = cultureFormater($c, [
    'prenoms' => array_map(static fn (array $a): string => (string) $a['prenom'], $amis),
    'nb'      => count($amis),
]);
$fiche['termine']      = (bool) $c['termine'];
$fiche['amis_detail']  = array_map(static fn (array $a): array => apiPersonne($a), $amis);

apiReponse(['success' => true, 'evenement' => $fiche]);
