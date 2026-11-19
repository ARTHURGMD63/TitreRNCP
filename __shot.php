<?php
/**
 * Ouverture d'une session de démonstration, pour les captures d'écran.
 *
 * ATTENTION — ce fichier contourne l'authentification. Il est verrouillé par
 * trois conditions cumulatives, de sorte qu'il soit inerte partout ailleurs
 * que sur un poste de développement :
 *
 *   1. la requête vient de la machine locale ;
 *   2. l'application tourne sur un poste de développement, au sens
 *      d'estEnDeveloppement() (includes/config.php) : APP_ENV lu dans
 *      l'environnement ET dans config.local.php, jamais en production ;
 *   3. un fichier témoin .shot-enabled est présent à la racine du projet.
 *
 * Le fichier témoin n'est pas versionné : un déploiement ne l'emporte pas,
 * et l'outil reste donc éteint même si le script part en ligne par erreur.
 *
 * Pour l'activer en local :  touch .shot-enabled
 */

$local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost'], true);
// Même test que le reste de l'application (audit SEC-17) : getenv() seul
// ignorait un app_env posé dans config.local.php, cas des mutualisés.
require_once __DIR__ . '/includes/config.php';
$dev   = estEnDeveloppement();
$actif = is_file(__DIR__ . '/.shot-enabled');

if (!$local || !$dev || !$actif) {
    http_response_code(404);
    exit;
}

session_start();

$as = $_GET['as'] ?? 'etudiant';
if ($as === 'partenaire') {
    $_SESSION['user_id']     = 4;
    $_SESSION['user_type']   = 'partenaire';
    $_SESSION['user_prenom'] = 'Jean';
    $_SESSION['user_nom']    = 'Patron';
    $_SESSION['user_ecole']  = '';
} else {
    $_SESSION['user_id']     = 1;
    $_SESSION['user_type']   = 'etudiant';
    $_SESSION['user_prenom'] = 'Arthur';
    $_SESSION['user_nom']    = 'Gramond';
    $_SESSION['user_ecole']  = 'UCA';
}

$to = $_GET['to'] ?? '/TitreRNCP/explore.php';
if (!preg_match('#^/TitreRNCP/[A-Za-z0-9_/\-]+\.php(\?.*)?$#', $to)) {
    $to = '/TitreRNCP/explore.php';
}
header('Location: ' . $to);
exit;
