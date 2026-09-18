<?php
require_once __DIR__ . '/../includes/auth_check.php'; // demarre la session

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
