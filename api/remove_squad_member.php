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
$member_id = $input['member_id'] ?? 0;

if ($member_id == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Vous ne pouvez pas vous retirer vous-même ici.']);
    exit;
}

$stmt = $pdo->prepare("SELECT createur_id FROM squads WHERE id = ?");
$stmt->execute([$squad_id]);
$squad = $stmt->fetch();

if (!$squad || $squad['createur_id'] != $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$stmt = $pdo->prepare("DELETE FROM squad_membres WHERE squad_id = ? AND user_id = ?");
$stmt->execute([$squad_id, $member_id]);

echo json_encode(['success' => true]);
