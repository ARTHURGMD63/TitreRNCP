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
    echo json_encode(['success' => false, 'message' => 'Squad invalide']);
    exit;
}

$stmt = $pdo->prepare("SELECT quota FROM squads WHERE id = ? AND date_heure >= NOW()");
$stmt->execute([$squad_id]);
$squad = $stmt->fetch();

if (!$squad) {
    echo json_encode(['success' => false, 'message' => 'Squad introuvable']);
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM squad_membres WHERE squad_id = ?");
$stmt->execute([$squad_id]);
$nbMembres = $stmt->fetchColumn();

if ($nbMembres >= $squad['quota']) {
    echo json_encode(['success' => false, 'message' => 'Complet']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO squad_membres (squad_id, user_id) VALUES (?, ?)");
    $stmt->execute([$squad_id, $_SESSION['user_id']]);
    echo json_encode(['success' => true, 'membres' => $nbMembres + 1]);
} catch (PDOException $e) {
    if ($e->getCode() == '23000') {
        echo json_encode(['success' => false, 'message' => 'Tu es déjà membre']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    }
}
