<?php
/**
 * « La soirée est en cours » — une seule définition, partagée par le fil
 * (hub.php), les photos (evenements_photos.php) et l'inscription
 * (api/inscrire.php, api/v1/evenement.php).
 *
 * evenements.date_fin (migration v24) donne la vraie fin quand
 * l'établissement l'a saisie. Pour les événements créés avant cette
 * migration, ou sans fin déclarée, on retombe sur l'ancienne approximation :
 * une fenêtre fixe après le début.
 */

/** Fenêtre par défaut quand aucune date de fin n'est déclarée. */
const EVENEMENT_DUREE_PAR_DEFAUT_HEURES = 12;

/** L'instant de fin à retenir pour $dateHeure/$dateFin, en timestamp Unix. */
function evenementFinEffective(string $dateHeure, ?string $dateFin): ?int
{
    $debut = strtotime($dateHeure);
    if ($debut === false) {
        return null;
    }
    if ($dateFin) {
        $fin = strtotime($dateFin);
        if ($fin !== false) {
            return $fin;
        }
    }

    return $debut + EVENEMENT_DUREE_PAR_DEFAUT_HEURES * 3600;
}

/** La soirée a-t-elle déjà commencé, et n'est-elle pas encore terminée ? */
function evenementEnCours(string $dateHeure, ?string $dateFin): bool
{
    $debut = strtotime($dateHeure);
    $fin   = evenementFinEffective($dateHeure, $dateFin);
    if ($debut === false || $fin === null) {
        return false;
    }

    return time() >= $debut && time() <= $fin;
}

/** La soirée est-elle terminée (donc plus rejoignable) ? */
function evenementTermine(string $dateHeure, ?string $dateFin): bool
{
    $fin = evenementFinEffective($dateHeure, $dateFin);

    return $fin !== null && time() > $fin;
}

/** Fragment SQL de la fin effective d'un événement, pour un WHERE/ORDER BY. */
function sqlFinEffectiveEvenement(string $aliasDateHeure = 'e.date_heure', string $aliasDateFin = 'e.date_fin'): string
{
    return "COALESCE($aliasDateFin, DATE_ADD($aliasDateHeure, INTERVAL " . EVENEMENT_DUREE_PAR_DEFAUT_HEURES . " HOUR))";
}
