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

$stmt = $pdo->prepare("DELETE FROM squad_membres WHERE squad_id = ? AND user_id = ?");
if ($stmt->execute([$squad_id, $_SESSION['user_id']])) {
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Squad quitté avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Tu ne fais pas partie de ce squad']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
