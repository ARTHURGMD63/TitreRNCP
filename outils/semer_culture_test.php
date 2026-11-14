<?php
/**
 * Semis de test de l'espace Culture : des événements variés et répartis
 * dans plusieurs villes, pour voir chaque écran (à la une, en cours, gratuit,
 * payant, complet, sans fin annoncée, passé…).
 *
 * L'espace Culture est indépendant des soirées et du sport : ce script ne
 * touche que culture_evenements et culture_inscriptions.
 *
 * Tout est repérable : titre commençant par « [TEST] », que --nettoyer retire
 * (les inscriptions partent avec, en cascade).
 *
 * Usage (à la racine du projet) :
 *   php outils/semer_culture_test.php             # sème (sans doublon)
 *   php outils/semer_culture_test.php --nettoyer  # retire tout
 * Variables : MYSQLHOST, MYSQLPORT, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE
 * (défaut : localhost, 3306, root, base « linkee »).
 *
 * ATTENTION : avec les variables de production, ceci écrit dans la vraie base.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('Europe/Paris');

const PREFIXE = '[TEST] ';

$host = getenv('MYSQLHOST') ?: 'localhost';
$name = getenv('MYSQLDATABASE') ?: 'linkee';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$port = getenv('MYSQLPORT') ?: '3306';

$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
if (!in_array($host, ['localhost', '127.0.0.1'], true)) {
    $options[PDO::MYSQL_ATTR_SSL_CA]                 = null;
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
}

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, $options);
} catch (PDOException $e) {
    fwrite(STDERR, "Connexion impossible : {$e->getMessage()}\n");
    exit(1);
}

echo "Base : $name sur $host:$port\n";

if (in_array('--nettoyer', $argv, true)) {
    $n = $pdo->exec("DELETE FROM culture_evenements WHERE titre LIKE '" . PREFIXE . "%'");
    echo "$n événement(s) de test retiré(s).\n";
    exit(0);
}

/** Un horaire relatif à maintenant : jours (négatif = passé) et heure du jour. */
$quand = static fn (int $jours, string $heure): string
    => (new DateTime("today $heure"))->modify(($jours >= 0 ? '+' : '') . "$jours days")->format('Y-m-d H:i:s');

// titre, catégorie, organisateur, lieu, ville, début, fin, quota, prix, à la une, inscrits fictifs
$evenements = [
    ['Nuit des musées', 'patrimoine', 'Ville de Clermont-Ferrand', 'Musée d\'art Roger-Quilliot', 'Clermont-Ferrand', $quand(2, '19:00'), $quand(2, '23:59'), 300, 0, 1, 12],
    ['Volcans, regards croisés', 'expo', 'Hôtel Fontfreyde', 'Hôtel Fontfreyde', 'Clermont-Ferrand', $quand(-5, '10:00'), $quand(20, '18:00'), 0, 0, 0, 0],
    ['Concert du kiosque', 'concert', 'Conservatoire Emmanuel-Chabrier', 'Jardin Lecoq', 'Clermont-Ferrand', $quand(4, '17:00'), null, 0, 0, 0, 0],
    ['Atelier sérigraphie', 'atelier', 'Collectif des Trois Coquins', 'La Cour des Trois Coquins', 'Clermont-Ferrand', $quand(6, '14:00'), $quand(6, '17:30'), 16, 5, 0, 16],
    ['Festival Court Métrage', 'festival', 'Sauve qui peut le court métrage', 'Maison de la Culture', 'Clermont-Ferrand', $quand(9, '10:00'), $quand(15, '23:00'), 0, 6, 1, 0],
    ['Cinéma en plein air', 'cinema', 'Ville de Riom', 'Place du Pré Madame', 'Riom', $quand(3, '21:30'), null, 150, 0, 0, 5],
    ['Impro en liberté', 'spectacle', 'Ligue d\'improvisation d\'Auvergne', 'Théâtre du Rond-Point', 'Issoire', $quand(5, '20:30'), null, 80, 8, 0, 3],
    ['Conférence : le volcanisme actif', 'conference', 'Université Clermont Auvergne', 'Amphi Cézeaux', 'Aubière', $quand(7, '18:00'), $quand(7, '20:00'), 200, 0, 0, 24],
    ['Visite du vieux Thiers', 'patrimoine', 'Office de tourisme de Thiers', 'Vallée des Rouets', 'Thiers', $quand(1, '15:00'), $quand(1, '17:00'), 25, 4, 0, 6],
    ['Soirée jazz à la Cave', 'concert', 'Jazz en Velay', 'Cave à jazz', 'Le Puy-en-Velay', $quand(11, '21:00'), null, 40, 12, 0, 0],
    ['Exposition Street Art', 'expo', 'Ville de Lyon', 'Halle Debourg', 'Lyon', $quand(-10, '11:00'), $quand(30, '19:00'), 0, 0, 1, 0],
    ['Nuit blanche des arts', 'festival', 'Ville de Paris', 'Parvis de l\'Hôtel de Ville', 'Paris', $quand(13, '19:00'), $quand(14, '02:00'), 0, 0, 0, 0],
    ['Masterclass photo (complet)', 'atelier', 'Rencontres photographiques', 'Studio Lumière', 'Clermont-Ferrand', $quand(8, '09:30'), $quand(8, '12:30'), 6, 15, 0, 6],
    ['Ciné-club : Nouvelle Vague', 'cinema', 'Ciné-club étudiant', 'Cinéma Le Capitole', 'Clermont-Ferrand', $quand(-12, '20:00'), null, 0, 3, 0, 0],
    ['Théâtre d\'ombres', 'spectacle', 'Compagnie Fil d\'Ariane', 'Salle Gaillard', 'Clermont-Ferrand', $quand(15, '19:30'), null, 60, 10, 0, 2],
    ['Rencontre : écrire la montagne', 'conference', 'Librairie Les Volcans', 'Librairie Les Volcans', 'Clermont-Ferrand', $quand(10, '18:30'), null, 30, 0, 0, 1],
];

$existe  = $pdo->prepare('SELECT id FROM culture_evenements WHERE titre = ?');
$ajoute  = $pdo->prepare(
    'INSERT INTO culture_evenements (titre, description, categorie, organisateur, lieu, ville, date_heure, date_fin, quota, prix, mis_en_avant)
     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
);
$inscrit = $pdo->prepare('INSERT IGNORE INTO culture_inscriptions (user_id, evenement_id) VALUES (?, ?)');
$etudiants = $pdo->query("SELECT id FROM users ORDER BY id LIMIT 30")->fetchAll(PDO::FETCH_COLUMN);

$crees = 0;
foreach ($evenements as [$titre, $cat, $orga, $lieu, $ville, $debut, $fin, $quota, $prix, $une, $nbInscrits]) {
    $titre = PREFIXE . $titre;
    $existe->execute([$titre]);
    if ($existe->fetchColumn()) {
        echo "  déjà là   $titre\n";
        continue;
    }
    $ajoute->execute([$titre, "Événement de test ($cat à $ville).", $cat, $orga, $lieu, $ville, $debut, $fin, $quota, $prix, $une]);
    $id = (int) $pdo->lastInsertId();
    foreach (array_slice($etudiants, 0, min($nbInscrits, count($etudiants))) as $uid) {
        $inscrit->execute([$uid, $id]);
    }
    $crees++;
    printf("  créé      %-42s %-18s %s\n", $titre, $ville, substr($debut, 0, 16));
}

echo "\nFait : $crees événement(s) créé(s). Pour tout retirer : php outils/semer_culture_test.php --nettoyer\n";
