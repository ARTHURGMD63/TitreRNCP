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

$input = json_decode(file_get_contents('php://input'), true);
$inscription_id = $input['inscription_id'] ?? 0;

if (!$inscription_id) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT i.id, i.evenement_id, e.date_heure
       FROM inscriptions i JOIN evenements e ON e.id = i.evenement_id
      WHERE i.id = ? AND i.user_id = ? AND i.statut = 'inscrit'"
);
$stmt->execute([(int) $inscription_id, $_SESSION['user_id']]);
$inscription = $stmt->fetch();

if (!$inscription) {
    echo json_encode(['success' => false, 'message' => 'Pass introuvable ou déjà utilisé']);
    exit;
}

// Une fois la soirée commencée, un pass ne s'annule plus : sinon une absence
// devenait une « annulation », et les statistiques de présence de
// l'établissement étaient faussées.
if (strtotime((string) $inscription['date_heure']) <= time()) {
    echo json_encode(['success' => false, 'message' => 'La soirée a commencé : le pass ne peut plus être annulé']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE inscriptions SET statut = 'annule' WHERE id = ?");
    $stmt->execute([$inscription['id']]);

    $stmt = $pdo->prepare("DELETE FROM economies WHERE user_id = ? AND evenement_id = ?");
    $stmt->execute([$_SESSION['user_id'], $inscription['evenement_id']]);

    $pdo->commit();

    // Une place vient de se liberer : c'est exactement l'information qui doit
    // remonter tout de suite chez ceux qui attendaient devant un « Complet ».
    fluxToucher($pdo, canalEvenement((int) $inscription['evenement_id']));

    echo json_encode(['success' => true, 'message' => 'Pass annulé avec succès']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logErreur('Annulation de pass impossible', $e, ['inscription' => $inscription['id']]);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
