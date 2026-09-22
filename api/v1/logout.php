<?php
/**
 * POST /api/v1/logout.php — revoque le jeton presente.
 *
 * Revoquer ce jeton-la et lui seul : se deconnecter d'un telephone ne doit pas
 * fermer la session de l'iPad ni celle du navigateur.
 */

require_once __DIR__ . '/_socle.php';

apiExigerMethode('POST');

$jeton = apiJetonPresente();
if ($jeton === null) {
    apiErreur('Authentification requise', 401, 'jeton_absent');
}

apiRevoquerJeton($pdo, $jeton);

apiReponse(['success' => true]);
