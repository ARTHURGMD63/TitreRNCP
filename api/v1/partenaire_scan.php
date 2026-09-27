<?php
/**
 * POST /api/v1/partenaire_scan.php {qr_code, event_id} — check-in à l'entrée.
 *
 * Reprend partenaire/api_scan.php à l'identique : retrait du préfixe
 * linkee:/studentlink: (optionnel, pour accepter aussi une saisie
 * manuelle), vérification que la soirée appartient bien à cet
 * établissement, puis la même machine à états sur inscriptions.statut.
 */

require_once __DIR__ . '/_socle.php';

apiExigerMethode('POST');

$u   = apiPartenaire($pdo);
$uid = (int) $u['id'];

$corps   = apiCorps();
$qrCode  = trim((string) ($corps['qr_code'] ?? ''));
$eventId = (int) ($corps['event_id'] ?? 0);

foreach (['linkee:', 'studentlink:'] as $prefixe) {
    if (stripos($qrCode, $prefixe) === 0) {
        $qrCode = substr($qrCode, strlen($prefixe));
        break;
    }
}

if ($qrCode === '' || !$eventId) {
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

$stmt = $pdo->prepare(
    'SELECT i.id, i.statut, u.prenom, u.nom
       FROM inscriptions i JOIN users u ON u.id = i.user_id
      WHERE i.qr_code = ? AND i.evenement_id = ?'
);
$stmt->execute([$qrCode, $eventId]);
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

$pdo->prepare("UPDATE inscriptions SET statut = 'checkin' WHERE id = ?")->execute([(int) $inscription['id']]);

apiReponse([
    'success' => true,
    'message' => 'Check-in validé pour ' . $inscription['prenom'] . ' ' . $inscription['nom'],
]);
