<?php
/**
 * GET  /api/v1/partenaire_photos.php — les photos de la fiche établissement.
 * POST /api/v1/partenaire_photos.php {photo, legende?} — en ajouter une
 *      (multipart, limite à VENUE_PHOTOS_MAX comme sur le site).
 *
 * Reprend partenaire/photos.php ; la suppression et le choix de couverture
 * vivent dans partenaire_photo_supprimer.php et partenaire_photo_couverture.php
 * (une action par fichier, comme evenement_photo_supprimer.php).
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/uploads.php';

const PARTENAIRE_PHOTOS_MAX = 12;

$u   = apiPartenaire($pdo);
$uid = (int) $u['id'];

$etab = apiEtablissementDe($pdo, $uid);
if (!$etab) {
    apiErreur('Aucun établissement associé à ce compte.', 404, 'etablissement_absent');
}

function partenairePhotoUrlSiExiste(string $fichier): ?string
{
    return is_file(venuePhotoDir() . '/' . $fichier) ? apiUrlAbsolue(venuePhotoUrl($fichier)) : null;
}

$methode = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($methode === 'POST') {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM etablissement_photos WHERE etablissement_id=?');
    $stmt->execute([$etab['id']]);
    $count = (int) $stmt->fetchColumn();

    if ($count >= PARTENAIRE_PHOTOS_MAX) {
        apiErreur('Tu as atteint la limite de ' . PARTENAIRE_PHOTOS_MAX . ' photos. Supprimes-en une pour en ajouter une nouvelle.', 422, 'limite_atteinte');
    }

    if (!isset($_FILES['photo']) || ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        apiErreur('Aucune photo reçue.', 422, 'photo_absente');
    }

    $legende = trim(is_string($_POST['legende'] ?? null) ? $_POST['legende'] : '');
    $legende = $legende !== '' ? substr($legende, 0, 255) : null;

    $result = storeUploadedImage($_FILES['photo'], venuePhotoDir());
    if (!$result['ok']) {
        apiErreur($result['error'], 422, 'photo_invalide');
    }

    $stmt = $pdo->prepare('SELECT COALESCE(MAX(position), -1) + 1 FROM etablissement_photos WHERE etablissement_id=?');
    $stmt->execute([$etab['id']]);
    $position = (int) $stmt->fetchColumn();

    $pdo->prepare(
        'INSERT INTO etablissement_photos (etablissement_id, fichier, legende, position) VALUES (?,?,?,?)'
    )->execute([$etab['id'], $result['filename'], $legende, $position]);

    apiReponse([
        'success' => true,
        'photo'   => [
            'id'       => (int) $pdo->lastInsertId(),
            'url'      => partenairePhotoUrlSiExiste($result['filename']),
            'legende'  => $legende,
            'position' => $position,
        ],
    ]);
}

apiExigerMethode('GET');

$stmt = $pdo->prepare('SELECT * FROM etablissement_photos WHERE etablissement_id=? ORDER BY position ASC, id ASC');
$stmt->execute([$etab['id']]);

$photos = array_map(
    static fn (array $p): array => [
        'id'       => (int) $p['id'],
        'url'      => partenairePhotoUrlSiExiste((string) $p['fichier']),
        'legende'  => $p['legende'] !== null ? (string) $p['legende'] : null,
        'position' => (int) $p['position'],
    ],
    $stmt->fetchAll()
);

apiReponse(['success' => true, 'max' => PARTENAIRE_PHOTOS_MAX, 'photos' => $photos]);
