<?php
/**
 * Variante ponctuelle de outils/migrer.php pour un poste Windows.
 *
 * includes/db.php ne trouve un certificat CA que sur les chemins Linux
 * habituels (/etc/ssl/...) ; sur Windows, aucun n'existe, la connexion à
 * TiDB part donc sans TLS et TiDB la refuse (erreur 1105 « insecure
 * transport »). Ce script se connecte lui-même, en TLS mais sans vérifier
 * le certificat du serveur — acceptable pour une tâche d'administration
 * ponctuelle depuis un poste de confiance, pas pour la configuration
 * normale de l'application (qui tourne sur Linux/Render, où un vrai
 * certificat CA est disponible).
 *
 * À supprimer une fois la migration posée.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$host = getenv('MYSQLHOST') ?: 'localhost';
$name = getenv('MYSQLDATABASE') ?: 'outsee';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$port = getenv('MYSQLPORT') ?: '3306';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE                      => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_SSL_CA                 => null,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        ]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Connexion impossible : {$e->getMessage()}\n");
    exit(1);
}

define('DB_NAME', $name);
define('DB_HOST', $host);
define('DB_PORT', $port);

require_once __DIR__ . '/../includes/migrations.php';

$toutes = listerMigrations();
if (!$toutes) {
    fwrite(STDERR, "Aucun fichier db_migrations_v*.sql trouvé à la racine du projet.\n");
    exit(1);
}

try {
    $faites = array_flip(migrationsAppliquees($pdo));
} catch (PDOException $e) {
    fwrite(STDERR, "Impossible de lire la table de suivi : {$e->getMessage()}\n");
    exit(1);
}

$attente = array_diff_key($toutes, $faites);

if (!$attente) {
    echo "Base à jour : rien à appliquer.\n";
    exit(0);
}

printf("Base : %s sur %s:%s\n%d migration(s) à appliquer.\n\n", DB_NAME, DB_HOST, DB_PORT, count($attente));

foreach ($attente as $version => $chemin) {
    printf("  %-5s %s … ", $version, basename($chemin));
    try {
        $n = appliquerMigration($pdo, $version, $chemin);
        printf("%d instruction(s), OK\n", $n);
    } catch (Throwable $e) {
        printf("ÉCHEC\n\n");
        fwrite(STDERR, "Migration $version interrompue : {$e->getMessage()}\n");
        exit(1);
    }
}

echo "\nTerminé.\n";
