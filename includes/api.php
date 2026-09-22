<?php
/**
 * Socle de l'API mobile : authentification par jeton et réponses JSON.
 *
 * POURQUOI UNE SECONDE FAÇON DE S'AUTHENTIFIER
 *
 * Le site web s'appuie sur la session PHP : le navigateur renvoie le cookie
 * tout seul. C'est précisément parce qu'il le renvoie tout seul — y compris
 * quand la requête part d'un autre site — que `protegerEcritureApi()` exige
 * par-dessus un jeton CSRF et une origine.
 *
 * Une application native ne renvoie rien toute seule. Elle range un jeton et
 * le présente explicitement à chaque appel. Cela supprime la faille CSRF à la
 * racine : une requête qu'aucun navigateur n'émet automatiquement ne peut pas
 * être déclenchée à l'insu de l'utilisateur. Le contrôle d'origine n'a pas
 * davantage de sens — une application n'a pas d'origine web à présenter.
 *
 * Les deux mondes cohabitent sans se gêner : `api/` reste réservé au site,
 * `api/v1/` à l'application. Aucun point existant n'est modifié.
 *
 * CE QUI N'EST PAS FAIT ICI, ET POURQUOI
 *
 * Pas de JWT. Un JWT est fait pour être vérifiable sans consulter la base —
 * utile quand plusieurs services doivent valider un jeton sans partager de
 * stockage. Ici il y a un serveur et une base : le seul effet serait de rendre
 * la révocation impossible, puisqu'un JWT reste valide jusqu'à son expiration
 * même après une déconnexion. Un jeton opaque en base se révoque en une ligne,
 * ce qui est exactement ce qu'on veut d'un téléphone perdu.
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/log.php';

/** Durée de vie d'un jeton, prolongée à chaque usage. */
const API_JETON_JOURS = 30;

/** En deçà, on ne réécrit pas `derniere_utilisation` (voir apiUtilisateur()). */
const API_TOUCHER_APRES_SECONDES = 3600;

/**
 * Termine la requête sur une réponse JSON.
 *
 * @param array<string,mixed> $donnees
 */
function apiReponse(array $donnees, int $code = 200): never
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        // L'API ne se met pas en cache : une liste d'événements ou un solde
        // affiché avec dix minutes de retard est pire qu'un appel de plus.
        header('Cache-Control: no-store');
    }

    echo json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Termine la requête sur une erreur.
 *
 * `code` est une étiquette stable que l'application peut tester, là où
 * `message` est destiné à l'affichage et peut être réécrit sans préavis.
 */
function apiErreur(string $message, int $statut = 400, string $code = ''): never
{
    apiReponse([
        'success' => false,
        'code'    => $code !== '' ? $code : (string) $statut,
        'message' => $message,
    ], $statut);
}

/** Refuse toute méthode autre que celle attendue. */
function apiExigerMethode(string $methode): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $methode) {
        header('Allow: ' . $methode);
        apiErreur('Méthode non autorisée', 405, 'methode');
    }
}

/**
 * Le corps JSON de la requête, en tableau.
 *
 * @return array<string,mixed>
 */
function apiCorps(): array
{
    $brut = file_get_contents('php://input');
    if ($brut === false || trim($brut) === '') {
        return [];
    }

    $decode = json_decode($brut, true);

    return is_array($decode) ? $decode : [];
}

/**
 * Le jeton présenté dans l'en-tête `Authorization: Bearer …`.
 *
 * Trois sources, parce que cet en-tête est le plus mal transmis de tous :
 * Apache ne le passe pas à PHP en CGI/FastCGI sans y être invité, et le
 * réécrit en `REDIRECT_HTTP_AUTHORIZATION` quand une règle de réécriture est
 * passée par là. Le .htaccess de `api/v1/` le rétablit ; ces replis couvrent
 * les hébergements où il ne peut pas être appliqué.
 */
function apiJetonPresente(): ?string
{
    // La lecture de l'en-tete vit dans session.php : elle sert aussi a decider
    // s'il faut ouvrir une session, et deux lectures finiraient par diverger —
    // ce qui est deja arrive une fois, l'une ayant le repli Apache et l'autre
    // non.
    if (!preg_match('/^Bearer\s+([A-Za-z0-9._-]+)$/i', enteteAutorisation(), $m)) {
        return null;
    }

    return $m[1];
}

/** L'empreinte sous laquelle un jeton est rangé. Voir db_migrations_v16.sql. */
function apiEmpreinte(string $jeton): string
{
    return hash('sha256', $jeton);
}

/**
 * Crée un jeton pour un compte et le renvoie EN CLAIR.
 *
 * C'est la seule fois où il est lisible : seule son empreinte est conservée.
 *
 * @return array{token:string,expire_le:string}
 */
function apiCreerJeton(PDO $pdo, int $userId, ?string $appareil = null): array
{
    // 32 octets de random_bytes : c'est le générateur cryptographique du
    // système, pas rand(). Un jeton devinable vaut un mot de passe devinable.
    $jeton  = bin2hex(random_bytes(32));
    $expire = date('Y-m-d H:i:s', time() + API_JETON_JOURS * 86400);

    $stmt = $pdo->prepare(
        'INSERT INTO api_tokens (user_id, token_hash, appareil, expire_le, derniere_utilisation)
         VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $userId,
        apiEmpreinte($jeton),
        $appareil !== null && $appareil !== '' ? mb_substr($appareil, 0, 120) : null,
        $expire,
    ]);

    return ['token' => $jeton, 'expire_le' => $expire];
}

/**
 * Le compte authentifié, ou 401.
 *
 * @return array<string,mixed> la ligne `users` du porteur du jeton
 */
function apiUtilisateur(PDO $pdo): array
{
    $jeton = apiJetonPresente();
    if ($jeton === null) {
        apiErreur('Authentification requise', 401, 'jeton_absent');
    }

    $stmt = $pdo->prepare(
        'SELECT t.id AS token_id, t.expire_le, t.derniere_utilisation,
                u.id, u.nom, u.prenom, u.email, u.ecole, u.promo, u.photo,
                u.interests, u.type
           FROM api_tokens t
           JOIN users u ON u.id = t.user_id
          WHERE t.token_hash = ?'
    );
    $stmt->execute([apiEmpreinte($jeton)]);
    $ligne = $stmt->fetch();

    // Même réponse pour « jeton inconnu » et « jeton périmé » : distinguer les
    // deux dirait à qui tâtonne lesquels de ses essais ont déjà existé.
    if (!$ligne || strtotime((string) $ligne['expire_le']) < time()) {
        apiErreur('Session expirée, reconnecte-toi', 401, 'jeton_invalide');
    }

    // Prolongation et horodatage, au plus une fois par heure. Écrire à chaque
    // appel transformerait toute lecture d'API en écriture — et le sondage du
    // direct, qui passe par ici, tape plusieurs fois par minute et par
    // appareil.
    $derniere = $ligne['derniere_utilisation'] !== null
        ? strtotime((string) $ligne['derniere_utilisation'])
        : 0;

    if (time() - $derniere > API_TOUCHER_APRES_SECONDES) {
        $maj = $pdo->prepare(
            'UPDATE api_tokens SET derniere_utilisation = NOW(), expire_le = ? WHERE id = ?'
        );
        $maj->execute([
            date('Y-m-d H:i:s', time() + API_JETON_JOURS * 86400),
            (int) $ligne['token_id'],
        ]);
    }

    return $ligne;
}

/** Le compte authentifié, en exigeant qu'il soit étudiant. */
function apiEtudiant(PDO $pdo): array
{
    $u = apiUtilisateur($pdo);
    if (($u['type'] ?? '') !== 'etudiant') {
        apiErreur('Réservé aux comptes étudiants', 403, 'role');
    }

    return $u;
}

/**
 * Ouvre une session applicative à partir du jeton, s'il y en a un de valide.
 *
 * POURQUOI CETTE FONCTION EXISTE
 *
 * Les quatorze points de `api/` portent 1 068 lignes de logique métier —
 * s'inscrire, suivre, rejoindre un squad, inviter, modérer — et lisent tous
 * `$_SESSION['user_id']`. Les réécrire pour le mobile aurait produit deux
 * implémentations de chaque règle, condamnées à diverger : le jour où le quota
 * d'une soirée change d'un côté, il ne change pas de l'autre.
 *
 * Plutôt que de dupliquer, on renseigne la session comme l'aurait fait une
 * connexion par formulaire. Les points existants continuent de lire ce qu'ils
 * ont toujours lu, sans une ligne de changement, et servent les deux clients.
 *
 * CE QUE CELA N'OUVRE PAS
 *
 * Aucun contournement du jeton CSRF pour un navigateur : cette fonction exige
 * un en-tête `Authorization`, qu'aucun navigateur n'ajoute tout seul. Un site
 * hostile ne peut pas le poser sur une requête inter-origine sans une
 * autorisation préalable que `api/v1/` n'accorde qu'en l'absence de cookies
 * (voir la note CORS du socle). La garde CSRF reste donc entière pour le web.
 *
 * @return bool vrai si un jeton valide a été présenté
 */
function apiSessionDepuisJeton(): bool
{
    $jeton = apiJetonPresente();
    if ($jeton === null) {
        return false;
    }

    // $pdo est créé par db.php, chargé avant l'appel par tous les points
    // concernés. Il est pris ici explicitement plutôt que reçu en paramètre :
    // la solution inverse aurait obligé à modifier les quatorze signatures,
    // c'est-à-dire exactement ce que cette fonction évite.
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!$pdo instanceof PDO) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT u.id, u.prenom, u.nom, u.type, u.ecole
           FROM api_tokens t
           JOIN users u ON u.id = t.user_id
          WHERE t.token_hash = ? AND t.expire_le >= NOW()'
    );
    $stmt->execute([apiEmpreinte($jeton)]);
    $u = $stmt->fetch();

    if (!$u) {
        return false;
    }

    $_SESSION['user_id']     = (int) $u['id'];
    $_SESSION['user_type']   = (string) $u['type'];
    $_SESSION['user_prenom'] = (string) $u['prenom'];
    $_SESSION['user_nom']    = (string) $u['nom'];
    $_SESSION['user_ecole']  = (string) ($u['ecole'] ?? '');

    // Rien à écarter : demarrerSession() n'a ouvert aucune session, parce
    // qu'elle a reconnu une requête à jeton (voir requeteAvecJeton()). Le
    // tableau rempli ci-dessus ne vit qu'en mémoire, le temps de la requête.
    return true;
}

/** Révoque le jeton présenté. Sans effet s'il n'existe pas. */
function apiRevoquerJeton(PDO $pdo, string $jeton): void
{
    $pdo->prepare('DELETE FROM api_tokens WHERE token_hash = ?')
        ->execute([apiEmpreinte($jeton)]);
}

/** Supprime les jetons périmés. Appelé par cron/entretien.php. */
function apiPurgerJetons(PDO $pdo): int
{
    $stmt = $pdo->prepare('DELETE FROM api_tokens WHERE expire_le < NOW()');
    $stmt->execute();

    return $stmt->rowCount();
}

/**
 * Met un compte en forme pour l'application.
 *
 * Point de passage unique : sans lui, chaque point d'API choisirait ses
 * propres clés, et l'application finirait par lire `prenom` ici et
 * `firstName` là. Surtout, c'est ici que l'on décide de ce qui ne sort pas —
 * le hachage du mot de passe n'a rien à faire dans une réponse, et il suffit
 * d'un `SELECT *` distrait pour l'y envoyer.
 *
 * @param array<string,mixed> $u
 * @return array<string,mixed>
 */
function apiProfil(array $u): array
{
    return [
        'id'        => (int) $u['id'],
        'prenom'    => (string) $u['prenom'],
        'nom'       => (string) $u['nom'],
        'email'     => (string) ($u['email'] ?? ''),
        'ecole'     => $u['ecole'] !== null ? (string) $u['ecole'] : null,
        'promo'     => $u['promo'] !== null ? (string) $u['promo'] : null,
        'type'      => (string) $u['type'],
        'photo_url' => !empty($u['photo']) ? avatarUrlAbsolue((string) $u['photo']) : null,
        'interets'  => interetsDepuisTexte($u['interests'] ?? null),
    ];
}

/**
 * URL absolue d'un fichier téléversé.
 *
 * L'application n'a pas de page courante : un chemin relatif comme
 * « /TitreRNCP/uploads/… » ne lui dit rien. Toute URL qui sort de l'API doit
 * donc porter son schéma et son hôte.
 */
function apiUrlAbsolue(string $chemin): string
{
    $enHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $hote    = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

    return ($enHttps ? 'https://' : 'http://') . $hote . $chemin;
}

/** URL absolue d'une photo de profil. */
function avatarUrlAbsolue(string $fichier): string
{
    return apiUrlAbsolue(baseUrl('/uploads/avatars/' . rawurlencode($fichier)));
}
