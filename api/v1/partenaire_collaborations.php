<?php
/**
 * Les événements en collaboration, côté établissement (migration v39).
 *
 *   GET  /api/v1/partenaire_collaborations.php?q=…
 *        les établissements qu'on peut inviter (nom ou ville), pour le
 *        formulaire de création d'un événement
 *   GET  /api/v1/partenaire_collaborations.php?invitations=1
 *        les invitations reçues : en attente d'abord, puis les
 *        collaborations acceptées d'événements à venir
 *   POST { "action": "accepter" | "refuser" | "quitter", "id": 7 }
 *        répondre à une invitation reçue, ou quitter une collaboration
 *
 * Inviter se fait en enregistrant l'événement (partenaire_evenement.php,
 * champ `collaborateur_id`) : l'invitation naît avec lui.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/collaborations.php';
require_once __DIR__ . '/../../includes/temps_reel.php';

$methode = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
apiExigerMethode($methode === 'POST' ? 'POST' : 'GET');

$u    = apiPartenaire($pdo);
$etab = apiEtablissementDe($pdo, (int) $u['id']);
if (!$etab) {
    apiErreur('Aucun établissement associé à ce compte.', 404, 'etablissement_absent');
}
$etabId = (int) $etab['id'];

if ($methode === 'GET') {
    if (!empty($_GET['invitations'])) {
        apiReponse(['success' => true, 'invitations' => collaborationInvitationsRecues($pdo, $etabId)]);
    }
    apiReponse([
        'success'        => true,
        'etablissements' => collaborationRechercher($pdo, $etabId, (string) ($_GET['q'] ?? '')),
    ]);
}

$corps  = apiCorps();
$action = (string) ($corps['action'] ?? '');
$id     = (int) ($corps['id'] ?? 0);

$erreur = collaborationRepondre($pdo, $etabId, $id, $action);
if ($erreur !== null) {
    apiErreur($erreur, $action === 'accepter' || $action === 'refuser' || $action === 'quitter' ? 409 : 400, 'collaboration');
}

// Accepter ou quitter change ce que voient les étudiants sur la carte.
if ($action !== 'refuser') {
    fluxToucher($pdo, CANAL_GLOBAL);
}

apiReponse([
    'success' => true,
    'message' => match ($action) {
        'accepter' => 'Collaboration acceptée : ton nom apparaît sur l’événement.',
        'refuser'  => 'Invitation refusée.',
        default    => 'Tu ne collabores plus à cet événement.',
    },
]);
