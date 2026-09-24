<?php
/**
 * Inscription à la liste d'attente du lancement.
 *
 * Seul point d'écriture accessible pendant le mode « bientôt disponible »
 * (includes/auth_check.php le laisse passer avec tout /api/) : c'est le
 * formulaire de la page d'accueil, pour des visiteurs qui n'ont pas encore
 * de compte.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

// Ecriture : POST obligatoire, jeton CSRF et origine verifies. Fonctionne
// sans session ouverte : csrfToken() n'exige pas d'utilisateur connecté,
// seulement une session démarrée — ce qu'auth_check.php a déjà fait.
protegerEcritureApi();

$input = json_decode(file_get_contents('php://input'), true);
$email = strtolower(trim((string) ($input['email'] ?? '')));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    echo json_encode(['success' => false, 'message' => 'Adresse e-mail invalide.']);
    exit;
}

try {
    // INSERT IGNORE : une adresse déjà inscrite ne redonne pas d'erreur — le
    // visiteur revient simplement sur une page qui recharge, sans savoir
    // s'il avait déjà réservé sa place la veille.
    $pdo->prepare('INSERT IGNORE INTO liste_attente (email) VALUES (?)')->execute([$email]);

    $count = (int) $pdo->query('SELECT COUNT(*) FROM liste_attente')->fetchColumn();

    echo json_encode(['success' => true, 'count' => $count]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
}
