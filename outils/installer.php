<?php
/**
 * outils/installer.php — installe le schéma sur la base configurée.
 *
 * Usage :
 *
 *   php outils/installer.php --etat      ce que contient la base, sans rien faire
 *   php outils/installer.php             installe si la base est vide
 *   php outils/installer.php --forcer    installe même si des tables existent
 *
 * La base visée est celle que lit includes/db.php : variables d'environnement
 * d'abord (MYSQLHOST, MYSQLDATABASE… — celles que Railway et consorts
 * fournissent), puis includes/config.local.php. Rien n'est écrit en dur ici.
 *
 * POURQUOI CET OUTIL EXISTE
 *
 * `db_setup.sql` commence par deux lignes qui lui sont indispensables en
 * local, et qui le rendent inutilisable ailleurs :
 *
 *     CREATE DATABASE IF NOT EXISTS studentlink …;
 *     USE studentlink;
 *
 * Sur un hébergeur, le nom de la base est imposé — « railway », ou
 * « c1234_studentlink » sur un mutualisé — et le compte n'a en général pas le
 * droit d'en créer une. Le fichier bascule alors sur une base qui n'existe
 * pas, ou pire : sur une AUTRE base du même serveur qui, elle, existe.
 *
 * Ce n'est pas théorique. Diriger `mysql` vers une base de test ne suffit pas :
 * le script bascule à la ligne 16 et s'exécute sur `studentlink`, quoi qu'on
 * ait demandé sur la ligne de commande. Le fichier ne contient ni DROP ni
 * DELETE, donc l'accident reste sans dégât — mais il écrit bien dans la
 * mauvaise base, sans que rien ne le signale.
 *
 * L'outil neutralise ces deux lignes et exécute le reste sur la connexion
 * déjà ouverte, quel que soit le nom de la base. Le commentaire du fichier
 * SQL qui invite à « remplacer les deux lignes par USE railway » devient
 * inutile : plus personne n'a à éditer un dump à la main avant un
 * déploiement.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Cet outil ne s'utilise qu'en ligne de commande.\n");
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/migrations.php';

/** @var PDO $pdo fourni par includes/db.php */

$options = array_slice($argv, 1);
$etat    = in_array('--etat', $options, true);
$forcer  = in_array('--forcer', $options, true);

$inconnues = array_diff($options, ['--etat', '--forcer']);
if ($inconnues) {
    fwrite(STDERR, 'Option inconnue : ' . implode(', ', $inconnues) . "\n");
    fwrite(STDERR, "Usage : php outils/installer.php [--etat|--forcer]\n");
    exit(2);
}

printf("Base : %s sur %s:%s\n", DB_NAME, DB_HOST, DB_PORT);

// ── Ce qui s'y trouve déjà ──────────────────────────────────────────────────

$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?'
);
$stmt->execute([DB_NAME]);
$nbTables = (int) $stmt->fetchColumn();

if ($etat) {
    printf("%d table(s).\n", $nbTables);

    if ($nbTables > 0) {
        $stmt = $pdo->prepare(
            'SELECT table_name FROM information_schema.tables
              WHERE table_schema = ? ORDER BY table_name'
        );
        $stmt->execute([DB_NAME]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $t) {
            echo "  $t\n";
        }
    }
    exit(0);
}

if ($nbTables > 0 && !$forcer) {
    fwrite(STDERR, "\nLa base contient déjà $nbTables table(s).\n");
    fwrite(STDERR, "Installer par-dessus est refusé par défaut : sur une base de production,\n");
    fwrite(STDERR, "ce serait réécrire des données réelles.\n\n");
    fwrite(STDERR, "Pour mettre à jour un schéma existant :  php outils/migrer.php\n");
    fwrite(STDERR, "Pour installer quand même              :  php outils/installer.php --forcer\n");
    exit(1);
}

// ── Lecture du dump, sans ses deux premières lignes ─────────────────────────

$chemin = dirname(__DIR__) . '/db_setup.sql';
$sql    = file_get_contents($chemin);

if ($sql === false) {
    fwrite(STDERR, "db_setup.sql introuvable.\n");
    exit(1);
}

// Uniquement en début de ligne, et uniquement ces deux instructions : un
// remplacement plus large toucherait les commentaires qui les expliquent, et
// surtout n'importe quel « USE » apparaissant dans une chaîne de données.
$avant = substr_count($sql, "\n");
$sql = preg_replace(
    '/^(CREATE DATABASE[^\n;]*;|USE\s+[^\n;]*;)\s*$/mi',
    '',
    $sql
) ?? $sql;

$instructions = decouperSql($sql);
printf("\n%d instruction(s) à exécuter.\n\n", count($instructions));

// ── Exécution ───────────────────────────────────────────────────────────────

$faites = 0;
foreach ($instructions as $i => $instruction) {
    try {
        executerInstruction($pdo, $instruction);
        $faites++;
    } catch (Throwable $e) {
        printf("ÉCHEC à l'instruction %d sur %d.\n\n", $i + 1, count($instructions));
        fwrite(STDERR, $e->getMessage() . "\n\n");
        fwrite(STDERR, "Extrait :\n" . substr(trim($instruction), 0, 300) . "\n");
        exit(1);
    }

    // Une installation complète, c'est plusieurs centaines d'instructions :
    // sans trace, on ne sait pas distinguer « en cours » de « bloqué ».
    if ($faites % 25 === 0) {
        printf("  %d/%d…\n", $faites, count($instructions));
    }
}

$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?'
);
$stmt->execute([DB_NAME]);

printf("\nTerminé : %d instruction(s), %d table(s).\n", $faites, (int) $stmt->fetchColumn());
echo "Vérifier avec : php outils/migrer.php --etat\n";
