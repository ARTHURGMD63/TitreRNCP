<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'etudiant') {
    echo json_encode(['success' => false]);
    exit;
}

$uid = $_SESSION['user_id'];
$limit = (int)($_GET['limit'] ?? 20);

$stmt = $pdo->prepare("
    SELECT 
        'event_join' AS action_type,
        u.id AS actor_id,
        u.prenom AS actor_prenom,
        u.nom AS actor_nom,
        e.titre AS target_title,
        et.nom AS target_subtitle,
        i.created_at AS action_date,
        e.id AS ref_id
    FROM follows_users fu
    JOIN inscriptions i ON i.user_id = fu.followed_id
    JOIN evenements e ON e.id = i.evenement_id AND e.date_heure >= NOW()
    JOIN users u ON u.id = fu.followed_id
    JOIN etablissements et ON et.id = e.etablissement_id
    WHERE fu.follower_id = ? AND i.statut = 'inscrit'
    
    UNION ALL
    
    SELECT 
        'squad_join' AS action_type,
        u.id AS actor_id,
        u.prenom AS actor_prenom,
        u.nom AS actor_nom,
        s.titre AS target_title,
        CONCAT('Session ', s.type) AS target_subtitle,
        sm.joined_at AS action_date,
        s.id AS ref_id
    FROM follows_users fu
    JOIN squad_membres sm ON sm.user_id = fu.followed_id
    JOIN squads s ON s.id = sm.squad_id AND s.date_heure >= NOW()
    JOIN users u ON u.id = fu.followed_id
    WHERE fu.follower_id = ?
    
    ORDER BY action_date DESC
    LIMIT ?
");
$stmt->execute([$uid, $uid, $limit]);
$feed = $stmt->fetchAll();

echo json_encode(['success' => true, 'feed' => $feed]);
