<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function requireLogin(string $redirect = '/TitreRNCP/auth/login.php'): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . $redirect);
        exit;
    }
}

function requirePartner(): void {
    requireLogin();
    if ($_SESSION['user_type'] !== 'partenaire') {
        header('Location: /TitreRNCP/explore.php');
        exit;
    }
}

function requireStudent(): void {
    requireLogin();
    if ($_SESSION['user_type'] !== 'etudiant') {
        header('Location: /TitreRNCP/partenaire/dashboard.php');
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
