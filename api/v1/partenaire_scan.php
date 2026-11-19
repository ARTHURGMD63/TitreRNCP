<?php
/**
 * POST /api/v1/partenaire_scan.php {qr_code|inscription_id, event_id} —
 * check-in à l'entrée.
 *
 * Reprend partenaire/api_scan.php à l'identique : retrait du préfixe
 * outsee:/linkee:/studentlink: (optionnel, pour accepter aussi une saisie
 * manuelle), vérification que la soirée appartient bien à cet
 * établissement, puis la même machine à états sur inscriptions.statut.
 *
 * `inscription_id` remplace `qr_code` pour la validation manuelle
 * (api/v1/partenaire_invites.php) : quand le scan ne marche pas — plusieurs
 * événements le même soir, lumière, code abîmé — le partenaire choisit la
 * personne dans la liste des inscrits plutôt que de forcer une photo.
 *
 * Depuis l'audit (PAY-17) : refusé hors de la fenêtre de l'événement
 * (evenementScanOuvert(), 409 hors_horaires), et chaque entrée garde qui l'a
 * validée et comment (inscriptions.checkin_mode / checkin_par, v41) : une
 * validation manuelle se distingue d'un scan.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/evenements_temps.php';

apiExigerMethode('POST');

$u   = apiPartenaire($pdo);
$uid = (int) $u['id'];

$corps         = apiCorps();
$qrCode        = trim((string) ($corps['qr_code'] ?? ''));
$inscriptionId = (int) ($corps['inscription_id'] ?? 0);
$eventId       = (int) ($corps['event_id'] ?? 0);

foreach (['outsee:', 'linkee:', 'studentlink:'] as $prefixe) {
    if (stripos($qrCode, $prefixe) === 0) {
        $qrCode = substr($qrCode, strlen($prefixe));
        break;
    }
}

if (($qrCode === '' && !$inscriptionId) || !$eventId) {
    apiErreur('Données manquantes', 422, 'donnees_manquantes');
}

// Un pass est 64 caractères hexadécimaux (bin2hex(random_bytes(32)), voir
// api/inscrire.php). Tout autre QR code — un menu, un site, un code abîmé —
// est refusé avant toute requête, avec un message qui dit ce qui se passe.
if (!$inscriptionId) {
    $qrCode = strtolower(trim($qrCode));
    if (strlen($qrCode) !== 64 || !ctype_xdigit($qrCode)) {
        apiErreur("Ce QR code n'est pas un pass Outsee.", 422, 'qr_invalide');
    }
}

$stmt = $pdo->prepare(
    'SELECT e.id, e.date_heure, e.date_fin FROM evenements e
     JOIN etablissements et ON et.id = e.etablissement_id
    WHERE e.id = ? AND et.user_id = ?'
);
$stmt->execute([$eventId, $uid]);
$evenement = $stmt->fetch();
if (!$evenement) {
    apiErreur('Événement non autorisé', 403, 'evenement_non_autorise');
}
$dateFin = $evenement['date_fin'] !== null ? (string) $evenement['date_fin'] : null;
if (!evenementScanOuvert((string) $evenement['date_heure'], $dateFin)) {
    apiErreur(evenementScanMessageFerme((string) $evenement['date_heure'], $dateFin), 409, 'hors_horaires');
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
    // Le pass existe peut-être pour un autre événement du même partenaire
    // (deux soirées le même week-end) : on le dit, plutôt qu'un « invalide »
    // qui laisse croire à une fraude. Un pass d'un autre partenaire reste
    // « invalide » : son événement ne nous regarde pas.
    if (!$inscriptionId) {
        $autre = $pdo->prepare(
            'SELECT e.titre FROM inscriptions i
               JOIN evenements e ON e.id = i.evenement_id
               JOIN etablissements et ON et.id = e.etablissement_id
              WHERE i.qr_code = ? AND et.user_id = ?
              LIMIT 1'
        );
        $autre->execute([$qrCode, $uid]);
        $titre = $autre->fetchColumn();
        if ($titre !== false) {
            apiErreur('Ce pass est pour « ' . $titre . ' », pas pour cet événement', 409, 'autre_evenement');
        }
    }
    apiErreur('Pass invalide pour cet événement', 404, 'pass_invalide');
}
if ($inscription['statut'] === 'checkin') {
    apiErreur('Ce pass a déjà été scanné', 409, 'deja_scanne');
}
if ($inscription['statut'] === 'annule') {
    apiErreur('Inscription annulée', 409, 'inscription_annulee');
}

// La condition est dans l'UPDATE, pas seulement dans le SELECT plus haut :
// deux téléphones qui scannent le même pass à la même seconde lisaient tous
// deux « inscrit » et validaient tous deux. Seul celui qui modifie
// réellement la ligne entre ; l'autre reçoit « déjà scanné ».
$maj = $pdo->prepare(
    "UPDATE inscriptions SET statut = 'checkin', checkin_le = NOW(), checkin_mode = ?, checkin_par = ?
      WHERE id = ? AND statut NOT IN ('checkin', 'annule')"
);
$maj->execute([$inscriptionId ? 'manuel' : 'scan', $uid, (int) $inscription['id']]);
if ($maj->rowCount() === 0) {
    apiErreur('Ce pass a déjà été scanné', 409, 'deja_scanne');
}

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
