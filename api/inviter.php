<?php
/**
 * Invitations entre etudiants : envoi, acceptation, refus.
 *
 * L'invitation n'est pas un simple insert : elle doit encore avoir un sens au
 * moment ou la personne l'ouvre. On refuse donc en amont tout ce qui
 * produirait une invitation morte (cible passee ou supprimee, destinataire
 * deja inscrit, doublon) plutot que de laisser l'erreur surgir a
 * l'acceptation, la ou l'invite n'y peut plus rien.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();

header('Content-Type: application/json');

$data   = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $data['action'] ?? 'send';
$moi    = (int) $_SESSION['user_id'];

/** Titre, quota et remplissage de la cible — null si elle n'existe plus ou est passee. */
function cibleValide(PDO $pdo, string $type, int $id): ?array
{
    if ($type === 'event') {
        $stmt = $pdo->prepare(
            "SELECT e.titre, e.quota,
                    (SELECT COUNT(*) FROM inscriptions i
                      WHERE i.evenement_id = e.id AND i.statut != 'annule') AS nb
               FROM evenements e
              WHERE e.id = ? AND e.date_heure >= NOW()"
        );
    } else {
        $stmt = $pdo->prepare(
            "SELECT s.titre, s.quota,
                    (SELECT COUNT(*) FROM squad_membres sm WHERE sm.squad_id = s.id) AS nb
               FROM squads s
              WHERE s.id = ? AND s.date_heure >= NOW()"
        );
    }
    $stmt->execute([$id]);
    $ligne = $stmt->fetch();
    return $ligne ?: null;
}

/** Le destinataire participe-t-il deja ? */
function dejaParticipant(PDO $pdo, string $type, int $cibleId, int $userId): bool
{
    if ($type === 'event') {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM inscriptions
              WHERE evenement_id = ? AND user_id = ? AND statut != 'annule'"
        );
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM squad_membres WHERE squad_id = ? AND user_id = ?");
    }
    $stmt->execute([$cibleId, $userId]);
    return (bool) $stmt->fetchColumn();
}

// ─── Envoi ──────────────────────────────────────────────────────────────────
if ($action === 'send') {
    $vers  = (int) ($data['to_user_id'] ?? 0);
    $type  = in_array($data['type'] ?? '', ['event', 'squad'], true) ? $data['type'] : null;
    $cible = (int) ($data['target_id'] ?? 0);

    if (!$vers || !$type || !$cible) {
        echo json_encode(['success' => false, 'message' => 'Données manquantes']);
        exit;
    }
    if ($vers === $moi) {
        echo json_encode(['success' => false, 'message' => "Tu ne peux pas t'inviter toi-même"]);
        exit;
    }

    // On n'invite que quelqu'un qu'on suit et qui a accepte : sans cela
    // l'invitation devient un canal de message non sollicite.
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM follows_users
          WHERE follower_id = ? AND followed_id = ? AND statut = 'accepted'"
    );
    $stmt->execute([$moi, $vers]);
    if (!$stmt->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => "Cette personne doit d'abord accepter ton abonnement"]);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM user_blocks
          WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)"
    );
    $stmt->execute([$moi, $vers, $vers, $moi]);
    if ($stmt->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Invitation impossible']);
        exit;
    }

    $infos = cibleValide($pdo, $type, $cible);
    if (!$infos) {
        echo json_encode(['success' => false, 'message' => "Cette sortie n'est plus disponible"]);
        exit;
    }
    if ((int) $infos['nb'] >= (int) $infos['quota']) {
        echo json_encode(['success' => false, 'message' => "C'est complet, plus de place à offrir"]);
        exit;
    }
    if (dejaParticipant($pdo, $type, $cible, $vers)) {
        echo json_encode(['success' => false, 'message' => 'Cette personne y participe déjà']);
        exit;
    }

    // La table n'avait pas d'index unique : on verifie avant d'ecrire, et la
    // migration v11 ajoute l'index pour les envois concurrents.
    $stmt = $pdo->prepare(
        "SELECT statut FROM invitations
          WHERE from_user_id = ? AND to_user_id = ? AND type = ? AND target_id = ?"
    );
    $stmt->execute([$moi, $vers, $type, $cible]);
    $existante = $stmt->fetchColumn();
    if ($existante === 'pending') {
        echo json_encode(['success' => false, 'message' => 'Invitation déjà envoyée']);
        exit;
    }
    if ($existante === 'declined') {
        echo json_encode(['success' => false, 'message' => 'Invitation déjà déclinée']);
        exit;
    }

    try {
        $pdo->prepare(
            "INSERT INTO invitations (from_user_id, to_user_id, type, target_id) VALUES (?,?,?,?)"
        )->execute([$moi, $vers, $type, $cible]);
        echo json_encode(['success' => true, 'message' => 'Invitation envoyée !']);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            echo json_encode(['success' => false, 'message' => 'Invitation déjà envoyée']);
        } else {
            logErreur('Invitation impossible', $e, ['de' => $moi, 'vers' => $vers, 'type' => $type]);
            echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
        }
    }
    exit;
}

// ─── Acceptation ────────────────────────────────────────────────────────────
if ($action === 'accept') {
    $inviteId = (int) ($data['invite_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM invitations WHERE id = ? AND to_user_id = ? AND statut = 'pending'");
    $stmt->execute([$inviteId, $moi]);
    $invite = $stmt->fetch();

    if (!$invite) {
        echo json_encode(['success' => false, 'message' => 'Invitation introuvable']);
        exit;
    }

    $cibleId = (int) $invite['target_id'];
    $infos   = cibleValide($pdo, $invite['type'], $cibleId);
    if (!$infos) {
        // L'invitation n'a plus d'objet : on la referme au lieu de la laisser
        // en attente indefiniment dans le profil.
        $pdo->prepare("UPDATE invitations SET statut = 'declined' WHERE id = ?")->execute([$inviteId]);
        echo json_encode(['success' => false, 'message' => "Cette sortie n'est plus disponible"]);
        exit;
    }

    $dedans = dejaParticipant($pdo, $invite['type'], $cibleId, $moi);
    if (!$dedans && (int) $infos['nb'] >= (int) $infos['quota']) {
        echo json_encode(['success' => false, 'message' => "Trop tard, c'est complet"]);
        exit;
    }

    try {
        if ($invite['type'] === 'event') {
            // Meme identifiant de pass que l'inscription directe : imprevisible.
            $pdo->prepare("INSERT INTO inscriptions (user_id, evenement_id, qr_code, statut) VALUES (?,?,?,'inscrit')")
                ->execute([$moi, $cibleId, bin2hex(random_bytes(32))]);
        } else {
            $pdo->prepare("INSERT INTO squad_membres (squad_id, user_id) VALUES (?,?)")
                ->execute([$cibleId, $moi]);
        }
    } catch (PDOException $e) {
        // 23000 : deja inscrit. L'invitation est honoree quand meme.
        if ($e->getCode() !== '23000') {
            logErreur("Acceptation d'invitation impossible", $e, ['invitation' => $inviteId, 'user' => $moi]);
            echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
            exit;
        }
    }

    $pdo->prepare("UPDATE invitations SET statut = 'accepted' WHERE id = ?")->execute([$inviteId]);
    echo json_encode(['success' => true, 'message' => 'Invitation acceptée !']);
    exit;
}

// ─── Refus ──────────────────────────────────────────────────────────────────
if ($action === 'decline') {
    $inviteId = (int) ($data['invite_id'] ?? 0);
    $pdo->prepare("UPDATE invitations SET statut = 'declined' WHERE id = ? AND to_user_id = ? AND statut = 'pending'")
        ->execute([$inviteId, $moi]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action inconnue']);
