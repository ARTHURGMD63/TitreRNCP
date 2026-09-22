<?php
// Configuration BDD, par ordre de priorité décroissant :
//
//   1. les variables d'environnement, là où l'hébergeur en fournit
//      (Railway, Docker, conteneurs en général) ;
//   2. includes/config.local.php, pour les hébergements mutualisés qui
//      n'en proposent pas — InfinityFree, o2switch, OVH mutualisé. Ce
//      fichier n'est pas versionné, le mot de passe reste sur le serveur.
//      Voir config.local.example.php ;
//   3. les valeurs WAMP par défaut, pour le poste de développement.
//
// Sans le point 2, un mutualisé retombait silencieusement sur « localhost »
// et « root », et la connexion échouait sans indiquer pourquoi.
require_once __DIR__ . '/log.php';
// La lecture des trois sources vit dans config.php : la configuration des
// e-mails en a le même besoin, et deux lecteurs finiraient par diverger.
require_once __DIR__ . '/config.php';
installerGardesErreurs();

define('DB_HOST', reglage('MYSQLHOST',     'host', 'localhost'));
define('DB_NAME', reglage('MYSQLDATABASE', 'name', 'studentlink'));
define('DB_USER', reglage('MYSQLUSER',     'user', 'root'));
define('DB_PASS', reglage('MYSQLPASSWORD', 'pass', ''));
define('DB_PORT', reglage('MYSQLPORT',     'port', '3306'));
define('DB_PERSISTANT', reglageBooleen('DB_PERSISTANT', 'persistant'));

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_TIMEOUT            => 10,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Les requêtes préparées sont réellement préparées côté serveur,
            // et non émulées : la valeur ne peut plus être interprétée comme
            // de la syntaxe SQL, quelle qu'elle soit.
            PDO::ATTR_EMULATE_PREPARES   => false,

            /*
             * Connexions persistantes, sur demande seulement.
             *
             * Ouvrir une connexion MySQL coute une poignee de main TCP puis
             * une authentification : negligeable quand la base tourne sur la
             * meme machine, mais 5 a 10 ms quand elle est sur un serveur
             * distinct, ce qui est le cas de tous les hebergements
             * mutualises. A cent requetes par seconde, c'est une seconde de
             * latence cumulee par seconde ecoulee.
             *
             * Pourquoi ce n'est pas active par defaut : une connexion
             * persistante est reutilisee telle quelle par la requete
             * suivante. Avec Apache en prefork, cela plafonne a une connexion
             * par processus — borne et sain. Sur un mutualise limite a 30
             * connexions simultanees, en revanche, c'est le meilleur moyen de
             * les epuiser et de rendre le site indisponible pour tout le
             * monde.
             *
             * Le choix appartient donc a l'hebergement, pas au code :
             * DB_PERSISTANT=1 dans l'environnement, ou 'persistant' => true
             * dans config.local.php.
             */
            PDO::ATTR_PERSISTENT         => DB_PERSISTANT,
        ]
    );
} catch (PDOException $e) {
    // Le message de PDO contient l'hôte et le nom de la base : il part dans
    // le journal, jamais dans la réponse.
    logErreur('Connexion à la base impossible', $e, ['hote' => DB_HOST, 'base' => DB_NAME]);

    http_response_code(503);
    if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Service momentanément indisponible']);
    } else {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><meta charset="utf-8"><title>Service indisponible</title>'
           . '<p style="font-family:system-ui;padding:40px">Service momentanément indisponible. '
           . 'Réessaie dans un instant.</p>';
    }
    exit;
}
