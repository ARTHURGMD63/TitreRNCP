<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'etudiant') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$evenement_id = $input['evenement_id'] ?? 0;

if (!$evenement_id) {
    echo json_encode(['success' => false, 'message' => 'Événement invalide']);
    exit;
}

// Check quota and if event exists
$stmt = $pdo->prepare("SELECT quota, prix_normal, reduction FROM evenements WHERE id = ? AND date_heure >= NOW()");
$stmt->execute([$evenement_id]);
$event = $stmt->fetch();

if (!$event) {
    echo json_encode(['success' => false, 'message' => 'Événement introuvable ou passé']);
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE evenement_id = ? AND statut != 'annule'");
$stmt->execute([$evenement_id]);
$nbInscrits = $stmt->fetchColumn();

if ($nbInscrits >= $event['quota']) {
    echo json_encode(['success' => false, 'message' => 'Complet']);
    exit;
}

// Generate QR code hash
$qr_code = hash('sha256', $_SESSION['user_id'] . '-' . $evenement_id . '-' . time());

try {
    $pdo->beginTransaction();
    
    // Insert inscription
    $stmt = $pdo->prepare("INSERT INTO inscriptions (user_id, evenement_id, qr_code, statut) VALUES (?, ?, ?, 'inscrit')");
    $stmt->execute([$_SESSION['user_id'], $evenement_id, $qr_code]);
    
    // Add economy if applicable
    if ($event['prix_normal'] > 0 && $event['reduction'] > 0) {
        $montant = round($event['prix_normal'] * $event['reduction'] / 100, 2);
        $stmt = $pdo->prepare("INSERT INTO economies (user_id, evenement_id, montant, date_economie) VALUES (?, ?, ?, CURDATE())");
        $stmt->execute([$_SESSION['user_id'], $evenement_id, $montant]);
    }
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'inscrits' => $nbInscrits + 1]);
} catch (PDOException $e) {
    $pdo->rollBack();
    if ($e->getCode() == '23000') {
        echo json_encode(['success' => false, 'message' => 'Tu es déjà inscrit']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    }
}
