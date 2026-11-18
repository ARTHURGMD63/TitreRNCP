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
 * Lit le témoin écrit par deploy/sauvegarder.sh après chaque sauvegarde
 * réussie (la date, en secondes Unix). Le dossier des sauvegardes lui-même
 * est réservé à root : la sonde, qui tourne en www-data, ne peut pas le lire.
 *
 * Vrai si le dossier du témoin n'existe pas (poste de développement,
 * hébergement sans sauvegarde locale) : ce contrôle ne vaut que sur le VPS,
 * où installer_serveur.sh crée ce dossier. Faux si le dossier existe mais
 * que le témoin manque ou est illisible : aucune sauvegarde n'a abouti.
 */
function santeSauvegarde(string $temoin = '/var/lib/outsee/derniere_sauvegarde'): bool
{
    if (!is_dir(dirname($temoin))) {
        return true;
    }

    $contenu = @file_get_contents($temoin);
    if ($contenu === false) {
        return false;
    }

    // La date écrite dans le fichier, à défaut sa date de modification.
    $date = ctype_digit(trim($contenu)) ? (int) trim($contenu) : (int) @filemtime($temoin);

    return $date > 0 && (time() - $date) <= SANTE_SAUVEGARDE_MAX_S;
}
