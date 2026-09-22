<?php
/**
 * outils/migrer.php — applique les migrations de schéma en attente.
 *
 * Remplace la pose manuelle dans phpMyAdmin, fichier par fichier, sans
 * trace de ce qui était déjà passé.
 *
 * Usage :
 *
 *   php outils/migrer.php               applique ce qui manque
 *   php outils/migrer.php --etat        liste sans rien exécuter
 *   php outils/migrer.php --adopter     marque tout comme appliqué, sans
 *                                       exécuter — pour raccorder une base
 *                                       déjà créée depuis db_setup.sql
 *   php outils/migrer.php --adopter=v13 marque comme appliqué jusqu'à v13
 *                                       seulement, puis laisse les suivantes
 *                                       en attente
 *
 * Pourquoi la borne : une base n'est pas forcément « à jour » ou « vierge ».
 * Une base de travail créée il y a quelques semaines a reçu v4 à v13 à la
 * main, et pas v14 ni v15. `--adopter` sans borne la déclarerait à jour et
 * les deux dernières ne seraient jamais posées : `flux_revisions` et
 * `user_interets` resteraient absentes, et le temps réel comme l'annuaire
 * tomberaient en erreur sans que rien n'indique pourquoi. Sans borne, il n'y
 * avait aucune commande correcte pour ce cas — et c'est le cas courant.
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
$adopter = false;
$borne   = null;   // dernière version à adopter, null = toutes

foreach ($options as $option) {
    if ($option === '--adopter') {
        $adopter = true;
    } elseif (str_starts_with($option, '--adopter=')) {
        $adopter = true;
        $borne   = substr($option, strlen('--adopter='));
    }
}

$inconnues = array_filter(
    $options,
    static fn (string $o): bool => $o !== '--etat'
        && $o !== '--adopter'
        && !str_starts_with($o, '--adopter=')
);
if ($inconnues) {
    fwrite(STDERR, "Option inconnue : " . implode(', ', $inconnues) . "\n");
    fwrite(STDERR, "Usage : php outils/migrer.php [--etat|--adopter|--adopter=vN]\n");
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
    $versions = array_keys($toutes);

    if ($borne !== null) {
        $rang = array_search($borne, $versions, true);
        if ($rang === false) {
            fwrite(STDERR, "Version inconnue : {$borne}\n");
            fwrite(STDERR, "Versions disponibles : " . implode(', ', $versions) . "\n");
            exit(2);
        }
        $versions = array_slice($versions, 0, $rang + 1);
    }

    $n = adopterMigrations($pdo, $versions);
    printf("%d migration(s) marquée(s) comme appliquée(s), sans exécution.\n", $n);

    $restantes = array_diff(array_keys($toutes), $versions);
    if ($restantes) {
        printf("Restent à appliquer : %s\n", implode(', ', $restantes));
        echo "Relancer sans option pour les poser.\n";
    } else {
        echo "La base est considérée à jour. Vérifier avec --etat.\n";
    }
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
        fwrite(STDERR, "Elle reste en attente et sera reproposée au prochain passage.\n\n");

        // Toutes les migrations ne sont pas rejouables : certaines posent une
        // colonne ou un index sans condition, et échouent sur « already
        // exists » quand le schéma les a déjà. Ce message affirmait
        // l'inverse — il envoyait chercher une erreur dans le fichier SQL
        // alors que la base était simplement déjà à jour sur ce point.
        $deja = str_contains($e->getMessage(), 'already exists')
             || str_contains($e->getMessage(), 'Duplicate');
        if ($deja) {
            fwrite(STDERR, "Cette erreur dit que le schéma porte déjà ce que la migration voulait\n");
            fwrite(STDERR, "ajouter : la base est en avance sur son suivi. La raccorder plutôt que\n");
            fwrite(STDERR, "la rejouer, avec la dernière version qu'elle contient réellement :\n");
            fwrite(STDERR, "    php outils/migrer.php --adopter=$version\n");
            fwrite(STDERR, "puis relancer sans option pour poser les suivantes.\n");
        } else {
            fwrite(STDERR, "Corriger la cause, puis relancer.\n");
        }
        exit(1);
    }
}

echo "\nTerminé.\n";
