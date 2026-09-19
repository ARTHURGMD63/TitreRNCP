<?php
/**
 * Génère un jeu de données de volume réaliste, pour mesurer.
 *
 * Six événements de démonstration ne disent rien de la tenue en charge : à ce
 * volume, une requête qui balaie toute une table et une requête qui lit un
 * index répondent toutes les deux en une milliseconde. La différence
 * n'apparaît qu'avec des milliers de lignes — c'est-à-dire exactement au
 * moment où il est trop tard pour s'en apercevoir.
 *
 * Ce script remplit une base de test avec des ordres de grandeur plausibles
 * pour une ville comme Clermont-Ferrand après une année d'exploitation.
 *
 * À N'UTILISER QUE SUR UNE BASE JETABLE. Il écrit des dizaines de milliers de
 * lignes et n'a aucun moyen de les distinguer des vraies ensuite.
 *
 * Exemple :
 *     MYSQLDATABASE=studentlink_perf MYSQLPORT=3307 php outils/semer_charge.php
 *
 * Options :
 *     --etudiants=N     défaut 5000
 *     --evenements=N    défaut 1500
 *     --inscriptions=N  défaut 40000
 *     --avis=N          défaut 4000
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$opt = static function (string $nom, int $defaut) use ($argv): int {
    foreach ($argv as $a) {
        if (str_starts_with($a, "--$nom=")) {
            return max(0, (int) substr($a, strlen($nom) + 3));
        }
    }

    return $defaut;
};

$nbEtudiants    = $opt('etudiants', 5000);
$nbEvenements   = $opt('evenements', 1500);
$nbInscriptions = $opt('inscriptions', 40000);
$nbAvis         = $opt('avis', 4000);

// Garde-fou : ce script ne doit jamais tourner sur la base de production.
if (!in_array(strtolower((string) getenv('APP_ENV')), ['', 'dev', 'local', 'test'], true)) {
    fwrite(STDERR, "Refus : APP_ENV indique un environnement qui n'est pas de test.\n");
    exit(1);
}

printf("Base : %s@%s:%s\n", DB_NAME, DB_HOST, DB_PORT);
printf("Cible : %d étudiants, %d événements, %d inscriptions, %d avis\n\n",
    $nbEtudiants, $nbEvenements, $nbInscriptions, $nbAvis);

$chrono = microtime(true);

/*
 * Les insertions passent par paquets, dans une transaction.
 *
 * Ligne à ligne, InnoDB écrit et synchronise son journal à chaque COMMIT :
 * 40 000 inscriptions prendraient plusieurs minutes. Par paquets de 500 dans
 * une transaction unique, c'est quelques secondes. C'est aussi, accessoirement,
 * la démonstration de ce que coûte une écriture non groupée.
 */
$parPaquet = 500;

$inserer = static function (PDO $pdo, string $table, array $colonnes, iterable $lignes, int $parPaquet): int {
    $trous  = '(' . implode(',', array_fill(0, count($colonnes), '?')) . ')';
    $entete = "INSERT IGNORE INTO $table (" . implode(',', $colonnes) . ') VALUES ';

    // Annotes explicitement : ces trois variables sont capturees par
    // reference, et l'analyse statique les figerait au type vide observe ici.
    /** @var list<string> $tampon */
    $tampon = [];
    /** @var list<mixed> $valeurs */
    $valeurs = [];
    $total = 0;

    $vider = static function () use ($pdo, $entete, &$tampon, &$valeurs, &$total): void {
        if (!$tampon) {
            return;
        }
        $stmt = $pdo->prepare($entete . implode(',', $tampon));
        $stmt->execute($valeurs);
        $total += $stmt->rowCount();
        $tampon = [];
        $valeurs = [];
    };

    $pdo->beginTransaction();
    foreach ($lignes as $ligne) {
        $tampon[] = $trous;
        foreach ($ligne as $v) {
            $valeurs[] = $v;
        }
        if (count($tampon) >= $parPaquet) {
            $vider();
        }
    }
    $vider();
    $pdo->commit();

    return $total;
};

// ─── Étudiants ──────────────────────────────────────────────────────────────
$ecoles   = ['UCA', 'Polytech', 'ESC', 'SIGMA', 'VetAgro', 'IUT', 'ISIMA', 'ENSACF'];
$interets = ['techno', 'rock', 'sport', 'cinema', 'jeux', 'cuisine', 'voyage', 'musique'];
$hash     = password_hash('charge', PASSWORD_DEFAULT);

$etudiants = (static function () use ($nbEtudiants, $ecoles, $interets, $hash): \Generator {
    for ($i = 0; $i < $nbEtudiants; $i++) {
        $gouts = [];
        for ($k = 0, $n = random_int(1, 4); $k < $n; $k++) {
            $gouts[] = $interets[array_rand($interets)];
        }
        yield [
            'Charge' . $i,
            'Test' . $i,
            "charge$i@exemple.test",
            $hash,
            $ecoles[array_rand($ecoles)],
            (string) random_int(2023, 2027),
            'etudiant',
            implode(',', array_unique($gouts)),
        ];
    }
})();

$n = $inserer($pdo, 'users',
    ['nom', 'prenom', 'email', 'password', 'ecole', 'promo', 'type', 'interests'],
    $etudiants, $parPaquet);
printf("  users            %6d lignes\n", $n);

$idsEtudiants = $pdo->query("SELECT id FROM users WHERE type='etudiant'")->fetchAll(PDO::FETCH_COLUMN);
$idsEtab      = $pdo->query('SELECT id FROM etablissements')->fetchAll(PDO::FETCH_COLUMN);

if (!$idsEtab) {
    fwrite(STDERR, "Aucun établissement : importer db_setup.sql d'abord.\n");
    exit(1);
}

// ─── Événements ─────────────────────────────────────────────────────────────
$types   = ['bar', 'boite', 'resto', 'afterwork'];
$styles  = ['techno', 'house', 'rap', 'rock', 'latino', null];

$evenements = (static function () use ($nbEvenements, $idsEtab, $types, $styles): \Generator {
    for ($i = 0; $i < $nbEvenements; $i++) {
        // Moitié passés, moitié à venir : le fil ne montre que l'avenir, mais
        // les avis et les statistiques vivent dans le passé.
        $jours = random_int(-180, 120);
        yield [
            $idsEtab[array_rand($idsEtab)],
            "Soirée de charge #$i",
            'Jeu de données de test pour la mesure de montée en charge.',
            $types[array_rand($types)],
            $styles[array_rand($styles)],
            date('Y-m-d H:i:s', strtotime("$jours days " . random_int(18, 23) . ':00')),
            random_int(30, 400),
            random_int(0, 40),
            random_int(0, 25),
        ];
    }
})();

$n = $inserer($pdo, 'evenements',
    ['etablissement_id', 'titre', 'description', 'type', 'style_musique',
     'date_heure', 'quota', 'reduction', 'prix_normal'],
    $evenements, $parPaquet);
printf("  evenements       %6d lignes\n", $n);

$idsEv = $pdo->query('SELECT id FROM evenements')->fetchAll(PDO::FETCH_COLUMN);

// ─── Inscriptions ───────────────────────────────────────────────────────────
$inscriptions = (static function () use ($nbInscriptions, $idsEtudiants, $idsEv): \Generator {
    for ($i = 0; $i < $nbInscriptions; $i++) {
        yield [
            $idsEtudiants[array_rand($idsEtudiants)],
            $idsEv[array_rand($idsEv)],
            bin2hex(random_bytes(16)),
            random_int(1, 10) === 1 ? 'checkin' : 'inscrit',
        ];
    }
})();

// INSERT IGNORE : la clé unique (user_id, evenement_id) rejette les doublons
// que le tirage aléatoire produit forcément. C'est voulu, pas une erreur.
$n = $inserer($pdo, 'inscriptions',
    ['user_id', 'evenement_id', 'qr_code', 'statut'],
    $inscriptions, $parPaquet);
printf("  inscriptions     %6d lignes\n", $n);

// ─── Avis ───────────────────────────────────────────────────────────────────
$avis = (static function () use ($nbAvis, $idsEtudiants, $idsEv): \Generator {
    for ($i = 0; $i < $nbAvis; $i++) {
        yield [
            $idsEtudiants[array_rand($idsEtudiants)],
            $idsEv[array_rand($idsEv)],
            random_int(3, 5),
            'Avis de test.',
        ];
    }
})();

$n = $inserer($pdo, 'avis', ['user_id', 'evenement_id', 'note', 'commentaire'], $avis, $parPaquet);
printf("  avis             %6d lignes\n", $n);

// ─── Abonnements entre étudiants ────────────────────────────────────────────
// Sans eux, le fil social et les notifications restent vides, et l'on
// mesurerait des requêtes qui ne trouvent jamais rien — le cas le plus
// favorable, donc le moins instructif.
$follows = (static function () use ($idsEtudiants): \Generator {
    foreach (array_slice($idsEtudiants, 0, 2000) as $moi) {
        for ($k = 0, $n = random_int(0, 12); $k < $n; $k++) {
            $autre = $idsEtudiants[array_rand($idsEtudiants)];
            if ($autre !== $moi) {
                yield [$moi, $autre, 'accepted'];
            }
        }
    }
})();

$n = $inserer($pdo, 'follows_users', ['follower_id', 'followed_id', 'statut'], $follows, $parPaquet);
printf("  follows_users    %6d lignes\n", $n);

printf("\nTerminé en %.1f s.\n", microtime(true) - $chrono);
echo "Penser à vider le cache applicatif : php -r \"require 'includes/cache.php'; cacheVider();\"\n";
