<?php
/**
 * Temps réel — le flux de révisions.
 *
 * Le problème à résoudre : l'utilisateur A s'inscrit à une soirée, et
 * l'utilisateur B, qui regarde la même page, doit le voir tout de suite.
 *
 * La solution évidente — chaque onglet redemande les données toutes les
 * quelques secondes — ne tient pas à l'échelle. Mille onglets qui
 * réinterrogent un compteur toutes les 15 secondes, ce sont 67 requêtes par
 * seconde dont 99 % répondent « rien n'a changé », chacune payant le
 * démarrage de PHP, l'ouverture de session et plusieurs requêtes SQL.
 *
 * La solution retenue tient en deux idées :
 *
 *   1. Un compteur par canal (`event:42`, `user:7`), incrémenté à chaque
 *      écriture. Savoir s'il y a du neuf coûte une lecture sur clé primaire.
 *   2. Un ETag HTTP construit sur ces compteurs. Quand rien n'a bougé, le
 *      serveur répond « 304 Not Modified », sans corps et sans avoir exécuté
 *      la moindre requête de données. Le navigateur, lui, sait nativement
 *      quoi en faire.
 *
 * Ce qui a été écarté, et pourquoi : les Server-Sent Events et les WebSockets
 * donnent un temps réel plus fin, mais gardent une connexion — donc un
 * processus PHP — ouverte par client. Sur Apache en mod_php comme sur un
 * mutualisé, mille clients connectés signifient mille processus : le serveur
 * s'arrête bien avant. L'interrogation courte avec ETag donne une latence
 * perçue de quelques secondes pour un coût qui, lui, reste proportionnel aux
 * changements réels et non au nombre de spectateurs.
 */

require_once __DIR__ . '/log.php';

/** Nom de canal pour un événement. */
function canalEvenement(int $id): string
{
    return 'event:' . $id;
}

/** Nom de canal pour un utilisateur (sa cloche, ses demandes). */
function canalUtilisateur(int $id): string
{
    return 'user:' . $id;
}

/** Nom de canal pour une squad. */
function canalSquad(int $id): string
{
    return 'squad:' . $id;
}

/** Le canal que tout le monde observe : création d'événements, annonces. */
const CANAL_GLOBAL = 'global';

/**
 * Signale qu'un ou plusieurs canaux viennent de changer.
 *
 * Appelée juste après l'écriture qu'elle annonce. Volontairement tolérante
 * aux pannes : si la table de flux est absente — migration v14 pas encore
 * passée — l'inscription de l'étudiant ne doit pas échouer pour autant. Le
 * temps réel est un confort, la donnée est le contrat.
 */
function fluxToucher(PDO $pdo, string ...$canaux): void
{
    $canaux = array_values(array_unique(array_filter($canaux)));
    if (!$canaux) {
        return;
    }

    // Une seule requête pour tous les canaux : un INSERT multi-valeurs vaut
    // mieux que quatre allers-retours réseau quand un même geste touche
    // l'événement, son auteur et le canal global.
    $trous  = implode(',', array_fill(0, count($canaux), '(?, 1)'));
    $sql    = "INSERT INTO flux_revisions (canal, revision) VALUES $trous
               ON DUPLICATE KEY UPDATE revision = revision + 1";

    try {
        $pdo->prepare($sql)->execute($canaux);
    } catch (PDOException $e) {
        logErreur('Flux temps réel : incrément impossible', $e, ['canaux' => implode(',', $canaux)]);
    }
}

/**
 * Lit la révision courante de chaque canal demandé.
 *
 * Un canal jamais touché vaut 0 : le client reçoit une réponse cohérente
 * plutôt qu'un trou dans le tableau.
 *
 * @param  list<string> $canaux
 * @return array<string,int>
 */
function fluxRevisions(PDO $pdo, array $canaux): array
{
    $canaux = array_values(array_unique(array_filter($canaux)));
    if (!$canaux) {
        return [];
    }

    $revisions = array_fill_keys($canaux, 0);

    try {
        $trous = implode(',', array_fill(0, count($canaux), '?'));
        $stmt  = $pdo->prepare("SELECT canal, revision FROM flux_revisions WHERE canal IN ($trous)");
        $stmt->execute($canaux);
        foreach ($stmt->fetchAll() as $ligne) {
            $revisions[$ligne['canal']] = (int) $ligne['revision'];
        }
    } catch (PDOException $e) {
        logErreur('Flux temps réel : lecture impossible', $e);
    }

    return $revisions;
}

/**
 * Empreinte courte et stable d'un jeu de révisions, pour servir d'ETag.
 *
 * Le tri par clé est indispensable : `?ev=2,1` et `?ev=1,2` décrivent le
 * même périmètre et doivent produire la même empreinte, sinon le client
 * retéléchargerait tout dès qu'il change l'ordre de ses paramètres.
 *
 * @param array<string,int> $revisions
 */
function fluxSignature(array $revisions): string
{
    ksort($revisions);

    return substr(hash('xxh128', json_encode($revisions) ?: ''), 0, 16);
}

/**
 * Répond « 304 Not Modified » si le client a déjà la bonne version.
 *
 * Le cœur du dispositif : quand cette fonction s'arrête sur un 304, la
 * requête n'aura coûté qu'un démarrage de PHP et une lecture sur clé
 * primaire. C'est ce qui rend tenable l'interrogation de mille onglets.
 *
 * Cache-Control: private — la réponse dépend de la session, aucun cache
 * partagé (proxy, CDN) ne doit la conserver et la resservir à quelqu'un
 * d'autre. no-cache autorise la mise en mémoire par le navigateur, mais
 * l'oblige à revalider : c'est exactement ce qu'on veut d'un ETag.
 *
 * @return bool vrai si la réponse a été envoyée et que l'appelant doit sortir
 */
function fluxRepondreSiInchange(string $signature): bool
{
    $etag = '"' . $signature . '"';
    header('ETag: ' . $etag);
    header('Cache-Control: private, no-cache, max-age=0, must-revalidate');

    $recu = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    // Un proxy peut préfixer l'ETag par W/ (validation faible). Le comparer
    // tel quel ferait échouer la revalidation derrière certains hébergeurs,
    // et le client retéléchargerait le corps à chaque fois pour rien.
    $recu = preg_replace('/^W\//', '', $recu) ?? $recu;

    if ($recu !== '' && $recu === $etag) {
        http_response_code(304);

        return true;
    }

    return false;
}

/**
 * Supprime les canaux dont plus personne ne se soucie.
 *
 * Un canal par événement et par utilisateur, cela finit par faire du monde.
 * Les canaux d'événements passés ne bougeront plus jamais : les garder ne
 * sert qu'à alourdir l'index. Appelée par le cron, jamais par une page.
 */
function fluxPurger(PDO $pdo, int $joursInactifs = 30): int
{
    try {
        $stmt = $pdo->prepare(
            "DELETE FROM flux_revisions
             WHERE canal <> 'global' AND maj_le < NOW() - INTERVAL ? DAY"
        );
        $stmt->execute([$joursInactifs]);

        return $stmt->rowCount();
    } catch (PDOException $e) {
        logErreur('Flux temps réel : purge impossible', $e);

        return 0;
    }
}
