<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'etudiant') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$squad_id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT createur_id FROM squads WHERE id = ?");
$stmt->execute([$squad_id]);
$squad = $stmt->fetch();

if (!$squad || $squad['createur_id'] != $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT u.id, u.prenom, u.nom, u.ecole
    FROM squad_membres sm
    JOIN users u ON u.id = sm.user_id
    WHERE sm.squad_id = ?
    ORDER BY sm.joined_at ASC
");
$stmt->execute([$squad_id]);
$members = $stmt->fetchAll();

echo json_encode(['success' => true, 'members' => $members, 'my_id' => $_SESSION['user_id']]);
