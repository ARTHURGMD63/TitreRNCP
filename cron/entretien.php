<?php
/**
 * Entretien périodique.
 *
 * Tout ce qui a été ajouté pour tenir la charge laisse des traces qu'il faut
 * balayer : des fichiers de cache périmés, des canaux de temps réel qui ne
 * bougeront plus jamais, des tentatives de connexion vieilles de six mois.
 * Rien de tout cela ne doit se faire pendant qu'un utilisateur attend sa
 * page — c'est précisément le genre de travail qui se déguise en lenteur
 * inexplicable, une requête sur cent.
 *
 * À lancer une fois par jour, en ligne de commande :
 *     php cron/entretien.php
 *
 * Options :
 *     --simulation   annonce ce qui serait supprimé, sans rien supprimer
 *
 * Sans exécution régulière, rien ne casse : le cache continue de servir, le
 * flux continue de compter. Seule la place occupée augmente lentement.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/cache.php';
require_once __DIR__ . '/../includes/temps_reel.php';

$simulation = in_array('--simulation', $argv ?? [], true);

printf("Entretien Linkee — %s%s\n\n", date('d/m/Y H:i'), $simulation ? ' (simulation)' : '');

// ─── 1. Fichiers de cache périmés ───────────────────────────────────────────
//
// APCu se purge tout seul et renvoie 0 ici. Le magasin fichier, lui, ne
// supprime une entrée qu'au moment où on tente de la lire : une clé jamais
// relue reste sur le disque indéfiniment.
if ($simulation) {
    echo "  cache      : purge des entrées périmées (non exécutée)\n";
} else {
    printf("  cache      : %d entrée(s) périmée(s) supprimée(s)\n", cachePurger());
}

// ─── 2. Canaux de temps réel abandonnés ─────────────────────────────────────
//
// Un canal par événement et par utilisateur. Ceux d'une soirée passée depuis
// un mois ne seront plus jamais incrémentés ni lus.
if ($simulation) {
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM flux_revisions
          WHERE canal <> 'global' AND maj_le < NOW() - INTERVAL 30 DAY"
    );
    printf("  flux       : %d canal(aux) seraient supprimés\n", (int) $stmt->fetchColumn());
} else {
    printf("  flux       : %d canal(aux) supprimé(s)\n", fluxPurger($pdo, 30));
}

// ─── 3. Tentatives de connexion ─────────────────────────────────────────────
//
// includes/security.php nettoie déjà la fenêtre glissante à chaque tentative,
// mais uniquement pour l'adresse concernée. Les adresses qui ne reviennent
// jamais laissent leurs lignes derrière elles, et la table grossit sans que
// personne ne la regarde.
$sql = "DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 7 DAY";
if ($simulation) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 7 DAY");
    printf("  connexions : %d ligne(s) seraient supprimée(s)\n", (int) $stmt->fetchColumn());
} else {
    $stmt = $pdo->query($sql);
    printf("  connexions : %d ligne(s) supprimée(s)\n", $stmt->rowCount());
}

// ─── 4. Jetons de réinitialisation expirés ──────────────────────────────────
//
// Un jeton périmé ne vaut plus rien, mais il reste une empreinte liée à une
// adresse e-mail : le conserver, c'est garder une donnée personnelle sans
// raison — ce que le registre RGPD du projet interdit.
if ($simulation) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM password_resets WHERE expires_at < NOW() - INTERVAL 1 DAY");
    printf("  jetons     : %d ligne(s) seraient supprimée(s)\n", (int) $stmt->fetchColumn());
} else {
    $stmt = $pdo->query("DELETE FROM password_resets WHERE expires_at < NOW() - INTERVAL 1 DAY");
    printf("  jetons     : %d ligne(s) supprimée(s)\n", $stmt->rowCount());
}

echo "\nTerminé.\n";
