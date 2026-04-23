<?php
// Railway or Local Database Configuration
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
        [PDO::ATTR_TIMEOUT => 10]
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Connexion BDD échouée: ' . $e->getMessage()]));
}
