<?php
require_once __DIR__ . '/includes/db.php';

try {
    // Check if column exists
    $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'interests'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN interests TEXT AFTER promo");
        echo "Colonne 'interests' ajoutée avec succès.<br>";
    } else {
        echo "La colonne 'interests' existe déjà.<br>";
    }

    echo "Migration terminée !";
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
