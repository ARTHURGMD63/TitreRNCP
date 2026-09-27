<?php
/**
 * POST /api/v1/evenement_photo_supprimer.php {photo_id} — retirer sa propre
 * photo d'une soirée (bouton « Supprimer ma photo » de la fiche événement).
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/uploads.php';

apiExigerMethode('POST');

$moi = apiEtudiant($pdo);
$uid = (int) $moi['id'];

$corps    = apiCorps();
$photoId  = (int) ($corps['photo_id'] ?? 0);

if ($photoId <= 0) {
    apiErreur('Photo manquante.', 422, 'id_manquant');
}

$stmt = $pdo->prepare('SELECT id, user_id, fichier FROM evenement_photos WHERE id = ?');
$stmt->execute([$photoId]);
$photo = $stmt->fetch();

if (!$photo) {
    apiErreur('Cette photo n’existe plus.', 404, 'introuvable');
}
if ((int) $photo['user_id'] !== $uid) {
    apiErreur('Tu ne peux supprimer que tes propres photos.', 403, 'refuse');
}

$pdo->prepare('DELETE FROM evenement_photos WHERE id = ?')->execute([$photoId]);
deleteStoredImage((string) $photo['fichier'], evenementPhotoDir());

apiReponse(['success' => true, 'message' => 'Photo supprimée.']);
