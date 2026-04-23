<?php
define('DB_HOST', 'sql100.infinityfree.com');
define('DB_NAME', 'if0_40695033_safepass');
define('DB_USER', 'if0_40695033'); // À vérifier sur ton panel InfinityFree
define('DB_PASS', 'TON_MOT_DE_PASSE_INFINITY'); // Mets ton mot de passe vPanel ici

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Connexion BDD échouée: ' . $e->getMessage()]));
}
