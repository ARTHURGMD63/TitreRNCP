<?php
/**
 * Sécurité — headers HTTP, CSRF, utilitaires.
 * Inclus automatiquement via auth_check.php
 */

// apiSessionDepuisJeton(), employee par protegerEcritureApi() pour reconnaitre
// l'application mobile. Ce fichier ne declenche rien au chargement : il ne
// declare que des fonctions, et celles qui dependent d'autres modules ne sont
// resolues qu'a l'appel.
require_once __DIR__ . '/api.php';

// ─── Headers de sécurité ──────────────────────────────────────────────────

function setSecurityHeaders(): void {
    if (headers_sent()) return;

    // Clickjacking
    header('X-Frame-Options: DENY');
    // MIME sniffing
    header('X-Content-Type-Options: nosniff');
    // XSS filter legacy browsers
    header('X-XSS-Protection: 1; mode=block');
    // Referrer
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // HSTS (HTTPS only)
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    // Content Security Policy
    header(
        "Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline'; " .   // bibliothèques auto-hébergées dans /assets/vendor
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
        "font-src 'self' https://fonts.gstatic.com; " .
        "img-src 'self' data: https:; " .
        "connect-src 'self'; " .
        "frame-ancestors 'none';"
    );
}

// ─── CSRF ─────────────────────────────────────────────────────────────────

function csrfToken(): string {
    // Filet de secours : dans le flux normal auth_check.php a deja demarre la
    // session avec ses drapeaux. Si on arrive ici sans, mieux vaut echouer
    // que d'ouvrir une session au cookie nu.
    if (session_status() === PHP_SESSION_NONE) {
        throw new LogicException('csrfToken() appele avant le demarrage de la session.');
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Retourne un champ hidden HTML prêt à inclure dans un <form> */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

/**
 * Le jeton CSRF, publié pour le JavaScript.
 *
 * Les appels d'API partent en fetch() et n'ont pas de <form> où glisser un
 * champ caché : ils lisent cette balise. Posée dans le <head> de chaque page
 * à côté de themeBootScript(), elle n'est lisible que par du script de même
 * origine — c'est précisément ce qui la rend inaccessible à un site tiers.
 */
function metaCsrf(): string {
    return '<meta name="csrf-token" content="' . htmlspecialchars(csrfToken(), ENT_QUOTES) . '">';
}

/**
 * Ramène une URL de retour à quelque chose d'interne.
 *
 * `Location: $_SERVER['HTTP_REFERER']` renvoyait l'utilisateur là d'où il
 * disait venir — et ce en-tête est posé par le navigateur à partir de la page
 * précédente, qui peut très bien être un site tiers. Une page hostile qui
 * pointe vers un formulaire de l'application avec un jeton CSRF volontairement
 * faux récupérait ainsi la personne sur son propre domaine, avec l'application
 * comme caution. C'est une redirection ouverte, le socle classique d'un
 * hameçonnage.
 *
 * On ne garde donc que le chemin, la requête et le fragment, et seulement si
 * l'hôte est bien le nôtre. Tout le reste retombe sur le défaut.
 */
function urlInterneOuDefaut(?string $url, string $defaut = '/'): string {
    if ($url === null || $url === '') {
        return $defaut;
    }

    // « //evil.tld/x » est une URL protocole-relatif : le navigateur y voit un
    // changement de domaine, parse_url() y voit un hôte. On la refuse avant
    // toute autre analyse, comme « /\evil.tld » que certains clients
    // normalisent en « //evil.tld ».
    if (str_starts_with($url, '//') || str_starts_with($url, '/\\')) {
        return $defaut;
    }

    $parties = parse_url($url);
    if ($parties === false) {
        return $defaut;
    }

    // URL absolue : elle doit désigner exactement l'hôte courant.
    if (isset($parties['host'])) {
        $hote = $parties['host'] . (isset($parties['port']) ? ':' . $parties['port'] : '');
        if (strcasecmp($hote, (string) ($_SERVER['HTTP_HOST'] ?? '')) !== 0) {
            return $defaut;
        }
    }

    $chemin = $parties['path'] ?? '';
    if ($chemin === '' || $chemin[0] !== '/') {
        // Un chemin relatif s'interpréterait depuis l'URL courante, pas depuis
        // celle qu'on croit reconstruire. On ne devine pas.
        return $defaut;
    }

    return $chemin
        . (isset($parties['query'])    ? '?' . $parties['query']    : '')
        . (isset($parties['fragment']) ? '#' . $parties['fragment'] : '');
}

/**
 * L'origine de la requête désigne-t-elle bien cette application ?
 *
 * Deuxième couche, derrière le jeton. Le navigateur pose `Origin` sur toute
 * requête non simple — donc sur tous les appels JSON de l'application — et
 * le script d'un site tiers ne peut ni le retirer ni le falsifier. Un jeton
 * qui fuiterait par un autre biais ne suffirait alors toujours pas.
 *
 * Absence d'`Origin` ET de `Referer` : on accepte. Quelques clients anciens
 * et certains proxys d'entreprise les suppriment, et refuser rendrait
 * l'application inutilisable derrière eux pour un gain nul — le jeton, lui,
 * reste exigé dans tous les cas.
 */
function origineFiable(): bool {
    $hote = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($hote === '') {
        return false;
    }

    $origine = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    if ($origine === '') {
        return true;
    }

    $parties = parse_url($origine);
    if (!is_array($parties) || !isset($parties['host'])) {
        return false;
    }

    $venuDe = $parties['host'] . (isset($parties['port']) ? ':' . $parties['port'] : '');

    return strcasecmp($venuDe, $hote) === 0;
}

/** Vérifie le token CSRF. Redirige vers la page précédente si invalide. */
function csrfVerify(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrfToken(), $token) || !origineFiable()) {
        // Régénère un token frais pour que la prochaine soumission fonctionne
        unset($_SESSION['csrf_token']);
        $_SESSION['csrf_error'] = 'Session expirée. Veuillez réessayer.';
        // Jamais l'en-tête brut : voir urlInterneOuDefaut().
        header('Location: ' . urlInterneOuDefaut($_SERVER['HTTP_REFERER'] ?? null, baseUrl('/')));
        exit;
    }
}

/**
 * Vérifie le jeton CSRF d'un appel d'API, et répond en JSON s'il manque.
 *
 * Pourquoi c'est nécessaire alors que le cookie est en SameSite=Lax. Lax
 * bloque effectivement l'envoi du cookie sur un POST venu d'un autre site :
 * l'application n'était donc pas vulnérable en l'état. Mais c'était sa seule
 * défense, là où le reste de l'application en a deux, et elle tenait
 * entièrement à une ligne de configuration de session — un passage en `None`
 * pour faire fonctionner une intégration tierce, et onze points d'écriture
 * s'ouvraient d'un coup, sans que rien dans leur code ne l'indique.
 *
 * Le jeton se lit dans l'en-tête `X-CSRF-Token`, ou dans le corps JSON pour
 * les appels qui ne peuvent pas poser d'en-tête. À défaut, `$_POST` couvre
 * les rares envois en formulaire.
 *
 * 403 et non une redirection : l'appelant est du JavaScript, il attend du
 * JSON et saura quoi en faire.
 */
function csrfVerifyApi(): void {
    $entete = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if ($entete === '') {
        $brut = file_get_contents('php://input');
        if (is_string($brut) && $brut !== '') {
            $corps = json_decode($brut, true);
            if (is_array($corps) && isset($corps['csrf_token']) && is_scalar($corps['csrf_token'])) {
                $entete = (string) $corps['csrf_token'];
            }
        }
    }
    if ($entete === '') {
        $entete = (string) ($_POST['csrf_token'] ?? '');
    }

    if (!hash_equals(csrfToken(), $entete) || !origineFiable()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'     => false,
            'code'        => 'csrf',
            'message'     => 'Session expirée. Recharge la page.',
        ]);
        exit;
    }
}

/**
 * Refuse tout ce qui n'est pas un POST, sur un point d'écriture.
 *
 * Une écriture atteignable en GET se déclenche depuis une balise <img>, sans
 * le moindre script : c'est la forme la plus simple de CSRF, et aucun jeton
 * n'est demandé à une image. Les onze points d'écriture de l'API sont déjà
 * appelés en POST par l'application ; cette garde le rend obligatoire.
 */
function exigerPost(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        exit;
    }
}

/**
 * Garde commune aux points d'écriture de l'API : POST, jeton, origine.
 *
 * Un seul appel à placer en tête de fichier — trois lignes à ne pas oublier
 * séparément, c'est trois occasions d'en oublier une.
 */
function protegerEcritureApi(): void {
    exigerPost();

    // Deux clients, deux preuves.
    //
    // L'application mobile presente un jeton dans « Authorization ». Elle
    // n'envoie aucun cookie, donc il n'y a pas de CSRF a couvrir : une requete
    // qu'aucun navigateur n'emet automatiquement ne peut pas etre declenchee a
    // l'insu de l'utilisateur. Le jeton EST la preuve.
    //
    // Le navigateur, lui, envoie son cookie tout seul — et c'est precisement
    // pour cela qu'il doit fournir en plus un jeton CSRF et une origine. Rien
    // n'est relache de ce cote : apiSessionDepuisJeton() exige un en-tete
    // qu'aucun navigateur n'ajoute de lui-meme.
    if (apiSessionDepuisJeton()) {
        return;
    }

    csrfVerifyApi();
}

// ─── Rate limiting (anti-bruteforce) ──────────────────────────────────────

/**
 * Limite les tentatives de login par IP.
 * Retourne true si bloqué, false sinon.
 */
function isRateLimited(PDO $pdo, string $ip, int $maxAttempts = 5, int $windowSeconds = 900): bool {
    // Nettoie les anciennes entrées
    $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? SECOND)")
        ->execute([$windowSeconds]);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)"
    );
    $stmt->execute([$ip, $windowSeconds]);
    return (int)$stmt->fetchColumn() >= $maxAttempts;
}

function recordLoginAttempt(PDO $pdo, string $ip, string $email): void {
    $pdo->prepare("INSERT INTO login_attempts (ip, email) VALUES (?,?)")
        ->execute([$ip, $email]);
}

function clearLoginAttempts(PDO $pdo, string $ip): void {
    $pdo->prepare("DELETE FROM login_attempts WHERE ip = ?")
        ->execute([$ip]);
}

// ─── Politique de mot de passe ────────────────────────────────────

/**
 * Longueur minimale d'un mot de passe, selon le type de compte.
 *
 * Trois valeurs coexistaient sans s'accorder : 6 à l'inscription, 8 à la
 * réinitialisation, 10 à la création d'un compte fondateur. Un fondateur
 * pouvait donc redescendre à 8 par « mot de passe oublié », en contournant
 * sans le vouloir la seule règle qui le concernait. Un compte admin ouvre
 * le fichier clients et la trésorerie : il ne partage pas le plancher des
 * comptes étudiants.
 */
function longueurMinimaleMotDePasse(?string $type = null): int {
    return $type === 'admin' ? 12 : 8;
}

// ─── Mot de passe oublié ───────────────────────────────────────────────────

function createPasswordResetToken(PDO $pdo, string $email): ?string {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if (!$stmt->fetch()) return null; // Email inconnu — on ne révèle pas

    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);

    // Invalide les anciens tokens
    $pdo->prepare("DELETE FROM password_resets WHERE email = ?")
        ->execute([$email]);

    $pdo->prepare(
        "INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
    )->execute([$email, $hash]);

    return $token; // Token brut à envoyer par email
}

function validateResetToken(PDO $pdo, string $token): ?string {
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare(
        "SELECT email FROM password_resets WHERE token_hash = ? AND expires_at > NOW() AND used = 0"
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    return $row ? $row['email'] : null;
}

function consumeResetToken(PDO $pdo, string $token, string $newPassword): bool {
    $email = validateResetToken($pdo, $token);
    if (!$email) return false;

    $hash = hash('sha256', $token);
    $pdo->prepare("UPDATE password_resets SET used=1 WHERE token_hash=?")->execute([$hash]);
    $pdo->prepare("UPDATE users SET password=? WHERE email=?")
        ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $email]);

    return true;
}

/**
 * Envoi de l'e-mail de réinitialisation.
 *
 * Passe par envoyerEmail() (includes/mail.php) comme tout le reste. Ce n'est
 * pas une simplification cosmétique : cette fonction appelait `mail()`
 * directement, avec ses propres en-têtes — un `From:` codé en dur sur un
 * domaine qui n'est pas forcément celui du serveur, aucun Message-ID, aucune
 * adresse d'enveloppe, et un sujet accentué envoyé en octets bruts. Le
 * message le plus critique de l'application, celui sans lequel on ne récupère
 * pas son compte, partait donc par le chemin le moins fiable.
 *
 * En local, rien n'est envoyé : le lien est mis en session et affiché par
 * auth/forgot.php, ce qui permet de tester le parcours sans serveur de mail.
 */
function sendResetEmail(string $email, string $token, string $baseUrl): bool {
    require_once __DIR__ . '/mail.php';

    $link = $baseUrl . '/auth/reset.php?token=' . urlencode($token);

    // Mode dev : pas d'envoi réel, on stocke le lien en session pour affichage.
    if (estEnLocal()) {
        $_SESSION['dev_reset_link'] = $link;
        return true;
    }

    return envoyerEmail(
        $email,
        'StudentLink — Réinitialisation de votre mot de passe',
        "Bonjour,\n\n"
        . "Cliquez sur ce lien pour réinitialiser votre mot de passe (valable 1 heure) :\n\n"
        . "$link\n\n"
        . "Si vous n'avez pas fait cette demande, ignorez cet e-mail : votre mot de passe "
        . "reste inchangé.\n\n"
        . "L'équipe StudentLink"
    );
}
