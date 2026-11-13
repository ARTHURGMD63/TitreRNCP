<?php
/**
 * Semis de test pour l'application : des soirées variées, pour voir ce que
 * donne chaque écran (en cours, flash, gratuite, sponsorisée, complète…).
 *
 * ATTENTION : lancé avec les variables de production, ceci écrit dans la
 * vraie base. Tout ce qui est créé est VISIBLE par les vrais utilisateurs
 * tant que ce n'est pas retiré. Tout est repérable :
 *   - établissements dont le nom commence par « [TEST] » ;
 *   - comptes en @outsee.invalid (un partenaire, des étudiants fictifs).
 *
 * Usage (PowerShell, à la racine du projet) :
 *   $env:MYSQLHOST = '…'; $env:MYSQLPORT = '4000'
 *   $env:MYSQLUSER = '…'; $env:MYSQLPASSWORD = '…'; $env:MYSQLDATABASE = '…'
 *   php outils/semer_test_app.php             # sème
 *   php outils/semer_test_app.php --nettoyer  # retire tout
 *
 * Les horaires sont calculés à partir de maintenant, à l'heure de Paris
 * (comme les saisit un partenaire) : relancer --nettoyer puis le semis
 * remet « en cours » et « flash » à l'heure.
 *
 * Connexion en TLS sans vérification du certificat, comme
 * outils/migrer_windows.php : includes/db.php ne trouve pas de CA sur
 * Windows. Tâche ponctuelle depuis un poste de confiance.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('Europe/Paris');

const PREFIXE   = '[TEST] ';
const DOMAINE   = '@outsee.invalid';
const PARTENAIRE = 'test-app' . DOMAINE;

$host = getenv('MYSQLHOST') ?: 'localhost';
$name = getenv('MYSQLDATABASE') ?: 'outsee';
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

echo "Base : $name sur $host:$port\n\n";

// ── Nettoyage ─────────────────────────────────────────────────────────────
if (in_array('--nettoyer', $argv, true)) {
    $pdo->beginTransaction();
    $etabs = $pdo->prepare('DELETE FROM etablissements WHERE nom LIKE ?');
    $etabs->execute([PREFIXE . '%']);
    // Les comptes fictifs emportent leurs inscriptions (clé étrangère en
    // CASCADE) ; les soirées partent avec leurs établissements.
    $comptes = $pdo->prepare('DELETE FROM users WHERE email = ? OR email LIKE ?');
    $comptes->execute([PARTENAIRE, 'test-etudiant-%' . DOMAINE]);
    $pdo->commit();
    echo "Retiré : {$etabs->rowCount()} établissement(s) et leurs soirées, {$comptes->rowCount()} compte(s) de test.\n";
    exit(0);
}

// ── Garde-fou : pas de double semis ───────────────────────────────────────
$deja = $pdo->prepare('SELECT COUNT(*) FROM etablissements WHERE nom LIKE ?');
$deja->execute([PREFIXE . '%']);
if ((int) $deja->fetchColumn() > 0) {
    fwrite(STDERR, "Des données de test sont déjà en base. Lancer d'abord : php outils/semer_test_app.php --nettoyer\n");
    exit(1);
}

$maintenant = new DateTimeImmutable('now');
/** Horaire relatif à maintenant, en heures (peut être négatif). */
$dans = static fn (float $heures): string => $maintenant->modify(sprintf('%+d minutes', (int) round($heures * 60)))->format('Y-m-d H:i:00');
/** Tel jour à venir, à telle heure : jour(1, '23:30') = demain 23 h 30. */
$jour = static fn (int $jours, string $heure): string => $maintenant->modify("+$jours days")->format('Y-m-d') . " $heure:00";
/** Ajoute des heures à un horaire déjà calculé. */
$plus = static fn (string $debut, float $heures): string => (new DateTimeImmutable($debut))->modify(sprintf('+%d minutes', (int) round($heures * 60)))->format('Y-m-d H:i:00');

$pdo->beginTransaction();

try {
    $motDePasse = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    // ── Partenaire de test ────────────────────────────────────────────────
    $pdo->prepare(
        "INSERT INTO users (nom, prenom, email, password, type, date_naissance, cgu_acceptees_le)
         VALUES ('App', 'Test', ?, ?, 'partenaire', '1990-01-01', NOW())"
    )->execute([PARTENAIRE, $motDePasse]);
    $partenaireId = (int) $pdo->lastInsertId();

    // ── Étudiants fictifs, pour remplir les jauges ────────────────────────
    $prenoms = ['Léa', 'Hugo', 'Inès', 'Lucas', 'Chloé', 'Nathan', 'Manon', 'Théo', 'Camille', 'Yanis',
                'Sarah', 'Enzo', 'Jade', 'Louis', 'Emma', 'Adam', 'Lina', 'Noah', 'Zoé', 'Rayan',
                'Alice', 'Tom', 'Louna', 'Sacha', 'Eva'];
    $etudiants = [];
    $insertEtudiant = $pdo->prepare(
        "INSERT INTO users (nom, prenom, email, password, ecole, promo, ville, type, date_naissance, cgu_acceptees_le)
         VALUES ('Test', ?, ?, ?, 'UCA', 'L2', 'Clermont-Ferrand', 'etudiant', '2004-03-15', NOW())"
    );
    foreach ($prenoms as $i => $prenom) {
        $insertEtudiant->execute([$prenom, sprintf('test-etudiant-%02d', $i + 1) . DOMAINE, $motDePasse]);
        $etudiants[] = (int) $pdo->lastInsertId();
    }

    // ── Établissements (Clermont-Ferrand) ─────────────────────────────────
    $lieux = [
        'comptoir' => ['Le Comptoir de Jaude', 'bar',       '12 place de Jaude',         45.7772, 3.0823],
        'hangar'   => ['Le Hangar',            'boite',     '3 rue du Port',             45.7842, 3.0950],
        'carmes'   => ['La Table des Carmes',  'resto',     '8 place des Carmes',        45.7766, 3.0905],
        'rooftop'  => ['Le Rooftop Delille',   'afterwork', '1 place Delille',           45.7795, 3.0962],
        'bunker'   => ['Le Bunker',            'boite',     '27 avenue de la Libération', 45.7730, 3.0740],
        'campus'   => ['Le Campus Bar',        'bar',       '4 avenue Blaise-Pascal',    45.7600, 3.1130],
    ];
    $etab = [];
    $insertEtab = $pdo->prepare(
        "INSERT INTO etablissements (user_id, nom, type, adresse, ville, latitude, longitude)
         VALUES (?, ?, ?, ?, 'Clermont-Ferrand', ?, ?)"
    );
    foreach ($lieux as $cle => [$nom, $type, $adresse, $lat, $lon]) {
        $insertEtab->execute([$partenaireId, PREFIXE . $nom, $type, $adresse, $lat, $lon]);
        $etab[$cle] = ['id' => (int) $pdo->lastInsertId(), 'nom' => $nom, 'type' => $type, 'adresse' => $adresse];
    }

    // ── Soirées ───────────────────────────────────────────────────────────
    $debutEnCours = $dans(-1);
    $debutFlash   = $dans(2);
    $soirees = [
        // En cours : commencée il y a une heure.
        ['comptoir', 'Happy hour prolongé', "Pintes et cocktails à prix cassé jusqu'à la fermeture.", 'generaliste',
         $debutEnCours, $plus($debutEnCours, 4), 20, 20, 6.00, 'inscrits' => 14, 'checkin' => 8],
        // Flash : remise qui expire dans trois heures.
        ['hangar', 'Flash : -50 % ce soir', "Entrée à moitié prix pour les 15 premiers inscrits.", 'techno',
         $debutFlash, $plus($debutFlash, 5), 15, 50, 12.00, 'inscrits' => 12, 'flash' => $dans(3)],
        // Gratuite.
        ['rooftop', 'Afterwork acoustique', "Guitare-voix au coucher du soleil, entrée libre.", 'live',
         $jour(1, '18:30'), $jour(1, '22:00'), 40, 0, 0, 'inscrits' => 9, 'gratuit' => true],
        // Sponsorisée.
        ['bunker', 'Warehouse : nuit techno', "Trois DJ, une seule salle, jusqu'au petit matin.", 'techno',
         $jour(1, '23:30'), $jour(2, '05:00'), 120, 30, 15.00, 'inscrits' => 6, 'sponsor' => true],
        ['carmes', 'Menu étudiant à -20 %', "Entrée, plat, dessert sur présentation du pass.", 'sans',
         $jour(1, '19:30'), $jour(1, '22:30'), 30, 20, 18.00, 'inscrits' => 4],
        ['campus', 'Soirée latino', "Initiation salsa à 21 h, puis reggaeton.", 'latino',
         $jour(2, '21:00'), $jour(3, '01:00'), 50, 15, 5.00, 'inscrits' => 3],
        ['hangar', 'Hip-hop session', "Open mic à minuit, battle de danse à 1 h.", 'hiphop',
         $jour(3, '23:00'), $jour(4, '04:00'), 80, 25, 10.00, 'inscrits' => 2],
        // Complète : toutes les places sont prises.
        ['comptoir', 'Quiz musical', "Équipes de quatre, blind test et lots à gagner.", 'pop',
         $jour(4, '20:30'), $jour(4, '23:30'), 25, 0, 0, 'inscrits' => 25, 'gratuit' => true],
        ['rooftop', 'Afterwork disco', "Boule à facettes et funk jusqu'à 23 h.", 'disco',
         $jour(5, '18:00'), $jour(5, '23:00'), 60, 10, 7.00, 'inscrits' => 0],
        ['bunker', 'Afro vibes', "Afrobeats et amapiano toute la nuit.", 'afro',
         $jour(6, '23:30'), $jour(7, '04:30'), 100, 20, 12.00, 'inscrits' => 1],
        ['carmes', 'Brunch étudiant', "Formule à volonté, viennoiseries maison.", 'sans',
         $jour(7, '11:30'), $jour(7, '14:30'), 35, 15, 16.00, 'inscrits' => 5],
        ['campus', 'House au campus', "Le foyer se transforme en club le temps d'une nuit.", 'house',
         $jour(9, '22:00'), $jour(10, '03:00'), 70, 20, 6.00, 'inscrits' => 0],
    ];

    $insertSoiree = $pdo->prepare(
        "INSERT INTO evenements
            (etablissement_id, titre, description, type, style_musique, date_heure, date_fin, quota,
             reduction, prix_normal, is_flash, flash_expiry, is_gratuit,
             is_sponsorise, sponsor_formule, sponsor_tarif, sponsor_jusqu_au, lieu)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)"
    );
    $insertInscription = $pdo->prepare(
        'INSERT INTO inscriptions (user_id, evenement_id, qr_code, statut, checkin_le) VALUES (?, ?, ?, ?, ?)'
    );

    foreach ($soirees as $n => $s) {
        [$cle, $titre, $description, $style, $debut, $fin, $quota, $reduction, $prix] = $s;
        $l = $etab[$cle];
        $flash   = $s['flash'] ?? null;
        $sponsor = !empty($s['sponsor']);
        $insertSoiree->execute([
            $l['id'], $titre, $description, $l['type'], $style, $debut, $fin, $quota,
            $reduction, $prix, $flash ? 1 : 0, $flash, !empty($s['gratuit']) ? 1 : 0,
            $sponsor ? 1 : 0, $sponsor ? 'top7' : null, $sponsor ? $jour(7, '23:59') : null,
            $l['nom'] . ', ' . $l['adresse'],
        ]);
        $evId = (int) $pdo->lastInsertId();

        // Des étudiants différents d'une soirée à l'autre.
        $nb      = min($s['inscrits'], $quota, count($etudiants));
        $checkin = $s['checkin'] ?? 0;
        for ($k = 0; $k < $nb; $k++) {
            $uid = $etudiants[($k + $n * 3) % count($etudiants)];
            $passe = $k < $checkin;
            $insertInscription->execute([
                $uid, $evId, bin2hex(random_bytes(32)),
                $passe ? 'checkin' : 'inscrit', $passe ? $dans(-0.5) : null,
            ]);
        }

        printf("  %-28s %-22s %s  %d/%d\n", $titre, $l['nom'], substr($debut, 0, 16), $nb, $quota);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Échec, rien n'a été écrit : {$e->getMessage()}\n");
    exit(1);
}

echo "\nFait : 6 établissements, " . count($soirees) . " soirées, " . count($etudiants) . " étudiants fictifs.\n";
echo "Pour tout retirer : php outils/semer_test_app.php --nettoyer\n";
