<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function baseUrl(string $path = ''): string {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $isLocal = str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');
    $base = $isLocal ? '/TitreRNCP' : '';
    return $base . $path;
}

function requireLogin(string $redirect = ''): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . baseUrl('/auth/login.php'));
        exit;
    }
}

function requirePartner(): void {
    requireLogin();
    if ($_SESSION['user_type'] !== 'partenaire') {
        header('Location: ' . baseUrl('/explore.php'));
        exit;
    }
}

function requireStudent(): void {
    requireLogin();
    if ($_SESSION['user_type'] !== 'etudiant') {
        header('Location: ' . baseUrl('/partenaire/dashboard.php'));
        exit;
    }
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function currentUser(): array {
    return [
        'id'     => $_SESSION['user_id'] ?? null,
        'prenom' => $_SESSION['user_prenom'] ?? '',
        'nom'    => $_SESSION['user_nom'] ?? '',
        'type'   => $_SESSION['user_type'] ?? '',
        'ecole'  => $_SESSION['user_ecole'] ?? '',
    ];
}
