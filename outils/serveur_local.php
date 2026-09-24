<?php
/**
 * Routeur du serveur PHP intégré, pour ouvrir Linkee aux téléphones du
 * réseau local sans Apache :
 *
 *   cd C:/wamp64/www
 *   php -d zlib.output_compression=On -S 0.0.0.0:8080 -t C:/wamp64/www TitreRNCP/outils/serveur_local.php
 *
 * Seul, `php -S` sert chaque fichier tel quel, et c'était lent sur
 * téléphone. En http://192.168.x.x le navigateur refuse le service worker
 * (il exige HTTPS ou localhost) ; sans en-tête de cache, feuille de style,
 * script, polices et photos étaient donc retéléchargés à chaque page, non
 * compressés, un par un — ce serveur ne traite qu'une requête à la fois.
 *
 * Ce routeur rend ce que fait `.htaccess` sous Apache :
 *  - le cache d'un an sur les fichiers statiques, que `asset()` versionne ;
 *  - la compression gzip des textes (et des pages PHP, par
 *    zlib.output_compression sur la ligne de commande) ;
 *  - les refus : fichiers cachés (.git, .env…), SQL, configuration,
 *    dossiers includes/, cron/, outils/, tests/, vendor/, et aucun script
 *    exécuté depuis uploads/. Sans eux, tout appareil du réseau pouvait
 *    lire le schéma ou l'historique git.
 *
 * Seul le projet est servi : les autres dossiers de www/ restent fermés.
 * Outil de développement : en production, c'est Apache et `.htaccess`.
 */

$projet = '/' . basename(dirname(__DIR__));
$chemin = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

if ($chemin === '/' || $chemin === $projet) {
    header('Location: ' . $projet . '/', true, 302);
    return true;
}

$refus = static function (): bool {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Introuvable.\n";
    return true;
};

if (!str_starts_with($chemin, $projet . '/') || str_contains($chemin, '..') || str_contains($chemin, "\0")) {
    return $refus();
}
$relatif = substr($chemin, strlen($projet));

if (preg_match('#/\.#', $relatif)                                                   // .git, .env, .shot-enabled…
    || preg_match('#^/(includes|cron|outils|tests|vendor|mobile|docker)(/|$)#i', $relatif)
    || preg_match('#\.(sql|md|neon|lock|dist|log|bak|old|swp|toml|phar|sh|bat|ps1|ini|conf|ya?ml)$#i', $relatif)
    || preg_match('#(^|/)(composer\.(json|lock)|package(-lock)?\.json|phpunit\.xml|Dockerfile)$#i', $relatif)
    || preg_match('#^/uploads/.*\.(php\d?|phtml|phps|pl|py|cgi|sh)$#i', $relatif)) {
    return $refus();
}

$fichier = $_SERVER['DOCUMENT_ROOT'] . $chemin;

// Pages PHP, dossiers (index.php) et chemins inconnus : le serveur intégré
// s'en charge comme d'habitude.
if (!is_file($fichier) || preg_match('#\.php$#i', $fichier)) {
    return false;
}

$types = [
    'css' => 'text/css; charset=UTF-8',        'js'    => 'text/javascript; charset=UTF-8',
    'json' => 'application/json',              'html'  => 'text/html; charset=UTF-8',
    'svg' => 'image/svg+xml',                  'png'   => 'image/png',
    'jpg' => 'image/jpeg',                     'jpeg'  => 'image/jpeg',
    'webp' => 'image/webp',                    'gif'   => 'image/gif',
    'ico' => 'image/x-icon',                   'woff2' => 'font/woff2',
    'woff' => 'font/woff',                     'txt'   => 'text/plain; charset=UTF-8',
    'pdf' => 'application/pdf',                'mp4'   => 'video/mp4',
];
$ext = strtolower(pathinfo($fichier, PATHINFO_EXTENSION));
$type = $types[$ext] ?? null;
if ($type === null) {
    return false;
}

// Mêmes durées que .htaccess.
if (basename($fichier) === 'sw.js' || $ext === 'html') {
    $cache = 'no-cache, max-age=0, must-revalidate';
} elseif (basename($fichier) === 'manifest.json') {
    $cache = 'public, max-age=86400';
} elseif (in_array($ext, ['css', 'js', 'png', 'jpg', 'jpeg', 'webp', 'svg', 'woff', 'woff2', 'ico', 'gif'], true)) {
    $cache = 'public, max-age=31536000, immutable';
} else {
    $cache = 'public, max-age=3600';
}

// La compression des pages PHP (ligne de commande) ne doit pas repasser
// sur un fichier déjà compressé ci-dessous, ni sur une image.
ini_set('zlib.output_compression', '0');

$taille = (int) filesize($fichier);
$etag = '"' . dechex((int) filemtime($fichier)) . '-' . dechex($taille) . '"';

header('Content-Type: ' . $type);
header('Cache-Control: ' . $cache);
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    return true;
}

$texte = in_array($ext, ['css', 'js', 'json', 'html', 'svg', 'txt'], true);
if ($texte && str_contains((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''), 'gzip')) {
    $corps = (string) gzencode((string) file_get_contents($fichier), 6);
    header('Content-Encoding: gzip');
    header('Vary: Accept-Encoding');
    header('Content-Length: ' . strlen($corps));
    echo $corps;
    return true;
}

header('Content-Length: ' . $taille);
readfile($fichier);
return true;
