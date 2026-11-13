<?php
/**
 * POST /api/v1/culture_inscription.php — s'inscrire à un événement culturel,
 * ou se désinscrire.
 *
 *   { "evenement_id": 3 }                        s'inscrire
 *   { "evenement_id": 3, "action": "annuler" }   se désinscrire
 *
 * Un refus métier (complet, terminé, déjà inscrit) répond 409 avec un message
 * fait pour être affiché tel quel, comme les écritures partagées avec le site.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/culture.php';
require_once __DIR__ . '/../../includes/temps_reel.php';

apiExigerMethode('POST');

$u      = apiEtudiant($pdo);
$uid    = (int) $u['id'];
$corps  = apiCorps();
$id     = (int) ($corps['evenement_id'] ?? 0);
$action = ($corps['action'] ?? 'inscrire') === 'annuler' ? 'annuler' : 'inscrire';

if ($id <= 0) {
    apiErreur('Événement invalide', 422, 'id_manquant');
}

if ($action === 'annuler') {
    cultureDesinscrire($pdo, $uid, $id);
} else {
    $refus = cultureInscrire($pdo, $uid, $id);
    if ($refus !== null) {
        apiErreur($refus, 409, 'refus');
    }
}

// Les amis qui regardent la liste voient le compteur bouger à leur prochain
// rafraîchissement du flux, comme pour une soirée.
fluxToucher($pdo, canalCulture($id), canalUtilisateur($uid));

$stmt = $pdo->prepare('SELECT COUNT(*) FROM culture_inscriptions WHERE evenement_id = ?');
$stmt->execute([$id]);

apiReponse([
    'success'  => true,
    'inscrit'  => $action === 'inscrire',
    'inscrits' => (int) $stmt->fetchColumn(),
    'message'  => $action === 'inscrire' ? 'Tu es inscrit !' : 'Inscription annulée',
]);
