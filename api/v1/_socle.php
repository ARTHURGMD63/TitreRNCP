<?php
/**
 * Chargement commun des points d'API de l'application mobile.
 *
 * Volontairement plus maigre que le socle du site : pas de session ouverte,
 * pas de gardes de role par redirection, pas de jeu d'icones ni de gabarits.
 * Une reponse d'API ne redirige jamais — elle repond 401 et laisse
 * l'application decider quoi montrer.
 *
 * `api/` (sans version) reste le socle du site web, avec ses cookies et son
 * jeton CSRF. Les deux ne se melangent pas : un client, une facon de
 * s'authentifier.
 */

// Même fuseau que le site (auth_check.php). Sans lui, PHP compte en UTC :
// une échéance flash lue en base (heure de Paris) paraissait deux heures
// plus tard, et « il y a 3 h » devenait « il y a 5 h ».
date_default_timezone_set('Europe/Paris');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/urls.php';
require_once __DIR__ . '/../../includes/interets.php';
require_once __DIR__ . '/../../includes/api.php';

/** @var PDO $pdo fourni par includes/db.php */

// Les en-tetes de securite du site (CSP, X-Frame-Options) visent un document
// affiche par un navigateur. Sur du JSON ils n'ont pas d'objet ; celui-ci, en
// revanche, evite qu'un navigateur ne tente de deviner un type et d'executer
// une reponse comme du script.
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');

    // ── CORS ────────────────────────────────────────────────────────────────
    //
    // Une application native ne connait pas CORS : c'est une regle de
    // navigateur. Ces en-tetes servent donc au developpement — `expo start
    // --web` sert l'application depuis un autre port — et a un eventuel front
    // web qui consommerait cette API.
    //
    // POURQUOI « * » EST SANS DANGER ICI, ALORS QU'IL SERAIT GRAVE SUR api/ :
    //
    // Ce qui rend une origine permissive dangereuse, c'est l'association avec
    // des identifiants envoyes automatiquement — les cookies. Un site hostile
    // fait alors emettre au navigateur une requete authentifiee a l'insu de
    // l'utilisateur, et CORS lui donne le droit d'en lire la reponse.
    //
    // Cette API n'a pas de cookie : elle exige un jeton que seul le detenteur
    // peut poser dans un en-tete. Un site tiers ne l'a pas, et la ligne
    // suivante est celle qui garantit qu'il ne l'aura jamais par ce biais —
    // Allow-Credentials n'est PAS envoye, donc le navigateur n'attachera
    // jamais de cookie a une requete inter-origine vers ces points. Sans elle,
    // « * » serait de toute facon refuse par le navigateur.
    //
    // api/ (le socle du site, protege par cookie et jeton CSRF) n'a pas de
    // CORS et ne doit pas en avoir : la distinction est exactement la.
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Max-Age: 86400');
}

// Requete preliminaire : le navigateur demande la permission avant d'envoyer
// un en-tete Authorization. Elle n'a pas de corps et ne doit surtout pas
// traverser l'authentification, puisqu'elle ne porte justement pas le jeton.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// AUCUNE ERREUR N'EST AFFICHEE, MEME HORS PRODUCTION.
//
// installerGardesErreurs() couvre les exceptions et rend bien du JSON pour
// « /api/ ». Mais un simple avertissement — une cle de tableau absente — n'est
// pas une exception : il est *affiche*, et Xdebug l'affiche en HTML. Ce
// fragment se glisse alors avant le JSON, la reponse cesse d'etre analysable,
// et l'application mobile signale « reponse invalide » la ou le probleme est
// une ligne de PHP nommee dans un tableau HTML que personne ne lira.
//
// C'est arrive des le premier point d'API ecrit, et c'est exactement le genre
// de panne qu'on met une heure a diagnostiquer depuis un telephone.
//
// Les erreurs ne sont pas ignorees pour autant : log_errors reste actif, et
// elles partent dans le journal — le seul endroit ou elles servent vraiment
// quand le client est une application native.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/**
 * Bornes de pagination communes.
 *
 * Une limite lue telle quelle dans l'URL laisse n'importe qui demander
 * « ?limite=100000 » et faire balayer la table entiere a la base. Elle est
 * donc bornee ici, une fois, plutot qu'a chaque point d'API.
 *
 * @return array{page:int,limite:int,offset:int}
 */
function apiPagination(int $limiteDefaut = 20, int $limiteMax = 50): array
{
    $page   = max(1, (int) ($_GET['page'] ?? 1));
    $limite = (int) ($_GET['limite'] ?? $limiteDefaut);
    $limite = max(1, min($limiteMax, $limite));

    return ['page' => $page, 'limite' => $limite, 'offset' => ($page - 1) * $limite];
}

/**
 * L'adresse de la photo d'un étudiant, ou null.
 *
 * Même règle qu'avatarHtml() côté web : un nom de fichier en base ne suffit
 * pas, le fichier doit exister. Sinon l'application afficherait une image
 * cassée là où le site montre l'initiale.
 */
function apiPhotoUrl(?string $fichier): ?string
{
    if ($fichier === null || $fichier === '') {
        return null;
    }
    require_once __DIR__ . '/../../includes/uploads.php';

    return is_file(avatarDir() . '/' . $fichier) ? avatarUrlAbsolue($fichier) : null;
}

/**
 * Une personne telle que les listes de l'application l'affichent.
 *
 * @param array<string,mixed> $p ligne de users (id, prenom, nom, photo…)
 * @return array<string,mixed>
 */
function apiPersonne(array $p): array
{
    return [
        'id'        => (int) $p['id'],
        'prenom'    => (string) $p['prenom'],
        'nom'       => (string) ($p['nom'] ?? ''),
        'ecole'     => isset($p['ecole']) && $p['ecole'] !== null ? (string) $p['ecole'] : null,
        'promo'     => isset($p['promo']) && $p['promo'] !== null ? (string) $p['promo'] : null,
        'photo_url' => apiPhotoUrl(isset($p['photo']) ? (string) $p['photo'] : null),
    ];
}
