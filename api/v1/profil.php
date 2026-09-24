<?php
/**
 * POST /api/v1/profil.php — « Enregistrer les modifications » de l'écran Moi.
 *
 * Formulaire multipart, comme celui de profil.php : ecole, promo,
 * interets[] (ou interests[]), supprimer_photo, et un fichier « photo ». Le
 * traitement est celui du site, ligne pour ligne : la photo ne remplace
 * l'ancienne qu'en cas de succès, l'ancien fichier est effacé, les intérêts
 * passent par le catalogue, la table indexée et le cache des écoles suivent.
 *
 * Une photo refusée ne bloque pas le reste de l'enregistrement : le site
 * enregistre l'école et la promo, et affiche l'erreur de la photo. La réponse
 * porte donc success:true avec `erreur_photo`.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/uploads.php';
require_once __DIR__ . '/../../includes/agregats.php';

apiExigerMethode('POST');

$moi = apiEtudiant($pdo);
$uid = (int) $moi['id'];

$stmt = $pdo->prepare('SELECT id, photo FROM users WHERE id=?');
$stmt->execute([$uid]);
$u = $stmt->fetch();

$ecole     = trim(is_string($_POST['ecole'] ?? null) ? $_POST['ecole'] : '');
$promo     = trim(is_string($_POST['promo'] ?? null) ? $_POST['promo'] : '');
$interests = interetsVersTexte(filtrerInterets($_POST['interets'] ?? $_POST['interests'] ?? []));

$photoErr = '';
if (!empty($_POST['supprimer_photo']) && !empty($u['photo'])) {
    deleteStoredImage($u['photo'], avatarDir());
    $pdo->prepare('UPDATE users SET photo=NULL WHERE id=?')->execute([$uid]);
} elseif (isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $res = storeUploadedImage($_FILES['photo'], avatarDir(), 400);
    if ($res['ok']) {
        $ancienne = $u['photo'] ?? null;
        $pdo->prepare('UPDATE users SET photo=? WHERE id=?')->execute([$res['filename'], $uid]);
        if ($ancienne) {
            deleteStoredImage($ancienne, avatarDir());
        }
    } else {
        $photoErr = $res['error'];
    }
}

$pdo->prepare('UPDATE users SET ecole=?, promo=?, interests=? WHERE id=?')->execute([$ecole, $promo, $interests, $uid]);
synchroniserInterets($pdo, $uid, interetsDepuisTexte($interests));
oublierEcolesRepresentees();

$stmt = $pdo->prepare('SELECT id, nom, prenom, email, ecole, promo, photo, interests, type FROM users WHERE id=?');
$stmt->execute([$uid]);
$ligne  = $stmt->fetch();
$profil = ['photo_url' => apiPhotoUrl($ligne['photo'] ?? null)] + apiProfil($ligne);

apiReponse([
    'success'      => true,
    'message'      => $photoErr !== '' ? null : 'Profil mis à jour.',
    'erreur_photo' => $photoErr !== '' ? $photoErr : null,
    'utilisateur'  => $profil,
]);
