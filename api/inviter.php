<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? 'send'; // send | accept | decline
$fromId = $_SESSION['user_id'];

header('Content-Type: application/json');

if ($action === 'send') {
    $toId    = (int)($data['to_user_id'] ?? 0);
    $type    = in_array($data['type'] ?? '', ['event', 'squad']) ? $data['type'] : null;
    $targetId = (int)($data['target_id'] ?? 0);

    if (!$toId || !$type || !$targetId) {
        echo json_encode(['success' => false, 'message' => 'Données manquantes']); exit;
    }

    // Check they follow each other or sender follows receiver
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM follows_users WHERE follower_id=? AND followed_id=?");
    $stmtCheck->execute([$fromId, $toId]);
    if (!$stmtCheck->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Tu dois suivre cet utilisateur']); exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO invitations (from_user_id, to_user_id, type, target_id) VALUES (?,?,?,?)");
        $stmt->execute([$fromId, $toId, $type, $targetId]);
        echo json_encode(['success' => true, 'message' => 'Invitation envoyée !']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Invitation déjà envoyée']);
    }

} elseif ($action === 'accept') {
    $inviteId = (int)($data['invite_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM invitations WHERE id=? AND to_user_id=? AND statut='pending'");
    $stmt->execute([$inviteId, $fromId]);
    $invite = $stmt->fetch();

    if (!$invite) { echo json_encode(['success' => false, 'message' => 'Invitation introuvable']); exit; }

    $pdo->prepare("UPDATE invitations SET statut='accepted' WHERE id=?")->execute([$inviteId]);

    // Auto-inscribe to event or squad
    if ($invite['type'] === 'event') {
        try {
            $qr = hash('sha256', $fromId . '-' . $invite['target_id'] . '-' . time());
            $pdo->prepare("INSERT INTO inscriptions (user_id, evenement_id, qr_code) VALUES (?,?,?)")
                ->execute([$fromId, $invite['target_id'], $qr]);
        } catch (PDOException $e) {}
    } elseif ($invite['type'] === 'squad') {
        try {
            $pdo->prepare("INSERT INTO squad_membres (squad_id, user_id) VALUES (?,?)")
                ->execute([$invite['target_id'], $fromId]);
        } catch (PDOException $e) {}
    }

    echo json_encode(['success' => true, 'message' => 'Invitation acceptée !']);

} elseif ($action === 'decline') {
    $inviteId = (int)($data['invite_id'] ?? 0);
    $pdo->prepare("UPDATE invitations SET statut='declined' WHERE id=? AND to_user_id=?")->execute([$inviteId, $fromId]);
    echo json_encode(['success' => true]);
}
