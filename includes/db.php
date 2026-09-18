<?php
// Configuration BDD — production (variables d'environnement) ou local (WAMP)
require_once __DIR__ . '/log.php';
installerGardesErreurs();

define('DB_HOST', getenv('MYSQLHOST') ?: 'localhost');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'studentlink');
define('DB_USER', getenv('MYSQLUSER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
define('DB_PORT', getenv('MYSQLPORT') ?: '3306');

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
