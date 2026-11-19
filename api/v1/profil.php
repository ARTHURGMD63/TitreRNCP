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
require_once __DIR__ . '/../../includes/profil_etudiant.php';

apiExigerMethode('POST');

$moi = apiEtudiant($pdo);
$uid = (int) $moi['id'];

$stmt = $pdo->prepare('SELECT id, photo, promo FROM users WHERE id=?');
$stmt->execute([$uid]);
$u = $stmt->fetch();

$ecole        = ecoleNormalisee(is_string($_POST['ecole'] ?? null) ? $_POST['ecole'] : '');
$promo        = trim(is_string($_POST['promo'] ?? null) ? $_POST['promo'] : '');
$interests    = interetsVersTexte(filtrerInterets($_POST['interets'] ?? $_POST['interests'] ?? []));
// Absent (ancienne version de l'app) : on ne change rien au réglage existant.
$comptePrive  = isset($_POST['compte_prive']) ? ($_POST['compte_prive'] === '1' ? 1 : 0) : null;

// Tout se vérifie avant d'écrire quoi que ce soit (ETU-16) : une école trop
// longue échouait en SQL après que la photo eut déjà été remplacée, et une
// promo libre fragmentait le classement « Mon école ».
if ($ecole === null) {
    apiErreur('Le nom de l’école est trop long (' . ECOLE_LONGUEUR_MAX . ' caractères au plus).', 422, 'ecole');
}
if (!promoAcceptee($promo, $u['promo'] ?? null)) {
    apiErreur('Choisis ta promo dans la liste.', 422, 'promo');
}

// La photo d'abord sur disque ; la base, elle, change en une seule écriture.
$photoErr     = '';
$ancienne     = $u['photo'] ?? null;
$photo        = $ancienne;
$nouveauFichier = null;
if (!empty($_POST['supprimer_photo']) && !empty($ancienne)) {
    $photo = null;
} elseif (isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $res = storeUploadedImage($_FILES['photo'], avatarDir(), 400);
    if ($res['ok']) {
        $nouveauFichier = $res['filename'];
        $photo          = $nouveauFichier;
    } else {
        $photoErr = $res['error'];
    }
}

try {
    $pdo->prepare(
        'UPDATE users SET photo=?, ecole=?, promo=?, interests=?, compte_prive=COALESCE(?, compte_prive) WHERE id=?'
    )->execute([$photo, $ecole, $promo, $interests, $comptePrive, $uid]);
} catch (PDOException $e) {
    // Rien n'a changé en base : le fichier qu'on vient de poser ne sert à rien.
    if ($nouveauFichier !== null) {
        deleteStoredImage($nouveauFichier, avatarDir());
    }
    logErreur('Enregistrement du profil impossible', $e, ['user' => $uid]);
    apiErreur('Enregistrement impossible. Réessaie dans un instant.', 500, 'enregistrement');
}
// L'ancien fichier ne part qu'une fois la base à jour.
if ($ancienne && $photo !== $ancienne) {
    deleteStoredImage($ancienne, avatarDir());
}
synchroniserInterets($pdo, $uid, interetsDepuisTexte($interests));
oublierEcolesRepresentees();
require_once __DIR__ . '/../../includes/hub.php';
hubAnnuaireOublier((int) $uid);

$stmt = $pdo->prepare('SELECT id, nom, prenom, email, telephone, ecole, promo, ville, photo, interests, type, compte_prive FROM users WHERE id=?');
$stmt->execute([$uid]);
$ligne  = $stmt->fetch();
$profil = ['photo_url' => apiPhotoUrl($ligne['photo'] ?? null)] + apiProfil($ligne);

apiReponse([
    'success'      => true,
    'message'      => $photoErr !== '' ? null : 'Profil mis à jour.',
    'erreur_photo' => $photoErr !== '' ? $photoErr : null,
    'utilisateur'  => $profil,
]);
