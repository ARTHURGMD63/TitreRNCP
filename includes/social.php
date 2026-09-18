<?php
/**
 * Graphe social — abonnements sur demande et modération.
 *
 * Règle unique du modèle : voir l'activité de quelqu'un (ses prochaines
 * sorties, ses squads) suppose un abonnement ACCEPTÉ par cette personne.
 * Une demande en attente ne donne aucun accès.
 *
 * Toute page qui affiche l'activité d'un autre étudiant doit passer par
 * canSeeActivity() — ne pas refaire la requête à la main.
 */

/** Aucun lien. */
const FOLLOW_NONE = 'none';
/** Demande envoyée, pas encore tranchée. */
const FOLLOW_PENDING = 'pending';
/** Demande acceptée : l'accès est ouvert. */
const FOLLOW_ACCEPTED = 'accepted';

/**
 * État de la demande de $me vers $other, du point de vue de $me.
 */
function followState(PDO $pdo, int $me, int $other): string
{
    if ($me === $other) {
        return FOLLOW_ACCEPTED;
    }
    $stmt = $pdo->prepare(
        "SELECT statut FROM follows_users WHERE follower_id = ? AND followed_id = ?"
    );
    $stmt->execute([$me, $other]);
    $statut = $stmt->fetchColumn();

    return $statut === false ? FOLLOW_NONE : (string) $statut;
}

/**
 * $me a-t-il le droit de voir l'activité de $other ?
 * Son propre profil est toujours visible ; sinon il faut un abonnement accepté.
 */
function canSeeActivity(PDO $pdo, int $me, int $other): bool
{
    return followState($pdo, $me, $other) === FOLLOW_ACCEPTED;
}

/**
 * Un blocage existe-t-il dans un sens ou dans l'autre ?
 * Le blocage est symétrique dans ses effets : ni l'un ni l'autre ne se voit.
 */
function isBlockedBetween(PDO $pdo, int $a, int $b): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1 FROM user_blocks
          WHERE (blocker_id = ? AND blocked_id = ?)
             OR (blocker_id = ? AND blocked_id = ?)
          LIMIT 1"
    );
    $stmt->execute([$a, $b, $b, $a]);

    return (bool) $stmt->fetchColumn();
}

/**
 * Identifiants à masquer à $me : ceux qu'il a bloqués et ceux qui l'ont bloqué.
 * À injecter dans les listes de personnes et dans le fil d'actualité.
 *
 * @return int[]
 */
function blockedIds(PDO $pdo, int $me): array
{
    $stmt = $pdo->prepare(
        "SELECT blocked_id AS id FROM user_blocks WHERE blocker_id = ?
         UNION
         SELECT blocker_id AS id FROM user_blocks WHERE blocked_id = ?"
    );
    $stmt->execute([$me, $me]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Fragment « AND id NOT IN (...) » prêt à concaténer, avec ses paramètres.
 * Renvoie une chaîne vide si rien n'est bloqué, pour ne pas alourdir la requête.
 *
 * @return array{0:string,1:int[]}
 */
function blockedFilterSql(PDO $pdo, int $me, string $column = 'id'): array
{
    $ids = blockedIds($pdo, $me);
    if (!$ids) {
        return ['', []];
    }
    $holders = implode(',', array_fill(0, count($ids), '?'));

    return [" AND {$column} NOT IN ({$holders})", $ids];
}

/**
 * Nombre de demandes d'abonnement en attente adressées à $me.
 * Sert la pastille de notification du profil.
 */
function pendingFollowCount(PDO $pdo, int $me): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM follows_users WHERE followed_id = ? AND statut = 'pending'"
    );
    $stmt->execute([$me]);

    return (int) $stmt->fetchColumn();
}
