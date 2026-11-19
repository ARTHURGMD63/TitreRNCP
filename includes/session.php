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
/**
 * L'en-tête « Authorization » de la requête, ou une chaîne vide.
 *
 * Trois sources, parce que c'est l'en-tête le plus mal transmis de tous :
 * Apache ne le passe pas à PHP en CGI/FastCGI sans y être invité — il s'en
 * sert lui-même pour l'authentification HTTP — et le renomme en
 * `REDIRECT_HTTP_AUTHORIZATION` dès qu'une règle de réécriture est passée par
 * là. `apache_request_headers()` est le dernier recours, et le seul qui
 * fonctionne là où aucune règle ne peut être posée.
 *
 * Cette lecture vit ici, au plus bas, parce que deux modules en ont besoin et
 * qu'ils ne peuvent pas dépendre l'un de l'autre : session.php doit savoir
 * s'il faut ouvrir une session, api.php doit lire le jeton lui-même. Une
 * première version n'avait mis le repli que dans api.php — et session.php,
 * qui regardait $_SERVER seul, ne voyait jamais le jeton sous Apache. Il
 * ouvrait donc une session pour chaque appel mobile, silencieusement.
 */
function enteteAutorisation(): string
{
    $entete = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if ($entete === '' && function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $nom => $valeur) {
            if (strcasecmp($nom, 'Authorization') === 0) {
                $entete = $valeur;
                break;
            }
        }
    }

    return is_string($entete) ? trim($entete) : '';
}

/**
 * La requête porte-t-elle un jeton d'application plutôt qu'un cookie ?
 *
 * On ne lit ici que la FORME de l'en-tête, jamais sa validité : la question
 * posée est « faut-il ouvrir une session de navigateur ? », et la réponse ne
 * dépend pas de la valeur du jeton. La validation, elle, a besoin de la base
 * et vit dans apiSessionDepuisJeton().
 */
function requeteAvecJeton(): bool
{
    return stripos(enteteAutorisation(), 'Bearer ') === 0;
}

function demarrerSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    // AUCUNE SESSION POUR L'APPLICATION MOBILE.
    //
    // Elle n'envoie pas de cookie et ne le lira jamais : la session ouverte
    // ici ne serait donc reprise par personne. Mais session_start() écrit son
    // fichier immédiatement, avant même qu'on y range quoi que ce soit —
    // mesuré : trois appels d'API laissaient trois fichiers derrière eux. À
    // mille appareils qui sondent le direct, c'est un dossier de sessions
    // mortes qui grossit sans fin, et un fichier créé puis abandonné à chaque
    // requête.
    //
    // $_SESSION reste un tableau ordinaire, que apiSessionDepuisJeton()
    // remplit ensuite : les quatorze points d'API lisent la même chose
    // qu'avant, sans savoir d'où elle vient.
    if (requeteAvecJeton()) {
        $_SESSION = [];
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

/**
 * Empreinte des identifiants d'un compte, gardée dans la session web.
 *
 * Dérivée du hachage du mot de passe : tout changement de mot de passe
 * (réinitialisation, changement depuis l'application, compte supprimé) en
 * donne une autre. Le hachage lui-même ne va pas dans la session.
 */
function empreinteIdentifiants(string $hachage): string
{
    return substr(hash('sha256', 'outsee-session|' . $hachage), 0, 32);
}

/**
 * La session web porte-t-elle encore les identifiants actuels du compte ?
 *
 * Changer ou réinitialiser le mot de passe ne supprimait que les jetons de
 * l'application : une session web volée restait ouverte (trente jours pour
 * un établissement). Chaque requête compare donc l'empreinte rangée à
 * l'ouverture de la session à celle du mot de passe en base (audit SEC-05).
 *
 * Une session ouverte avant ce contrôle n'a pas d'empreinte : elle reçoit
 * celle du moment, et le prochain changement de mot de passe la fermera.
 * Vrai aussi quand aucun compte n'est connecté.
 */
function sessionIdentifiantsAJour(PDO $pdo): bool
{
    if (empty($_SESSION['user_id'])) {
        return true;
    }

    $stmt = $pdo->prepare('SELECT password, type FROM users WHERE id = ?');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($ligne) || ($ligne['type'] ?? '') === 'supprime') {
        return false;
    }

    $actuelle = empreinteIdentifiants((string) $ligne['password']);
    $rangee   = $_SESSION['identifiants'] ?? null;
    if (!is_string($rangee) || $rangee === '') {
        $_SESSION['identifiants'] = $actuelle;
        return true;
    }

    return hash_equals($rangee, $actuelle);
}
