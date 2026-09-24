<?php
/**
 * outils/creer_base.php — crée la base sur un serveur distant, si elle manque.
 *
 * includes/db.php se connecte directement À la base : il ne peut donc pas la
 * créer. Cet outil se connecte au serveur seul, avec les mêmes variables
 * (MYSQLHOST, MYSQLPORT, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE, MYSQL_SSL_CA),
 * et crée la base en utf8mb4. Utilisé par outils/installer_distant.ps1.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$hote = (string) getenv('MYSQLHOST');
$port = (string) (getenv('MYSQLPORT') ?: '3306');
$base = (string) (getenv('MYSQLDATABASE') ?: 'linkee');
$ca   = (string) getenv('MYSQL_SSL_CA');

if (!preg_match('/^[A-Za-z0-9_]+$/', $base)) {
    fwrite(STDERR, "Nom de base invalide : $base\n");
    exit(2);
}

$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 15];
if ($ca !== '') {
    $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
}

try {
    $pdo = new PDO("mysql:host=$hote;port=$port;charset=utf8mb4", (string) getenv('MYSQLUSER'), (string) getenv('MYSQLPASSWORD'), $options);
    $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$base` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (PDOException $e) {
    fwrite(STDERR, 'Connexion ou création impossible : ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Serveur : $version\nBase « $base » prête.\n";
