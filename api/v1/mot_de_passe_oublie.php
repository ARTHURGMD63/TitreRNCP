<?php
/**
 * POST /api/v1/mot_de_passe_oublie.php — demander un lien de réinitialisation.
 *
 * Même traitement que auth/forgot.php : l'adresse est validée, un lien part si
 * elle correspond à un compte, et la réponse est la même dans tous les cas —
 * sans quoi l'écran dirait qui est inscrit. Le lien ouvre auth/reset.php sur
 * le site : c'est là que le nouveau mot de passe se choisit, depuis n'importe
 * quel appareil.
 *
 * Corps JSON : email.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/security.php';

apiExigerMethode('POST');

$corps = apiCorps();
$email = trim(is_string($corps['email'] ?? null) ? $corps['email'] : '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiErreur('Adresse email invalide.', 422, 'email');
}

$token = createPasswordResetToken($pdo, $email);
if ($token) {
    // Le lien doit être absolu : il est suivi depuis un client de messagerie.
    sendResetEmail($email, $token, apiUrlAbsolue(baseUrl('')));
}

// En local, rien ne part : sendResetEmail() range le lien pour que l'écran
// l'affiche (« Mode dev »), exactement comme auth/forgot.php.
$lienDev = $_SESSION['dev_reset_link'] ?? null;

apiReponse(['success' => true, 'lien_dev' => is_string($lienDev) ? $lienDev : null]);
