<?php
/**
 * École et promotion d'un étudiant : bornes et liste des promotions,
 * partagées par l'inscription (api/v1/inscription.php), l'écran Moi
 * (api/v1/moi.php) et l'enregistrement du profil (api/v1/profil.php).
 *
 * Avant l'audit (ETU-16), le profil acceptait n'importe quelle école et
 * n'importe quelle promo, de n'importe quelle longueur : une valeur trop
 * longue échouait en SQL après que la photo eut déjà été remplacée, et des
 * promos libres fragmentaient le classement « Mon école ».
 */

/** Longueur de users.ecole (VARCHAR(100)). */
const ECOLE_LONGUEUR_MAX = 100;

/** Longueur de users.promo (VARCHAR(10)). */
const PROMO_LONGUEUR_MAX = 10;

/**
 * Les promotions proposées, dans l'ordre des sélecteurs (le mobile en a la
 * copie dans mobile/src/catalogue.ts, PROMOS).
 *
 * @return list<string>
 */
function promosDisponibles(): array
{
    return ['L1', 'L2', 'L3', 'M1', 'M2', 'BUT1', 'BUT2', 'BUT3'];
}

/**
 * L'école saisie, espaces resserrés, ou null si elle est trop longue.
 * Une chaîne vide veut dire « pas d'école ».
 */
function ecoleNormalisee(string $saisie): ?string
{
    $ecole = trim((string) preg_replace('/\s+/u', ' ', $saisie));

    return mb_strlen($ecole) <= ECOLE_LONGUEUR_MAX ? $ecole : null;
}

/**
 * La promo est-elle acceptable ? Vide, dans la liste, ou inchangée : une
 * valeur enregistrée avant la liste reste en place tant que l'étudiant n'y
 * touche pas, plutôt que de bloquer tout l'enregistrement de son profil.
 */
function promoAcceptee(string $promo, ?string $actuelle = null): bool
{
    return $promo === ''
        || in_array($promo, promosDisponibles(), true)
        || ($actuelle !== null && $actuelle !== '' && $promo === $actuelle && mb_strlen($promo) <= PROMO_LONGUEUR_MAX);
}
