<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'etudiant') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$squad_id = $input['squad_id'] ?? 0;

if (!$squad_id) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

// Ensure the squad belongs to the user
$stmt = $pdo->prepare("SELECT id FROM squads WHERE id = ? AND createur_id = ?");
$stmt->execute([$squad_id, $_SESSION['user_id']]);
$squad = $stmt->fetch();

if (!$squad) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé à supprimer ce squad']);
    exit;
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("DELETE FROM squad_membres WHERE squad_id = ?");
    $stmt->execute([$squad_id]);
    $stmt = $pdo->prepare("DELETE FROM squads WHERE id = ?");
    $stmt->execute([$squad_id]);
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Squad supprimé avec succès']);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
