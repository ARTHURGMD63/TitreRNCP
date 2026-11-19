<?php
/**
 * POST /api/v1/evenement_photo_signaler.php {photo_id, motif, details?} —
 * signaler une photo postée par quelqu'un d'autre pendant une soirée.
 *
 * Mêmes règles que le signalement de profil (api/moderation.php) : un seul
 * signalement par personne et par photo (contrainte UNIQUE côté base), motif
 * fermé, précisions coupées à 500 caractères.
 *
 * Ne signale que qui peut voir la photo (mêmes règles que
 * evenement_photos.php : soirée en cours, ou entrée validée à cette soirée ;
 * compte privé suivi ; pas de blocage). Le masquage automatique ne compte
 * que les signaleurs validés à l'entrée de la soirée, selon un seuil relatif
 * au nombre de présents (photoSeuilMasquage()) : avant (ETU-08), trois
 * comptes quelconques masquaient n'importe quelle photo, ids énumérables.
 * Un contenu peut heurter plus vite qu'un profil : mieux vaut le retirer
 * pendant qu'un modérateur regarde, mais pas sur la parole d'inconnus.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/social.php';
require_once __DIR__ . '/../../includes/evenements_photos.php';

apiExigerMethode('POST');

$moi = apiEtudiant($pdo);
$uid = (int) $moi['id'];

$motifs        = ['n_apparait_pas', 'contenu_inapproprie', 'spam', 'autre'];

$corps   = apiCorps();
$photoId = (int) ($corps['photo_id'] ?? 0);
$motif   = in_array($corps['motif'] ?? '', $motifs, true) ? $corps['motif'] : 'autre';
$details = trim(is_string($corps['details'] ?? null) ? $corps['details'] : '');
$details = $details !== '' ? mb_substr($details, 0, 500) : null;

if ($photoId <= 0) {
    apiErreur('Photo manquante.', 422, 'id_manquant');
}

$stmt = $pdo->prepare(
    'SELECT p.id, p.user_id, p.evenement_id, e.date_heure, e.date_fin
       FROM evenement_photos p JOIN evenements e ON e.id = p.evenement_id
      WHERE p.id = ?'
);
$stmt->execute([$photoId]);
$photo = $stmt->fetch();

if (!$photo) {
    apiErreur('Cette photo n’existe plus.', 404, 'introuvable');
}
$auteurId = (int) $photo['user_id'];
$eid      = (int) $photo['evenement_id'];
if ($auteurId === $uid) {
    apiErreur('Tu ne peux pas signaler ta propre photo.', 422, 'auteur');
}

// Même réponse qu'une photo inexistante : on ne confirme pas l'existence
// d'une photo à qui ne peut pas la voir.
$enCours    = evenementPhotosEnCours((string) $photo['date_heure'], $photo['date_fin'] !== null ? (string) $photo['date_fin'] : null);
$estPresent = evenementPhotosEstCheckin($pdo, $uid, $eid);
if ((!$enCours && !$estPresent)
    || isBlockedBetween($pdo, $uid, $auteurId)
    || !peutVoirPhotosDe($pdo, $uid, $auteurId)) {
    apiErreur('Cette photo n’existe plus.', 404, 'introuvable');
}

try {
    $pdo->prepare(
        'INSERT IGNORE INTO evenement_photo_signalements (photo_id, reporter_id, motif, details) VALUES (?,?,?,?)'
    )->execute([$photoId, $uid, $motif, $details]);

    // Signaleurs validés à l'entrée de cette soirée, et présents en tout.
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM evenement_photo_signalements s
           JOIN inscriptions i ON i.user_id = s.reporter_id AND i.evenement_id = ? AND i.statut = 'checkin'
          WHERE s.photo_id = ?"
    );
    $stmt->execute([$eid, $photoId]);
    $signaleursPresents = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE evenement_id = ? AND statut = 'checkin'");
    $stmt->execute([$eid]);
    if ($signaleursPresents >= photoSeuilMasquage((int) $stmt->fetchColumn())) {
        $pdo->prepare('UPDATE evenement_photos SET masquee = 1 WHERE id = ?')->execute([$photoId]);
    }
} catch (PDOException $e) {
    apiErreur('Erreur serveur.', 500, 'enregistrement');
}

apiReponse(['success' => true, 'message' => 'Signalement envoyé. Merci.']);
