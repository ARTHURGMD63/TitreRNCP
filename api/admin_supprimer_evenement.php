<?php
/**
 * Supprime un événement — réservé à l'administrateur.
 *
 * inscriptions et economies référencent evenements.id en ON DELETE CASCADE :
 * supprimer la ligne effacerait aussi les pass des étudiants. La suppression
 * suit donc la règle commune (includes/evenements_suppression.php, PAY-10) :
 * refusée tant qu'un pass non annulé ou un paiement encaissé y est rattaché.
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

$input = json_decode(file_get_contents('php://input'), true);
$evenementId = (int) ($input['evenement_id'] ?? 0);

if (!$evenementId) {
    echo json_encode(['success' => false, 'message' => 'Événement invalide']);
    exit;
}

$resultat = evenementSupprimer($pdo, $evenementId);
if ($resultat['ok']) {
    fluxToucher($pdo, CANAL_GLOBAL);
}
echo json_encode(['success' => $resultat['ok'], 'code' => $resultat['code'], 'message' => $resultat['message']]);
