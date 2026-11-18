<?php
/**
 * Semis de test de l'onglet Sport : des clubs dans plusieurs villes, leurs
 * terrains et créneaux, des parties à compléter, des événements de club et
 * des sorties proposées par des étudiants.
 *
 * Ce que l'onglet Sport affiche, et ce que ce script remplit :
 *   - terrains libres : créneaux « libre » des clubs abonnés (crm_clients) ;
 *   - parties à compléter : créneaux réservés, ouverts, pas encore pleins ;
 *   - événements de club : evenements de type « sport » (tournoi, stage…) ;
 *   - sorties d'étudiants : squads publiques (association) d'un sport.
 *
 * ATTENTION : avec les variables de production, ceci écrit dans la vraie
 * base, et tout est visible des vrais utilisateurs jusqu'au nettoyage. Tout
 * est repérable :
 *   - clubs dont le nom commence par « [TEST] » (fiche CRM comprise) ;
 *   - comptes en test-sport…@outsee.invalid (un partenaire, des étudiants).
 * Aucun vrai compte n'est inscrit ni ajouté à une partie.
 *
 * Usage (à la racine du projet) :
 *   php outils/semer_sport_test.php             # sème
 *   php outils/semer_sport_test.php --nettoyer  # retire tout
 * Variables : MYSQLHOST, MYSQLPORT, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE.
 *
 * Les horaires partent de maintenant (heure de Paris) : --nettoyer puis un
 * nouveau semis remettent tout à venir.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('Europe/Paris');

const PREFIXE    = '[TEST] ';
const DOMAINE    = '@outsee.invalid';
const PARTENAIRE = 'test-sport' . DOMAINE;
const ETUDIANTS  = 'test-sport-etudiant-%';

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

/** Les clubs de test : ceux du partenaire de test, nommés « [TEST] ». */
$clubsDeTest = static function (PDO $pdo): array {
    $stmt = $pdo->prepare(
        "SELECT e.id FROM etablissements e JOIN users u ON u.id = e.user_id
          WHERE u.email = ? AND e.nom LIKE ?"
    );
    $stmt->execute([PARTENAIRE, PREFIXE . '%']);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
};

// ── Nettoyage ─────────────────────────────────────────────────────────────
if (in_array('--nettoyer', $argv, true)) {
    $pdo->beginTransaction();
    $ids = $clubsDeTest($pdo);
    $crm = 0;
    if ($ids) {
        $trous = implode(',', array_fill(0, count($ids), '?'));
        // La fiche CRM survivrait au club (ON DELETE SET NULL) : on la retire.
        $s = $pdo->prepare("DELETE FROM crm_clients WHERE etablissement_id IN ($trous)");
        $s->execute($ids);
        $crm = $s->rowCount();
    }
    // Le partenaire emporte ses clubs, qui emportent terrains, créneaux et
    // événements ; les étudiants emportent leurs sorties et leurs places.
    $comptes = $pdo->prepare('DELETE FROM users WHERE email = ? OR email LIKE ?');
    $comptes->execute([PARTENAIRE, ETUDIANTS . DOMAINE]);
    $pdo->commit();
    printf("Retiré : %d club(s) et tout leur contenu, %d fiche(s) CRM, %d compte(s) de test.\n", count($ids), $crm, $comptes->rowCount());
    exit(0);
}

$deja = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
$deja->execute([PARTENAIRE]);
if ((int) $deja->fetchColumn() > 0) {
    fwrite(STDERR, "Le semis Sport est déjà en base. Lancer d'abord : php outils/semer_sport_test.php --nettoyer\n");
    exit(1);
}

$maintenant = new DateTimeImmutable('now');
/** Tel jour à venir, à telle heure : $jour(1, '18:30') = demain 18 h 30. */
$jour = static fn (int $jours, string $heure): string => $maintenant->modify("+$jours days")->format('Y-m-d') . " $heure:00";
/** Ajoute des minutes à un horaire. */
$plus = static fn (string $debut, int $minutes): string => (new DateTimeImmutable($debut))->modify("+$minutes minutes")->format('Y-m-d H:i:s');

$pdo->beginTransaction();

try {
    $motDePasse = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    // ── Comptes ───────────────────────────────────────────────────────────
    $pdo->prepare(
        "INSERT INTO users (nom, prenom, email, password, type, date_naissance, cgu_acceptees_le)
         VALUES ('Sport', 'Test', ?, ?, 'partenaire', '1988-06-01', NOW())"
    )->execute([PARTENAIRE, $motDePasse]);
    $partenaireId = (int) $pdo->lastInsertId();

    $prenoms = ['Maxime', 'Clara', 'Julien', 'Anaïs', 'Bastien', 'Margaux', 'Kylian', 'Romane', 'Mehdi', 'Lou', 'Arthur', 'Juliette'];
    $etudiants = [];
    $insertEtudiant = $pdo->prepare(
        "INSERT INTO users (nom, prenom, email, password, ecole, promo, ville, type, date_naissance, cgu_acceptees_le)
         VALUES ('Test', ?, ?, ?, 'UCA', 'L3', 'Clermont-Ferrand', 'etudiant', '2003-09-10', NOW())"
    );
    foreach ($prenoms as $i => $prenom) {
        $insertEtudiant->execute([$prenom, sprintf('test-sport-etudiant-%02d', $i + 1) . DOMAINE, $motDePasse]);
        $etudiants[] = (int) $pdo->lastInsertId();
    }

    // ── Clubs, un peu partout ─────────────────────────────────────────────
    // nom, ville, adresse, lat, lon, terrains [nom, sport, joueurs, durée, prix]
    $clubs = [
        'padel'    => ['Padel Club des Volcans', 'Clermont-Ferrand', '14 rue de l\'Oradou', 45.7700, 3.1050, [
            ['Piste 1', 'padel', 4, 90, 36], ['Piste 2', 'padel', 4, 90, 36], ['Court central', 'tennis', 2, 60, 18],
        ]],
        'foot'     => ['Urban Five Aubière', 'Aubière', '2 rue Blaise-Pascal', 45.7520, 3.1110, [
            ['Terrain A', 'foot5', 10, 60, 80], ['Terrain B', 'foot5', 10, 60, 80],
        ]],
        'escalade' => ['Block\'Out Clermont', 'Clermont-Ferrand', '45 rue Kessler', 45.7850, 3.0880, [
            ['Salle de bloc', 'escalade', 6, 120, 14],
        ]],
        'squash'   => ['Squash Arena Riom', 'Riom', '8 avenue de Clermont', 45.8890, 3.1130, [
            ['Court 1', 'squash', 2, 45, 12], ['Salle badminton', 'badminton', 4, 60, 16],
        ]],
        'golf'     => ['Golf des Dômes', 'Orcines', 'Route du Puy de Dôme', 45.7830, 3.0100, [
            ['Practice', 'golf', 4, 60, 10],
        ]],
        'tennis'   => ['Tennis Club d\'Issoire', 'Issoire', '1 allée des Sports', 45.5440, 3.2490, [
            ['Court terre battue', 'tennis', 2, 60, 15], ['Court couvert', 'tennis', 4, 90, 22],
        ]],
        'lyon'     => ['Padel Factory Lyon', 'Lyon', '120 rue de Gerland', 45.7300, 4.8320, [
            ['Piste panoramique', 'padel', 4, 90, 44],
        ]],
    ];

    $insertEtab = $pdo->prepare(
        "INSERT INTO etablissements (user_id, nom, type, adresse, ville, latitude, longitude)
         VALUES (?, ?, 'club', ?, ?, ?, ?)"
    );
    $insertSportEtab = $pdo->prepare('INSERT IGNORE INTO etablissement_sports (etablissement_id, sport) VALUES (?, ?)');
    // Abonnement « club » actif : sans lui, les créneaux n'apparaissent pas.
    $insertCrm = $pdo->prepare(
        "INSERT INTO crm_clients (etablissement_id, nom, categorie, ville, adresse, statut, offre, mrr)
         VALUES (?, ?, 'club', ?, ?, 'actif', 'club', 0)"
    );
    $insertTerrain = $pdo->prepare(
        'INSERT INTO terrains (etablissement_id, nom, sport, joueurs, duree_min, prix) VALUES (?, ?, ?, ?, ?, ?)'
    );

    $terrains = [];  // liste de [id, club, sport, joueurs, durée, prix, nom, ville]
    $etab     = [];
    foreach ($clubs as $cle => [$nom, $ville, $adresse, $lat, $lon, $liste]) {
        $insertEtab->execute([$partenaireId, PREFIXE . $nom, $adresse, $ville, $lat, $lon]);
        $id = (int) $pdo->lastInsertId();
        $etab[$cle] = ['id' => $id, 'nom' => $nom, 'ville' => $ville, 'adresse' => $adresse];
        $insertCrm->execute([$id, PREFIXE . $nom, $ville, $adresse]);
        foreach ($liste as [$tNom, $sport, $joueurs, $duree, $prix]) {
            $insertSportEtab->execute([$id, $sport]);
            $insertTerrain->execute([$id, $tNom, $sport, $joueurs, $duree, $prix]);
            $terrains[] = ['id' => (int) $pdo->lastInsertId(), 'club' => $nom, 'sport' => $sport, 'joueurs' => $joueurs,
                           'duree' => $duree, 'prix' => $prix, 'nom' => $tNom, 'ville' => $ville];
        }
    }

    // ── Créneaux : libres, parties à compléter, parties pleines ───────────
    $insertCreneau = $pdo->prepare(
        'INSERT INTO creneaux (terrain_id, debut, fin, prix, joueurs, statut, organisateur_id, ouvert, niveau, reserve_le)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertJoueur = $pdo->prepare('INSERT INTO creneau_joueurs (creneau_id, user_id, invite_par) VALUES (?, ?, ?)');

    $heures  = ['10:00', '12:30', '18:00', '19:30', '21:00'];
    $niveaux = ['tous', 'debutant', 'inter', 'avance'];
    $nb      = ['libre' => 0, 'a_completer' => 0, 'pleine' => 0];
    $tour    = 0;
    foreach ($terrains as $t) {
        for ($j = 1; $j <= 6; $j++) {
            foreach ($heures as $h => $heure) {
                // Un créneau sur deux, décalé d'un terrain à l'autre : des
                // trous réalistes plutôt qu'un planning plein.
                if (($j + $h + $t['id']) % 2 === 1) {
                    continue;
                }
                $debut = $jour($j, $heure);
                $fin   = $plus($debut, $t['duree']);
                $tour++;

                // Un créneau sur cinq est déjà réservé par un étudiant de test.
                if ($tour % 5 !== 0) {
                    $insertCreneau->execute([$t['id'], $debut, $fin, $t['prix'], $t['joueurs'], 'libre', null, 0, 'tous', null]);
                    $nb['libre']++;
                    continue;
                }

                $orga   = $etudiants[$tour % count($etudiants)];
                $pleine = $tour % 15 === 0;
                $ouvert = $t['joueurs'] > 2 || $pleine ? 1 : 0;
                $insertCreneau->execute([
                    $t['id'], $debut, $fin, $t['prix'], $t['joueurs'], 'reserve', $orga,
                    $ouvert, $niveaux[$tour % 4], $maintenant->format('Y-m-d H:i:s'),
                ]);
                $creneauId = (int) $pdo->lastInsertId();

                // L'organisateur, puis des amis : complet, ou une à trois places libres.
                $places = $pleine ? $t['joueurs'] : max(1, $t['joueurs'] - 1 - ($tour % 3));
                $insertJoueur->execute([$creneauId, $orga, null]);
                for ($k = 1; $k < $places; $k++) {
                    $insertJoueur->execute([$creneauId, $etudiants[($tour + $k) % count($etudiants)], $orga]);
                }
                $nb[$pleine ? 'pleine' : 'a_completer']++;
            }
        }
    }

    // ── Événements de club ────────────────────────────────────────────────
    $insertEvenement = $pdo->prepare(
        "INSERT INTO evenements (etablissement_id, titre, description, type, sport, format, date_heure, date_fin,
                                 quota, reduction, prix_normal, is_gratuit, lieu)
         VALUES (?, ?, ?, 'sport', ?, ?, ?, ?, ?, 0, ?, ?, ?)"
    );
    $insertInscription = $pdo->prepare(
        "INSERT INTO inscriptions (user_id, evenement_id, qr_code, statut) VALUES (?, ?, ?, 'inscrit')"
    );
    // club, titre, sport, format, jour, heure, durée (min), quota, prix, inscrits
    $evenements = [
        ['padel',    'Tournoi padel étudiant',          'padel',     'tournoi',    5,  '09:00', 480, 32, 15, 18],
        ['padel',    'Initiation padel gratuite',       'padel',     'initiation', 2,  '14:00', 120, 12, 0,  12],
        ['foot',     'Five du jeudi soir',              'foot5',     'match',      4,  '20:00', 90,  20, 5,  7],
        ['escalade', 'Soirée bloc & DJ',                'escalade',  'soiree',     3,  '19:00', 240, 60, 10, 23],
        ['squash',   'Stage badminton débutant',        'badminton', 'stage',      8,  '10:00', 360, 16, 25, 4],
        ['golf',     'Initiation golf au practice',     'golf',      'initiation', 6,  '15:00', 120, 10, 0,  2],
        ['tennis',   'Tournoi double mixte',            'tennis',    'tournoi',    10, '09:30', 420, 24, 12, 9],
        ['lyon',     'Padel afterwork',                 'padel',     'cours',      7,  '18:30', 120, 16, 20, 0],
        ['escalade', 'Cours collectif technique',       'escalade',  'cours',      1,  '18:00', 90,  8,  12, 8],
    ];
    foreach ($evenements as $n => [$cle, $titre, $sport, $format, $j, $heure, $duree, $quota, $prix, $inscrits]) {
        $debut = $jour($j, $heure);
        $e     = $etab[$cle];
        $insertEvenement->execute([
            $e['id'], PREFIXE . $titre, "Événement de test ($sport, {$e['ville']}).", $sport, $format,
            $debut, $plus($debut, $duree), $quota, $prix, $prix == 0 ? 1 : 0, $e['nom'] . ', ' . $e['adresse'],
        ]);
        $evId = (int) $pdo->lastInsertId();
        for ($k = 0; $k < min($inscrits, $quota, count($etudiants)); $k++) {
            $insertInscription->execute([$etudiants[($k + $n) % count($etudiants)], $evId, bin2hex(random_bytes(32))]);
        }
    }

    // ── Sorties d'étudiants (publiques : proposées par une association) ──
    $insertSquad = $pdo->prepare(
        "INSERT INTO squads (createur_id, type, cree_par_association, visibilite, titre, description, niveau, date_heure, lieu, quota)
         VALUES (?, ?, 1, 'public', ?, ?, ?, ?, ?, ?)"
    );
    $insertMembre = $pdo->prepare('INSERT INTO squad_membres (squad_id, user_id) VALUES (?, ?)');
    // sport, titre, niveau, jour, heure, lieu, quota, membres
    $sorties = [
        ['running',  'Footing du dimanche au Puy de Dôme', 'tous',     2, '09:00', 'Col de Ceyssat, Orcines',        15, 6],
        ['velo',     'Sortie vélo vers Volvic',            'inter',    3, '10:00', 'Place de Jaude, Clermont-Ferrand', 10, 4],
        ['basket',   'Basket 3x3 aux Cézeaux',             'tous',     1, '17:30', 'Playground des Cézeaux, Aubière', 12, 11],
        ['yoga',     'Yoga au jardin Lecoq',               'debutant', 4, '08:30', 'Jardin Lecoq, Clermont-Ferrand',  20, 3],
        ['muscu',    'Séance muscu à deux',                'avance',   2, '19:00', 'Salle Fitness Park, Riom',         4, 2],
        ['running',  'Run nocturne sur les quais',         'inter',    6, '20:30', 'Quai Saint-Antoine, Lyon',         25, 9],
        ['autre',    'Randonnée au lac Pavin',             'tous',     9, '08:00', 'Lac Pavin, Besse',                 12, 12],
    ];
    foreach ($sorties as $n => [$sport, $titre, $niveau, $j, $heure, $lieu, $quota, $membres]) {
        $createur = $etudiants[$n % count($etudiants)];
        $insertSquad->execute([$createur, $sport, PREFIXE . $titre, 'Sortie de test.', $niveau, $jour($j, $heure), $lieu, $quota]);
        $squadId = (int) $pdo->lastInsertId();
        for ($k = 0; $k < min($membres, $quota, count($etudiants)); $k++) {
            $insertMembre->execute([$squadId, $etudiants[($n + $k) % count($etudiants)]]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Échec, rien n'a été écrit : {$e->getMessage()}\n");
    exit(1);
}

printf(
    "Fait : %d clubs, %d terrains, %d créneaux libres, %d parties à compléter, %d parties pleines,\n"
    . "      %d événements de club, %d sorties d'étudiants, %d étudiants fictifs.\n",
    count($clubs), count($terrains), $nb['libre'], $nb['a_completer'], $nb['pleine'],
    count($evenements), count($sorties), count($etudiants)
);
echo "Pour tout retirer : php outils/semer_sport_test.php --nettoyer\n";
