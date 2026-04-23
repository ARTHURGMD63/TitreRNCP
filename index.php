<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user_id'])) {
    $loc = $_SESSION['user_type'] === 'partenaire'
        ? '/TitreRNCP/partenaire/dashboard.php'
        : '/TitreRNCP/explore.php';
    header('Location: ' . $loc);
    exit;
}
header('Location: /TitreRNCP/auth/login.php');
exit;
