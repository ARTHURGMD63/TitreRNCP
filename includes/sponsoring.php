<?php
/**
 * Grille tarifaire des posts sponsorisés.
 *
 * Une seule source pour le formulaire partenaire, la fiche événement et le
 * fil Explore : le tarif affiché à l'achat, celui enregistré et celui relu
 * plus tard viennent tous d'ici. Le montant reste néanmoins copié dans la
 * ligne `evenements` au moment de l'achat — une grille qui évolue ne doit pas
 * réécrire ce qui a déjà été facturé.
 */

/**
 * Les deux formules vendues — la grille tarifaire Outsee (HT, à l'unité).
 * `notifications` : nombre de notifications comprises, dont l'établissement
 * choisit le moment d'envoi.
 *
 * @return array<string, array{nom:string, tarif:float, heures:int, resume:string, notifications:int}>
 */
function formulesSponsoring(): array
{
    return [
        'boost24' => [
            'nom'           => 'Coup de projecteur',
            'tarif'         => 70.00,
            'heures'        => 48,
            'resume'        => "48 h en tête du fil Explore, 1 notification à l'heure de ton choix",
            'notifications' => 1,
        ],
        'top7' => [
            'nom'           => 'Top du fil',
            'tarif'         => 350.00,
            'heures'        => 24 * 7,
            'resume'        => "7 jours en tête du fil, badge « Sponsorisé », 3 notifications à l'heure de ton choix",
            'notifications' => 3,
        ],
    ];
}

/**
 * Formules qui ne se vendent plus mais dont des événements existants portent
 * encore le code : elles servent à relire l'historique, jamais à acheter.
 *
 * @return array<string, array{nom:string, tarif:float, heures:int, resume:string, notifications:int}>
 */
function formulesSponsoringAnciennes(): array
{
    return [
        'premium30' => [
            'nom' => 'Premium', 'tarif' => 129.00, 'heures' => 24 * 30,
            'resume' => '30 jours en tête du fil', 'notifications' => 0,
        ],
    ];
}

/** La formule existe-t-elle dans la grille ? */
function formuleSponsoringValide(?string $code): bool
{
    return $code !== null && array_key_exists($code, formulesSponsoring());
}

function tarifSponsoring(string $code): float
{
    $f = formulesSponsoring()[$code] ?? null;
    return $f ? (float) $f['tarif'] : 0.0;
}

/** Nombre de notifications comprises dans une formule. */
function notificationsSponsoring(?string $code): int
{
    $f = formulesSponsoring()[(string) $code] ?? null;
    return $f ? (int) $f['notifications'] : 0;
}

/**
 * Les formules que la mise en avant offerte de l'abonnement peut couvrir.
 *
 * Le Premium comprend « une mise en avant par mois » : c'est un Coup de
 * projecteur (70 €), pas un Top du fil (350 €). Sans cette borne, la mise en
 * avant offerte couvrirait la formule la plus chère.
 *
 * @return list<string>
 */
function formulesSponsoringOffrables(): array
{
    return ['boost24'];
}

/**
 * Fin de la mise en avant.
 *
 * Elle court à partir de l'achat, pas de la date de l'événement : une soirée
 * programmée dans six mois doit pouvoir être poussée dès maintenant.
 * Elle ne dépasse en revanche jamais la fin de l'événement — payer pour mettre
 * en avant une soirée finie n'aurait aucun sens. C'est la fin et non le début :
 * une soirée en cours reste en tête du fil, la mise en avant ne s'arrête donc
 * pas à l'ouverture des portes.
 *
 * Retourne null si la formule est inconnue ou si l'événement est déjà fini.
 */
function finSponsoring(string $code, string $dateEvenement, ?int $maintenant = null, ?string $dateFinEvenement = null): ?string
{
    $f = formulesSponsoring()[$code] ?? null;
    if (!$f) return null;

    $depart = $maintenant ?? time();
    $fin    = $depart + $f['heures'] * 3600;

    require_once __DIR__ . '/evenements_temps.php';
    $finEvenement = evenementFinEffective($dateEvenement, $dateFinEvenement);
    if ($finEvenement !== null) {
        if ($finEvenement <= $depart) {
            return null;
        }
        $fin = min($fin, $finEvenement);
    }
    return date('Y-m-d H:i:s', $fin);
}

/** Libellé court pour l'affichage partenaire : « Top du fil — 49 € ». */
function libelleSponsoring(?string $code): string
{
    $f = formulesSponsoring()[(string) $code] ?? formulesSponsoringAnciennes()[(string) $code] ?? null;
    if (!$f) return '—';
    return $f['nom'] . ' — ' . number_format($f['tarif'], 0, ',', ' ') . ' €';
}

/**
 * La mise en avant est-elle encore active pour cette ligne d'événement ?
 *
 * @param array<string, mixed> $evenement
 */
function sponsoringActif(array $evenement): bool
{
    if (empty($evenement['is_sponsorise'])) return false;
    $fin = $evenement['sponsor_jusqu_au'] ?? null;
    return $fin === null || strtotime($fin) > time();
}

/**
 * Le déroulé d'une mise en avant : quand elle a démarré, quand elle se
 * termine, combien de temps il reste.
 *
 * Tout est calculé ICI, côté serveur : `sponsor_debut` et `sponsor_jusqu_au`
 * sont des heures de Paris sans fuseau, et un téléphone réglé ailleurs les lirait
 * de travers. L'application reçoit `restant_s` et décompte à partir de là.
 *
 * `debut` est null pour une mise en avant lancée avant la migration v37 : on
 * n'affiche alors que sa fin. `fin` est null pour une mise en avant sans borne.
 *
 * @param array<string, mixed> $evenement
 * @param int|null             $maintenant l'heure Unix, pour les tests
 * @return array{formule:?string, nom:?string, debut:?string, fin:?string, restant_s:?int, duree_s:?int, ecoule_pct:?int, actif:bool, terminee:bool}|null
 *         null quand l'événement n'a jamais eu de mise en avant
 */
function sponsoringChrono(array $evenement, ?int $maintenant = null): ?array
{
    if (empty($evenement['is_sponsorise'])) {
        return null;
    }
    $maintenant ??= time();
    $debut = !empty($evenement['sponsor_debut']) ? (string) $evenement['sponsor_debut'] : null;
    $fin   = !empty($evenement['sponsor_jusqu_au']) ? (string) $evenement['sponsor_jusqu_au'] : null;
    $tDebut = $debut !== null ? strtotime($debut) : false;
    $tFin   = $fin !== null ? strtotime($fin) : false;

    $restant = $tFin !== false ? max(0, $tFin - $maintenant) : null;
    $duree   = ($tDebut !== false && $tFin !== false && $tFin > $tDebut) ? $tFin - $tDebut : null;
    $pct     = null;
    if ($duree !== null) {
        $pct = (int) max(0, min(100, round(($maintenant - $tDebut) / $duree * 100)));
    }
    $code = isset($evenement['sponsor_formule']) ? (string) $evenement['sponsor_formule'] : null;
    $actif = $tFin === false || $tFin > $maintenant;

    return [
        'formule'    => $code,
        'nom'        => $code !== null ? libelleSponsoring($code) : null,
        'debut'      => $tDebut !== false ? $debut : null,
        'fin'        => $tFin !== false ? $fin : null,
        'restant_s'  => $restant,
        'duree_s'    => $duree,
        'ecoule_pct' => $pct,
        'actif'      => $actif,
        'terminee'   => !$actif,
    ];
}

/**
 * Une durée en secondes, lisible : « 1 j 23 h 05 min », « 4 h 12 min »,
 * « 8 min », « moins d'une minute ».
 */
function dureeLisible(int $secondes): string
{
    $secondes = max(0, $secondes);
    $jours    = intdiv($secondes, 86400);
    $heures   = intdiv($secondes % 86400, 3600);
    $minutes  = intdiv($secondes % 3600, 60);

    // Les unités à zéro disparaissent : « 2 j » et non « 2 j 0 h 00 min ».
    $parts = [];
    if ($jours > 0) {
        $parts[] = $jours . ' j';
    }
    if ($heures > 0) {
        $parts[] = $heures . ' h';
    }
    if ($minutes > 0) {
        $parts[] = ($jours > 0 || $heures > 0 ? sprintf('%02d', $minutes) : (string) $minutes) . ' min';
    }

    return $parts ? implode(' ', $parts) : "moins d'une minute";
}
