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

/**
 * Jusqu'à quand on peut s'inscrire : la clôture fixée par l'établissement
 * (migration v38) si elle tombe avant le début, sinon le début lui-même.
 * Heure de Paris, comme date_heure.
 */
function evenementClotureInscriptions(string $dateHeure, ?string $jusquAu): string
{
    if ($jusquAu && strtotime($jusquAu) !== false && strtotime($jusquAu) < strtotime($dateHeure)) {
        return $jusquAu;
    }

    return $dateHeure;
}

/** Les inscriptions sont-elles encore ouvertes ? */
function evenementInscriptionsOuvertes(string $dateHeure, ?string $jusquAu): bool
{
    return time() < strtotime(evenementClotureInscriptions($dateHeure, $jusquAu));
}

/** Fragment SQL de la clôture des inscriptions (même règle qu'au-dessus). */
function sqlClotureInscriptions(string $alias = 'e'): string
{
    return "LEAST($alias.date_heure, COALESCE($alias.inscriptions_jusqu_au, $alias.date_heure))";
}

/** Fragment SQL de la fin effective d'un événement, pour un WHERE/ORDER BY. */
function sqlFinEffectiveEvenement(string $aliasDateHeure = 'e.date_heure', string $aliasDateFin = 'e.date_fin'): string
{
    return "COALESCE($aliasDateFin, DATE_ADD($aliasDateHeure, INTERVAL " . EVENEMENT_DUREE_PAR_DEFAUT_HEURES . " HOUR))";
}

/**
 * Recalcule evenements.fin_effective (migration v41) pour un événement.
 *
 * Le fil filtre sur cette colonne indexée plutôt que sur l'expression
 * sqlFinEffectiveEvenement(), qu'aucun index ne sert (DB-02). Elle n'est pas
 * générée par la base (TiDB n'accepte pas d'ajouter une colonne générée
 * STORED) : chaque écriture de date_heure ou de date_fin doit donc appeler
 * cette fonction, dans la même transaction quand il y en a une. Un oubli se
 * voit vite : un événement à fin_effective NULL n'apparaît plus dans le fil.
 */
function evenementMajFinEffective(PDO $pdo, int $id): void
{
    $pdo->prepare('UPDATE evenements SET fin_effective = ' . sqlFinEffectiveEvenement('date_heure', 'date_fin') . ' WHERE id = ?')
        ->execute([$id]);
}

/**
 * Même calcul pour toutes les lignes qui n'ont pas encore de fin effective :
 * pour les outils de semis, qui insèrent en masse.
 */
function evenementsMajFinEffectiveManquantes(PDO $pdo): int
{
    return (int) $pdo->exec('UPDATE evenements SET fin_effective = ' . sqlFinEffectiveEvenement('date_heure', 'date_fin') . ' WHERE fin_effective IS NULL');
}
