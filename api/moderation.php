<?php
/**
 * Blocage et signalement.
 *
 * Exigé par les stores pour toute application sociale (Apple, règle 1.2 :
 * contenu généré par les utilisateurs). Bloquer coupe le lien dans les deux
 * sens et supprime les abonnements existants ; signaler dépose une trace
 * consultable côté administration.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/social.php';
header('Content-Type: application/json');

// Ecriture : POST obligatoire, jeton CSRF et origine verifies.
// Voir protegerEcritureApi() dans includes/security.php.
protegerEcritureApi();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'etudiant') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true);
$action    = $input['action'] ?? '';
$target_id = (int) ($input['target_id'] ?? 0);
$uid       = (int) $_SESSION['user_id'];

if (!$target_id || $target_id === $uid) {
    echo json_encode(['success' => false, 'message' => 'Cible invalide']);
    exit;
}

$stmt = $pdo->prepare("SELECT 1 FROM users WHERE id = ? AND type = 'etudiant'");
$stmt->execute([$target_id]);
if (!$stmt->fetchColumn()) {
    echo json_encode(['success' => false, 'message' => 'Étudiant introuvable']);
    exit;
}

try {
    switch ($action) {
        case 'block':
            $pdo->prepare("INSERT IGNORE INTO user_blocks (blocker_id, blocked_id) VALUES (?,?)")
                ->execute([$uid, $target_id]);
            // Un blocage rompt le lien existant dans les deux sens, demandes
            // en attente comprises : sinon la personne resterait abonnée.
            $pdo->prepare("DELETE FROM follows_users
                            WHERE (follower_id=? AND followed_id=?)
                               OR (follower_id=? AND followed_id=?)")
                ->execute([$uid, $target_id, $target_id, $uid]);
            // Et les invitations non tranchées échangées avec cette personne.
            $pdo->prepare("DELETE FROM invitations
                            WHERE statut='pending'
                              AND ((from_user_id=? AND to_user_id=?) OR (from_user_id=? AND to_user_id=?))")
                ->execute([$uid, $target_id, $target_id, $uid]);

            echo json_encode(['success' => true, 'bloque' => true, 'message' => 'Personne bloquée']);
            break;

        case 'unblock':
            $pdo->prepare("DELETE FROM user_blocks WHERE blocker_id=? AND blocked_id=?")
                ->execute([$uid, $target_id]);
            echo json_encode(['success' => true, 'bloque' => false, 'message' => 'Blocage levé']);
            break;

        case 'report':
            $motifs = ['spam', 'harcelement', 'contenu_inapproprie', 'usurpation', 'autre'];
            $motif  = in_array($input['motif'] ?? '', $motifs, true) ? $input['motif'] : 'autre';
            $details = trim((string) ($input['details'] ?? ''));
            if (mb_strlen($details) > 500) {
                $details = mb_substr($details, 0, 500);
            }

            // Un même signalement non traité ne se duplique pas.
            $stmt = $pdo->prepare("SELECT 1 FROM user_reports
                                    WHERE reporter_id=? AND reported_id=? AND statut='nouveau' LIMIT 1");
            $stmt->execute([$uid, $target_id]);
            if ($stmt->fetchColumn()) {
                echo json_encode(['success' => true, 'message' => 'Signalement déjà enregistré']);
                exit;
            }

            $pdo->prepare("INSERT INTO user_reports (reporter_id, reported_id, motif, details) VALUES (?,?,?,?)")
                ->execute([$uid, $target_id, $motif, $details !== '' ? $details : null]);

            echo json_encode(['success' => true, 'message' => 'Signalement envoyé. Merci.']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action inconnue']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
