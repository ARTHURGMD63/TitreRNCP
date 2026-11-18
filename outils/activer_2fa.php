<?php
/**
 * Active, regarde ou retire la double authentification d'un administrateur.
 *
 *     php outils/activer_2fa.php admin@outsee.fr              active (ou régénère)
 *     php outils/activer_2fa.php admin@outsee.fr --etat       dit si elle est active
 *     php outils/activer_2fa.php admin@outsee.fr --desactiver la retire
 *
 * L'activation affiche, UNE seule fois : le secret (à saisir dans
 * l'application d'authentification, ou le lien à en faire un QR code) et dix
 * codes de secours à ranger hors du téléphone. Seules leurs empreintes
 * restent en base.
 *
 * C'est un outil de terminal, volontairement : une page d'enrôlement
 * permettrait à quiconque a volé une session d'ajouter SON téléphone.
 * Variables de base : MYSQLHOST, MYSQLPORT, MYSQLUSER, MYSQLPASSWORD,
 * MYSQLDATABASE (comme outils/migrer.php).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/totp.php';

$email = (string) ($argv[1] ?? '');
$mode  = (string) ($argv[2] ?? '');

if ($email === '' || str_starts_with($email, '--')) {
    fwrite(STDERR, "Usage : php outils/activer_2fa.php <e-mail> [--etat | --desactiver]\n");
    exit(1);
}

$stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ? AND type = 'admin'");
$stmt->execute([$email]);
$admin = $stmt->fetch();
if (!$admin) {
    fwrite(STDERR, "Aucun compte administrateur n'a l'adresse « $email ».\n");
    exit(1);
}
$id = (int) $admin['id'];

if ($mode === '--etat') {
    echo totpActif($pdo, $id) ? "Double authentification : ACTIVE.\n" : "Double authentification : non activée.\n";
    exit(0);
}

if ($mode === '--desactiver') {
    $pdo->prepare('DELETE FROM totp_comptes WHERE user_id = ?')->execute([$id]);
    echo "Double authentification retirée pour $email.\n";
    exit(0);
}

$secret  = totpGenererSecret();
$secours = totpGenererCodesSecours();
$pdo->prepare('REPLACE INTO totp_comptes (user_id, secret, dernier_pas, codes_secours) VALUES (?, ?, 0, ?)')
    ->execute([$id, $secret, json_encode(array_map('totpEmpreinteSecours', $secours))]);

echo "\nDouble authentification activée pour $email.\n\n";
echo "1. Dans votre application d'authentification, ajoutez un compte à la main avec ce secret :\n\n";
echo "     $secret\n\n";
echo "   (ou ouvrez ce lien depuis un téléphone / générez-en un QR code) :\n\n";
echo '     ' . totpUriEnrolement($secret, $email) . "\n\n";
echo "2. Notez ces codes de secours, hors de votre téléphone. Chacun sert une seule fois,\n";
echo "   et ils ne seront PLUS jamais affichés :\n\n";
foreach ($secours as $code) {
    echo "     $code\n";
}
echo "\nÀ la prochaine connexion, le code à six chiffres sera demandé.\n";
