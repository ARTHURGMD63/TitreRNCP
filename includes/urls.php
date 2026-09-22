<?php
/**
 * Fabrication des URL de l'application : prefixe d'installation, liens
 * internes, empreinte des fichiers statiques.
 *
 * Extrait d'auth_check.php pour la meme raison que session.php l'avait ete
 * avant : l'API mobile a besoin de construire des URL — celle d'une photo de
 * profil, par exemple — mais pas d'ouvrir une session ni de charger les
 * gardes de role, le jeu d'icones et les aides de formatage.
 *
 * Charger auth_check.php depuis un point d'API demarrait une session PHP a
 * chaque appel. L'application native n'envoie aucun cookie : cette session
 * n'etait jamais reprise, et chaque requete laissait derriere elle un fichier
 * de session a purger.
 *
 * auth_check.php requiert ce fichier : toutes les pages du site continuent
 * donc d'appeler baseUrl() et asset() sans rien changer.
 */

/**
 * Le chemin sous lequel l'application est servie, sans barre finale.
 *
 * « /TitreRNCP » sous WAMP, où le projet vit dans un sous-dossier de la racine
 * web ; chaîne vide sur Railway ou sur un mutualisé, où il EST la racine.
 *
 * Ce préfixe se déduisait du nom d'hôte : « localhost » ou « 127.0.0.1 »
 * voulait dire sous-dossier, tout le reste racine. La règle tenait tant que le
 * poste de développement ne se visitait que depuis lui-même. Ouvrir le site à
 * un téléphone du même réseau — http://192.168.x.x:8080/TitreRNCP/ — la mettait
 * en défaut : l'hôte n'est plus « localhost », le préfixe tombait, et chaque
 * lien, feuille de style et script pointait à la racine du serveur. La page
 * arrivait nue et aucun lien ne menait nulle part.
 *
 * L'hôte ne dit rien de l'endroit où le code est installé. Le chemin du script
 * en cours, lui, le dit exactement : il suffit de retirer de son URL la partie
 * qui correspond à sa position dans le projet.
 *
 *   SCRIPT_FILENAME  C:/wamp64/www/TitreRNCP/partenaire/dashboard.php
 *   racine projet    C:/wamp64/www/TitreRNCP
 *   reste            /partenaire/dashboard.php
 *   SCRIPT_NAME      /TitreRNCP/partenaire/dashboard.php
 *   donc préfixe     /TitreRNCP
 *
 * Vrai quel que soit l'hôte, le port, le nom du dossier, et que l'on passe par
 * une page de la racine ou d'un sous-dossier.
 */
function calculerPrefixe(string $scriptUrl, string $scriptDisque, string $racineProjet): string {
    $normaliser   = static fn (string $c): string => str_replace('\\', '/', $c);
    $scriptDisque = $normaliser($scriptDisque);
    $racine       = rtrim($normaliser($racineProjet), '/');

    if ($scriptUrl === '' || $scriptDisque === '' || $racine === '') {
        return '';
    }

    // Windows ne distingue pas la casse des chemins : « C:/WAMP64/www » et
    // « C:/wamp64/www » désignent le même dossier, et Apache n'emploie pas
    // toujours la même graphie que PHP.
    $memeDebut = DIRECTORY_SEPARATOR === '\\'
        ? stripos($scriptDisque, $racine . '/') === 0
        : str_starts_with($scriptDisque, $racine . '/');

    if (!$memeDebut) {
        return '';
    }

    $reste = substr($scriptDisque, strlen($racine));   // /partenaire/dashboard.php

    return str_ends_with($scriptUrl, $reste)
        ? rtrim(substr($scriptUrl, 0, -strlen($reste)), '/')
        // Repli : servi à la racine. C'est le cas de Railway et des
        // conteneurs, et le moins mauvais des paris — un préfixe inventé
        // casserait tous les liens, alors que son absence ne casse que
        // l'installation en sous-dossier.
        : '';
}

function prefixeApplication(): string {
    static $prefixe = null;

    if ($prefixe !== null) {
        return $prefixe;
    }

    $scriptDisque = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
    $racine       = dirname(__DIR__);

    // realpath() résout « .. » et les liens symboliques des deux côtés : sans
    // cela, une racine web qui passe par un lien ne correspondrait jamais au
    // chemin du script. Il est appelé ici, au bord, pour que le calcul
    // lui-même reste une fonction du seul texte des chemins — donc testable.
    $reelScript = realpath($scriptDisque);
    $reelRacine = realpath($racine);

    return $prefixe = calculerPrefixe(
        (string) ($_SERVER['SCRIPT_NAME'] ?? ''),
        $reelScript !== false ? $reelScript : $scriptDisque,
        $reelRacine !== false ? $reelRacine : $racine
    );
}

function baseUrl(string $path = ''): string {
    return prefixeApplication() . $path;
}

/**
 * Le même préfixe, publié pour le JavaScript.
 *
 * app.js refaisait le calcul de son côté, avec la même règle sur le nom
 * d'hôte — donc le même défaut, à corriger au même moment dans deux langages.
 * Une seule source désormais : PHP le calcule, la page le transporte.
 */
function metaBase(): string {
    return '<meta name="base-url" content="' . htmlspecialchars(prefixeApplication(), ENT_QUOTES) . '">';
}

/**
 * URL d'un fichier statique, suffixée par sa date de modification.
 *
 * Sans cela, après un déploiement les navigateurs continuent de servir
 * l'ancien CSS ou l'ancien JS depuis leur cache : une refonte visuelle
 * n'atteint jamais les utilisateurs déjà venus. L'empreinte change à
 * chaque modification du fichier, donc le cache se renouvelle tout seul —
 * et reste pleinement efficace tant que le fichier ne bouge pas.
 */
function asset(string $path): string {
    // Memoise : is_file() puis filemtime() font deux appels systeme, et la
    // fonction est appelee plusieurs fois par page — davantage encore par le
    // gabarit partenaire. Sur un hebergement mutualise, ou le code vit sur un
    // disque reseau, un stat() n'est pas gratuit.
    static $empreintes = [];

    if (!array_key_exists($path, $empreintes)) {
        $disque = dirname(__DIR__) . $path;
        $empreintes[$path] = is_file($disque) ? filemtime($disque) : null;
    }
    $v = $empreintes[$path];

    return baseUrl($path) . ($v ? '?v=' . $v : '');
}
