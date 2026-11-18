<?php
/**
 * Contrôles de santé du serveur : ce qui, en panne, rend l'application
 * inutilisable sans que personne ne le dise.
 *
 * Servis par sante.php (sonde HTTP, appelable par un service de surveillance)
 * et par cron/sante.php (alerte par e-mail). Chaque contrôle rend un booléen :
 * jamais de détail technique côté sonde, le diagnostic est dans les journaux.
 */

/** Part d'espace disque libre en dessous de laquelle on alerte. */
const SANTE_DISQUE_MIN = 0.10;

/** Âge maximal de la dernière sauvegarde de la base, en secondes (36 h : une nuit manquée suffit à alerter). */
const SANTE_SAUVEGARDE_MAX_S = 36 * 3600;

/** La base répond-elle à une vraie lecture (pas seulement à la connexion) ? */
function santeBase(PDO $pdo): bool
{
    try {
        return $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() !== false;
    } catch (Throwable $e) {
        logErreur('Contrôle de santé : lecture de la base impossible', $e);

        return false;
    }
}

/** Reste-t-il de la place sur le disque de l'application ? */
function santeDisque(?string $dossier = null): bool
{
    $dossier ??= __DIR__;
    $libre = @disk_free_space($dossier);
    $total = @disk_total_space($dossier);

    if ($libre === false || $total === false || $total <= 0) {
        return true; // illisible : on ne déclenche pas une fausse alerte
    }

    return ($libre / $total) >= SANTE_DISQUE_MIN;
}

/**
 * La dernière sauvegarde est-elle récente ?
 *
 * Vrai si le dossier de sauvegarde n'existe pas (poste de développement,
 * hébergement sans sauvegarde locale) : ce contrôle ne vaut que sur le VPS.
 */
function santeSauvegarde(string $dossier = '/var/backups/outsee'): bool
{
    if (!is_dir($dossier)) {
        return true;
    }

    $plusRecent = 0;
    foreach (glob($dossier . '/outsee_*.sql.gz') ?: [] as $fichier) {
        $plusRecent = max($plusRecent, (int) filemtime($fichier));
    }

    return $plusRecent > 0 && (time() - $plusRecent) <= SANTE_SAUVEGARDE_MAX_S;
}
