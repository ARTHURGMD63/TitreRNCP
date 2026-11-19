<?php
/**
 * Âge minimum d'un événement (migration v44).
 *
 * L'établissement le fixe (18 par défaut, Outsee étant réservé aux majeurs).
 * Il se compare à l'âge de l'étudiant LE JOUR DE L'ÉVÉNEMENT, pas le jour de
 * l'inscription : on peut réserver une soirée « 21 ans et plus » la veille de
 * son anniversaire si elle a lieu après.
 *
 * Appliqué à trois endroits : l'inscription (api/inscrire.php), l'acceptation
 * d'une invitation (api/inviter.php) et le scan à l'entrée
 * (api/v1/partenaire_scan.php).
 */

/** L'âge minimum de toute la plateforme, et la valeur par défaut d'un événement. */
const AGE_MINIMUM_DEFAUT = 18;

/** Le plus grand âge minimum qu'un établissement peut fixer. */
const AGE_MINIMUM_MAX = 99;

/** Ramène une saisie à un âge minimum valable (18 à 99) ; 18 si elle est vide ou absurde. */
function ageMinimumNormalise(mixed $saisie): int
{
    if (is_string($saisie)) {
        $saisie = trim($saisie);
    }
    if ($saisie === null || $saisie === '' || !is_numeric($saisie)) {
        return AGE_MINIMUM_DEFAUT;
    }

    return max(AGE_MINIMUM_DEFAUT, min(AGE_MINIMUM_MAX, (int) $saisie));
}

/**
 * L'âge d'une personne née le $naissance (AAAA-MM-JJ) à la date $jour
 * (« AAAA-MM-JJ » ou « AAAA-MM-JJ HH:MM:SS »). Null si une date est illisible.
 */
function ageALaDate(?string $naissance, string $jour): ?int
{
    if ($naissance === null || $naissance === '') {
        return null;
    }
    $n = DateTimeImmutable::createFromFormat('!Y-m-d', substr($naissance, 0, 10));
    $j = DateTimeImmutable::createFromFormat('!Y-m-d', substr($jour, 0, 10));
    if (!$n || !$j || $n > $j) {
        return null;
    }

    return $n->diff($j)->y;
}

/**
 * La personne a-t-elle l'âge requis le jour de l'événement ?
 *
 * Une date de naissance inconnue (compte antérieur à la v10) n'est pas un
 * refus : l'inscription d'alors vérifiait déjà la majorité, et c'est à
 * l'entrée, pièce d'identité en main, que l'établissement tranche.
 */
function ageMinimumAtteint(?string $naissance, string $dateEvenement, int $ageMinimum): bool
{
    $age = ageALaDate($naissance, $dateEvenement);

    return $age === null || $age >= $ageMinimum;
}

/** « Réservé aux 21 ans et plus » : le message de refus, le même partout. */
function ageMinimumMessage(int $ageMinimum): string
{
    return "Cet événement est réservé aux $ageMinimum ans et plus.";
}
