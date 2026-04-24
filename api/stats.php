<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'partenaire') {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$event_id = (int)($_GET['event_id'] ?? 0);

// Verify the event belongs to this partner
$chk = $pdo->prepare("SELECT e.id FROM evenements e JOIN etablissements et ON et.id = e.etablissement_id WHERE e.id = ? AND et.user_id = ?");
$chk->execute([$event_id, $_SESSION['user_id']]);
if (!$chk->fetch()) {
    echo json_encode(['error' => 'Non autorisé']); exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE evenement_id = ? AND statut = 'inscrit'");
$stmt->execute([$event_id]);
$inscrits = $stmt->fetchColumn();

echo json_encode(['inscrits' => (int)$inscrits]);
