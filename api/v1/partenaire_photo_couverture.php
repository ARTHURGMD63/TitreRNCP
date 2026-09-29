<?php
/**
 * POST /api/v1/partenaire_photo_couverture.php {photo_id} — définir la photo
 * de couverture de l'établissement. Reprend l'action « cover » de
 * partenaire/photos.php : la couverture passe en position 0, les autres sont
 * décalées.
 */

require_once __DIR__ . '/_socle.php';

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

$stmt = $pdo->prepare('SELECT id FROM etablissement_photos WHERE id=? AND etablissement_id=?');
$stmt->execute([$photoId, $etab['id']]);

if ($stmt->fetchColumn() === false) {
    apiErreur('Photo introuvable.', 404, 'introuvable');
}

$pdo->prepare('UPDATE etablissement_photos SET position = position + 1 WHERE etablissement_id=?')
    ->execute([$etab['id']]);
$pdo->prepare('UPDATE etablissement_photos SET position = 0 WHERE id=? AND etablissement_id=?')
    ->execute([$photoId, $etab['id']]);

apiReponse(['success' => true, 'message' => 'Photo de couverture mise à jour.']);
