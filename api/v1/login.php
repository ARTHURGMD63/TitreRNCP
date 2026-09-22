<?php
/**
 * POST /api/v1/login.php — echange un couple e-mail / mot de passe contre un
 * jeton d'appareil.
 *
 * Corps attendu : {"email": "...", "password": "...", "appareil": "iPhone 14"}
 * Reponse       : {"success": true, "token": "...", "expire_le": "...",
 *                  "utilisateur": {...}}
 *
 * Le jeton renvoye ici est la SEULE occasion de le lire : seule son empreinte
 * est conservee. L'application doit le ranger dans le trousseau du systeme
 * (Keychain sur iOS), pas dans un stockage ordinaire.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/security.php';

apiExigerMethode('POST');

$corps = apiCorps();
$email = trim((string) ($corps['email'] ?? ''));
$mdp   = (string) ($corps['password'] ?? '');

if ($email === '' || $mdp === '') {
    apiErreur('E-mail et mot de passe requis', 422, 'champs_manquants');
}

// Meme limiteur que le formulaire web, et volontairement la meme table :
// sinon l'API deviendrait le chemin non protege pour essayer des mots de
// passe en rafale, pendant que le site compte consciencieusement ses cinq
// tentatives.
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
if (isRateLimited($pdo, $ip)) {
    apiErreur('Trop de tentatives. Reessaie dans quinze minutes.', 429, 'trop_de_tentatives');
}

$stmt = $pdo->prepare('SELECT id, nom, prenom, email, password, ecole, promo, photo, interests, type
                         FROM users WHERE email = ?');
$stmt->execute([$email]);
$u = $stmt->fetch();

// password_verify() est appele meme quand le compte n'existe pas, sur un
// hachage temoin : sans cela, une adresse inconnue repond nettement plus vite
// qu'une adresse connue, et le temps de reponse suffit a dresser la liste des
// comptes existants.
$hachage = $u ? (string) $u['password'] : '$2y$12$0000000000000000000000000000000000000000000000000000';
$valide  = password_verify($mdp, $hachage) && $u !== false;

if (!$valide) {
    recordLoginAttempt($pdo, $ip, $email);
    // Un seul message pour les deux cas : « mot de passe incorrect » confirme
    // que l'adresse existe.
    apiErreur('Identifiants incorrects', 401, 'identifiants');
}

clearLoginAttempts($pdo, $ip);

$jeton = apiCreerJeton($pdo, (int) $u['id'], isset($corps['appareil']) ? (string) $corps['appareil'] : null);

apiReponse([
    'success'     => true,
    'token'       => $jeton['token'],
    'expire_le'   => $jeton['expire_le'],
    'utilisateur' => apiProfil($u),
]);
