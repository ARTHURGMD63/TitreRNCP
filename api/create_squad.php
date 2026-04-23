<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'etudiant') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$titre = trim($input['titre'] ?? '');
$type = $input['type'] ?? 'autre';
$niveau = $input['niveau'] ?? 'tous';
$date_heure = $input['date_heure'] ?? '';
$quota = (int)($input['quota'] ?? 10);
$lieu = trim($input['lieu'] ?? '');
$desc = trim($input['description'] ?? '');

if (!$titre || !$date_heure) {
    echo json_encode(['success' => false, 'message' => 'Veuillez remplir les champs obligatoires']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("INSERT INTO squads (createur_id, type, titre, description, niveau, date_heure, lieu, quota) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $type, $titre, $desc, $niveau, $date_heure, $lieu, $quota]);
    
    $squad_id = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("INSERT INTO squad_membres (squad_id, user_id) VALUES (?, ?)");
    $stmt->execute([$squad_id, $_SESSION['user_id']]);
    
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la création']);
}
