<?php
/**
 * Annule un événement — réservé à l'administrateur.
 *
 * Un événement qui a des inscrits ne se supprime plus (PAY-10) : pour le
 * retirer (contenu illicite, établissement injoignable), on l'annule. Ses
 * pass sont annulés, les inscrits prévenus, et il sort du fil. Contrairement
 * à l'établissement, l'administrateur peut aussi annuler un événement passé.
 * Règle commune : includes/evenements_suppression.php.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/evenements_suppression.php';
require_once __DIR__ . '/../includes/temps_reel.php';
header('Content-Type: application/json');

// Ecriture : POST obligatoire, jeton CSRF et origine verifies.
// Voir protegerEcritureApi() dans includes/security.php.
protegerEcritureApi();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$input       = json_decode((string) file_get_contents('php://input'), true);
$evenementId = (int) ($input['evenement_id'] ?? 0);
$motif       = is_string($input['motif'] ?? null) ? $input['motif'] : null;

if (!$evenementId) {
    echo json_encode(['success' => false, 'message' => 'Événement invalide']);
    exit;
}

$resultat = evenementAnnuler($pdo, $evenementId, null, (int) $_SESSION['user_id'], $motif);
if ($resultat['ok']) {
    fluxToucher($pdo, CANAL_GLOBAL);
}
echo json_encode(['success' => $resultat['ok'], 'code' => $resultat['code'], 'message' => $resultat['message']]);
