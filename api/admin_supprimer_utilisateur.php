<?php
/**
 * Supprime un compte étudiant ou partenaire — réservé à l'administrateur.
 *
 * Même chemin que la suppression demandée par la personne elle-même
 * (includes/compte.php) : abonnement Stripe résilié d'abord, données
 * personnelles effacées, pièces comptables et statistiques conservées sans
 * identité. Un DELETE FROM users laissait courir le prélèvement d'un
 * établissement supprimé et effaçait ses registres financiers en cascade.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/compte.php';
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

if (!in_array($cible['type'], ['etudiant', 'partenaire'], true)) {
    echo json_encode(['success' => false, 'message' => 'Compte déjà supprimé']);
    exit;
}

try {
    if ($cible['type'] === 'etudiant') {
        compteNettoyerApres($pdo, compteSupprimerEtudiant($pdo, $cibleId));
    } else {
        compteNettoyerApresPartenaire($pdo, compteSupprimerPartenaire($pdo, $cibleId));
    }
    echo json_encode(['success' => true, 'message' => 'Compte supprimé']);
} catch (Throwable $e) {
    logErreur('Suppression de compte impossible', $e, ['cible' => $cibleId, 'admin' => $_SESSION['user_id']]);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
