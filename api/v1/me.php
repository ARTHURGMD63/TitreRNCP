<?php
/**
 * GET /api/v1/me.php — le compte rattache au jeton.
 *
 * L'application l'appelle au lancement : si la reponse est 401, le jeton
 * range dans le trousseau est perime ou revoque, et il faut redemander une
 * connexion. C'est aussi ce qui prolonge le jeton (voir apiUtilisateur()).
 */

require_once __DIR__ . '/_socle.php';

apiExigerMethode('GET');

$u = apiUtilisateur($pdo);

apiReponse(['success' => true, 'utilisateur' => apiProfil($u)]);
