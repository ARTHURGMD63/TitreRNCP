<?php
/**
 * Places d'une sortie : la règle de remplissage, écrite une seule fois.
 *
 * Elle se lisait à six endroits sous des formes voisines
 * (`$nb >= $quota`, `$quota > 0 && $inscrits >= $quota`…). Voisines n'est pas
 * identiques : à la création, le quota d'un événement ne descend pas sous 1,
 * mais une ligne plus ancienne ou saisie en base peut valoir 0, et les
 * écritures et les affichages ne la traitaient pas de la même façon. Les deux
 * comportements sont donc nommés ici, au lieu de rester implicites.
 */

/**
 * Une inscription ou une adhésion est-elle encore possible ?
 *
 * À utiliser là où l'on décide d'écrire. Un quota nul ferme la sortie : on
 * n'ajoute jamais quelqu'un à une sortie qui n'a aucune place.
 */
function estComplet(int $occupees, int $quota): bool
{
    return $occupees >= $quota;
}

/**
 * Ce que l'on montre d'une sortie : places restantes et mention « complet ».
 *
 * Un quota nul ou négatif n'est pas un compteur, c'est l'absence de limite :
 * on n'affiche alors ni places restantes ni « complet ».
 *
 * @return array{restantes: int|null, complet: bool}
 */
function remplissage(int $quota, int $occupees): array
{
    if ($quota <= 0) {
        return ['restantes' => null, 'complet' => false];
    }

    return [
        'restantes' => max(0, $quota - $occupees),
        'complet'   => $occupees >= $quota,
    ];
}

/**
 * Pass actifs qu'un étudiant peut tenir en même temps, sur des soirées pas
 * encore terminées (ETU-11).
 *
 * Sans plafond, un compte (ou une poignée de comptes jetables) réservait
 * toutes les places d'une soirée, ou une place à chaque soirée de la ville,
 * sans intention de venir. Dix couvre largement un étudiant qui sort
 * beaucoup : une réservation se libère dès que la soirée est passée ou que
 * le pass est annulé.
 */
const INSCRIPTIONS_A_VENIR_MAX = 10;

/** L'étudiant qui tient déjà $actives pass à venir peut-il en prendre un de plus ? */
function inscriptionPlafondAtteint(int $actives): bool
{
    return $actives >= INSCRIPTIONS_A_VENIR_MAX;
}
