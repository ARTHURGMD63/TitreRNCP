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
require_once __DIR__ . '/../../includes/totp.php';

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
$ip = ipClient();
if (isRateLimited($pdo, $ip, 5, 900, $email)) {
    apiErreur('Trop de tentatives. Reessaie dans quinze minutes.', 429, 'trop_de_tentatives');
}

$stmt = $pdo->prepare('SELECT id, nom, prenom, email, telephone, password, ecole, promo, ville, photo, interests, type, compte_prive
                         FROM users WHERE email = ?');
$stmt->execute([$email]);
$u = $stmt->fetch();

// password_verify() est appele meme quand le compte n'existe pas, sur un
// hachage temoin : sans cela, une adresse inconnue repond nettement plus vite
// qu'une adresse connue, et le temps de reponse suffit a dresser la liste des
// comptes existants.
$hachage = $u ? (string) $u['password'] : hachageTemoin();
$valide  = password_verify($mdp, $hachage) && $u !== false && !hachageInterdit($hachage);

if (!$valide) {
    recordLoginAttempt($pdo, $ip, $email);
    // Un seul message pour les deux cas : « mot de passe incorrect » confirme
    // que l'adresse existe.
    apiErreur('Identifiants incorrects', 401, 'identifiants');
}

// Double authentification des administrateurs (includes/totp.php). Le code est
// vérifié AVANT d'effacer les tentatives : sinon un mot de passe volé
// permettrait d'essayer des codes sans limite. Un compte sans secret enregistré
// se connecte comme avant.
if (($u['type'] ?? '') === 'admin' && totpActif($pdo, (int) $u['id'])) {
    $codeTotp = trim((string) ($corps['code_totp'] ?? ''));
    if ($codeTotp === '') {
        apiErreur('Code de vérification requis', 401, 'totp_requis');
    }
    $cleLimite = 'totp:' . (int) $u['id'];
    if (isRateLimited($pdo, $ip, 5, 900, $cleLimite, 10)) {
        apiErreur('Trop de tentatives. Reessaie dans quinze minutes.', 429, 'trop_de_tentatives');
    }
    if (!totpAccepterConnexion($pdo, (int) $u['id'], $codeTotp)) {
        recordLoginAttempt($pdo, $ip, $cleLimite);
        apiErreur('Code de vérification incorrect', 401, 'totp_invalide');
    }
    clearLoginAttempts($pdo, $ip, $cleLimite);
}

clearLoginAttempts($pdo, $ip, $email);

$jeton = apiCreerJeton($pdo, (int) $u['id'], isset($corps['appareil']) ? (string) $corps['appareil'] : null, apiDureeJetonJours((string) $u['type']));

apiReponse([
    'success'     => true,
    'token'       => $jeton['token'],
    'expire_le'   => $jeton['expire_le'],
    'utilisateur' => apiProfil($u),
]);
