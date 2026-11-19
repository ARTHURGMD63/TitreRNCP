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
require_once __DIR__ . '/../includes/liste_attente.php';
header('Content-Type: application/json');

// Ecriture : POST obligatoire, jeton CSRF et origine verifies. Fonctionne
// sans session ouverte : csrfToken() n'exige pas d'utilisateur connecté,
// seulement une session démarrée — ce qu'auth_check.php a déjà fait.
protegerEcritureApi();

$input   = json_decode(file_get_contents('php://input'), true);
$email   = strtolower(trim((string) ($input['email'] ?? '')));
$parrain = trim((string) ($input['parrain'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    echo json_encode(['success' => false, 'message' => 'Adresse e-mail invalide.']);
    exit;
}

try {
    // Le parrain n'est retenu que s'il correspond à une inscription réelle :
    // un code inventé ou périmé s'ignore silencieusement, il ne doit pas
    // faire échouer l'inscription de celui qui le porte.
    $parrainId = $parrain !== '' ? idListeAttenteDepuisCode($pdo, $parrain) : null;

    // INSERT IGNORE : une adresse déjà inscrite ne redonne pas d'erreur — le
    // visiteur revient simplement sur une page qui recharge, sans savoir
    // s'il avait déjà réservé sa place la veille. Le parrain n'est posé qu'à
    // la création : un revenant ne change pas de parrain au second passage.
    // Code aléatoire (ETU-12). INSERT IGNORE se tait aussi sur un code déjà
    // pris : si l'adresse n'est pas en base, c'est le code qui a coïncidé,
    // on en tire un autre (une chance sur 10^15).
    $id = 0;
    for ($essai = 0; $essai < 3 && $id === 0; $essai++) {
        $stmt = $pdo->prepare('INSERT IGNORE INTO liste_attente (email, parrain_id, code) VALUES (?, ?, ?)');
        $stmt->execute([$email, $parrainId, codeParrainageAleatoire()]);

        if ($stmt->rowCount() > 0) {
            $id = (int) $pdo->lastInsertId();
        } else {
            $existant = $pdo->prepare('SELECT id FROM liste_attente WHERE email = ?');
            $existant->execute([$email]);
            $id = (int) $existant->fetchColumn();
        }
    }

    $statut = statutListeAttente($pdo, $id);
    $count  = (int) $pdo->query('SELECT COUNT(*) FROM liste_attente')->fetchColumn();

    echo json_encode(['success' => true, 'count' => $count] + ($statut ?? []));
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
}
