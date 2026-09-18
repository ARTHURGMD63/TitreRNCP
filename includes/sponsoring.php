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

/** @return array<string, array{nom:string, tarif:float, heures:int, resume:string}> */
function formulesSponsoring(): array
{
    return [
        'boost24' => [
            'nom'    => 'Coup de projecteur',
            'tarif'  => 19.00,
            'heures' => 24,
            'resume' => '24 h en tête du fil Explore',
        ],
        'top7' => [
            'nom'    => 'Top du fil',
            'tarif'  => 49.00,
            'heures' => 24 * 7,
            'resume' => '7 jours en tête du fil, badge « Sponsorisé »',
        ],
        'premium30' => [
            'nom'    => 'Premium',
            'tarif'  => 129.00,
            'heures' => 24 * 30,
            'resume' => '30 jours en tête du fil, priorité maximale',
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

/**
 * Fin de la mise en avant.
 *
 * Elle court à partir de l'achat, pas de la date de l'événement : une soirée
 * programmée dans six mois doit pouvoir être poussée dès maintenant.
 * Elle ne dépasse en revanche jamais l'événement lui-même — payer pour mettre
 * en avant une soirée passée n'aurait aucun sens.
 */
function finSponsoring(string $code, string $dateEvenement, ?int $maintenant = null): ?string
{
    $f = formulesSponsoring()[$code] ?? null;
    if (!$f) return null;

    $depart = $maintenant ?? time();
    $fin    = $depart + $f['heures'] * 3600;
    $event  = strtotime($dateEvenement);
    if ($event && $event < $fin) {
        $fin = $event;
    }
    return date('Y-m-d H:i:s', $fin);
}

/** Libellé court pour l'affichage partenaire : « Top du fil — 49 € ». */
function libelleSponsoring(?string $code): string
{
    $f = formulesSponsoring()[$code] ?? null;
    if (!$f) return '—';
    return $f['nom'] . ' — ' . number_format($f['tarif'], 0, ',', ' ') . ' €';
}

/** La mise en avant est-elle encore active pour cette ligne d'événement ? */
function sponsoringActif(array $evenement): bool
{
    if (empty($evenement['is_sponsorise'])) return false;
    $fin = $evenement['sponsor_jusqu_au'] ?? null;
    return $fin === null || strtotime($fin) > time();
}
