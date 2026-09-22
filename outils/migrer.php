<?php
/**
 * outils/migrer.php — applique les migrations de schéma en attente.
 *
 * Remplace la pose manuelle dans phpMyAdmin, fichier par fichier, sans
 * trace de ce qui était déjà passé.
 *
 * Usage :
 *
 *   php outils/migrer.php              applique ce qui manque
 *   php outils/migrer.php --etat       liste sans rien exécuter
 *   php outils/migrer.php --adopter    marque tout comme appliqué, sans
 *                                      exécuter — pour raccorder une base
 *                                      déjà créée depuis db_setup.sql
 *
 * Ligne de commande uniquement : un outil qui modifie le schéma n'a rien à
 * faire derrière une URL, même protégée par un mot de passe.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/migrations.php';

/** @var PDO $pdo fourni par includes/db.php */

$options = array_slice($argv, 1);
$etat    = in_array('--etat', $options, true);
$adopter = in_array('--adopter', $options, true);

$inconnues = array_diff($options, ['--etat', '--adopter']);
if ($inconnues) {
    fwrite(STDERR, "Option inconnue : " . implode(', ', $inconnues) . "\n");
    fwrite(STDERR, "Usage : php outils/migrer.php [--etat|--adopter]\n");
    exit(2);
}

$toutes = listerMigrations();
if (!$toutes) {
    fwrite(STDERR, "Aucun fichier db_migrations_v*.sql trouvé à la racine du projet.\n");
    exit(1);
}

try {
    $faites = array_flip(migrationsAppliquees($pdo));
} catch (PDOException $e) {
    fwrite(STDERR, "Impossible de lire la table de suivi : {$e->getMessage()}\n");
    exit(1);
}

$attente = array_diff_key($toutes, $faites);

// ── État ────────────────────────────────────────────────────────────────────

if ($etat) {
    echo "Base : " . DB_NAME . " sur " . DB_HOST . ":" . DB_PORT . "\n\n";
    foreach ($toutes as $version => $chemin) {
        printf("  %-5s %s  %s\n",
            $version,
            isset($faites[$version]) ? 'appliquée ' : 'EN ATTENTE',
            basename($chemin)
        );
    }
    printf("\n%d appliquée(s), %d en attente.\n", count($toutes) - count($attente), count($attente));
    exit(0);
}

// ── Raccordement d'une base existante ───────────────────────────────────────

if ($adopter) {
    $n = adopterMigrations($pdo, array_keys($toutes));
    printf("%d migration(s) marquée(s) comme appliquée(s), sans exécution.\n", $n);
    echo "La base est considérée à jour. Vérifier avec --etat.\n";
    exit(0);
}

// ── Application ─────────────────────────────────────────────────────────────

if (!$attente) {
    echo "Base à jour : rien à appliquer.\n";
    exit(0);
}

printf("Base : %s sur %s:%s\n%d migration(s) à appliquer.\n\n",
    DB_NAME, DB_HOST, DB_PORT, count($attente));

foreach ($attente as $version => $chemin) {
    printf("  %-5s %s … ", $version, basename($chemin));

    try {
        $n = appliquerMigration($pdo, $version, $chemin);
        printf("%d instruction(s), OK\n", $n);
    } catch (Throwable $e) {
        // On s'arrête net : appliquer la suivante sur un schéma à moitié
        // migré produit des erreurs qui n'ont plus rien à voir avec la cause.
        printf("ÉCHEC\n\n");
        fwrite(STDERR, "Migration $version interrompue : {$e->getMessage()}\n");
        fwrite(STDERR, "Elle reste en attente et sera reproposée au prochain passage.\n");
        fwrite(STDERR, "Les migrations sont idempotentes : corriger, puis relancer.\n");
        exit(1);
    }
}

echo "\nTerminé.\n";
