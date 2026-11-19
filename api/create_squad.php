<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/sports.php';
header('Content-Type: application/json');

// Ecriture : POST obligatoire, jeton CSRF et origine verifies.
// Voir protegerEcritureApi() dans includes/security.php.
protegerEcritureApi();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'etudiant') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$input = is_array($input) ? $input : [];
$texte = static fn (string $cle): string => is_scalar($input[$cle] ?? null) ? trim((string) $input[$cle]) : '';
$titre = mb_substr($texte('titre'), 0, 255);
$type = in_array($input['type'] ?? '', ['running', 'velo', 'muscu', 'autre'], true) ? $input['type'] : 'autre';
// Liste blanche et date contrôlée, comme api/v1/sport_squad.php : une valeur
// hors liste faisait échouer l'INSERT (erreur masquée), et une date passée
// créait un squad qui ne pouvait pas avoir lieu (ETU-03, ETU-06).
$niveau = $texte('niveau') !== '' ? $texte('niveau') : 'tous';
$date_heure = squadDateValide($texte('date_heure'));
$quota = max(2, min(500, (int)($input['quota'] ?? 10)));
$lieu = mb_substr($texte('lieu'), 0, 255);
$desc = mb_substr($texte('description'), 0, 1000);
$association = !empty($input['cree_par_association']) ? 1 : 0;

if (!$titre || $texte('date_heure') === '') {
    echo json_encode(['success' => false, 'message' => 'Veuillez remplir les champs obligatoires']);
    exit;
}
if (!in_array($niveau, niveauxSport(), true)) {
    echo json_encode(['success' => false, 'message' => 'Niveau invalide']);
    exit;
}
if ($date_heure === null) {
    echo json_encode(['success' => false, 'message' => 'La date doit être à venir, dans les douze prochains mois']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO squads (createur_id, type, cree_par_association, titre, description, niveau, date_heure, lieu, quota) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $type, $association, $titre, $desc, $niveau, $date_heure, $lieu, $quota]);
    
    $squad_id = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("INSERT INTO squad_membres (squad_id, user_id) VALUES (?, ?)");
    $stmt->execute([$squad_id, $_SESSION['user_id']]);
    
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la création']);
}
