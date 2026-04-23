<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'partenaire') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$qr_code = $input['qr_code'] ?? '';
$event_id = $input['event_id'] ?? 0;

if (!$qr_code || !$event_id) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

// Verify the event belongs to this partner
$stmt = $pdo->prepare("
    SELECT e.id FROM evenements e
    JOIN etablissements et ON et.id = e.etablissement_id
    WHERE e.id = ? AND et.user_id = ?
");
$stmt->execute([$event_id, $_SESSION['user_id']]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Événement non autorisé']);
    exit;
}

// Find the inscription
$stmt = $pdo->prepare("
    SELECT i.id, i.statut, u.prenom, u.nom 
    FROM inscriptions i
    JOIN users u ON u.id = i.user_id
    WHERE i.qr_code = ? AND i.evenement_id = ?
");
$stmt->execute([$qr_code, $event_id]);
$inscription = $stmt->fetch();

if (!$inscription) {
    echo json_encode(['success' => false, 'message' => 'Pass invalide pour cet événement']);
    exit;
}

if ($inscription['statut'] === 'checkin') {
    echo json_encode(['success' => false, 'message' => 'Ce pass a déjà été scanné']);
    exit;
}

if ($inscription['statut'] === 'annule') {
    echo json_encode(['success' => false, 'message' => 'Inscription annulée']);
    exit;
}

// Update status to checkin
$stmt = $pdo->prepare("UPDATE inscriptions SET statut = 'checkin' WHERE id = ?");
if ($stmt->execute([$inscription['id']])) {
    echo json_encode([
        'success' => true, 
        'message' => 'Check-in validé pour ' . htmlspecialchars($inscription['prenom'] . ' ' . $inscription['nom'])
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
}
