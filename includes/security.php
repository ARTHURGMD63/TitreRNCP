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
// reglage() pour ipClient(), estEnProduction() pour hachageInterdit().
require_once __DIR__ . '/config.php';

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
        // Polices hébergées avec l'application (assets/fonts) : plus aucune
        // origine tierce à autoriser pour les styles ni pour les polices.
        "style-src 'self' 'unsafe-inline'; " .
        "font-src 'self'; " .
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
    // Un jeton présenté mais refusé (expiré, révoqué) : 401, comme sur
    // api/v1. Sans cela l'application recevait un 403 « CSRF » qu'elle ne
    // savait pas lire comme une fin de session, et restait coincée dans une
    // interface où tout échouait.
    if (function_exists('apiJetonPresente') && apiJetonPresente() !== null) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'code'    => 'jeton_invalide',
            'message' => 'Session expirée, reconnecte-toi',
        ]);
        exit;
    }

    csrfVerifyApi();
}

// ─── Rate limiting (anti-bruteforce) ──────────────────────────────────────

/**
 * Plages publiées par Cloudflare (https://www.cloudflare.com/ips/), pour le
 * mot-clé « cloudflare » de PROXY_CONFIANCE. À relire de temps en temps :
 * Cloudflare les change rarement, mais il les change.
 */
const PLAGES_CLOUDFLARE = [
    '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
    '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
    '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
    '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
    '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
];

/** Plages privées et de bouclage, pour le mot-clé « prive » de PROXY_CONFIANCE. */
const PLAGES_PRIVEES = [
    '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '127.0.0.0/8',
    '100.64.0.0/10', '::1/128', 'fc00::/7',
];

/**
 * L'adresse appartient-elle à la plage (notation CIDR, ou adresse seule) ?
 * IPv4 et IPv6 ; une plage mal écrite ne contient rien.
 */
function ipDansPlage(string $ip, string $plage): bool {
    [$reseau, $longueur] = array_pad(explode('/', trim($plage), 2), 2, null);
    $binIp     = @inet_pton($ip);
    $binReseau = @inet_pton((string) $reseau);
    if ($binIp === false || $binReseau === false || strlen($binIp) !== strlen($binReseau)) {
        return false;
    }
    $bits = strlen($binIp) * 8;
    $n    = $longueur === null ? $bits : (ctype_digit($longueur) ? (int) $longueur : -1);
    if ($n < 0 || $n > $bits) {
        return false;
    }
    $octets = intdiv($n, 8);
    if (substr($binIp, 0, $octets) !== substr($binReseau, 0, $octets)) {
        return false;
    }
    $reste = $n % 8;
    if ($reste === 0) {
        return true;
    }
    $masque = (0xFF << (8 - $reste)) & 0xFF;

    return (ord($binIp[$octets]) & $masque) === (ord($binReseau[$octets]) & $masque);
}

/**
 * Les proxys de confiance déclarés : liste de plages séparées par des
 * virgules ou des espaces, avec deux mots-clés, « prive » (réseaux privés
 * et bouclage) et « cloudflare » (plages publiées par Cloudflare).
 *
 * @return list<string>
 */
function proxysDeConfiance(string $brut): array {
    $plages = [];
    foreach (preg_split('/[\s,]+/', strtolower(trim($brut)), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $element) {
        $ajout = match ($element) {
            'prive', 'privé', 'private' => PLAGES_PRIVEES,
            'cloudflare'                => PLAGES_CLOUDFLARE,
            default                     => [$element],
        };
        array_push($plages, ...$ajout);
    }

    return $plages;
}

/**
 * L'adresse du client selon les en-têtes qu'on a décidé de croire.
 * Séparée de ipClient() pour être testée sans toucher à l'environnement.
 *
 * @param array<string,mixed> $server  $_SERVER ou un équivalent
 * @param string              $mode    'aucun', 'cloudflare' ou 'x-forwarded-for'
 * @param list<string>        $proxys  plages des proxys de confiance
 */
function ipDepuisRequete(array $server, string $mode, array $proxys): string {
    $valide = static fn (string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP) !== false;
    $fiable = static function (string $ip) use ($proxys): bool {
        foreach ($proxys as $plage) {
            if (ipDansPlage($ip, $plage)) {
                return true;
            }
        }
        return false;
    };

    $direct = trim((string) ($server['REMOTE_ADDR'] ?? ''));
    if (!$valide($direct)) {
        return '0.0.0.0';
    }
    // Un en-tête n'est lu que si la connexion vient d'un proxy déclaré :
    // n'importe quel client peut écrire CF-Connecting-IP ou X-Forwarded-For.
    if (!$fiable($direct)) {
        return $direct;
    }

    if ($mode === 'cloudflare') {
        $cf = trim((string) ($server['HTTP_CF_CONNECTING_IP'] ?? ''));
        return $valide($cf) ? $cf : $direct;
    }

    if ($mode === 'x-forwarded-for') {
        // Chaque proxy ajoute à DROITE l'adresse qui lui a parlé : on remonte
        // de droite à gauche tant que l'entrée est un proxy de confiance. La
        // première qui ne l'est pas est le client ; tout ce qui est à sa
        // gauche a été écrit par lui et ne vaut rien.
        $chaine = array_reverse(array_map('trim', explode(',', (string) ($server['HTTP_X_FORWARDED_FOR'] ?? ''))));
        $dernier = $direct;
        foreach ($chaine as $ip) {
            if ($ip === '' || !$valide($ip)) {
                break; // chaîne abîmée : on s'arrête au dernier maillon sûr
            }
            if (!$fiable($ip)) {
                return $ip;
            }
            $dernier = $ip;
        }
        return $dernier;
    }

    return $direct;
}

/**
 * L'adresse IP du client, comptée par les limiteurs.
 *
 * POURQUOI un réglage : derrière un répartiteur (Render, Docker, nginx en
 * frontal), `REMOTE_ADDR` est l'adresse du proxy, la même pour tout le
 * monde, et le limiteur compterait tous les utilisateurs comme un seul. Mais
 * croire un en-tête dès que `REMOTE_ADDR` est privée (l'ancienne règle)
 * laissait, derrière un proxy qui transmet les en-têtes du client, choisir
 * son adresse à chaque requête : toutes les limites par IP sautaient
 * (audit SEC-07).
 *
 * Deux réglages (variable d'environnement, ou clé de config.local.php) :
 *   - IP_ENTETE / ip_entete : 'aucun' (défaut, seule REMOTE_ADDR compte),
 *     'cloudflare' (CF-Connecting-IP) ou 'x-forwarded-for' ;
 *   - PROXY_CONFIANCE / proxy_confiance : les plages d'où l'en-tête est
 *     accepté (« 10.0.0.0/8, prive, cloudflare »). Vide en mode cloudflare :
 *     les plages de Cloudflare.
 */
function ipClient(): string {
    $mode = strtolower(trim(reglage('IP_ENTETE', 'ip_entete', 'aucun')));
    $brut = reglage('PROXY_CONFIANCE', 'proxy_confiance');
    if ($mode === 'cloudflare' && trim($brut) === '') {
        $brut = 'cloudflare';
    }

    return ipDepuisRequete($_SERVER, $mode, proxysDeConfiance($brut));
}

/**
 * Limite les tentatives par IP, et par compte quand l'e-mail est connu.
 * Retourne true si bloqué, false sinon.
 *
 * Sans e-mail : au plus $maxAttempts tentatives par IP dans la fenêtre.
 *
 * Avec l'e-mail (connexion) : trois compteurs.
 *   - IP + compte : $maxAttempts — le blocage ordinaire. Il ne touche que
 *     l'adresse d'où viennent les échecs : un attaquant qui tape dix fois
 *     un faux mot de passe sur le compte d'un établissement ne l'empêche
 *     plus de se connecter depuis chez lui (audit SEC-06) ;
 *   - compte, toutes IP : $maxParCompte (par défaut 10 × $maxAttempts) —
 *     un plafond de sûreté contre une force brute répartie. Il faut alors
 *     une dizaine d'adresses pour bloquer la victime, au lieu de deux
 *     auparavant (2 × $maxAttempts) ;
 *   - IP, tous comptes : 10 × $maxAttempts — un campus entier sort souvent
 *     par une seule adresse (NAT) : cinq fautes de frappe d'étudiants
 *     différents ne doivent pas bloquer tout le monde, mais un robot qui
 *     balaie les comptes depuis une adresse est arrêté.
 *
 * Les codes de double authentification passent un $maxParCompte plus bas :
 * celui qui les essaie connaît déjà le mot de passe, et six chiffres se
 * devinent bien plus vite qu'un mot de passe.
 */
function isRateLimited(PDO $pdo, string $ip, int $maxAttempts = 5, int $windowSeconds = 900, ?string $email = null, ?int $maxParCompte = null): bool {
    // Nettoie les anciennes entrées (index sur attempted_at, migration v33).
    $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? SECOND)")
        ->execute([$windowSeconds]);

    $compter = static function (string $where, array $params) use ($pdo, $windowSeconds): int {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts WHERE $where AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        $stmt->execute([...$params, $windowSeconds]);
        return (int) $stmt->fetchColumn();
    };

    // Les compteurs des autres actions (« #motif:... », voir limiteAtteinte())
    // ne comptent pas ici : cinq rapports de plantage depuis le Wi-Fi d'un
    // campus ne doivent pas bloquer les connexions de tout le campus.
    $connexion = "email NOT LIKE '#%'";
    if ($email === null || $email === '') {
        return $compter("ip = ? AND $connexion", [$ip]) >= $maxAttempts;
    }

    return $compter('ip = ? AND email = ?', [$ip, $email]) >= $maxAttempts
        || $compter('email = ?', [$email]) >= ($maxParCompte ?? 10 * $maxAttempts)
        || $compter("ip = ? AND $connexion", [$ip]) >= 10 * $maxAttempts;
}

/**
 * Limite propre à une action (motif) : un compteur par adresse IP et, si un
 * compte est donné, un compteur par compte toutes adresses confondues.
 *
 * Chaque action a son compteur. Avant, tout partageait le compteur par IP de
 * la connexion : quelques rapports de plantage ou demandes de code depuis un
 * même réseau bloquaient le changement de mot de passe et la suppression de
 * compte de tout le monde derrière cette adresse, et pouvaient servir à
 * bloquer un compte exprès.
 *
 * Les lignes vivent dans login_attempts, colonne email = « #motif:compte »,
 * purgées avec le reste.
 */
function limiteAtteinte(PDO $pdo, string $motif, string $ip, int $maxParIp, int $fenetre = 900, ?string $compte = null, ?int $maxParCompte = null): bool {
    $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? SECOND)")
        ->execute([$fenetre]);

    $prefixe = '#' . $motif . ':';
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND email LIKE ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)'
    );
    $stmt->execute([$ip, $prefixe . '%', $fenetre]);
    if ((int) $stmt->fetchColumn() >= $maxParIp) {
        return true;
    }
    if ($compte === null || $compte === '') {
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts WHERE email = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)'
    );
    $stmt->execute([mb_substr($prefixe . mb_strtolower($compte), 0, 255), $fenetre]);

    return (int) $stmt->fetchColumn() >= ($maxParCompte ?? $maxParIp);
}

/** Enregistre une tentative pour limiteAtteinte(). */
function enregistrerTentative(PDO $pdo, string $motif, string $ip, ?string $compte = null): void {
    $pdo->prepare('INSERT INTO login_attempts (ip, email) VALUES (?, ?)')
        ->execute([$ip, mb_substr('#' . $motif . ':' . mb_strtolower((string) $compte), 0, 255)]);
}

function recordLoginAttempt(PDO $pdo, string $ip, string $email): void {
    $pdo->prepare("INSERT INTO login_attempts (ip, email) VALUES (?,?)")
        ->execute([$ip, $email]);
}

/**
 * Efface les échecs du compte qui vient de se connecter, depuis cette IP.
 *
 * Plus jamais toute l'IP : sinon quatre essais sur le compte d'une victime,
 * une connexion réussie sur son propre compte, et le compteur repartait de
 * zéro — une force brute illimitée.
 */
function clearLoginAttempts(PDO $pdo, string $ip, ?string $email = null): void {
    if ($email === null || $email === '') {
        return;
    }
    $pdo->prepare("DELETE FROM login_attempts WHERE ip = ? AND email = ?")
        ->execute([$ip, $email]);
}

// ─── Politique de mot de passe ────────────────────────────────────

/**
 * Un hachage témoin VALIDE, au coût par défaut de ce PHP, vérifié quand
 * l'adresse ne correspond à aucun compte.
 *
 * L'ancien témoin était mal formé (59 caractères) : selon les versions,
 * password_verify() rendait la main aussitôt ou calculait un coût différent
 * de celui des vrais comptes, et le temps de réponse disait si l'adresse
 * existait. Celui-ci est un vrai hachage d'un secret jeté, au même coût que
 * password_hash(..., PASSWORD_DEFAULT) : 12 depuis PHP 8.4, 10 avant.
 */
function hachageDemonstration(string $hachage): bool {
    // Hachage public de « password » (documentation de Laravel, repris par
    // db_setup.sql pour les comptes de démonstration). En production, aucun
    // compte ne s'ouvre avec : une base installée avec les données de démo
    // ne donne pas d'accès à quiconque lit le dépôt.
    return hash_equals('$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', $hachage);
}

/**
 * Le mot de passe est-il refusé d'office (compte de démonstration en production) ?
 *
 * COMPTES_DEMO=1 (ou 'comptes_demo' => true dans config.local.php) rouvre ces
 * comptes en production, le temps d'une démonstration. Leur mot de passe est
 * public (db_setup.sql) : retirer la variable les referme, sans redéployer.
 */
function hachageInterdit(string $hachage): bool {
    return estEnProduction() && hachageDemonstration($hachage) && !comptesDemoOuverts();
}

/** Les comptes de démonstration sont-ils ouverts malgré la production ? */
function comptesDemoOuverts(): bool {
    return reglageBooleen('COMPTES_DEMO', 'comptes_demo');
}

function hachageTemoin(): string {
    return PHP_VERSION_ID >= 80400
        ? '$2y$12$0jvEzpwyNPKK7WVN.ZdeeepUsu3VYuMrfdt1ieoJGj8m9Yrg1hVoe'
        : '$2y$10$XRDEfR8.JIa6cYVIxVi2AuKlXF8GCX18q4kPpU18Xl.8UfLsfBRCW';
}

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

    // Consommation conditionnelle : deux envois simultanés du même lien ne
    // posent pas deux mots de passe, seul le premier passe.
    $hash = hash('sha256', $token);
    $marque = $pdo->prepare("UPDATE password_resets SET used=1 WHERE token_hash=? AND used=0 AND expires_at > NOW()");
    $marque->execute([$hash]);
    if ($marque->rowCount() !== 1) return false;
    $pdo->prepare("UPDATE users SET password=? WHERE email=?")
        ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $email]);

    // Un mot de passe changé ferme les accès ouverts avec l'ancien : jetons de
    // l'application (un téléphone volé reste connecté sinon) et codes de
    // réinitialisation en cours.
    $pdo->prepare("DELETE t FROM api_tokens t JOIN users u ON u.id = t.user_id WHERE u.email = ?")
        ->execute([$email]);
    $pdo->prepare("DELETE FROM password_resets WHERE email = ? AND used = 0")->execute([$email]);

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
        'Outsee — Réinitialisation de votre mot de passe',
        "Bonjour,\n\n"
        . "Cliquez sur ce lien pour réinitialiser votre mot de passe (valable 1 heure) :\n\n"
        . "$link\n\n"
        . "Si vous n'avez pas fait cette demande, ignorez cet e-mail : votre mot de passe "
        . "reste inchangé.\n\n"
        . "L'équipe Outsee"
    );
}
