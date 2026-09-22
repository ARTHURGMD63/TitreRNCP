<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/social.php';
require_once __DIR__ . '/../includes/gamification.php';
require_once __DIR__ . '/../includes/temps_reel.php';
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
$type      = $input['type'] ?? '';
$target_id = (int) ($input['target_id'] ?? 0);
$uid       = (int) $_SESSION['user_id'];

// follow   : envoie une demande (ou s'abonne, pour un établissement)
// unfollow : annule une demande en attente, ou se désabonne
// accept / decline : réservés à la personne visée par la demande
$actions = ['follow', 'unfollow', 'accept', 'decline'];

if (!$target_id || !in_array($action, $actions, true) || !in_array($type, ['user', 'etablissement'], true)) {
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
    exit;
}

if ($type === 'user' && $target_id === $uid) {
    echo json_encode(['success' => false, 'message' => 'Tu ne peux pas te suivre toi-même']);
    exit;
}

// Un établissement reste un abonnement libre : c'est un lieu public,
// il n'y a personne pour accepter et rien de privé à protéger.
if ($type === 'etablissement' && !in_array($action, ['follow', 'unfollow'], true)) {
    echo json_encode(['success' => false, 'message' => 'Action impossible sur un établissement']);
    exit;
}

try {
    if ($type === 'etablissement') {
        if ($action === 'follow') {
            $pdo->prepare("INSERT IGNORE INTO follows_etablissements (user_id, etablissement_id) VALUES (?,?)")
                ->execute([$uid, $target_id]);
            $etat = FOLLOW_ACCEPTED;
        } else {
            $pdo->prepare("DELETE FROM follows_etablissements WHERE user_id=? AND etablissement_id=?")
                ->execute([$uid, $target_id]);
            $etat = FOLLOW_NONE;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows_etablissements WHERE etablissement_id=?");
        $stmt->execute([$target_id]);
        checkBadges($pdo, $uid);

        echo json_encode(['success' => true, 'etat' => $etat, 'count' => (int) $stmt->fetchColumn()]);
        exit;
    }

    // ── Abonnement entre étudiants ──────────────────────────────────────
    if (isBlockedBetween($pdo, $uid, $target_id)) {
        echo json_encode(['success' => false, 'message' => 'Action indisponible']);
        exit;
    }

    if ($action === 'follow') {
        // La demande naît en attente : c'est la personne visée qui ouvre l'accès.
        $pdo->prepare("INSERT IGNORE INTO follows_users (follower_id, followed_id, statut) VALUES (?,?, 'pending')")
            ->execute([$uid, $target_id]);
        $etat    = followState($pdo, $uid, $target_id);
        $message = $etat === FOLLOW_ACCEPTED ? 'Tu suis déjà cette personne' : 'Demande envoyée';

    } elseif ($action === 'unfollow') {
        // Couvre l'annulation d'une demande et le désabonnement.
        $pdo->prepare("DELETE FROM follows_users WHERE follower_id=? AND followed_id=?")
            ->execute([$uid, $target_id]);
        $etat    = FOLLOW_NONE;
        $message = 'Abonnement retiré';

    } else {
        // accept / decline : $target_id est ici la personne qui a demandé.
        $stmt = $pdo->prepare(
            "SELECT 1 FROM follows_users WHERE follower_id=? AND followed_id=? AND statut='pending'"
        );
        $stmt->execute([$target_id, $uid]);
        if (!$stmt->fetchColumn()) {
            echo json_encode(['success' => false, 'message' => 'Demande introuvable']);
            exit;
        }

        if ($action === 'accept') {
            $pdo->prepare("UPDATE follows_users SET statut='accepted', responded_at=NOW()
                            WHERE follower_id=? AND followed_id=?")
                ->execute([$target_id, $uid]);
            $message = 'Demande acceptée';
        } else {
            // Un refus efface la ligne : pas d'état mort qui empêcherait de
            // redemander plus tard. Contre l'insistance, c'est le blocage qui agit.
            $pdo->prepare("DELETE FROM follows_users WHERE follower_id=? AND followed_id=?")
                ->execute([$target_id, $uid]);
            $message = 'Demande refusée';
        }
        $etat = null; // sans objet : l'action porte sur la demande d'un tiers
    }

    // Seuls les abonnements acceptés sont comptés.
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows_users WHERE followed_id=? AND statut='accepted'");
    $stmt->execute([$action === 'follow' || $action === 'unfollow' ? $target_id : $uid]);
    $count = (int) $stmt->fetchColumn();

    checkBadges($pdo, $uid);

    // Les deux cotes de la relation changent d'etat : celui qui demande voit
    // son bouton bouger, celui qui recoit voit sa pastille s'incrementer. Les
    // deux canaux sont donc touches, et la cloche d'en face se met a jour sans
    // que la personne ait besoin de recharger quoi que ce soit.
    fluxToucher($pdo, canalUtilisateur($uid), canalUtilisateur($target_id));

    echo json_encode([
        'success'  => true,
        'etat'     => $etat,
        'count'    => $count,
        'message'  => $message,
        'attentes' => pendingFollowCount($pdo, $uid),
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
