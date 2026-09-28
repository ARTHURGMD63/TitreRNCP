<?php
/**
 * Supprime un événement — réservé à l'administrateur.
 *
 * inscriptions et economies référencent evenements.id en ON DELETE CASCADE :
 * supprimer la ligne efface aussi les inscriptions et les économies liées.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
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

$stmt = $pdo->prepare("SELECT id FROM evenements WHERE id = ?");
$stmt->execute([$evenementId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Événement introuvable']);
    exit;
}

try {
    $pdo->prepare("DELETE FROM evenements WHERE id = ?")->execute([$evenementId]);
    echo json_encode(['success' => true, 'message' => 'Événement supprimé']);
} catch (PDOException $e) {
    logErreur('Suppression d\'événement impossible', $e, ['evenement' => $evenementId, 'admin' => $_SESSION['user_id']]);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
