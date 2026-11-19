<?php
/**
 * Semis de démonstration : l'application telle qu'elle sera une fois lancée.
 *
 * Soirées, Sport et Culture se remplissent de contenu crédible — lieux,
 * clubs et organisateurs FICTIFS (aucun vrai commerce n'est engagé), rues
 * réelles de Clermont-Ferrand, horaires calculés à partir de maintenant,
 * jauges partiellement remplies par des étudiants fictifs.
 *
 * Contrairement aux semis de test (semer_test_app.php…), rien n'est étiqueté
 * « [TEST] » à l'écran. Ce qui permet de tout retirer est invisible :
 *   - tous les comptes créés sont en @demo.outsee.invalid (étudiants,
 *     gérants de lieux, organisateur culturel) ;
 *   - soirées, clubs, créneaux, sorties et événements culturels leur sont
 *     rattachés.
 *
 * ATTENTION : avec les variables de production, ceci écrit dans la vraie
 * base. Les vrais utilisateurs voient ces événements comme des vrais, et
 * peuvent s'y inscrire. Retirer le semis avant le lancement public.
 *
 * Usage (à la racine du projet) :
 *   php outils/semer_demo.php               # sème
 *   php outils/semer_demo.php --rafraichir  # retire puis ressème (dates recalées sur maintenant)
 *   php outils/semer_demo.php --nettoyer    # retire tout
 * Variables : MYSQLHOST, MYSQLPORT, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('Europe/Paris');

const DOMAINE_DEMO = '@demo.outsee.invalid';

$host = getenv('MYSQLHOST') ?: 'localhost';
$name = getenv('MYSQLDATABASE') ?: 'outsee';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$port = getenv('MYSQLPORT') ?: '3306';

$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
if (!in_array($host, ['localhost', '127.0.0.1'], true)) {
    $options[PDO::MYSQL_ATTR_SSL_CA]                 = null;
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
}

/** Une connexion neuve : celle du semis, ou celle qui nettoie après un échec. */
$connecter = static function () use ($host, $port, $name, $user, $pass, $options): PDO {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, $options);
    $pdo->exec("SET time_zone = '" . date('P') . "'");
    return $pdo;
};

try {
    $pdo = $connecter();
} catch (PDOException $e) {
    fwrite(STDERR, "Connexion impossible : {$e->getMessage()}\n");
    exit(1);
}

echo "Base : $name sur $host:$port\n\n";

/**
 * Retire tout le semis, dans l'ordre des dépendances (sans compter sur les
 * cascades : certaines clés sont en SET NULL).
 *
 * @return array<string, int> lignes retirées par table
 */
function nettoyerDemo(PDO $pdo): array
{
    $comptes = $pdo->query("SELECT id FROM users WHERE email LIKE '%" . DOMAINE_DEMO . "'")->fetchAll(PDO::FETCH_COLUMN);
    if (!$comptes) {
        return [];
    }
    $u = implode(',', array_map('intval', $comptes));
    $etabs = $pdo->query("SELECT id FROM etablissements WHERE user_id IN ($u)")->fetchAll(PDO::FETCH_COLUMN);
    $e = $etabs ? implode(',', array_map('intval', $etabs)) : '0';

    $requetes = [
        'culture_inscriptions' => "DELETE FROM culture_inscriptions WHERE user_id IN ($u)
                                      OR evenement_id IN (SELECT id FROM culture_evenements WHERE cree_par IN ($u))",
        'culture_evenements'   => "DELETE FROM culture_evenements WHERE cree_par IN ($u)",
        'creneau_joueurs'      => "DELETE FROM creneau_joueurs WHERE user_id IN ($u)
                                      OR creneau_id IN (SELECT c.id FROM creneaux c JOIN terrains t ON t.id = c.terrain_id WHERE t.etablissement_id IN ($e))",
        'creneaux'             => "DELETE FROM creneaux WHERE organisateur_id IN ($u)
                                      OR terrain_id IN (SELECT id FROM terrains WHERE etablissement_id IN ($e))",
        'terrains'             => "DELETE FROM terrains WHERE etablissement_id IN ($e)",
        'etablissement_sports' => "DELETE FROM etablissement_sports WHERE etablissement_id IN ($e)",
        'crm_clients'          => "DELETE FROM crm_clients WHERE etablissement_id IN ($e)",
        'inscriptions'         => "DELETE FROM inscriptions WHERE user_id IN ($u)
                                      OR evenement_id IN (SELECT id FROM evenements WHERE etablissement_id IN ($e))",
        'evenements'           => "DELETE FROM evenements WHERE etablissement_id IN ($e)",
        'squad_membres'        => "DELETE FROM squad_membres WHERE user_id IN ($u)
                                      OR squad_id IN (SELECT id FROM squads WHERE createur_id IN ($u))",
        'squads'               => "DELETE FROM squads WHERE createur_id IN ($u)",
        'etablissements'       => "DELETE FROM etablissements WHERE id IN ($e)",
        'user_interets'        => "DELETE FROM user_interets WHERE user_id IN ($u)",
        'users'                => "DELETE FROM users WHERE id IN ($u)",
    ];
    $retire = [];
    $pdo->beginTransaction();
    try {
        foreach ($requetes as $table => $sql) {
            $retire[$table] = (int) $pdo->exec($sql);
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }

    return $retire;
}

$nettoyer   = in_array('--nettoyer', $argv, true);
$rafraichir = in_array('--rafraichir', $argv, true);

if ($nettoyer || $rafraichir) {
    try {
        $retire = nettoyerDemo($pdo);
    } catch (Throwable $e) {
        fwrite(STDERR, "Échec du nettoyage, rien n'a été retiré : {$e->getMessage()}\n");
        exit(1);
    }
    if (!$retire) {
        echo "Aucun semis de démonstration en base.\n";
    } else {
        printf("Retiré : %d compte(s), %d lieu(x) et club(s), %d événement(s), %d événement(s) culturel(s), %d sortie(s).\n",
            $retire['users'], $retire['etablissements'], $retire['evenements'], $retire['culture_evenements'], $retire['squads']);
    }
    if ($nettoyer) {
        exit(0);
    }
    echo "\n";
}

$deja = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email LIKE '%" . DOMAINE_DEMO . "'")->fetchColumn();
if ($deja > 0) {
    fwrite(STDERR, "Le semis de démonstration est déjà en base. Pour recaler les dates : php outils/semer_demo.php --rafraichir\n");
    exit(1);
}

// ── Le temps ──────────────────────────────────────────────────────────────
$maintenant = new DateTimeImmutable('now');
/** Horaire relatif à maintenant, en heures (peut être négatif), arrondi au quart d'heure. */
$dans = static function (float $heures) use ($maintenant): string {
    $t = $maintenant->modify(sprintf('%+d minutes', (int) round($heures * 60)));
    $m = (int) (round((int) $t->format('i') / 15) * 15);
    return $t->setTime((int) $t->format('H'), 0)->modify("+$m minutes")->format('Y-m-d H:i:00');
};
/** Tel jour (0 = aujourd'hui), à telle heure. */
$jour = static fn (int $jours, string $heure): string => $maintenant->modify(($jours >= 0 ? '+' : '') . "$jours days")->format('Y-m-d') . " $heure:00";
/** Ajoute des minutes à un horaire. */
$plus = static fn (string $debut, int $minutes): string => (new DateTimeImmutable($debut))->modify("+$minutes minutes")->format('Y-m-d H:i:00');
$qr = static fn (): string => bin2hex(random_bytes(32));

/**
 * Insère des lignes par paquets de 200, en une requête par paquet.
 *
 * Ligne à ligne, le semis enchaînait plus de mille allers-retours dans une
 * seule transaction : la passerelle de TiDB Cloud coupait la connexion en
 * route (« MySQL server has gone away »).
 *
 * @param list<string>             $colonnes
 * @param list<list<mixed>>        $lignes
 */
$inserer = static function (string $table, array $colonnes, array $lignes) use (&$pdo): void {
    $trous = '(' . implode(',', array_fill(0, count($colonnes), '?')) . ')';
    foreach (array_chunk($lignes, 200) as $paquet) {
        $pdo->prepare("INSERT INTO $table (" . implode(', ', $colonnes) . ') VALUES ' . implode(',', array_fill(0, count($paquet), $trous)))
            ->execute(array_merge(...$paquet));
    }
};

// Une transaction par espace (Soirées, Sport, Culture), pas une seule pour
// tout : chacune reste courte. Si l'une échoue, ce qui a déjà été validé est
// retiré par nettoyerDemo() : jamais de semis à moitié en base.
$pdo->beginTransaction();

try {
    // Mot de passe aléatoire jeté : personne ne se connecte à ces comptes.
    $motDePasse = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $slug = static fn (string $s): string => trim((string) preg_replace('/[^a-z0-9]+/', '.', strtolower(
        strtr($s, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'ï' => 'i', 'î' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'É' => 'e'])
    )), '.');

    // ── Étudiants ─────────────────────────────────────────────────────────
    // prénom, nom, école, promo, naissance, intérêts
    $personnes = [
        ['Léa', 'Martin', 'UCA', 'L2', '2005-02-14', 'Sorties,Bars,Musique'],
        ['Hugo', 'Bernard', 'SIGMA Clermont', 'M1', '2002-06-03', 'Running,Vélo,Bars'],
        ['Inès', 'Dubois', 'UCA', 'L3', '2004-11-21', 'Boîtes,Techno,Sorties'],
        ['Lucas', 'Thomas', 'INP Ingénieurs', 'BUT2', '2005-04-09', 'Gaming,Code,Foot'],
        ['Chloé', 'Robert', 'IFSI', 'L1', '2006-01-30', 'Sorties,Yoga,Cinéma'],
        ['Nathan', 'Richard', 'UCA', 'M2', '2001-09-17', 'Foot,Muscu,Bars'],
        ['Manon', 'Petit', 'UCA', 'L2', '2005-07-25', 'Art,Cinéma,Voyage'],
        ['Théo', 'Durand', 'SIGMA Clermont', 'L3', '2003-12-02', 'Tennis,Running,Sorties'],
        ['Camille', 'Leroy', 'UCA', 'M1', '2002-03-11', 'Musique,Art,Sorties'],
        ['Yanis', 'Moreau', 'INP Ingénieurs', 'BUT3', '2004-08-28', 'Foot,Gaming,Boîtes'],
        ['Sarah', 'Simon', 'UCA', 'L1', '2006-05-06', 'Mixologie,Bars,Sorties'],
        ['Enzo', 'Laurent', 'UCA', 'L3', '2003-10-19', 'Techno,Boîtes,Musique'],
        ['Jade', 'Lefebvre', 'IFSI', 'L2', '2005-01-08', 'Yoga,Voyage,Cinéma'],
        ['Louis', 'Michel', 'SIGMA Clermont', 'M2', '2001-11-29', 'Vélo,Running,Bars'],
        ['Emma', 'Garcia', 'UCA', 'L2', '2005-09-13', 'Sorties,Mixologie,Musique'],
        ['Adam', 'David', 'INP Ingénieurs', 'BUT1', '2006-02-22', 'Gaming,Code,Échecs'],
        ['Lina', 'Bertrand', 'UCA', 'M1', '2002-07-04', 'Art,Musique,Voyage'],
        ['Noah', 'Roux', 'UCA', 'L3', '2003-04-27', 'Muscu,Foot,Boîtes'],
        ['Zoé', 'Vincent', 'IFSI', 'L3', '2003-06-15', 'Running,Yoga,Sorties'],
        ['Rayan', 'Fournier', 'UCA', 'L2', '2005-03-01', 'Soirées,Boîtes,Foot'],
        ['Alice', 'Morel', 'SIGMA Clermont', 'L3', '2004-12-24', 'Photo,Voyage,Art'],
        ['Tom', 'Girard', 'UCA', 'M2', '2001-05-18', 'Tennis,Bars,Musique'],
        ['Louna', 'André', 'UCA', 'L1', '2006-08-07', 'Sorties,Cinéma,Boîtes'],
        ['Sacha', 'Lefèvre', 'INP Ingénieurs', 'BUT2', '2005-10-10', 'Code,Gaming,Musique'],
        ['Eva', 'Mercier', 'UCA', 'L2', '2005-06-30', 'Yoga,Art,Sorties'],
        ['Maxime', 'Dupont', 'UCA', 'M1', '2002-01-16', 'Running,Vélo,Foot'],
        ['Clara', 'Lambert', 'SIGMA Clermont', 'M1', '2002-09-05', 'Running,Cuisine,Bars'],
        ['Julien', 'Bonnet', 'UCA', 'L3', '2003-02-20', 'Muscu,Soirées,Sorties'],
        ['Anaïs', 'François', 'IFSI', 'L2', '2004-04-12', 'Musique,Sorties,Voyage'],
        ['Bastien', 'Martinez', 'UCA', 'M2', '2000-12-09', 'Foot,Bars,Tennis'],
        ['Margaux', 'Legrand', 'UCA', 'L1', '2006-03-23', 'Boîtes,Techno,Mixologie'],
        ['Mehdi', 'Garnier', 'INP Ingénieurs', 'BUT3', '2004-07-01', 'Foot,Gaming,Photo'],
        ['Romane', 'Faure', 'UCA', 'L3', '2003-08-14', 'Cinéma,Art,Yoga'],
        ['Lou', 'Rousseau', 'SIGMA Clermont', 'L3', '2004-05-26', 'Vélo,Lecture,Voyage'],
        ['Arthur', 'Blanc', 'UCA', 'L2', '2005-11-03', 'Sorties,Bars,Foot'],
        ['Juliette', 'Guerin', 'UCA', 'M1', '2002-10-22', 'Musique,Cinéma,Art'],
        ['Kylian', 'Muller', 'UCA', 'L1', '2006-06-11', 'Foot,Muscu,Soirées'],
        ['Ambre', 'Henry', 'IFSI', 'L1', '2006-09-19', 'Sorties,Boîtes,Mixologie'],
        ['Gabriel', 'Roussel', 'UCA', 'M2', '2001-03-07', 'Running,Tennis,Vélo'],
        ['Nina', 'Nicolas', 'SIGMA Clermont', 'M2', '2001-12-31', 'Voyage,Yoga,Art'],
    ];
    $insertUser = $pdo->prepare(
        "INSERT INTO users (nom, prenom, email, password, ecole, promo, ville, type, date_naissance, interests, cgu_acceptees_le, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $lignesInterets = [];
    $etudiants = [];
    foreach ($personnes as $i => [$prenom, $nom, $ecole, $promo, $naissance, $interets]) {
        // Inscrits au fil des dernières semaines, pas tous le même jour.
        $arrivee = $maintenant->modify('-' . (3 + ($i * 7) % 60) . ' days')->modify('-' . (($i * 37) % 600) . ' minutes')->format('Y-m-d H:i:s');
        $insertUser->execute([
            $nom, $prenom, $slug("$prenom.$nom") . DOMAINE_DEMO, $motDePasse, $ecole, $promo, 'Clermont-Ferrand',
            'etudiant', $naissance, $interets, $arrivee, $arrivee,
        ]);
        $id = (int) $pdo->lastInsertId();
        foreach (explode(',', $interets) as $interet) {
            $lignesInterets[] = [$id, $interet];
        }
        $etudiants[] = $id;
    }
    $inserer('user_interets', ['user_id', 'interet'], $lignesInterets);
    $nbEtudiants = count($etudiants);
    /** Le k-ième participant d'une liste qui tourne, décalée par $graine. */
    $participant = static fn (int $graine, int $k): int => $etudiants[($graine * 7 + $k * 3) % $nbEtudiants];

    // ── Gérants (un compte par lieu, comme dans la vraie vie) ─────────────
    $insertGerant = $pdo->prepare(
        "INSERT INTO users (nom, prenom, email, password, type, date_naissance, cgu_acceptees_le)
         VALUES (?, ?, ?, ?, 'partenaire', ?, NOW())"
    );
    $nouveauGerant = static function (string $prenom, string $nom, string $lieu) use ($insertGerant, $pdo, $motDePasse, $slug): int {
        $insertGerant->execute([$nom, $prenom, $slug($lieu) . DOMAINE_DEMO, $motDePasse, '1985-01-01']);
        return (int) $pdo->lastInsertId();
    };
    $insertEtab = $pdo->prepare(
        "INSERT INTO etablissements (user_id, nom, type, adresse, ville, latitude, longitude)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    // ══ SOIRÉES ══════════════════════════════════════════════════════════
    // clé => nom, type, adresse, lat, lon, gérant
    $lieux = [
        'salers'   => ['Le Comptoir des Salers', 'bar', '18 rue Saint-Dominique', 45.7781, 3.0853, ['Julien', 'Chabrol']],
        'bulles'   => ['La Fabrique à Bulles', 'bar', '9 rue des Petits-Gras', 45.7790, 3.0869, ['Sophie', 'Vidal']],
        'volcan'   => ['Le Volcan Club', 'boite', '22 boulevard Trudaine', 45.7774, 3.0900, ['Karim', 'Benali']],
        'entrepot' => ['L\'Entrepôt 63', 'boite', '61 avenue Édouard-Michelin', 45.7845, 3.1080, ['Thomas', 'Pradier']],
        'patio'    => ['Le Patio Saint-Pierre', 'afterwork', '4 place Saint-Pierre', 45.7797, 3.0863, ['Élodie', 'Mazet']],
        'margot'   => ['Chez Margot', 'resto', '15 rue Pascal', 45.7768, 3.0877, ['Margot', 'Teyssier']],
        'monte'    => ['Le Monte-Charge', 'bar', '31 rue Fontgiève', 45.7810, 3.0810, ['Pierre', 'Aubert']],
        'cezeaux'  => ['La Terrasse des Cézeaux', 'bar', '12 avenue des Landais, Aubière', 45.7605, 3.1110, ['Nicolas', 'Fayolle']],
        'mezcal'   => ['El Mezcal', 'bar', '6 rue Ballainvilliers', 45.7752, 3.0870, ['Diego', 'Ramos']],
    ];
    $etab = [];
    foreach ($lieux as $cle => [$nom, $type, $adresse, $lat, $lon, [$gPrenom, $gNom]]) {
        $gerant = $nouveauGerant($gPrenom, $gNom, $nom);
        $insertEtab->execute([$gerant, $nom, $type, $adresse, 'Clermont-Ferrand', $lat, $lon]);
        $etab[$cle] = ['id' => (int) $pdo->lastInsertId(), 'nom' => $nom, 'type' => $type, 'adresse' => $adresse];
    }

    $enCours = $dans(-1.5);
    $flash   = $dans(2.5);
    // lieu, titre, description, style, début, durée (min), quota, réduction %, prix normal, inscrits, options
    $soirees = [
        ['salers', 'Afterwork en terrasse', "Planches de charcuterie d'Auvergne et pintes à 4 euros jusqu'à 21 h. On garde la terrasse chauffée ouverte tant qu'il y a du monde.", 'generaliste',
            $enCours, 300, 40, 20, 6.0, 31, ['checkin' => 22]],
        ['volcan', 'Ce soir : entrée à -50 %', "Les quinze premiers inscrits entrent à moitié prix avant minuit. DJ résident aux platines, vestiaire offert.", 'house',
            $flash, 330, 15, 50, 12.0, 11, ['flash' => $dans(4)]],
        ['patio', 'Apéro acoustique', "Duo guitare-voix sur la place, de la folk aux reprises pop. Entrée libre, consommation conseillée.", 'live',
            $jour(1, '18:30'), 210, 60, 0, 0.0, 23, ['gratuit' => true]],
        ['entrepot', 'Nuit techno : Basalte', "Trois DJ de la scène lyonnaise et clermontoise, son Funktion-One, jusqu'à 5 h. Pass étudiant obligatoire à l'entrée.", 'techno',
            $jour(1, '23:30'), 330, 250, 30, 15.0, 87, ['sponsor' => true]],
        ['margot', 'Menu étudiant du soir', "Truffade maison, salade verte et dessert du jour : la formule complète sur présentation du pass Outsee.", 'sans',
            $jour(1, '19:30'), 150, 30, 20, 17.0, 12, []],
        ['mezcal', 'Soirée latino', "Initiation salsa et bachata à 21 h avec deux profs de l'école Son Cubano, puis reggaeton jusqu'à la fermeture.", 'latino',
            $jour(2, '21:00'), 240, 80, 15, 6.0, 46, []],
        ['bulles', 'Dégustation bières auvergnates', "Six bières de brasseries du Puy-de-Dôme, présentées par le brasseur. Planche de fromages comprise.", 'sans',
            $jour(2, '19:00'), 150, 24, 25, 16.0, 24, []],
        ['monte', 'Concert : The Dômes', "Rock garage, premier album sorti cet automne. Première partie : Midnight Lava. Ouverture des portes à 20 h 30.", 'rock',
            $jour(3, '21:00'), 180, 120, 20, 10.0, 64, []],
        ['volcan', 'Hip-hop Night', "Open mic à minuit, battle de danse à 1 h, et les classiques du rap français entre les deux.", 'hiphop',
            $jour(3, '23:00'), 300, 200, 25, 12.0, 58, []],
        ['cezeaux', 'Blind test géant', "Équipes de quatre à six, quatre manches, des places de concert à gagner. Inscriptions conseillées, ça part vite.", 'pop',
            $jour(4, '20:00'), 180, 40, 0, 0.0, 40, ['gratuit' => true]],
        ['salers', 'Quiz de culture générale', "Le quiz mensuel du Comptoir : six thèmes, une planche offerte à l'équipe gagnante.", 'sans',
            $jour(5, '20:30'), 150, 40, 10, 5.0, 17, []],
        ['patio', 'Afterwork disco', "Boule à facettes, funk et disco des années 70 à 90. Cocktails à 6 euros jusqu'à 22 h.", 'disco',
            $jour(5, '18:30'), 270, 90, 15, 7.0, 21, []],
        ['entrepot', 'Afro Vibes', "Afrobeats, amapiano et coupé-décalé toute la nuit avec le collectif Kalinda.", 'afro',
            $jour(6, '23:30'), 300, 220, 20, 13.0, 34, []],
        ['mezcal', 'Tacos & mezcal', "Soirée dégustation : trois mezcals, tacos al pastor à volonté, playlist cumbia.", 'latino',
            $jour(6, '19:30'), 180, 36, 20, 22.0, 9, []],
        ['margot', 'Grand brunch', "Formule à volonté : viennoiseries maison, œufs brouillés, pancakes et jus pressés.", 'sans',
            $jour(7, '11:00'), 210, 40, 15, 19.0, 26, []],
        ['monte', 'Scène ouverte', "Musique, slam, stand-up : chacun a dix minutes. Inscription des artistes sur place dès 19 h 30.", 'live',
            $jour(8, '20:00'), 210, 70, 0, 0.0, 15, ['gratuit' => true]],
        ['volcan', 'Soirée années 2000', "Britney, Daft Punk, Diam's : la playlist de votre adolescence, de minuit à 5 h.", 'pop',
            $jour(9, '23:30'), 330, 250, 30, 12.0, 41, ['sponsor_formule' => 'boost24']],
        ['cezeaux', 'Soirée de rentrée des BDE', "Les associations étudiantes des Cézeaux réunies : DJ, stands et navettes de retour vers le centre jusqu'à 2 h.", 'generaliste',
            $jour(10, '21:00'), 300, 300, 0, 0.0, 132, ['gratuit' => true]],
        ['bulles', 'Afterwork jeux de société', "Plus de deux cents jeux en libre accès, des animateurs pour expliquer les règles, et la happy hour prolongée.", 'sans',
            $jour(11, '18:00'), 240, 50, 20, 5.0, 8, []],
        ['entrepot', 'House Session', "Deep house et disco house, invité spécial venu de Berlin, jusqu'au lever du jour.", 'house',
            $jour(13, '23:30'), 330, 250, 25, 15.0, 19, []],
    ];
    $insertSoiree = $pdo->prepare(
        "INSERT INTO evenements
            (etablissement_id, titre, description, type, style_musique, date_heure, date_fin, quota,
             reduction, prix_normal, is_flash, flash_expiry, is_gratuit,
             is_sponsorise, sponsor_formule, sponsor_tarif, sponsor_debut, sponsor_jusqu_au, lieu, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $colsInscription = ['user_id', 'evenement_id', 'qr_code', 'statut', 'checkin_le'];
    $lignesInscriptions = [];
    $nbInscriptions = 0;
    foreach ($soirees as $n => [$cle, $titre, $description, $style, $debut, $duree, $quota, $reduction, $prix, $inscrits, $o]) {
        $l = $etab[$cle];
        $formule = $o['sponsor_formule'] ?? (!empty($o['sponsor']) ? 'top7' : null);
        $insertSoiree->execute([
            $l['id'], $titre, $description, $l['type'], $style, $debut, $plus($debut, $duree), $quota,
            $reduction, $prix, isset($o['flash']) ? 1 : 0, $o['flash'] ?? null, !empty($o['gratuit']) ? 1 : 0,
            $formule ? 1 : 0, $formule, 0, $formule ? $dans(-24) : null, $formule ? $jour(7, '23:59') : null,
            $l['nom'] . ', ' . $l['adresse'], $maintenant->modify('-' . (2 + $n % 6) . ' days')->format('Y-m-d H:i:s'),
        ]);
        $evId = (int) $pdo->lastInsertId();
        // Au-delà des étudiants fictifs, la jauge affichée reste celle-ci : on
        // inscrit au plus autant de personnes qu'il y en a.
        $nb = min($inscrits, $quota, $nbEtudiants);
        $checkin = $o['checkin'] ?? 0;
        $vus = [];
        for ($k = 0; count($vus) < $nb; $k++) {
            $uid = $participant($n, $k);
            if (isset($vus[$uid])) {
                $uid = $etudiants[($k + $n) % $nbEtudiants];
                if (isset($vus[$uid])) {
                    continue;
                }
            }
            $vus[$uid] = true;
            $passe = count($vus) <= $checkin;
            $lignesInscriptions[] = [$uid, $evId, $qr(), $passe ? 'checkin' : 'inscrit', $passe ? $dans(-1) : null];
            $nbInscriptions++;
        }
    }

    $inserer('inscriptions', $colsInscription, $lignesInscriptions);
    $lignesInscriptions = [];
    $pdo->commit();
    echo "  Soirées : validées
";
    $pdo->beginTransaction();

    // ══ SPORT ════════════════════════════════════════════════════════════
    $insertSportEtab = $pdo->prepare('INSERT IGNORE INTO etablissement_sports (etablissement_id, sport) VALUES (?, ?)');
    $insertCrm = $pdo->prepare(
        "INSERT INTO crm_clients (etablissement_id, nom, categorie, ville, adresse, statut, offre, mrr)
         VALUES (?, ?, 'club', ?, ?, 'actif', 'club', 0)"
    );
    $insertTerrain = $pdo->prepare(
        'INSERT INTO terrains (etablissement_id, nom, sport, joueurs, duree_min, prix) VALUES (?, ?, ?, ?, ?, ?)'
    );
    // clé => nom, ville, adresse, lat, lon, gérant, terrains [nom, sport, joueurs, durée, prix]
    $clubs = [
        'padel'    => ['Padel Arverne', 'Clermont-Ferrand', '14 rue de l\'Oradou', 45.7700, 3.1050, ['Romain', 'Delorme'], [
            ['Piste 1', 'padel', 4, 90, 36], ['Piste 2', 'padel', 4, 90, 36], ['Piste panoramique', 'padel', 4, 90, 42],
        ]],
        'five'     => ['Five des Cézeaux', 'Aubière', '3 rue Roche-Genès', 45.7560, 3.1120, ['Samir', 'Haddad'], [
            ['Terrain A', 'foot5', 10, 60, 80], ['Terrain B', 'foot5', 10, 60, 80],
        ]],
        'bloc'     => ['Vertical Bloc', 'Clermont-Ferrand', '45 rue Kessler', 45.7850, 3.0880, ['Aurélie', 'Brun'], [
            ['Salle de bloc', 'escalade', 6, 120, 14],
        ]],
        'raquette' => ['Raquettes de Montferrand', 'Clermont-Ferrand', '10 rue du Ressort', 45.7935, 3.1150, ['Olivier', 'Peyronnet'], [
            ['Court de squash 1', 'squash', 2, 45, 12], ['Court de squash 2', 'squash', 2, 45, 12], ['Salle badminton', 'badminton', 4, 60, 16],
        ]],
        'tennis'   => ['Tennis Club de la Pradelle', 'Clermont-Ferrand', '2 rue de la Pradelle', 45.7900, 3.0760, ['Claire', 'Rocher'], [
            ['Court terre battue', 'tennis', 2, 60, 15], ['Court couvert', 'tennis', 4, 90, 22],
        ]],
    ];
    $terrains = [];
    $club = [];
    foreach ($clubs as $cle => [$nom, $ville, $adresse, $lat, $lon, [$gPrenom, $gNom], $liste]) {
        $gerant = $nouveauGerant($gPrenom, $gNom, $nom);
        $insertEtab->execute([$gerant, $nom, 'club', $adresse, $ville, $lat, $lon]);
        $id = (int) $pdo->lastInsertId();
        $club[$cle] = ['id' => $id, 'nom' => $nom, 'ville' => $ville, 'adresse' => $adresse];
        $insertCrm->execute([$id, $nom, $ville, $adresse]);
        foreach ($liste as [$tNom, $sport, $joueurs, $duree, $prix]) {
            $insertSportEtab->execute([$id, $sport]);
            $insertTerrain->execute([$id, $tNom, $sport, $joueurs, $duree, $prix]);
            $terrains[] = ['id' => (int) $pdo->lastInsertId(), 'sport' => $sport, 'joueurs' => $joueurs, 'duree' => $duree, 'prix' => $prix];
        }
    }

    $insertCreneau = $pdo->prepare(
        'INSERT INTO creneaux (terrain_id, debut, fin, prix, joueurs, statut, organisateur_id, ouvert, niveau, reserve_le)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $colsCreneau = ['terrain_id', 'debut', 'fin', 'prix', 'joueurs', 'statut', 'organisateur_id', 'ouvert', 'niveau', 'reserve_le'];
    $lignesLibres = [];
    $lignesJoueurs = [];
    $heures  = ['09:00', '10:30', '12:00', '17:30', '19:00', '20:30'];
    $niveaux = ['tous', 'debutant', 'inter', 'avance'];
    $nbCreneaux = ['libre' => 0, 'ouverte' => 0, 'pleine' => 0];
    $tour = 0;
    foreach ($terrains as $ti => $t) {
        for ($j = 0; $j <= 7; $j++) {
            foreach ($heures as $h => $heure) {
                $debut = $jour($j, $heure);
                if ($debut <= $maintenant->format('Y-m-d H:i:s')) {
                    continue;
                }
                // Le soir est plus demandé : on y réserve davantage, et on laisse
                // des trous ailleurs, comme un vrai planning.
                $tour++;
                $soir = $h >= 3;
                $hasard = ($ti * 31 + $j * 17 + $h * 7) % 10;
                if (!$soir && $hasard < 4) {
                    continue;
                }
                $reserve = $soir ? $hasard < 6 : $hasard < 2;
                $fin = $plus($debut, $t['duree']);
                if (!$reserve) {
                    $lignesLibres[] = [$t['id'], $debut, $fin, $t['prix'], $t['joueurs'], 'libre', null, 0, 'tous', null];
                    $nbCreneaux['libre']++;
                    continue;
                }
                $orga   = $participant($tour, 0);
                $pleine = $hasard % 3 === 0;
                $ouvert = $t['joueurs'] > 2 && !$pleine ? 1 : 0;
                $insertCreneau->execute([
                    $t['id'], $debut, $fin, $t['prix'], $t['joueurs'], 'reserve', $orga, $ouvert || $pleine ? 1 : 0,
                    $niveaux[$tour % 4], $maintenant->modify('-' . ($tour % 48) . ' hours')->format('Y-m-d H:i:s'),
                ]);
                $creneauId = (int) $pdo->lastInsertId();
                $places = $pleine ? $t['joueurs'] : max(1, $t['joueurs'] - 1 - ($tour % 2));
                $dans_partie = [$orga => true];
                $lignesJoueurs[] = [$creneauId, $orga, null];
                for ($k = 1; count($dans_partie) < $places && $k < 40; $k++) {
                    $uid = $participant($tour + 5, $k);
                    if (isset($dans_partie[$uid])) {
                        continue;
                    }
                    $dans_partie[$uid] = true;
                    $lignesJoueurs[] = [$creneauId, $uid, $orga];
                }
                $nbCreneaux[$pleine ? 'pleine' : ($ouvert ? 'ouverte' : 'pleine')]++;
            }
        }
    }

    $inserer('creneaux', $colsCreneau, $lignesLibres);
    $inserer('creneau_joueurs', ['creneau_id', 'user_id', 'invite_par'], $lignesJoueurs);

    $insertEvSport = $pdo->prepare(
        "INSERT INTO evenements (etablissement_id, titre, description, type, sport, format, date_heure, date_fin,
                                 quota, reduction, prix_normal, is_gratuit, lieu, created_at)
         VALUES (?, ?, ?, 'sport', ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)"
    );
    // club, titre, description, sport, format, jour, heure, durée, quota, prix, inscrits
    $evSport = [
        ['padel', 'Tournoi padel étudiant', "Tableau à 16 équipes, deux niveaux, matchs en 2 sets gagnants. Balles fournies, lots pour les finalistes.", 'padel', 'tournoi', 5, '09:00', 480, 32, 15, 26],
        ['padel', 'Initiation padel', "Deux heures avec un moniteur pour découvrir les règles, la vitre et le service. Raquettes prêtées.", 'padel', 'initiation', 2, '14:00', 120, 12, 0, 12],
        ['five', 'Five en nocturne', "Matchs de 20 minutes en rotation, équipes formées sur place selon le niveau.", 'foot5', 'match', 3, '20:00', 120, 30, 5, 18],
        ['bloc', 'Soirée bloc & DJ', "Nouveaux blocs ouverts pour l'occasion, DJ set et bar à smoothies jusqu'à 23 h.", 'escalade', 'soiree', 4, '19:00', 240, 80, 10, 37],
        ['bloc', 'Cours technique débutant', "Placement des pieds, lecture de voie et chute en sécurité, en petit groupe.", 'escalade', 'cours', 1, '18:00', 90, 8, 12, 8],
        ['raquette', 'Stage badminton', "Une journée pour progresser : échauffement, technique de frappe, puis matchs en double.", 'badminton', 'stage', 8, '10:00', 360, 16, 25, 7],
        ['tennis', 'Tournoi double mixte', "Tournoi en poules puis tableau final. Ouvert aux classés comme aux non-classés.", 'tennis', 'tournoi', 10, '09:30', 420, 24, 12, 14],
        ['raquette', 'Ladder squash', "Classement permanent : défiez le joueur juste au-dessus de vous, un match par soir.", 'squash', 'match', 6, '18:30', 150, 12, 6, 5],
    ];
    foreach ($evSport as $n => [$cle, $titre, $description, $sport, $format, $j, $heure, $duree, $quota, $prix, $inscrits]) {
        $c = $club[$cle];
        $debut = $jour($j, $heure);
        $insertEvSport->execute([
            $c['id'], $titre, $description, $sport, $format, $debut, $plus($debut, $duree), $quota, $prix, $prix == 0 ? 1 : 0,
            $c['nom'] . ', ' . $c['adresse'], $maintenant->modify('-' . (3 + $n) . ' days')->format('Y-m-d H:i:s'),
        ]);
        $evId = (int) $pdo->lastInsertId();
        $vus = [];
        for ($k = 0; count($vus) < min($inscrits, $quota, $nbEtudiants) && $k < 200; $k++) {
            $uid = $participant($n + 40, $k);
            if (isset($vus[$uid])) {
                $uid = $etudiants[($k + $n) % $nbEtudiants];
                if (isset($vus[$uid])) {
                    continue;
                }
            }
            $vus[$uid] = true;
            $lignesInscriptions[] = [$uid, $evId, $qr(), 'inscrit', null];
            $nbInscriptions++;
        }
    }

    $insertSquad = $pdo->prepare(
        "INSERT INTO squads (createur_id, type, cree_par_association, visibilite, titre, description, niveau, date_heure, lieu, quota)
         VALUES (?, ?, 1, 'public', ?, ?, ?, ?, ?, ?)"
    );
    $inserer('inscriptions', $colsInscription, $lignesInscriptions);
    $lignesMembres = [];
    // sport, titre, description, niveau, jour, heure, lieu, quota, membres
    $sorties = [
        ['running', 'Footing au Puy de Dôme', "Montée par le chemin des Muletiers, 9 km et 500 m de dénivelé, à allure de conversation.", 'tous', 2, '09:00', 'Col de Ceyssat, Orcines', 15, 9],
        ['velo', 'Sortie vélo vers Volvic', "60 km aller-retour, pause café à Volvic. Casque obligatoire.", 'inter', 3, '10:00', 'Place de Jaude, Clermont-Ferrand', 10, 5],
        ['basket', 'Basket 3x3 aux Cézeaux', "On fait les équipes sur place, tous les niveaux sont les bienvenus.", 'tous', 1, '17:30', 'Playground des Cézeaux, Aubière', 12, 10],
        ['yoga', 'Yoga au jardin Lecoq', "Une heure de vinyasa doux près du bassin. Apportez votre tapis.", 'debutant', 4, '08:30', 'Jardin Lecoq, Clermont-Ferrand', 20, 7],
        ['running', 'Run nocturne', "8 km autour de la place de Jaude et du jardin Lecoq, frontale conseillée.", 'inter', 5, '20:30', 'Place de Jaude, Clermont-Ferrand', 25, 13],
        ['autre', 'Randonnée au lac Pavin', "Tour du lac puis montée au puy de Montchal. Covoiturage depuis le centre.", 'tous', 6, '08:00', 'Lac Pavin, Besse-et-Saint-Anastaise', 12, 12],
        ['muscu', 'Séance jambes à deux', "Je cherche un binôme pour une séance squat et presse, niveau confirmé.", 'avance', 2, '18:00', 'Salle Basic-Fit, Clermont-Ferrand', 2, 1],
    ];
    foreach ($sorties as $n => [$sport, $titre, $description, $niveau, $j, $heure, $lieu, $quota, $membres]) {
        $createur = $participant($n + 70, 0);
        $insertSquad->execute([$createur, $sport, $titre, $description, $niveau, $jour($j, $heure), $lieu, $quota]);
        $squadId = (int) $pdo->lastInsertId();
        $vus = [];
        for ($k = 0; count($vus) < min($membres, $quota) && $k < 100; $k++) {
            $uid = $k === 0 ? $createur : $participant($n + 90, $k);
            if (isset($vus[$uid])) {
                continue;
            }
            $vus[$uid] = true;
            $lignesMembres[] = [$squadId, $uid];
        }
    }

    $inserer('squad_membres', ['squad_id', 'user_id'], $lignesMembres);
    $pdo->commit();
    echo "  Sport   : validé
";
    $pdo->beginTransaction();

    // ══ CULTURE ══════════════════════════════════════════════════════════
    // L'espace Culture est saisi par l'équipe : un compte « organisateur »
    // fictif sert seulement à retrouver ces événements au nettoyage.
    $insertGerant->execute(['Culture', 'Équipe', 'culture' . DOMAINE_DEMO, $motDePasse, '1990-01-01']);
    $equipeCulture = (int) $pdo->lastInsertId();
    $insertCulture = $pdo->prepare(
        'INSERT INTO culture_evenements (titre, description, categorie, organisateur, lieu, ville, date_heure, date_fin, quota, prix, mis_en_avant, cree_par)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $lignesCulture = [];
    // titre, description, catégorie, organisateur, lieu, ville, début, fin, quota, prix, à la une, inscrits
    $culture = [
        ['Basalte, matière du volcan', "Photographies grand format des coulées de la chaîne des Puys, prises au drone et au microscope.", 'expo', 'Galerie Pierre-Noire', 'Galerie Pierre-Noire, 7 rue des Gras', 'Clermont-Ferrand', $jour(-6, '10:00'), $jour(24, '19:00'), 0, 0, 1, 0],
        ['Concert au kiosque', "L'harmonie des étudiants joue des musiques de film, de Morricone à Hans Zimmer.", 'concert', 'Harmonie universitaire', 'Kiosque du jardin Lecoq', 'Clermont-Ferrand', $jour(2, '17:00'), null, 0, 0, 0, 0],
        ['Atelier sérigraphie', "Imprimez votre tote bag en trois couleurs, encres et supports fournis. Places limitées.", 'atelier', 'Atelier L\'Encrier', 'Atelier L\'Encrier, 22 rue du Port', 'Clermont-Ferrand', $jour(3, '14:00'), $jour(3, '17:30'), 14, 5, 0, 14],
        ['Ciné-club : Les Parapluies de Cherbourg', "Projection en version restaurée, suivie d'une discussion animée par des étudiants en cinéma.", 'cinema', 'Ciné-club Étoile Noire', 'Salle des Arcades, 3 rue Blatin', 'Clermont-Ferrand', $jour(1, '20:00'), $jour(1, '22:30'), 90, 3, 0, 31],
        ['Impro : le match', "Deux équipes, un arbitre et vos thèmes tirés au sort. Le public vote à chaque manche.", 'spectacle', 'Compagnie des Quatre Vents', 'Théâtre de la Cour, 11 rue Gaultier-de-Biauzat', 'Clermont-Ferrand', $jour(4, '20:30'), null, 120, 8, 1, 46],
        ['Conférence : le réveil des volcans d\'Auvergne', "Un volcanologue fait le point sur l'activité de la chaîne des Puys et sur ce que l'on sait vraiment.", 'conference', 'Société des Amis des Dômes', 'Amphithéâtre Rabelais, Aubière', 'Aubière', $jour(5, '18:00'), $jour(5, '19:45'), 250, 0, 0, 58],
        ['Nuit du patrimoine étudiant', "Visites guidées de nuit de la cathédrale et des ruelles du vieux Clermont, départ toutes les 30 minutes.", 'patrimoine', 'Association Clermont Médiéval', 'Parvis de la cathédrale', 'Clermont-Ferrand', $jour(6, '20:00'), $jour(6, '23:30'), 200, 0, 1, 77],
        ['Jazz à la cave', "Trio piano-contrebasse-batterie, standards et compositions. Le bar reste ouvert pendant le concert.", 'concert', 'Jazz en Dômes', 'La Cave des Carmes, 5 place des Carmes', 'Clermont-Ferrand', $jour(7, '21:00'), null, 50, 10, 0, 22],
        ['Festival des courts étudiants', "Trois soirs de courts métrages réalisés par des étudiants de toute la région, prix du public le dernier soir.", 'festival', 'Collectif Plan Large', 'Salle des Arcades, 3 rue Blatin', 'Clermont-Ferrand', $jour(9, '19:00'), $jour(11, '23:00'), 0, 6, 1, 0],
        ['Cinéma en plein air', "Projection d'un classique choisi par vote, transats et plaids fournis. Annulé en cas de pluie.", 'cinema', 'Collectif Plan Large', 'Place de la Victoire', 'Clermont-Ferrand', $jour(8, '21:30'), null, 150, 0, 0, 39],
        ['Atelier d\'écriture', "Deux heures pour écrire une nouvelle à partir d'une photo tirée au sort, en petit groupe bienveillant.", 'atelier', 'Les Mots Volcaniques', 'Médiathèque du Jardin, 2 rue Bergier', 'Clermont-Ferrand', $jour(10, '18:00'), $jour(10, '20:00'), 12, 0, 0, 5],
        ['Exposition : affiches de concerts 1970-2000', "Plus de deux cents affiches originales des salles clermontoises, prêtées par des collectionneurs.", 'expo', 'Association Mémoire Sonore', 'Hall des Gras, 18 rue des Gras', 'Clermont-Ferrand', $jour(-12, '11:00'), $jour(16, '19:00'), 0, 0, 0, 0],
        ['Théâtre d\'ombres', "Un conte pour adultes en silhouettes découpées et musique jouée en direct.", 'spectacle', 'Compagnie Fil d\'Ariane', 'Théâtre de la Cour, 11 rue Gaultier-de-Biauzat', 'Clermont-Ferrand', $jour(12, '19:30'), null, 70, 10, 0, 12],
        ['Masterclass photo de nuit', "Réglages, pose longue et retouche : on sort ensemble photographier la ville la nuit. Appareil indispensable.", 'atelier', 'Club photo Pierre-Noire', 'Galerie Pierre-Noire, 7 rue des Gras', 'Clermont-Ferrand', $jour(13, '20:00'), $jour(13, '23:00'), 10, 15, 0, 10],
        ['Rencontre : écrire la montagne', "Deux autrices auvergnates parlent de leurs romans et de la place des paysages dans l'écriture.", 'conference', 'Les Mots Volcaniques', 'Médiathèque du Jardin, 2 rue Bergier', 'Clermont-Ferrand', $jour(14, '18:30'), null, 60, 0, 0, 8],
    ];
    foreach ($culture as $n => [$titre, $description, $cat, $orga, $lieu, $ville, $debut, $fin, $quota, $prix, $une, $inscrits]) {
        $insertCulture->execute([$titre, $description, $cat, $orga, $lieu, $ville, $debut, $fin, $quota, $prix, $une, $equipeCulture]);
        $evId = (int) $pdo->lastInsertId();
        $nb = $quota > 0 ? min($inscrits, $quota, $nbEtudiants) : min($inscrits, $nbEtudiants);
        for ($k = 0; $k < $nb; $k++) {
            $lignesCulture[] = [$participant($n + 120, $k), $evId];
        }
    }
    $inserer('culture_inscriptions', ['user_id', 'evenement_id'], $lignesCulture);

    $pdo->commit();
    echo "  Culture : validée\n\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Échec : {$e->getMessage()} (ligne {$e->getLine()})\n");
    // La connexion a pu tomber : on en ouvre une neuve pour retirer ce
    // qu'une étape précédente a déjà validé.
    try {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Throwable) {
        // Connexion perdue : la transaction en cours est annulée d'office.
    }
    try {
        nettoyerDemo($connecter());
        fwrite(STDERR, "Ce qui avait déjà été écrit a été retiré : la base est comme avant. Relancer le semis.\n");
    } catch (Throwable $e2) {
        fwrite(STDERR, "Le retrait a échoué aussi ({$e2->getMessage()}) : lancer php outils/semer_demo.php --nettoyer\n");
    }
    exit(1);
}

// Fin effective (migration v41) : la colonne indexée du fil, que le code
// tient à jour à chaque écriture. Les semis écrivent en direct : on la pose.
require_once __DIR__ . '/../includes/evenements_temps.php';
evenementsMajFinEffectiveManquantes($pdo);

printf(
    "Fait :\n"
    . "  Soirées : %d lieux, %d soirées (une en cours, une flash, deux sponsorisées, des gratuites et des complètes)\n"
    . "  Sport   : %d clubs, %d terrains, %d créneaux libres, %d parties ouvertes, %d complètes, %d événements, %d sorties\n"
    . "  Culture : %d événements\n"
    . "  %d étudiants fictifs, %d inscriptions\n\n",
    count($lieux), count($soirees),
    count($clubs), count($terrains), $nbCreneaux['libre'], $nbCreneaux['ouverte'], $nbCreneaux['pleine'], count($evSport), count($sorties),
    count($culture),
    $nbEtudiants, $nbInscriptions
);
echo "Les dates suivent le moment du semis. Pour les recaler : php outils/semer_demo.php --rafraichir\n";
echo "Pour tout retirer avant le lancement : php outils/semer_demo.php --nettoyer\n";
