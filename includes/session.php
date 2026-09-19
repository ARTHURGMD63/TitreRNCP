<?php
/**
 * Démarrage de session — extrait de auth_check.php.
 *
 * Pourquoi ce fichier existe : les points d'entrée appelés très souvent
 * (api/live.php, interrogé par chaque onglet ouvert) ont besoin de la session
 * pour savoir qui parle, mais n'ont rien à faire du jeu d'icônes, des
 * gabarits ni des aides de formatage que auth_check.php traîne avec lui.
 * Charger 40 Ko de PHP pour répondre « rien de neuf » est exactement ce
 * qu'il ne faut pas faire à mille onglets ouverts.
 *
 * auth_check.php appelle la même fonction : la configuration du cookie n'est
 * donc écrite qu'une fois, et les deux chemins restent forcément identiques.
 */

/**
 * Ouvre la session avec un cookie durci.
 *
 * Doit impérativement s'exécuter AVANT tout session_start(), sans quoi les
 * drapeaux ne s'appliquent pas au cookie déjà émis.
 */
function demarrerSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    // Derrière le proxy de Railway, HTTPS se lit sur l'en-tête transmis.
    $enHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        // Lax et non Strict : un lien entrant vers l'application doit encore
        // trouver la session, sinon chaque partage ramène à l'écran de
        // connexion. Les écritures passent toutes par POST, protégées par le
        // jeton CSRF.
        'samesite' => 'Lax',
        'secure'   => $enHttps,
    ]);
    session_start();
}

/**
 * Ouvre la session, lit ce qu'il faut, et rend la main immédiatement.
 *
 * Le point que tout le monde oublie : PHP pose un verrou exclusif sur le
 * fichier de session pendant TOUTE la durée de la requête. Deux requêtes du
 * même utilisateur ne s'exécutent donc pas en parallèle, elles se suivent.
 * Un onglet qui interroge api/live.php met en file d'attente le chargement de
 * page que l'utilisateur vient de demander dans un autre onglet — et cela se
 * voit, sous forme de secondes d'attente inexpliquées.
 *
 * Les pages en lecture seule n'écrivent rien dans $_SESSION : elles peuvent
 * refermer le verrou tout de suite. $_SESSION reste lisible ensuite, seules
 * les écritures ne sont plus persistées.
 *
 * @return array{id:?int,type:string} l'identité du porteur de la session
 */
function sessionLectureSeule(): array
{
    demarrerSession();

    $identite = [
        'id'   => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        'type' => (string) ($_SESSION['user_type'] ?? ''),
    ];

    session_write_close();

    return $identite;
}
