<?php
/**
 * Règles communes aux points d'API de photos d'événement.
 *
 * « En cours » vient désormais de evenements_temps.php : evenements.date_fin
 * (migration v24) donne la vraie fin quand l'établissement l'a saisie, sinon
 * on retombe sur l'ancienne fenêtre fixe après le début.
 */

require_once __DIR__ . '/evenements_temps.php';

/** La soirée $dateHeure/$dateFin est-elle encore "en cours" pour les photos ? */
function evenementPhotosEnCours(string $dateHeure, ?string $dateFin = null): bool
{
    return evenementEnCours($dateHeure, $dateFin);
}

/**
 * $uid a-t-il été validé à l'entrée de $evenementId (scan du partenaire) ?
 * Même requête que api/v1/avis.php : seul le check-in prouve une présence
 * réelle, une simple inscription ne suffit pas.
 */
function evenementPhotosEstCheckin(PDO $pdo, int $uid, int $evenementId): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1 FROM inscriptions WHERE user_id = ? AND evenement_id = ? AND statut = 'checkin'"
    );
    $stmt->execute([$uid, $evenementId]);

    return (bool) $stmt->fetchColumn();
}

/** Signalements de participants qu'il faut au minimum pour masquer une photo. */
const PHOTO_SIGNALEMENTS_MIN = 3;

/** Au-delà, le seuil ne monte plus : un modérateur regarde de toute façon. */
const PHOTO_SIGNALEMENTS_MAX = 10;

/**
 * Combien de signalements de participants masquent automatiquement une
 * photo, selon le nombre de personnes validées à l'entrée de la soirée.
 *
 * Un seuil fixe de trois laissait trois comptes quelconques masquer
 * n'importe quelle photo (ETU-08). Seuls comptent désormais les signaleurs
 * validés à l'entrée de CETTE soirée, et il en faut 5 % des présents, entre
 * PHOTO_SIGNALEMENTS_MIN et PHOTO_SIGNALEMENTS_MAX. Les autres signalements
 * restent enregistrés pour les modérateurs.
 */
function photoSeuilMasquage(int $presents): int
{
    return max(PHOTO_SIGNALEMENTS_MIN, min(PHOTO_SIGNALEMENTS_MAX, (int) ceil(max(0, $presents) * 0.05)));
}
