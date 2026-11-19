<?php
/**
 * Déconnexion du site.
 *
 * En POST avec le jeton CSRF seulement : en GET, n'importe quelle page
 * pouvait déconnecter un établissement en glissant
 * <img src=".../auth/logout.php"> (audit SEC-10). Les liens « Se
 * déconnecter » sont des formulaires (formulaireDeconnexion()). Un ancien
 * lien ou favori en GET renvoie simplement vers l'espace du compte.
 */
require_once __DIR__ . '/../includes/auth_check.php'; // demarre la session

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . (isLoggedIn() ? accueilSelonType($_SESSION['user_type'] ?? null) : baseUrl('/auth/login.php')));
    exit;
}
csrfVerify();

// Vider les données de session
$_SESSION = [];

// Supprimer le cookie de session côté navigateur
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: ' . baseUrl('/auth/login.php'));
exit;
