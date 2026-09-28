<?php
/**
 * Supprime un compte étudiant ou partenaire — réservé à l'administrateur.
 *
 * Toutes les tables qui référencent users.id le font en ON DELETE CASCADE
 * (ou SET NULL pour les journaux d'admin) : supprimer la ligne suffit à
 * effacer aussi ses inscriptions, squads, abonnements, jetons, etc.
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
$cibleId = (int) ($input['user_id'] ?? 0);

if (!$cibleId) {
    echo json_encode(['success' => false, 'message' => 'Compte invalide']);
    exit;
}

if ($cibleId === (int) $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Impossible de supprimer son propre compte']);
    exit;
}

$stmt = $pdo->prepare("SELECT type FROM users WHERE id = ?");
$stmt->execute([$cibleId]);
$cible = $stmt->fetch();

if (!$cible) {
    echo json_encode(['success' => false, 'message' => 'Compte introuvable']);
    exit;
}

// Un administrateur ne se supprime jamais l'un l'autre depuis cet écran :
// cette suppression-là exige un accès direct à la base, pas un clic.
if ($cible['type'] === 'admin') {
    echo json_encode(['success' => false, 'message' => 'Impossible de supprimer un compte administrateur']);
    exit;
}

try {
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$cibleId]);
    echo json_encode(['success' => true, 'message' => 'Compte supprimé']);
} catch (PDOException $e) {
    logErreur('Suppression de compte impossible', $e, ['cible' => $cibleId, 'admin' => $_SESSION['user_id']]);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
