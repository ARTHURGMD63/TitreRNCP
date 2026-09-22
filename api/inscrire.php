<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/temps_reel.php';
header('Content-Type: application/json');

// Ecriture : POST obligatoire, jeton CSRF et origine verifies.
// Voir protegerEcritureApi() dans includes/security.php.
protegerEcritureApi();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'etudiant') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$input        = json_decode(file_get_contents('php://input'), true);
$evenement_id = (int) ($input['evenement_id'] ?? 0);
$uid          = (int) $_SESSION['user_id'];

if (!$evenement_id) {
    echo json_encode(['success' => false, 'message' => 'Événement invalide']);
    exit;
}

try {
    $pdo->beginTransaction();

    /*
     * Le contrôle du quota et l'insertion doivent être atomiques. Auparavant
     * le comptage se faisait hors transaction : deux étudiants qui cliquaient
     * en même temps sur la dernière place lisaient le même total et passaient
     * tous les deux. FOR UPDATE verrouille la ligne de l'événement, donc les
     * inscriptions concurrentes sur un même événement se suivent au lieu de
     * se chevaucher.
     */
    $stmt = $pdo->prepare(
        "SELECT quota, prix_normal, reduction
           FROM evenements
          WHERE id = ? AND date_heure >= NOW()
          FOR UPDATE"
    );
    $stmt->execute([$evenement_id]);
    $event = $stmt->fetch();

    if (!$event) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Événement introuvable ou passé']);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM inscriptions WHERE evenement_id = ? AND statut != 'annule'"
    );
    $stmt->execute([$evenement_id]);
    $nbInscrits = (int) $stmt->fetchColumn();

    if ($nbInscrits >= (int) $event['quota']) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Complet']);
        exit;
    }

    // Identifiant du pass : imprévisible, et propre à ce couple étudiant/événement.
    $qr_code = bin2hex(random_bytes(32));

    $pdo->prepare(
        "INSERT INTO inscriptions (user_id, evenement_id, qr_code, statut)
         VALUES (?, ?, ?, 'inscrit')"
    )->execute([$uid, $evenement_id, $qr_code]);

    if ($event['prix_normal'] > 0 && $event['reduction'] > 0) {
        $montant = round($event['prix_normal'] * $event['reduction'] / 100, 2);
        $pdo->prepare(
            "INSERT INTO economies (user_id, evenement_id, montant, date_economie)
             VALUES (?, ?, ?, CURDATE())"
        )->execute([$uid, $evenement_id, $montant]);
    }

    $pdo->commit();

    // Le compteur affiche desormais une valeur de plus : les autres onglets
    // ouverts sur cette soiree doivent l'apprendre a leur prochaine
    // interrogation, et non au prochain rechargement de page.
    fluxToucher($pdo, canalEvenement($evenement_id));

    echo json_encode(['success' => true, 'inscrits' => $nbInscrits + 1]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e->getCode() === '23000') {
        echo json_encode(['success' => false, 'message' => 'Tu es déjà inscrit']);
    } else {
        logErreur('Inscription impossible', $e, ['evenement' => $evenement_id, 'user' => $uid]);
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    }
}
