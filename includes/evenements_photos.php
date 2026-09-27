<?php
/**
 * Règles communes aux points d'API de photos d'événement.
 *
 * « La soirée est en cours » n'existe nulle part ailleurs dans le schéma —
 * evenements n'a ni statut ni date de fin, tout le reste du code compare
 * juste date_heure à NOW(). On ajoute donc ici la seule notion qui manque,
 * bornée dans le temps plutôt que confiée à une clôture manuelle : une
 * fenêtre fixe après le début de la soirée.
 */

/** Durée pendant laquelle une soirée reste "en cours" pour les photos. */
const EVENEMENT_PHOTOS_FENETRE_HEURES = 12;

/** La soirée $dateHeure est-elle encore "en cours" pour les photos ? */
function evenementPhotosEnCours(string $dateHeure): bool
{
    $debut = strtotime($dateHeure);
    if ($debut === false) {
        return false;
    }
    $fin = $debut + EVENEMENT_PHOTOS_FENETRE_HEURES * 3600;

    return time() >= $debut && time() <= $fin;
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
