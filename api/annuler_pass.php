<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'etudiant') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$inscription_id = $input['inscription_id'] ?? 0;

if (!$inscription_id) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, evenement_id FROM inscriptions WHERE id = ? AND user_id = ? AND statut = 'inscrit'");
$stmt->execute([$inscription_id, $_SESSION['user_id']]);
$inscription = $stmt->fetch();

if (!$inscription) {
    echo json_encode(['success' => false, 'message' => 'Pass introuvable ou déjà utilisé']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE inscriptions SET statut = 'annule' WHERE id = ?");
    $stmt->execute([$inscription['id']]);

    $stmt = $pdo->prepare("DELETE FROM economies WHERE user_id = ? AND evenement_id = ?");
    $stmt->execute([$_SESSION['user_id'], $inscription['evenement_id']]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Pass annulé avec succès']);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
