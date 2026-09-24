<?php
/**
 * GET  /api/v1/avis.php?event_id=12 — l'écran « Laisser un avis ».
 * POST /api/v1/avis.php {event_id, note, commentaire} — l'enregistrer.
 *
 * Mêmes règles qu'avis.php : il faut avoir été pointé à l'entrée (statut
 * « checkin »), la note va de 1 à 5, le commentaire est coupé à mille
 * caractères, un second envoi modifie le premier. Après l'écriture, les
 * badges sont vérifiés, la note moyenne du lieu est recalculée et le flux
 * temps réel de la soirée est touché — les trois appels du site.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/gamification.php';
require_once __DIR__ . '/../../includes/agregats.php';
require_once __DIR__ . '/../../includes/temps_reel.php';

$moi = apiEtudiant($pdo);
$uid = (int) $moi['id'];

$methode = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$corps   = $methode === 'POST' ? apiCorps() : [];
$eid     = (int) ($methode === 'POST' ? ($corps['event_id'] ?? 0) : ($_GET['event_id'] ?? 0));

if ($eid <= 0) {
    apiErreur('Événement manquant.', 422, 'id_manquant');
}

$stmt = $pdo->prepare("SELECT i.id FROM inscriptions i WHERE i.user_id=? AND i.evenement_id=? AND i.statut='checkin'");
$stmt->execute([$uid, $eid]);
if (!$stmt->fetch()) {
    apiErreur('Tu pourras laisser un avis une fois ton entrée validée.', 403, 'no_checkin');
}

$stmt = $pdo->prepare('SELECT e.*, et.nom AS etab_nom FROM evenements e JOIN etablissements et ON et.id=e.etablissement_id WHERE e.id=?');
$stmt->execute([$eid]);
$event = $stmt->fetch();
if (!$event) {
    apiErreur('Cette soirée n’existe plus', 404, 'introuvable');
}

if ($methode === 'POST') {
    $note        = (int) ($corps['note'] ?? 0);
    $commentaire = substr(trim(is_string($corps['commentaire'] ?? null) ? $corps['commentaire'] : ''), 0, 1000);

    if ($note < 1 || $note > 5) {
        apiErreur('Note invalide.', 422, 'note');
    }

    try {
        $pdo->prepare('INSERT INTO avis (user_id, evenement_id, note, commentaire) VALUES (?,?,?,?)
                       ON DUPLICATE KEY UPDATE note=VALUES(note), commentaire=VALUES(commentaire)')
            ->execute([$uid, $eid, $note, $commentaire]);
        checkBadges($pdo, $uid);
        oublierNotesEtablissements();
        fluxToucher($pdo, canalEvenement($eid));
    } catch (PDOException $e) {
        apiErreur('Erreur lors de l\'enregistrement.', 500, 'enregistrement');
    }

    apiReponse(['success' => true, 'message' => 'Merci pour ton avis !']);
}

apiExigerMethode('GET');

$stmt = $pdo->prepare('SELECT note, commentaire FROM avis WHERE user_id=? AND evenement_id=?');
$stmt->execute([$uid, $eid]);
$existant = $stmt->fetch();

apiReponse([
    'success'   => true,
    'evenement' => [
        'id'            => $eid,
        'titre'         => (string) $event['titre'],
        'etablissement' => (string) $event['etab_nom'],
        'date'          => dateFr((string) $event['date_heure'], 'j F · H\hi'),
    ],
    'avis' => $existant ? ['note' => (int) $existant['note'], 'commentaire' => (string) ($existant['commentaire'] ?? '')] : null,
]);
