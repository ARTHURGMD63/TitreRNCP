<?php
// Configuration BDD, par ordre de priorité décroissant :
//
//   1. les variables d'environnement, là où l'hébergeur en fournit
//      (Railway, Docker, conteneurs en général) ;
//   2. includes/config.local.php, pour les hébergements mutualisés qui
//      n'en proposent pas — InfinityFree, o2switch, OVH mutualisé. Ce
//      fichier n'est pas versionné, le mot de passe reste sur le serveur.
//      Voir config.local.example.php ;
//   3. les valeurs WAMP par défaut, pour le poste de développement.
//
// Sans le point 2, un mutualisé retombait silencieusement sur « localhost »
// et « root », et la connexion échouait sans indiquer pourquoi.
require_once __DIR__ . '/log.php';
installerGardesErreurs();

$configLocale = is_file(__DIR__ . '/config.local.php')
    ? require __DIR__ . '/config.local.php'
    : [];
if (!is_array($configLocale)) {
    $configLocale = [];
}

$reglage = static fn(string $variable, string $cle, string $defaut): string
    => (string) (getenv($variable) ?: ($configLocale[$cle] ?? $defaut));

define('DB_HOST', $reglage('MYSQLHOST',     'host', 'localhost'));
define('DB_NAME', $reglage('MYSQLDATABASE', 'name', 'studentlink'));
define('DB_USER', $reglage('MYSQLUSER',     'user', 'root'));
define('DB_PASS', $reglage('MYSQLPASSWORD', 'pass', ''));
define('DB_PORT', $reglage('MYSQLPORT',     'port', '3306'));

// Le mot de passe n'a pas à traîner dans une variable du contexte global.
unset($configLocale, $reglage);

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_TIMEOUT            => 10,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Les requêtes préparées sont réellement préparées côté serveur,
            // et non émulées : la valeur ne peut plus être interprétée comme
            // de la syntaxe SQL, quelle qu'elle soit.
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Le message de PDO contient l'hôte et le nom de la base : il part dans
    // le journal, jamais dans la réponse.
    logErreur('Connexion à la base impossible', $e, ['hote' => DB_HOST, 'base' => DB_NAME]);

    http_response_code(503);
    if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Service momentanément indisponible']);
    } else {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><meta charset="utf-8"><title>Service indisponible</title>'
           . '<p style="font-family:system-ui;padding:40px">Service momentanément indisponible. '
           . 'Réessaie dans un instant.</p>';
    }
    exit;
}
