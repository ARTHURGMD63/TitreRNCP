<?php
/**
 * POST /api/v1/mot_de_passe_reinitialiser.php — choisir un nouveau mot de passe
 * avec le code reçu par e-mail.
 *
 * Corps JSON : email, code (six chiffres), mot_de_passe.
 *
 * Toute erreur sur l'adresse ou le code — compte inconnu, code faux, expiré,
 * épuisé — se dit pareil : « Code invalide ou expiré ». Dire lequel des deux
 * est faux apprendrait à un tiers quelles adresses ont un compte.
 *
 * Après un succès, tous les appareils sont déconnectés (jetons révoqués) : si
 * quelqu'un d'autre tenait la session, c'est ce qu'on cherche à couper.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/compte.php';

apiExigerMethode('POST');

// Un code à six chiffres se devine en un million d'essais : la limite par
// code (cinq saisies) est la vraie protection, celle-ci borne en plus ce
// qu'une même machine peut tenter sur plusieurs adresses.
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
if (isRateLimited($pdo, $ip, 15, 900)) {
    apiErreur('Trop de tentatives. Réessaie dans quinze minutes.', 429, 'trop_de_tentatives');
}

$corps = apiCorps();
$email = trim(is_string($corps['email'] ?? null) ? $corps['email'] : '');
$code  = trim(is_string($corps['code'] ?? null) ? $corps['code'] : '');
$pass  = is_string($corps['mot_de_passe'] ?? null) ? $corps['mot_de_passe'] : '';

// Seuls les échecs comptent contre la limite : une personne qui réussit ne doit
// pas se retrouver bloquée en voulant se connecter juste après.
$refuser = static function () use ($pdo, $ip, $email): never {
    recordLoginAttempt($pdo, $ip, $email);
    apiErreur('Code invalide ou expiré.', 422, 'code_invalide');
};

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $code)) {
    $refuser();
}

$compte = compteParEmail($pdo, $email);
if ($compte === null || !codeReinitialisationVerifier($pdo, $compte['email'], $code)) {
    $refuser();
}

// Le code est juste. Un mot de passe refusé ne le brûle pas : la personne
// corrige et renvoie, elle ne recommence pas depuis l'e-mail.
$minimum = longueurMinimaleMotDePasse($compte['type']);
if (mb_strlen($pass) < $minimum) {
    apiErreur("Le mot de passe doit faire au moins $minimum caractères.", 422, 'mot_de_passe');
}

codeReinitialisationAppliquer($pdo, $compte['id'], $compte['email'], $pass);
clearLoginAttempts($pdo, $ip);

apiReponse(['success' => true]);
