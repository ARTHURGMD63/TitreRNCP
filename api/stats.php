<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'partenaire') {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$event_id = $_GET['event_id'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE evenement_id = ? AND statut = 'inscrit'");
$stmt->execute([$event_id]);
$inscrits = $stmt->fetchColumn();

echo json_encode(['inscrits' => (int)$inscrits]);
