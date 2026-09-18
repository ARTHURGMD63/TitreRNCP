<?php
/**
 * Crée ou promeut un compte fondateur (type `admin`).
 *
 * En ligne de commande uniquement. Un formulaire web qui fabrique des comptes
 * administrateurs est une porte ouverte : le jour où il est oublié en ligne,
 * il donne le back-office entier. Ici, il faut déjà un accès au serveur.
 *
 * Usage :
 *   php outils/creer_admin.php "prenom" "nom" "email" "motdepasse"
 *   php outils/creer_admin.php --promouvoir "email"
 *
 * Le mot de passe apparaît dans l'historique du shell : changez-le depuis
 * « Mot de passe oublié » après la première connexion, ou passez par
 * --promouvoir sur un compte créé normalement par le formulaire d'inscription.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Cet outil ne s'utilise qu'en ligne de commande.\n");
}

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

$args = array_slice($argv, 1);

function sortir(string $message, int $code = 1): never
{
    fwrite($code === 0 ? STDOUT : STDERR, $message . "\n");
    exit($code);
}

// ─── Promotion d'un compte existant ─────────────────────────────────────────
if (($args[0] ?? '') === '--promouvoir') {
    $email = trim($args[1] ?? '');
    if ($email === '') sortir('Usage : php outils/creer_admin.php --promouvoir "email"');

    $stmt = $pdo->prepare("SELECT id, prenom, nom, type FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $compte = $stmt->fetch();

    if (!$compte) sortir("Aucun compte avec l'email $email.");
    if ($compte['type'] === 'admin') sortir("{$compte['prenom']} {$compte['nom']} est déjà administrateur.", 0);

    $pdo->prepare("UPDATE users SET type = 'admin' WHERE id = ?")->execute([$compte['id']]);
    sortir("{$compte['prenom']} {$compte['nom']} ({$email}) est maintenant administrateur.", 0);
}

// ─── Création ───────────────────────────────────────────────────────────────
if (count($args) < 4) {
    sortir(
        "Usage :\n"
        . "  php outils/creer_admin.php \"prenom\" \"nom\" \"email\" \"motdepasse\"\n"
        . "  php outils/creer_admin.php --promouvoir \"email\""
    );
}

[$prenom, $nom, $email, $motdepasse] = $args;

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) sortir("Email invalide : $email");
// Plus exigeant que le formulaire public : ce compte voit la base clients,
// les coordonnées des étudiants et la trésorerie. La règle vit dans
// security.php, pour qu'elle s'applique aussi à la réinitialisation — sinon
// un fondateur redescend sous le plancher par « mot de passe oublié ».
$minimum = longueurMinimaleMotDePasse('admin');
if (strlen($motdepasse) < $minimum) {
    sortir("Le mot de passe d'un compte fondateur doit faire au moins $minimum caractères.");
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) sortir("Un compte existe déjà avec $email. Utilisez --promouvoir.");

$pdo->prepare(
    "INSERT INTO users (nom, prenom, email, password, type, cgu_acceptees_le)
     VALUES (?,?,?,?,'admin',NOW())"
)->execute([$nom, $prenom, $email, password_hash($motdepasse, PASSWORD_DEFAULT)]);

sortir("Compte fondateur créé : $prenom $nom <$email>\nConnexion : /auth/login.php puis /admin/", 0);
