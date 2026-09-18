<?php
// Gestion sécurisée des images uploadées par les partenaires.
//
// Principe : on ne fait JAMAIS confiance au nom de fichier ni au type MIME
// envoyés par le navigateur. On relit le fichier côté serveur (finfo +
// getimagesize) et on régénère nous-mêmes un nom aléatoire et une extension.

const UPLOAD_MAX_BYTES   = 2 * 1024 * 1024; // 2 Mo (aligné sur upload_max_filesize)
const UPLOAD_MAX_PIXELS  = 6000;            // garde-fou anti « decompression bomb »
const UPLOAD_MIN_PIXELS  = 200;

/** Types réellement acceptés : type MIME détecté => extension imposée. */
const UPLOAD_ALLOWED_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

/** Dossier de stockage des photos d'établissement (chemin disque). */
function venuePhotoDir(): string {
    return dirname(__DIR__) . '/uploads/etablissements';
}

/** URL publique d'une photo d'établissement. */
function venuePhotoUrl(string $filename): string {
    return baseUrl('/uploads/etablissements/' . rawurlencode($filename));
}

/** Dossier de stockage des photos de profil (chemin disque). */
function avatarDir(): string {
    return dirname(__DIR__) . '/uploads/avatars';
}

/** URL publique d'une photo de profil. */
function avatarUrl(string $filename): string {
    return baseUrl('/uploads/avatars/' . rawurlencode($filename));
}

/**
 * Rend l'avatar d'un étudiant : sa photo si elle existe, son initiale sinon.
 *
 * Centralisé ici parce que l'avatar apparaît sur huit écrans : sans point
 * unique, la moitié afficherait encore l'initiale après l'ajout des photos.
 */
function avatarHtml(?string $photo, string $prenom, int $taille = 48, string $fond = 'var(--bleu)'): string
{
    $base = sprintf(
        'width:%1$dpx;height:%1$dpx;border-radius:50%%;flex-shrink:0;',
        $taille
    );

    if ($photo !== null && $photo !== '' && is_file(avatarDir() . '/' . $photo)) {
        return sprintf(
            '<img src="%s" alt="Photo de %s" loading="lazy" style="%sobject-fit:cover;display:block;">',
            htmlspecialchars(avatarUrl($photo), ENT_QUOTES),
            htmlspecialchars($prenom, ENT_QUOTES),
            $base
        );
    }

    // Repli : l'initiale, dans le registre éditorial du reste de l'app.
    $corps = max(11, (int) round($taille * 0.4));

    return sprintf(
        '<div aria-hidden="true" style="%sbackground:%s;display:flex;align-items:center;justify-content:center;'
        . 'font-family:var(--font-display);font-weight:var(--fw-display);color:var(--sur-media);font-size:%dpx;">%s</div>',
        $base,
        $fond,
        $corps,
        htmlspecialchars(mb_strtoupper(mb_substr($prenom, 0, 1)), ENT_QUOTES)
    );
}

/**
 * Traduit un code d'erreur PHP d'upload en message lisible.
 */
function uploadErrorMessage(int $code): string {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Image trop lourde (2 Mo maximum).';
        case UPLOAD_ERR_PARTIAL:
            return "L'envoi a été interrompu, réessaie.";
        case UPLOAD_ERR_NO_FILE:
            return 'Aucun fichier sélectionné.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
        case UPLOAD_ERR_EXTENSION:
            return "Le serveur n'a pas pu enregistrer l'image.";
        default:
            return "L'envoi de l'image a échoué.";
    }
}

/**
 * Valide et stocke une image uploadée.
 *
 * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int} $file Entrée de $_FILES
 * @param int $maxLargeur Largeur maximale conservée ; au-delà l'image est réduite.
 * @return array{ok:bool,filename?:string,error?:string}
 */
function storeUploadedImage(array $file, string $destDir, int $maxLargeur = 1600): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => uploadErrorMessage((int)($file['error'] ?? UPLOAD_ERR_NO_FILE))];
    }

    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => "Fichier invalide."];
    }

    if (($file['size'] ?? 0) > UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'error' => 'Image trop lourde (2 Mo maximum).'];
    }

    // 1) Type MIME réel, lu dans le contenu du fichier (pas l'en-tête HTTP).
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($tmp);
    if (!is_string($mime) || !isset(UPLOAD_ALLOWED_TYPES[$mime])) {
        return ['ok' => false, 'error' => 'Format non supporté. Utilise JPG, PNG ou WebP.'];
    }

    // 2) Le fichier doit être une image réellement décodable.
    $info = @getimagesize($tmp);
    if ($info === false) {
        return ['ok' => false, 'error' => "Ce fichier n'est pas une image valide."];
    }
    [$width, $height] = $info;
    $allowedImageTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
    if (!in_array($info[2], $allowedImageTypes, true)) {
        return ['ok' => false, 'error' => 'Format non supporté. Utilise JPG, PNG ou WebP.'];
    }
    if ($width > UPLOAD_MAX_PIXELS || $height > UPLOAD_MAX_PIXELS) {
        return ['ok' => false, 'error' => 'Image trop grande (6000 px maximum par côté).'];
    }
    if ($width < UPLOAD_MIN_PIXELS || $height < UPLOAD_MIN_PIXELS) {
        return ['ok' => false, 'error' => 'Image trop petite (200 px minimum par côté).'];
    }

    // 3) Nom régénéré côté serveur : le nom d'origine n'est jamais réutilisé.
    $ext      = UPLOAD_ALLOWED_TYPES[$mime];
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;

    if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        return ['ok' => false, 'error' => "Le dossier de stockage est inaccessible."];
    }

    $dest = $destDir . '/' . $filename;
    if (!move_uploaded_file($tmp, $dest)) {
        return ['ok' => false, 'error' => "Impossible d'enregistrer l'image."];
    }
    @chmod($dest, 0644);

    // 4) Réduction : une photo prise au téléphone fait plusieurs milliers de
    //    pixels de large. Servie telle quelle, elle est intégralement
    //    téléchargée par chaque mobile. On la ramène à une taille d'affichage
    //    réaliste avant qu'elle ne soit jamais servie.
    redimensionnerImage($dest, $info[2], $maxLargeur);

    return ['ok' => true, 'filename' => $filename];
}

/**
 * Réduit une image sur place si elle dépasse la largeur cible.
 *
 * Volontairement conservatrice : en cas d'échec (GD absente, mémoire
 * insuffisante), l'original est conservé plutôt que perdu.
 *
 * @param int $typeImage Constante IMAGETYPE_* renvoyée par getimagesize()
 */
function redimensionnerImage(string $chemin, int $typeImage, int $largeurCible): bool
{
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }

    $info = @getimagesize($chemin);
    if ($info === false) {
        return false;
    }
    [$largeur, $hauteur] = $info;
    if ($largeur <= $largeurCible) {
        return false; // déjà à la bonne échelle
    }

    // Lecture, écriture et qualité décrites au même endroit : le garde
    // ci-dessous suffit ensuite, sans branche morte à la fin.
    $formats = [
        IMAGETYPE_JPEG => ['imagecreatefromjpeg', 'imagejpeg', 82],
        IMAGETYPE_PNG  => ['imagecreatefrompng',  'imagepng',   6],
        IMAGETYPE_WEBP => ['imagecreatefromwebp', 'imagewebp', 82],
    ];
    if (!isset($formats[$typeImage])) {
        return false;
    }
    [$lire, $ecrire, $qualite] = $formats[$typeImage];
    if (!function_exists($lire) || !function_exists($ecrire)) {
        return false;
    }

    $source = @$lire($chemin);
    if ($source === false) {
        return false;
    }

    $nouvelleHauteur = (int) round($hauteur * $largeurCible / $largeur);
    $cible = imagecreatetruecolor($largeurCible, $nouvelleHauteur);

    // La transparence du PNG et du WebP doit survivre au redimensionnement.
    if (in_array($typeImage, [IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        imagealphablending($cible, false);
        imagesavealpha($cible, true);
        $transparent = imagecolorallocatealpha($cible, 0, 0, 0, 127);
        imagefilledrectangle($cible, 0, 0, $largeurCible, $nouvelleHauteur, $transparent);
    }

    imagecopyresampled($cible, $source, 0, 0, 0, 0, $largeurCible, $nouvelleHauteur, $largeur, $hauteur);

    $ok = $ecrire($cible, $chemin, $qualite);

    imagedestroy($source);
    imagedestroy($cible);

    return (bool) $ok;
}

/**
 * Supprime le fichier d'une photo. Le nom est re-validé pour empêcher
 * toute traversée de répertoire (../).
 */
function deleteStoredImage(string $filename, string $destDir): bool {
    if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $filename)) {
        return false;
    }
    $path = $destDir . '/' . $filename;
    return is_file($path) ? @unlink($path) : true;
}
