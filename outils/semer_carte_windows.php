<?php
/**
 * Semis ponctuel de test pour la carte interactive — variante Windows.
 *
 * ATTENTION : ceci écrit dans la vraie base de production. Les
 * établissements et événements créés sont VISIBLES par de vrais
 * utilisateurs dans l'application (Explorer, pas seulement la carte) tant
 * qu'ils ne sont pas supprimés — leurs noms commencent par « [TEST CARTE] »
 * précisément pour qu'on puisse les repérer et les effacer facilement.
 *
 * Nettoyage, une fois le test terminé :
 *   DELETE FROM etablissements WHERE nom LIKE '[TEST CARTE]%';
 *   (les événements liés partent avec, par la clé étrangère en CASCADE)
 *
 * À supprimer (ce fichier) une fois le test terminé, comme
 * outils/migrer_windows.php.
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

// Compte partenaire de test, un seul pour tous les faux établissements.
$emailTest = 'test-carte@outsee.invalid';
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$emailTest]);
$userId = $stmt->fetchColumn();

if (!$userId) {
    $pdo->prepare(
        "INSERT INTO users (nom, prenom, email, password, type, date_naissance, cgu_acceptees_le)
         VALUES ('Carte', 'Test', ?, ?, 'partenaire', '1990-01-01', NOW())"
    )->execute([$emailTest, password_hash('test-non-utilise', PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();
    echo "Compte de test créé (id $userId).\n";
} else {
    echo "Compte de test déjà présent (id $userId), réutilisé.\n";
}

// Quatre points répartis dans Clermont-Ferrand, avec un événement à venir
// chacun (demain, à des heures différentes) pour bien voir plusieurs
// épingles sur la carte.
$lieux = [
    ['nom' => '[TEST CARTE] Le Bar Fictif',      'lat' => 45.7772, 'lon' => 3.0870, 'type' => 'bar'],
    ['nom' => '[TEST CARTE] La Boîte Imaginaire', 'lat' => 45.7830, 'lon' => 3.0820, 'type' => 'boite'],
    ['nom' => '[TEST CARTE] Le Resto d\'Essai',   'lat' => 45.7700, 'lon' => 3.1000, 'type' => 'resto'],
    ['nom' => '[TEST CARTE] After d\'Exemple',    'lat' => 45.7900, 'lon' => 3.0950, 'type' => 'afterwork'],
];

foreach ($lieux as $i => $l) {
    $pdo->prepare(
        'INSERT INTO etablissements (user_id, nom, type, adresse, ville, latitude, longitude)
         VALUES (?,?,?,?,?,?,?)'
    )->execute([$userId, $l['nom'], $l['type'], 'Adresse de test', 'Clermont-Ferrand', $l['lat'], $l['lon']]);
    $etabId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO evenements (etablissement_id, titre, description, type, date_heure, quota, reduction, is_gratuit, lieu)
         VALUES (?,?,?,?,DATE_ADD(NOW(), INTERVAL ? HOUR),50,10,0,?)"
    )->execute([$etabId, $l['nom'] . ' — soirée', 'Événement de test pour la carte.', $l['type'], 4 + $i * 6, $l['nom']]);

    echo "  {$l['nom']} → établissement #$etabId, un événement créé.\n";
}

echo "\nFait. Pour tout retirer ensuite :\n";
echo "  DELETE FROM etablissements WHERE nom LIKE '[TEST CARTE]%';\n";
