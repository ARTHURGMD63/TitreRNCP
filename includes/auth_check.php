<?php
// Aligne PHP sur le fuseau du serveur MySQL (heure locale française) :
// les fenêtres temporelles calculées en PHP (graphiques, comparaisons)
// doivent correspondre aux TIMESTAMP écrits par MySQL.
date_default_timezone_set('Europe/Paris');

require_once __DIR__ . '/security.php';

// reglage() / reglageBooleen() : le mode « bientôt disponible » plus bas en
// a besoin, et rien avant ce point ne garantit que config.php est chargé.
require_once __DIR__ . '/config.php';

// Le durcissement du cookie de session vit dans session.php : api/live.php a
// besoin d'ouvrir la session sans charger tout ce fichier, et deux copies de
// ces paramètres finiraient tôt ou tard par diverger.
require_once __DIR__ . '/session.php';
demarrerSession();

setSecurityHeaders();
expirerSessionInactive();

// Jeu d'icones : inclus ici parce que ce fichier est le point commun de
// toutes les pages, donc icon() est disponible partout sans require repete.
require_once __DIR__ . '/icons.php';

// Fabrication des URL. Extrait ici pour que l'API mobile puisse construire
// ses liens sans demarrer de session ni charger les gardes de role.
require_once __DIR__ . '/urls.php';

/**
 * Mode « bientôt disponible ».
 *
 * Avant le lancement public, seule la page d'accueil (compte à rebours et
 * liste d'attente) doit être joignable. Un lien partagé trop tôt, une URL
 * indexée par un moteur de recherche, ou quelqu'un qui tape /explore.php à
 * la main ne doivent pas donner accès à une application qui n'a pas encore
 * ouvert.
 *
 * Piloté par une variable d'environnement plutôt que codé en dur : couper
 * l'interrupteur au lancement ne demande alors aucune modification de code,
 * juste APP_COMING_SOON=0 (ou l'équivalent côté hébergeur mutualisé).
 * Vrai par défaut — l'oubli d'activer la variable ne doit jamais laisser
 * l'application ouverte avant l'heure.
 */
function modeBientotDisponible(): bool
{
    return reglageBooleen('APP_COMING_SOON', 'coming_soon', true);
}

/**
 * Bloque l'accès direct aux pages de l'application pendant le mode
 * « bientôt disponible ». Appelée automatiquement en bas de ce fichier : les
 * pages qui chargent auth_check.php sont donc couvertes sans y penser à
 * chaque fois, sur le même principe que setSecurityHeaders().
 *
 * Un administrateur connecté passe outre : c'est lui qui prépare le
 * lancement (back-office, comptes de test), et lui seul a besoin d'entrer
 * avant l'heure.
 *
 * La comparaison porte sur le chemin réel du fichier exécuté
 * (SCRIPT_FILENAME), jamais sur l'URL : un hébergement mutualisé sert
 * l'application depuis /TitreRNCP/, Render depuis la racine, et les deux
 * doivent être couverts par la même liste, écrite une fois.
 */
function verifierGateBientotDisponible(): void
{
    // PHPUnit, outils/migrer.php, tout ce qui tourne en ligne de commande :
    // jamais « le public ». Sans ce garde, le simple fait de charger
    // auth_check.php dans un test (tests/Unit/AuthCheckTest.php, entre
    // autres) déclenchait exit() en pleine collecte des tests, et la suite
    // s'arrêtait sans le moindre message.
    if (PHP_SAPI === 'cli' || !modeBientotDisponible() || ($_SESSION['user_type'] ?? null) === 'admin') {
        return;
    }

    static $autorises = [
        '/index.php',
        '/auth/login.php',
        '/mentions-legales.php',
        '/confidentialite.php',
        '/cgu.php',
    ];

    $racine  = dirname(__DIR__);
    $reel    = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $relatif = $reel !== false ? str_replace('\\', '/', substr($reel, strlen($racine))) : '';

    if (in_array($relatif, $autorises, true)) {
        return;
    }

    // « /api/ » (site) et « /api/v1/ » (mobile) restent joignables : l'API
    // mobile ne charge pas ce fichier (api/v1/_socle.php est indépendant),
    // et les points d'écriture partagés (api/inscrire.php, api/inviter.php…)
    // sont ceux que l'application mobile, déjà en test, utilise. Bloquer le
    // *site* pendant le compte à rebours n'a pas à casser le développement
    // mobile — seules les pages HTML sont concernées par cette page d'attente.
    if (str_starts_with($relatif, '/api/')) {
        return;
    }

    header('Location: ' . baseUrl('/'));
    exit;
}
verifierGateBientotDisponible();

/**
 * La page d'accueil d'un compte, selon son type.
 *
 * Sans point unique, chaque garde renvoyait vers « l'autre » espace, et les
 * renvois se répondaient : un administrateur envoyé sur explore.php était
 * repoussé par requireStudent() vers le tableau de bord partenaire, que
 * requirePartner() repoussait vers explore.php. Boucle infinie, compte
 * fondateur inutilisable. Un type inconnu retombe côté étudiant, qui est
 * l'espace le moins privilégié.
 */
function accueilSelonType(?string $type): string {
    return match ($type) {
        'partenaire' => baseUrl('/partenaire/dashboard.php'),
        'admin'      => baseUrl('/admin/index.php'),
        default      => baseUrl('/explore.php'),
    };
}

/**
 * Délai d'inactivité avant déconnexion, en secondes.
 *
 * Le back-office ouvre le fichier clients, les coordonnées des étudiants et
 * la trésorerie : une session oubliée sur un écran y coûte infiniment plus
 * cher que côté étudiant, où une reconnexion permanente ferait simplement
 * fuir l'usage. D'où deux délais, et non un compromis qui ne convient à
 * personne.
 */
function delaiInactivite(?string $type): int {
    return $type === 'admin' ? 60 * 60 : 30 * 24 * 60 * 60;
}

/**
 * Ferme une session restée inactive trop longtemps.
 *
 * session_regenerate_id(true) plutôt que session_destroy() : l'ancien fichier
 * de session est supprimé et son identifiant devient inutilisable, mais la
 * session reste ouverte le temps de porter le message jusqu'à l'écran de
 * connexion. Détruite, elle ne pourrait rien expliquer.
 */
function expirerSessionInactive(): void {
    if (empty($_SESSION['user_id'])) return;

    $limite = delaiInactivite($_SESSION['user_type'] ?? null);
    $derniere = $_SESSION['derniere_activite'] ?? time();

    if (time() - $derniere > $limite) {
        $_SESSION = [];
        // Meme garde que setSecurityHeaders() : appelee apres le moindre
        // octet envoye, la regeneration echoue et affiche son avertissement
        // au milieu de la page. Vider la session suffit a couper l'acces.
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
        $_SESSION['session_expiree'] = true;
        return;
    }

    $_SESSION['derniere_activite'] = time();
}

function requireLogin(string $redirect = ''): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . baseUrl('/auth/login.php'));
        exit;
    }
}

function requirePartner(): void {
    requireLogin();
    if (($_SESSION['user_type'] ?? '') !== 'partenaire') {
        header('Location: ' . accueilSelonType($_SESSION['user_type'] ?? null));
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['user_type'] ?? '') !== 'admin') {
        header('Location: ' . accueilSelonType($_SESSION['user_type'] ?? null));
        exit;
    }
}

function requireStudent(): void {
    requireLogin();
    if (($_SESSION['user_type'] ?? '') !== 'etudiant') {
        header('Location: ' . accueilSelonType($_SESSION['user_type'] ?? null));
        exit;
    }
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Applique le thème avant le premier rendu et colore la barre d'état.
 *
 * Sans la balise theme-color, la barre du navigateur restait claire au-dessus
 * d'une application sombre. Elle est posée ici parce que cette fonction est
 * déjà le point unique inclus par les 21 pages.
 *
 * Charte Linkee : l'app étudiant est « la nuit » — basalte par défaut, le
 * choix enregistré dans « Moi » restant respecté. L'espace partenaire et le
 * back-office sont « le jour » : $fixe = 'light' les tient en craie quel que
 * soit le thème stocké par le navigateur, et data-theme-fixe le signale à
 * app.js, qui réapplique sinon le thème stocké.
 */
function themeBootScript(?string $fixe = null): string {
    if ($fixe === 'light' || $fixe === 'dark') {
        return '<meta name="theme-color" content="' . ($fixe === 'dark' ? '#111013' : '#F5F1E8') . '">'
             . '<script>(function(){'
             . 'var d=document.documentElement;'
             . 'd.setAttribute("data-theme","' . $fixe . '");'
             . 'd.setAttribute("data-theme-fixe","' . $fixe . '");'
             . '})();</script>';
    }
    return '<meta name="theme-color" content="#111013">'
         . '<script>(function(){'
         . 'var t=localStorage.getItem("theme")||"dark";'
         . 'document.documentElement.setAttribute("data-theme",t);'
         . 'var m=document.head.querySelector("meta[name=theme-color]");'
         . 'if(m)m.setAttribute("content",t==="dark"?"#111013":"#F5F1E8");'
         . '})();</script>';
}

/** Retourne et efface le flash CSRF s'il existe, sinon ''. */
function csrfFlash(): string {
    if (!empty($_SESSION['csrf_error'])) {
        $msg = $_SESSION['csrf_error'];
        unset($_SESSION['csrf_error']);
        return '<div class="form-error" role="alert" aria-live="assertive">' . htmlspecialchars($msg) . '</div>';
    }
    return '';
}

function currentUser(): array {
    return [
        'id'     => $_SESSION['user_id'] ?? null,
        'prenom' => $_SESSION['user_prenom'] ?? '',
        'nom'    => $_SESSION['user_nom'] ?? '',
        'type'   => $_SESSION['user_type'] ?? '',
        'ecole'  => $_SESSION['user_ecole'] ?? '',
    ];
}

/**
 * Âge en années révolues à partir d'une date « AAAA-MM-JJ ».
 *
 * Renvoie null si la date est absente, mal formée ou impossible (le 31
 * février, qu'un champ date natif laisse passer sur certains clients).
 * DateTime::diff gère les années bissextiles, ce qu'un calcul en jours
 * divisé par 365 ne fait pas.
 */
function ageEnAnnees(?string $date): ?int
{
    if (!$date) {
        return null;
    }
    $naissance = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $erreurs   = DateTimeImmutable::getLastErrors();
    if (!$naissance || ($erreurs && ($erreurs['warning_count'] || $erreurs['error_count']))) {
        return null;
    }
    $aujourdhui = new DateTimeImmutable('today');
    if ($naissance > $aujourdhui) {
        return null;
    }

    return (int) $naissance->diff($aujourdhui)->y;
}

/**
 * Date formatée en français.
 *
 * date() ne connaît que l'anglais : « WED 16 SEP » s'affichait partout, sur
 * une application destinée à des étudiants clermontois. On formate d'abord,
 * puis on traduit les seuls jetons de jour et de mois — les autres caractères
 * du motif sont déjà neutres.
 *
 * @param string|int $quand date ISO ou horodatage
 */
function dateFr($quand, string $format = 'D j M'): string
{
    $ts = is_int($quand) ? $quand : (int) strtotime((string) $quand);

    static $jours = ['Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mer', 'Thu' => 'Jeu',
                     'Fri' => 'Ven', 'Sat' => 'Sam', 'Sun' => 'Dim'];
    static $mois  = ['Jan' => 'Janv', 'Feb' => 'Févr', 'Mar' => 'Mars', 'Apr' => 'Avr',
                     'May' => 'Mai', 'Jun' => 'Juin', 'Jul' => 'Juil', 'Aug' => 'Août',
                     'Sep' => 'Sept', 'Oct' => 'Oct', 'Nov' => 'Nov', 'Dec' => 'Déc'];
    static $longs = ['Monday' => 'Lundi', 'Tuesday' => 'Mardi', 'Wednesday' => 'Mercredi',
                     'Thursday' => 'Jeudi', 'Friday' => 'Vendredi', 'Saturday' => 'Samedi',
                     'Sunday' => 'Dimanche', 'January' => 'Janvier', 'February' => 'Février',
                     'March' => 'Mars', 'April' => 'Avril', 'June' => 'Juin',
                     'July' => 'Juillet', 'August' => 'Août', 'September' => 'Septembre',
                     'October' => 'Octobre', 'November' => 'Novembre', 'December' => 'Décembre'];

    // Les noms longs d'abord : « Mar » est un préfixe de « March ».
    return strtr(date($format, $ts), $longs + $jours + $mois);
}
