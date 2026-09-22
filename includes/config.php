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

/** L'application tourne-t-elle en production ? */
function estEnProduction(): bool
{
    return in_array(strtolower((string) getenv('APP_ENV')), ['production', 'prod'], true);
}
