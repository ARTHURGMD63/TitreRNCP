<?php
/**
 * Double authentification par code à usage unique (TOTP, RFC 6238).
 *
 * Le code à six chiffres qu'affiche une application d'authentification
 * (Google Authenticator, Authy, 1Password…) change toutes les trente
 * secondes. Il se calcule à partir d'un secret partagé et de l'heure : aucun
 * SMS, aucun service tiers.
 *
 * Réservé aux comptes administrateurs (back-office : finances, clients).
 * C'est un OPT-IN par compte : un administrateur sans secret enregistré se
 * connecte comme avant ; dès qu'il en a un, le code devient obligatoire. Les
 * étudiants et les établissements ne sont pas concernés.
 *
 * Activation depuis le terminal, jamais depuis une page : voir
 * outils/activer_2fa.php. Quelqu'un qui aurait volé une session ne doit pas
 * pouvoir s'enrôler lui-même.
 */

const TOTP_PERIODE  = 30;
const TOTP_CHIFFRES = 6;
/** Pas de tolérance de chaque côté : une horloge de téléphone en retard de 30 s reste acceptée. */
const TOTP_DERIVE   = 1;
const TOTP_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

/** Un secret aléatoire de 160 bits, en base32 (32 caractères). */
function totpGenererSecret(): string
{
    $octets = random_bytes(20);
    $bits   = '';
    foreach (str_split($octets) as $o) {
        $bits .= str_pad(decbin(ord($o)), 8, '0', STR_PAD_LEFT);
    }

    $secret = '';
    foreach (str_split($bits, 5) as $groupe) {
        $secret .= TOTP_ALPHABET[bindec($groupe)];
    }

    return $secret;
}

/** Décode du base32 (casse, espaces et « = » ignorés). Chaîne vide si un caractère est invalide. */
function totpDecoderBase32(string $secret): string
{
    $secret = strtoupper(str_replace([' ', '=', '-'], '', $secret));
    $bits   = '';
    foreach (str_split($secret) as $c) {
        $v = strpos(TOTP_ALPHABET, $c);
        if ($v === false) {
            return '';
        }
        $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
    }

    $octets = '';
    foreach (str_split($bits, 8) as $groupe) {
        if (strlen($groupe) === 8) {
            $octets .= chr(bindec($groupe));
        }
    }

    return $octets;
}

/** Le code attendu pour un « pas » de temps donné (RFC 4226, HMAC-SHA1). */
function totpCode(string $secret, int $pas): string
{
    $cle = totpDecoderBase32($secret);
    if ($cle === '') {
        return '';
    }

    $hmac   = hash_hmac('sha1', pack('J', $pas), $cle, true);
    $debut  = ord($hmac[19]) & 0x0F;
    $entier = ((ord($hmac[$debut]) & 0x7F) << 24)
            | (ord($hmac[$debut + 1]) << 16)
            | (ord($hmac[$debut + 2]) << 8)
            | ord($hmac[$debut + 3]);

    return str_pad((string) ($entier % (10 ** TOTP_CHIFFRES)), TOTP_CHIFFRES, '0', STR_PAD_LEFT);
}

/**
 * Ce code est-il valable maintenant ? Renvoie le pas de temps qui l'a
 * validé (pour mémoriser qu'il est consommé), ou null.
 *
 * @param int|null $maintenant l'heure Unix, pour les tests
 */
function totpVerifier(string $secret, string $code, ?int $maintenant = null, int $dernierPas = 0): ?int
{
    $code = preg_replace('/\s+/', '', $code) ?? '';
    if (!preg_match('/^\d{' . TOTP_CHIFFRES . '}$/', $code)) {
        return null;
    }

    $pasActuel = intdiv($maintenant ?? time(), TOTP_PERIODE);
    for ($d = -TOTP_DERIVE; $d <= TOTP_DERIVE; $d++) {
        $pas = $pasActuel + $d;
        // Un pas déjà utilisé (ou plus ancien) ne revaut pas : sans cela, un
        // code lu par-dessus l'épaule resterait rejouable pendant 90 s.
        if ($pas > $dernierPas && hash_equals(totpCode($secret, $pas), $code)) {
            return $pas;
        }
    }

    return null;
}

/** Le lien que lit une application d'authentification (en QR code, ou collé à la main). */
function totpUriEnrolement(string $secret, string $compte, string $emetteur = 'Outsee'): string
{
    return 'otpauth://totp/' . rawurlencode($emetteur . ':' . $compte)
        . '?secret=' . $secret . '&issuer=' . rawurlencode($emetteur)
        . '&algorithm=SHA1&digits=' . TOTP_CHIFFRES . '&period=' . TOTP_PERIODE;
}

/**
 * Dix codes de secours à usage unique, au cas où le téléphone est perdu.
 *
 * @return list<string> en clair (à montrer UNE fois) ; seules les empreintes sont stockées
 */
function totpGenererCodesSecours(int $nombre = 10): array
{
    $codes = [];
    for ($i = 0; $i < $nombre; $i++) {
        $codes[] = bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2));
    }

    return $codes;
}

function totpEmpreinteSecours(string $code): string
{
    return hash('sha256', strtolower(trim($code)));
}

/**
 * Le compte exige-t-il un code ? Renvoie sa ligne `totp_comptes`, ou null.
 *
 * Tolérante : si la table n'existe pas encore (migration v36 pas passée),
 * personne n'est bloqué — la connexion reste celle d'avant.
 *
 * @return array{secret:string,dernier_pas:int,codes_secours:?string}|null
 */
function totpDuCompte(PDO $pdo, int $userId): ?array
{
    try {
        $stmt = $pdo->prepare('SELECT secret, dernier_pas, codes_secours FROM totp_comptes WHERE user_id = ?');
        $stmt->execute([$userId]);
        $ligne = $stmt->fetch();
    } catch (PDOException) {
        return null;
    }

    if (!$ligne) {
        return null;
    }

    return [
        'secret'        => (string) $ligne['secret'],
        'dernier_pas'   => (int) $ligne['dernier_pas'],
        'codes_secours' => $ligne['codes_secours'] !== null ? (string) $ligne['codes_secours'] : null,
    ];
}

/**
 * Valide le code saisi à la connexion : un code de l'application, ou un code
 * de secours (qui se consomme).
 */
function totpAccepterConnexion(PDO $pdo, int $userId, string $saisie): bool
{
    $ligne = totpDuCompte($pdo, $userId);
    if ($ligne === null) {
        return true; // pas de double authentification sur ce compte
    }

    $pas = totpVerifier($ligne['secret'], $saisie, null, $ligne['dernier_pas']);
    if ($pas !== null) {
        $pdo->prepare('UPDATE totp_comptes SET dernier_pas = ? WHERE user_id = ?')->execute([$pas, $userId]);

        return true;
    }

    $secours = $ligne['codes_secours'] !== null ? json_decode($ligne['codes_secours'], true) : [];
    $secours = is_array($secours) ? $secours : [];
    $empreinte = totpEmpreinteSecours($saisie);
    foreach ($secours as $i => $h) {
        if (is_string($h) && hash_equals($h, $empreinte)) {
            unset($secours[$i]);
            $pdo->prepare('UPDATE totp_comptes SET codes_secours = ? WHERE user_id = ?')
                ->execute([json_encode(array_values($secours)), $userId]);

            return true;
        }
    }

    return false;
}

/** Ce compte est-il protégé par une double authentification ? */
function totpActif(PDO $pdo, int $userId): bool
{
    return totpDuCompte($pdo, $userId) !== null;
}
