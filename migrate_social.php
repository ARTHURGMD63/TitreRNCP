<?php
require_once __DIR__ . '/includes/db.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS follows_users (
            follower_id INT NOT NULL,
            followed_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (follower_id, followed_id),
            FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (followed_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");
    echo "Table follows_users créée ou déjà existante.<br>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS follows_etablissements (
            user_id INT NOT NULL,
            etablissement_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, etablissement_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE
        );
    ");
    echo "Table follows_etablissements créée ou déjà existante.<br>";

    echo "Migration terminée avec succès ! Vous pouvez supprimer ce fichier.";
} catch (PDOException $e) {
    echo "Erreur lors de la création des tables : " . $e->getMessage();
}
