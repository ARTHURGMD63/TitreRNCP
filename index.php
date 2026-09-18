<?php
require_once __DIR__ . '/includes/auth_check.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . accueilSelonType($_SESSION['user_type'] ?? null));
    exit;
}
header('Location: ' . baseUrl('/auth/login.php'));
exit;
