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
