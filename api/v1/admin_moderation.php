<?php
/**
 * GET  /api/v1/admin_moderation.php[?statut=nouveau|traite] — les
 *      signalements de profils.
 * POST /api/v1/admin_moderation.php {action:'traiter', id} — marquer un
 *      signalement comme traité.
 *
 * Reprend admin/moderation.php : mêmes colonnes, même compte de récidive
 * (nombre total de signalements visant la même personne).
 */

require_once __DIR__ . '/_socle.php';

$moi = apiAdmin($pdo);
$uid = (int) $moi['id'];

$methode = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($methode === 'POST') {
    $corps = apiCorps();
    if (($corps['action'] ?? '') !== 'traiter') {
        apiErreur('Action inconnue', 422, 'action_inconnue');
    }
    $id = (int) ($corps['id'] ?? 0);
    if (!$id) {
        apiErreur('Signalement manquant', 422, 'id_manquant');
    }

    $pdo->prepare(
        "UPDATE user_reports SET statut='traite', traite_par=?, traite_le=NOW() WHERE id=? AND statut='nouveau'"
    )->execute([$uid, $id]);

    apiReponse(['success' => true, 'message' => 'Signalement traité.']);
}

apiExigerMethode('GET');

$filtre = ($_GET['statut'] ?? 'nouveau') === 'traite' ? 'traite' : 'nouveau';

$stmt = $pdo->prepare(
    "SELECT r.*,
            s.prenom AS s_prenom, s.nom AS s_nom,
            c.id AS c_id, c.prenom AS c_prenom, c.nom AS c_nom, c.photo AS c_photo,
            a.prenom AS a_prenom,
            (SELECT COUNT(*) FROM user_reports r2 WHERE r2.reported_id = r.reported_id) AS total_cible
       FROM user_reports r
       JOIN users s ON s.id = r.reporter_id
       JOIN users c ON c.id = r.reported_id
  LEFT JOIN users a ON a.id = r.traite_par
      WHERE r.statut = ?
      ORDER BY r.created_at DESC"
);
$stmt->execute([$filtre]);
$signalements = $stmt->fetchAll();

$nbNouveaux = (int) $pdo->query("SELECT COUNT(*) FROM user_reports WHERE statut='nouveau'")->fetchColumn();

apiReponse([
    'success'     => true,
    'nb_nouveaux' => $nbNouveaux,
    'signalements' => array_map(
        static fn (array $r): array => [
            'id'     => (int) $r['id'],
            'motif'  => (string) $r['motif'],
            'details' => $r['details'] !== null ? (string) $r['details'] : null,
            'created_at' => (string) $r['created_at'],
            'total_cible' => (int) $r['total_cible'],
            'signale_par' => (string) $r['s_prenom'] . ' ' . mb_substr((string) $r['s_nom'], 0, 1) . '.',
            'personne' => [
                'id'        => (int) $r['c_id'],
                'prenom'    => (string) $r['c_prenom'],
                'nom'       => (string) $r['c_nom'],
                'photo_url' => apiPhotoUrl($r['c_photo'] ?? null),
            ],
            'traite_par' => $r['a_prenom'] !== null ? (string) $r['a_prenom'] : null,
            'traite_le'  => $r['traite_le'] !== null ? (string) $r['traite_le'] : null,
        ],
        $signalements
    ),
]);
