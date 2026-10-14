<?php
/**
 * POST /api/v1/mot_de_passe_oublie.php — demander un code de réinitialisation.
 *
 * Un code à six chiffres part par e-mail, qui se saisit dans l'application
 * (mot_de_passe_reinitialiser.php). Le lien vers une page du site n'existe
 * plus ici : l'application doit se suffire à elle-même.
 *
 * La réponse est la même dans tous les cas — adresse inconnue, code déjà
 * envoyé il y a moins d'une minute, ou envoi réussi — sans quoi l'écran dirait
 * qui est inscrit.
 *
 * Corps JSON : email.
 * Réponse    : { success: true, code_dev: string|null }. `code_dev` n'est rempli
 *              qu'en local, où rien ne part : l'écran l'affiche (« Mode dev »).
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/compte.php';

apiExigerMethode('POST');

// Sans cette garde, un tiers connaissant l'adresse d'une victime pouvait la
// bombarder de mails de réinitialisation à volonté — nuisance directe, et
// risque de faire flaguer le domaine d'envoi comme source de spam.
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
if (isRateLimited($pdo, $ip, 5, 900)) {
    apiErreur('Trop de tentatives. Réessaie dans quinze minutes.', 429, 'trop_de_tentatives');
}

$corps = apiCorps();
$email = trim(is_string($corps['email'] ?? null) ? $corps['email'] : '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiErreur('Adresse email invalide.', 422, 'email');
}

recordLoginAttempt($pdo, $ip, $email);

$codeDev = null;
$code    = codeReinitialisationCreer($pdo, $email);
if ($code !== null) {
    codeReinitialisationEnvoyer($email, $code);
    if (estEnLocal()) {
        $codeDev = $code;
    }
}

apiReponse(['success' => true, 'code_dev' => $codeDev]);
