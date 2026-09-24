<?php
/**
 * api/live.php — « qu'est-ce qui a changé depuis tout à l'heure ? »
 *
 * Un seul point d'interrogation pour toute l'application : la pastille de la
 * cloche, les compteurs d'inscrits, les places restantes. Avant, chaque
 * élément de page interrogeait son propre endpoint ; trois compteurs sur un
 * écran, c'étaient trois requêtes HTTP toutes les quinze secondes.
 *
 * Trois décisions expliquent la forme de ce fichier, et chacune se paie
 * directement en nombre d'utilisateurs simultanés supportés :
 *
 *  1. Amorçage minimal. Ni auth_check.php, ni le jeu d'icônes, ni les
 *     gabarits : la session, la base, le flux. C'est le fichier PHP le plus
 *     appelé de l'application, chaque require y compte.
 *
 *  2. Verrou de session relâché immédiatement (sessionLectureSeule). PHP
 *     garde sinon un verrou exclusif sur le fichier de session pendant toute
 *     la requête, et l'interrogation d'un onglet bloquerait le chargement de
 *     page demandé dans un autre onglet du même utilisateur.
 *
 *  3. ETag d'abord, données ensuite. Le cas courant — rien n'a changé —
 *     coûte une lecture sur clé primaire et renvoie un 304 sans corps. Les
 *     requêtes de comptage ne s'exécutent que lorsqu'il y a réellement
 *     quelque chose à dire.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/temps_reel.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$identite = sessionLectureSeule();
$uid      = $identite['id'];

// L'application mobile présente un jeton, pas de cookie : même pont que pour
// les écritures (protegerEcritureApi()). Une requête de navigateur n'est pas
// concernée — elle n'envoie jamais d'en-tête « Authorization: Bearer ».
if ($uid === null && requeteAvecJeton()) {
    require_once __DIR__ . '/../includes/api.php';
    if (apiSessionDepuisJeton()) {
        $uid = (int) $_SESSION['user_id'];
    }
}

if ($uid === null) {
    // 401 et non 200 : le client sait alors qu'il doit cesser d'interroger
    // et renvoyer l'utilisateur vers la connexion, au lieu de boucler
    // indéfiniment sur une réponse vide.
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expirée']);
    exit;
}

/*
 * Périmètre observé. Le client annonce les événements affichés à l'écran ;
 * on y ajoute d'office son canal personnel (cloche, demandes) et le canal
 * global (nouvelles soirées publiées).
 *
 * La liste est bornée à 60 identifiants : un paramètre d'URL bricolé avec
 * dix mille identifiants ferait une clause IN de dix mille marqueurs, soit
 * exactement le genre de requête qu'un attaquant répète pour occuper la base.
 */
const LIVE_MAX_CANAUX = 60;

$evIds = array_slice(
    array_values(array_unique(array_filter(
        array_map('intval', explode(',', (string) ($_GET['ev'] ?? ''))),
        static fn(int $id): bool => $id > 0
    ))),
    0,
    LIVE_MAX_CANAUX
);

$canaux = array_merge(
    [CANAL_GLOBAL, canalUtilisateur($uid)],
    array_map('canalEvenement', $evIds)
);

$revisions = fluxRevisions($pdo, $canaux);

// Le point de bascule : si l'empreinte du périmètre est celle que le client
// détient déjà, on s'arrête ici. Aucune requête de données n'est exécutée.
if (fluxRepondreSiInchange(fluxSignature($revisions))) {
    exit;
}

$reponse = [
    'success'   => true,
    'revisions' => $revisions,
];

/*
 * Compteurs d'inscrits des événements affichés — une seule requête groupée
 * pour la totalité de l'écran, et non une par carte. L'index
 * idx_insc_ev_statut la sert entièrement.
 */
if ($evIds) {
    $trous = implode(',', array_fill(0, count($evIds), '?'));
    $stmt  = $pdo->prepare(
        "SELECT evenement_id, COUNT(*) AS nb
           FROM inscriptions
          WHERE evenement_id IN ($trous) AND statut <> 'annule'
          GROUP BY evenement_id"
    );
    $stmt->execute($evIds);

    // Les événements sans aucun inscrit ne ressortent pas d'un GROUP BY :
    // sans cette initialisation, un compteur retombé à zéro garderait à
    // l'écran sa dernière valeur connue.
    $compteurs = array_fill_keys($evIds, 0);
    foreach ($stmt->fetchAll() as $ligne) {
        $compteurs[(int) $ligne['evenement_id']] = (int) $ligne['nb'];
    }
    // Les clés d'un tableau PHP à index entiers deviennent un tableau JSON si
    // elles sont contiguës. Forcées en chaînes, elles restent un objet, que le
    // client indexe par identifiant d'événement.
    $reponse['inscrits'] = (object) array_combine(
        array_map('strval', array_keys($compteurs)),
        array_values($compteurs)
    );
}

/*
 * Ce qui attend une réponse : demandes d'abonnement et invitations. Un seul
 * aller-retour, deux sous-requêtes servies chacune par un index existant
 * (idx_follows_demandes et k_to). Construire la liste complète des
 * notifications ici aurait coûté cinq requêtes pour afficher un chiffre.
 */
$stmt = $pdo->prepare(
    "SELECT
        (SELECT COUNT(*) FROM follows_users
          WHERE followed_id = ? AND statut = 'pending') AS demandes,
        (SELECT COUNT(*) FROM invitations
          WHERE to_user_id = ? AND statut = 'pending')  AS invitations"
);
$stmt->execute([$uid, $uid]);
$attente = $stmt->fetch() ?: ['demandes' => 0, 'invitations' => 0];

$reponse['aTraiter'] = (int) $attente['demandes'] + (int) $attente['invitations'];

echo json_encode($reponse);
