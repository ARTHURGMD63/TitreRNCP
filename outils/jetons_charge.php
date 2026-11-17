<?php
/**
 * Crée des jetons d'API pour un test de charge, un par ligne dans un fichier.
 *
 *     php outils/jetons_charge.php jetons.txt --nombre=200
 *
 * Le fichier est ensuite donné à l'injecteur :
 *
 *     php outils/charge.php --url=https://…/api/v1/personnes.php \
 *                           --bearers=jetons.txt --concurrence=100 --total=5000
 *
 * Chaque jeton appartient à un compte étudiant différent (les premiers, par
 * identifiant), et expire dans deux heures. Il faut des comptes distincts :
 * l'annuaire se calcule par personne, et cent requêtes d'un même compte
 * mesureraient le cache, pas le serveur.
 *
 * À N'UTILISER QUE SUR UNE BASE DE TEST (voir outils/semer_charge.php), jamais
 * sur les vrais comptes : le script refuse de tourner quand APP_ENV=production,
 * sauf --force. Le fichier produit donne accès aux comptes : le supprimer après
 * le test.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api.php';

$fichier = (string) ($argv[1] ?? '');
$nombre  = 100;
$force   = false;
foreach (array_slice($argv, 2) as $a) {
    if (str_starts_with($a, '--nombre=')) {
        $nombre = max(1, (int) substr($a, 9));
    } elseif ($a === '--force') {
        $force = true;
    }
}

if ($fichier === '' || str_starts_with($fichier, '--')) {
    fwrite(STDERR, "Usage : php outils/jetons_charge.php <fichier> [--nombre=100] [--force]\n");
    exit(1);
}
if (!$force && getenv('APP_ENV') === 'production') {
    fwrite(STDERR, "APP_ENV=production : ce script crée des accès à de vrais comptes. Utilise une base de test, ou --force.\n");
    exit(1);
}

$ids = $pdo->query("SELECT id FROM users WHERE type = 'etudiant' ORDER BY id LIMIT " . (int) $nombre)->fetchAll(PDO::FETCH_COLUMN);
if (!$ids) {
    fwrite(STDERR, "Aucun compte étudiant : semer d'abord (outils/semer_charge.php).\n");
    exit(1);
}

$lignes = [];
foreach ($ids as $id) {
    $jeton = apiCreerJeton($pdo, (int) $id, 'test-charge');
    // Courte durée de vie : un oubli de nettoyage ne laisse pas d'accès durable.
    $pdo->prepare('UPDATE api_tokens SET expire_le = DATE_ADD(NOW(), INTERVAL 2 HOUR) WHERE token_hash = ?')
        ->execute([apiEmpreinte($jeton['token'])]);
    $lignes[] = $jeton['token'];
}

file_put_contents($fichier, implode("\n", $lignes) . "\n");
printf("%d jetons écrits dans %s (valables 2 h). Supprime ce fichier après le test.\n", count($lignes), $fichier);
