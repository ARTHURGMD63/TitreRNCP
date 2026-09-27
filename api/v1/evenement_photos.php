<?php
/**
 * GET  /api/v1/evenement_photos.php?id=12 — les photos postées par les
 *      participants pendant la soirée, section « Photos de la soirée » /
 *      « Tes photos de cette soirée » de la fiche événement.
 * POST /api/v1/evenement_photos.php {evenement_id, photo, legende?} —
 *      poster une photo (multipart, réservé aux personnes check-in tant
 *      que la soirée est en cours).
 *
 * Règle de visibilité, dans les deux sens :
 *  - soirée en cours : toutes les photos non masquées, sauf celles d'un
 *    compte privé qu'on ne suit pas (peutVoirPhotosDe()) ;
 *  - soirée close : réservé à qui a checkin à CETTE soirée — sinon la
 *    liste est vide et `reserve_participants` le signale — puis même règle
 *    de compte privé.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/uploads.php';
require_once __DIR__ . '/../../includes/social.php';
require_once __DIR__ . '/../../includes/evenements_photos.php';

$moi = apiEtudiant($pdo);
$uid = (int) $moi['id'];

$methode = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$eid     = (int) ($methode === 'POST' ? ($_POST['evenement_id'] ?? 0) : ($_GET['id'] ?? 0));

if ($eid <= 0) {
    apiErreur('Événement manquant.', 422, 'id_manquant');
}

$stmt = $pdo->prepare('SELECT id, date_heure FROM evenements WHERE id = ?');
$stmt->execute([$eid]);
$evenement = $stmt->fetch();
if (!$evenement) {
    apiErreur('Cette soirée n’existe plus', 404, 'introuvable');
}

$enCours    = evenementPhotosEnCours((string) $evenement['date_heure']);
$estCheckin = evenementPhotosEstCheckin($pdo, $uid, $eid);

function evenementPhotoUrlSiExiste(string $fichier): ?string
{
    return is_file(evenementPhotoDir() . '/' . $fichier) ? apiUrlAbsolue(evenementPhotoUrl($fichier)) : null;
}

if ($methode === 'POST') {
    $peutPublier = $enCours && $estCheckin;
    if (!$peutPublier) {
        apiErreur(
            $estCheckin ? "Cette soirée n'est plus en cours." : "Tu pourras poster une photo une fois ton entrée validée.",
            403,
            'photo_refusee'
        );
    }

    if (!isset($_FILES['photo']) || ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        apiErreur('Aucune photo reçue.', 422, 'photo_absente');
    }

    $legende = trim(is_string($_POST['legende'] ?? null) ? $_POST['legende'] : '');
    $legende = $legende !== '' ? substr($legende, 0, 255) : null;

    $res = storeUploadedImage($_FILES['photo'], evenementPhotoDir(), 1280);
    if (!$res['ok']) {
        apiErreur($res['error'], 422, 'photo_invalide');
    }

    $pdo->prepare('INSERT INTO evenement_photos (evenement_id, user_id, fichier, legende) VALUES (?,?,?,?)')
        ->execute([$eid, $uid, $res['filename'], $legende]);
    $photoId = (int) $pdo->lastInsertId();

    apiReponse([
        'success' => true,
        'photo'   => [
            'id'         => $photoId,
            'url'        => evenementPhotoUrlSiExiste($res['filename']),
            'legende'    => $legende,
            'created_at' => date('Y-m-d H:i:s'),
            'auteur'     => ['id' => $uid, 'prenom' => (string) $moi['prenom'], 'photo_url' => apiPhotoUrl($moi['photo'] ?? null)],
            'moi'        => true,
        ],
    ]);
}

apiExigerMethode('GET');

if (!$enCours && !$estCheckin) {
    apiReponse(['success' => true, 'en_cours' => false, 'peut_publier' => false, 'reserve_participants' => true, 'photos' => []]);
}

$stmt = $pdo->prepare(
    'SELECT p.id, p.fichier, p.legende, p.created_at, p.user_id,
            u.prenom, u.photo
       FROM evenement_photos p
       JOIN users u ON u.id = p.user_id
      WHERE p.evenement_id = ? AND p.masquee = 0
      ORDER BY p.created_at DESC'
);
$stmt->execute([$eid]);
$lignes = $stmt->fetchAll();

$photos = [];
foreach ($lignes as $p) {
    $auteurId = (int) $p['user_id'];
    if ($auteurId !== $uid && isBlockedBetween($pdo, $uid, $auteurId)) {
        continue;
    }
    if (!peutVoirPhotosDe($pdo, $uid, $auteurId)) {
        continue;
    }
    $photos[] = [
        'id'         => (int) $p['id'],
        'url'        => evenementPhotoUrlSiExiste((string) $p['fichier']),
        'legende'    => $p['legende'] !== null ? (string) $p['legende'] : null,
        'created_at' => (string) $p['created_at'],
        'auteur'     => [
            'id'        => $auteurId,
            'prenom'    => (string) $p['prenom'],
            'photo_url' => apiPhotoUrl($p['photo'] ?? null),
        ],
        'moi' => $auteurId === $uid,
    ];
}

apiReponse([
    'success'              => true,
    'en_cours'             => $enCours,
    'peut_publier'         => $enCours && $estCheckin,
    'reserve_participants' => false,
    'photos'               => $photos,
]);
