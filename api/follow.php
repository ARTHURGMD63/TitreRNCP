<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'etudiant') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? ''; // 'follow' or 'unfollow'
$type   = $input['type'] ?? '';   // 'user' or 'etablissement'
$target_id = (int)($input['target_id'] ?? 0);
$uid = $_SESSION['user_id'];

if (!$target_id || !in_array($action, ['follow','unfollow']) || !in_array($type, ['user','etablissement'])) {
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
    exit;
}

if ($type === 'user' && $target_id === $uid) {
    echo json_encode(['success' => false, 'message' => 'Tu ne peux pas te suivre toi-même']);
    exit;
}

try {
    if ($type === 'user') {
        if ($action === 'follow') {
            $pdo->prepare("INSERT IGNORE INTO follows_users (follower_id, followed_id) VALUES (?,?)")->execute([$uid, $target_id]);
        } else {
            $pdo->prepare("DELETE FROM follows_users WHERE follower_id=? AND followed_id=?")->execute([$uid, $target_id]);
        }
    } else {
        if ($action === 'follow') {
            $pdo->prepare("INSERT IGNORE INTO follows_etablissements (user_id, etablissement_id) VALUES (?,?)")->execute([$uid, $target_id]);
        } else {
            $pdo->prepare("DELETE FROM follows_etablissements WHERE user_id=? AND etablissement_id=?")->execute([$uid, $target_id]);
        }
    }
    
    // Get updated counts
    if ($type === 'user') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows_users WHERE followed_id=?");
        $stmt->execute([$target_id]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows_etablissements WHERE etablissement_id=?");
        $stmt->execute([$target_id]);
    }
    $count = $stmt->fetchColumn();
    
    echo json_encode(['success' => true, 'action' => $action, 'count' => (int)$count]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
