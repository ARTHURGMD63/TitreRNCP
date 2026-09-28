<?php
/**
 * POST /api/v1/partenaire_scan.php {qr_code|inscription_id, event_id} —
 * check-in à l'entrée.
 *
 * Reprend partenaire/api_scan.php à l'identique : retrait du préfixe
 * linkee:/studentlink: (optionnel, pour accepter aussi une saisie
 * manuelle), vérification que la soirée appartient bien à cet
 * établissement, puis la même machine à états sur inscriptions.statut.
 *
 * `inscription_id` remplace `qr_code` pour la validation manuelle
 * (api/v1/partenaire_invites.php) : quand le scan ne marche pas — plusieurs
 * événements le même soir, lumière, code abîmé — le partenaire choisit la
 * personne dans la liste des inscrits plutôt que de forcer une photo.
 */

require_once __DIR__ . '/_socle.php';

apiExigerMethode('POST');

$u   = apiPartenaire($pdo);
$uid = (int) $u['id'];

$corps         = apiCorps();
$qrCode        = trim((string) ($corps['qr_code'] ?? ''));
$inscriptionId = (int) ($corps['inscription_id'] ?? 0);
$eventId       = (int) ($corps['event_id'] ?? 0);

foreach (['linkee:', 'studentlink:'] as $prefixe) {
    if (stripos($qrCode, $prefixe) === 0) {
        $qrCode = substr($qrCode, strlen($prefixe));
        break;
    }
}

if (($qrCode === '' && !$inscriptionId) || !$eventId) {
    apiErreur('Données manquantes', 422, 'donnees_manquantes');
}

$stmt = $pdo->prepare(
    'SELECT e.id FROM evenements e
     JOIN etablissements et ON et.id = e.etablissement_id
    WHERE e.id = ? AND et.user_id = ?'
);
$stmt->execute([$eventId, $uid]);
if (!$stmt->fetch()) {
    apiErreur('Événement non autorisé', 403, 'evenement_non_autorise');
}

if ($inscriptionId) {
    $stmt = $pdo->prepare(
        'SELECT i.id, i.statut, u.id AS user_id, u.prenom, u.nom, u.ecole, u.promo, u.photo
           FROM inscriptions i JOIN users u ON u.id = i.user_id
          WHERE i.id = ? AND i.evenement_id = ?'
    );
    $stmt->execute([$inscriptionId, $eventId]);
} else {
    $stmt = $pdo->prepare(
        'SELECT i.id, i.statut, u.id AS user_id, u.prenom, u.nom, u.ecole, u.promo, u.photo
           FROM inscriptions i JOIN users u ON u.id = i.user_id
          WHERE i.qr_code = ? AND i.evenement_id = ?'
    );
    $stmt->execute([$qrCode, $eventId]);
}
$inscription = $stmt->fetch();

if (!$inscription) {
    apiErreur('Pass invalide pour cet événement', 404, 'pass_invalide');
}
if ($inscription['statut'] === 'checkin') {
    apiErreur('Ce pass a déjà été scanné', 409, 'deja_scanne');
}
if ($inscription['statut'] === 'annule') {
    apiErreur('Inscription annulée', 409, 'inscription_annulee');
}

$pdo->prepare("UPDATE inscriptions SET statut = 'checkin', checkin_le = NOW() WHERE id = ?")
    ->execute([(int) $inscription['id']]);

apiReponse([
    'success' => true,
    'message' => 'Check-in validé pour ' . $inscription['prenom'] . ' ' . $inscription['nom'],
    'personne' => [
        'id'        => (int) $inscription['user_id'],
        'prenom'    => (string) $inscription['prenom'],
        'nom'       => (string) $inscription['nom'],
        'ecole'     => $inscription['ecole'] !== null ? (string) $inscription['ecole'] : null,
        'promo'     => $inscription['promo'] !== null ? (string) $inscription['promo'] : null,
        'photo_url' => apiPhotoUrl($inscription['photo'] ?? null),
    ],
]);
