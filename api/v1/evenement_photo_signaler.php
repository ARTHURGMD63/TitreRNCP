<?php
/**
 * POST /api/v1/evenement_photo_signaler.php {photo_id, motif, details?} —
 * signaler une photo postée par quelqu'un d'autre pendant une soirée.
 *
 * Mêmes règles que le signalement de profil (api/moderation.php) : un seul
 * signalement par personne et par photo (contrainte UNIQUE côté base), motif
 * fermé, précisions coupées à 500 caractères. Au 3e signalement distinct,
 * la photo est masquée automatiquement — un contenu peut heurter plus vite
 * qu'un profil, mieux vaut la retirer pendant qu'un modérateur regarde.
 */

require_once __DIR__ . '/_socle.php';

apiExigerMethode('POST');

$moi = apiEtudiant($pdo);
$uid = (int) $moi['id'];

$seuilMasquage = 3;
$motifs        = ['n_apparait_pas', 'contenu_inapproprie', 'spam', 'autre'];

$corps   = apiCorps();
$photoId = (int) ($corps['photo_id'] ?? 0);
$motif   = in_array($corps['motif'] ?? '', $motifs, true) ? $corps['motif'] : 'autre';
$details = trim(is_string($corps['details'] ?? null) ? $corps['details'] : '');
$details = $details !== '' ? substr($details, 0, 500) : null;

if ($photoId <= 0) {
    apiErreur('Photo manquante.', 422, 'id_manquant');
}

$stmt = $pdo->prepare('SELECT id, user_id FROM evenement_photos WHERE id = ?');
$stmt->execute([$photoId]);
$photo = $stmt->fetch();

if (!$photo) {
    apiErreur('Cette photo n’existe plus.', 404, 'introuvable');
}
if ((int) $photo['user_id'] === $uid) {
    apiErreur('Tu ne peux pas signaler ta propre photo.', 422, 'auteur');
}

try {
    $pdo->prepare(
        'INSERT IGNORE INTO evenement_photo_signalements (photo_id, reporter_id, motif, details) VALUES (?,?,?,?)'
    )->execute([$photoId, $uid, $motif, $details]);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM evenement_photo_signalements WHERE photo_id = ?');
    $stmt->execute([$photoId]);
    if ((int) $stmt->fetchColumn() >= $seuilMasquage) {
        $pdo->prepare('UPDATE evenement_photos SET masquee = 1 WHERE id = ?')->execute([$photoId]);
    }
} catch (PDOException $e) {
    apiErreur('Erreur serveur.', 500, 'enregistrement');
}

apiReponse(['success' => true, 'message' => 'Signalement envoyé. Merci.']);
