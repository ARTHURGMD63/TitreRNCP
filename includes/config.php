<?php
/**
 * Lecture des réglages, quelle que soit la façon dont l'hébergeur les fournit.
 *
 * Trois sources, par ordre de priorité décroissant :
 *
 *   1. les variables d'environnement, là où l'hébergeur en propose
 *      (Railway, Docker, conteneurs en général) ;
 *   2. includes/config.local.php, pour les mutualisés qui n'en proposent pas
 *      — InfinityFree, o2switch, OVH mutualisé. Ce fichier n'est pas
 *      versionné : mots de passe et clés restent sur le serveur ;
 *   3. la valeur par défaut passée à l'appel.
 *
 * Extrait de db.php parce que la configuration des e-mails a le même besoin.
 * Sans point unique, chaque fichier relisait config.local.php à sa façon —
 * et c'est exactement ainsi que sendResetEmail() s'était retrouvé à envoyer
 * par un chemin différent de tout le reste de l'application.
 */

/**
 * Le contenu de config.local.php, lu une seule fois.
 *
 * @return array<string,mixed>
 */
function configLocale(): array
{
    static $config = null;

    if ($config === null) {
        $chemin = __DIR__ . '/config.local.php';
        $lu     = is_file($chemin) ? require $chemin : [];
        $config = is_array($lu) ? $lu : [];
    }

    return $config;
}

/**
 * Un réglage, cherché dans l'environnement puis dans config.local.php.
 *
 * @param string $variable nom de la variable d'environnement
 * @param string $cle      clé correspondante dans config.local.php
 */
function reglage(string $variable, string $cle, string $defaut = ''): string
{
    $env = getenv($variable);
    if ($env !== false && $env !== '') {
        return $env;
    }

    $local = configLocale()[$cle] ?? null;

    return $local === null ? $defaut : (string) $local;
}

/**
 * Un réglage booléen.
 *
 * Accepte les écritures qu'un panneau d'hébergeur ou un fichier YAML peuvent
 * produire : « 1 », « true », « on », « yes ». Tout le reste est faux, y
 * compris l'absence — en cas de doute, on n'active pas.
 */
function reglageBooleen(string $variable, string $cle, bool $defaut = false): bool
{
    $brut = reglage($variable, $cle, $defaut ? '1' : '0');

    return in_array(strtolower(trim($brut)), ['1', 'true', 'on', 'yes'], true);
}

/**
 * L'environnement déclaré : APP_ENV, ou 'app_env' dans config.local.php.
 * Sur le VPS, il vaut « production » (voir deploy/).
 */
function environnementApp(): string
{
    return strtolower(trim(reglage('APP_ENV', 'app_env')));
}

/** L'application tourne-t-elle en production ? */
function estEnProduction(): bool
{
    return in_array(environnementApp(), ['production', 'prod'], true);
}

/**
 * L'application tourne-t-elle sur un poste de développement ?
 *
 * C'est elle qui autorise les raccourcis de test : afficher le code de
 * réinitialisation au lieu de l'envoyer, montrer les traces d'erreur. Elle
 * ne lit plus l'en-tête Host, que n'importe quel client écrit à sa guise :
 * `Host: localhost` envoyé à l'adresse IP du serveur suffisait à recevoir le
 * code de réinitialisation de n'importe quel compte.
 *
 * Vrai si APP_ENV le dit (local, dev, test), ou si rien n'est déclaré ET que
 * la requête vient de la machine elle-même (WAMP, `php -S`). En production,
 * jamais.
 */
function estEnDeveloppement(): bool
{
    if (estEnProduction()) {
        return false;
    }
    $env = environnementApp();
    if (in_array($env, ['local', 'dev', 'developpement', 'development', 'test'], true)) {
        return true;
    }
    if ($env !== '') {
        return false;
    }
    if (PHP_SAPI === 'cli') {
        return true;
    }
    // Sans APP_ENV, seule une requête de la machine elle-même ET servie par
    // un serveur de poste de travail (`php -S`, WAMP sous Windows) compte.
    // Sur un hébergement Linux sans APP_ENV, un proxy local (nginx devant
    // PHP-FPM, Apache derrière un répartiteur) présente lui aussi 127.0.0.1 :
    // toute la clientèle serait passée en mode développement, code de
    // réinitialisation affiché compris.
    if (PHP_SAPI !== 'cli-server' && PHP_OS_FAMILY !== 'Windows') {
        return false;
    }
    $distante = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    return in_array($distante, ['127.0.0.1', '::1'], true);
}

/**
 * L'adresse publique du site, sans barre finale.
 *
 * Réglée par APP_URL (ou 'url_publique' dans config.local.php) en
 * production : les liens envoyés par e-mail et les retours de paiement ne
 * doivent pas dépendre de l'en-tête Host, que le client choisit. Sans
 * réglage, sur un poste de développement, l'hôte demandé sert de repli.
 */
function urlPublique(string $chemin = ''): string
{
    $base = rtrim(reglage('APP_URL', 'url_publique'), '/');
    if ($base === '' && !estEnDeveloppement()) {
        // Hors poste de développement, l'hôte demandé n'est jamais une
        // source : n'importe qui l'écrit, et un lien de réinitialisation
        // pointerait vers son site. Mieux vaut un échec visible au journal.
        error_log('[Outsee] APP_URL (ou url_publique) manquant : lien public impossible à construire.');
        throw new RuntimeException('APP_URL manquant');
    }
    if ($base === '') {
        require_once __DIR__ . '/urls.php';
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $base  = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . prefixeApplication();
    }

    return $base . $chemin;
}
