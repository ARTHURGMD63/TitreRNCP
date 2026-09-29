<?php
/**
 * POST /api/v1/partenaire_photo_supprimer.php {photo_id} — retirer une photo
 * de la fiche établissement. Reprend l'action « delete » de
 * partenaire/photos.php.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/uploads.php';

apiExigerMethode('POST');

$u   = apiPartenaire($pdo);
$uid = (int) $u['id'];

$etab = apiEtablissementDe($pdo, $uid);
if (!$etab) {
    apiErreur('Aucun établissement associé à ce compte.', 404, 'etablissement_absent');
}

$corps   = apiCorps();
$photoId = (int) ($corps['photo_id'] ?? 0);

if ($photoId <= 0) {
    apiErreur('Photo manquante.', 422, 'id_manquant');
}

// Filtré sur etablissement_id : un partenaire ne peut pas supprimer la photo
// d'un autre établissement en devinant un id.
$stmt = $pdo->prepare('SELECT fichier FROM etablissement_photos WHERE id=? AND etablissement_id=?');
$stmt->execute([$photoId, $etab['id']]);
$fichier = $stmt->fetchColumn();

if ($fichier === false) {
    apiErreur('Photo introuvable.', 404, 'introuvable');
}

$pdo->prepare('DELETE FROM etablissement_photos WHERE id=? AND etablissement_id=?')
    ->execute([$photoId, $etab['id']]);
deleteStoredImage((string) $fichier, venuePhotoDir());

apiReponse(['success' => true, 'message' => 'Photo supprimée.']);
