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
