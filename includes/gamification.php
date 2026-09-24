<?php
/**
 * Helpers gamification : calcul XP, niveau, et attribution automatique des badges.
 * Appeler checkBadges($pdo, $uid) après chaque action majeure (inscription, squad, follow, avis).
 */

function getUserStats(PDO $pdo, int $uid): array {
    $stats = [];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE user_id=? AND statut != 'annule'");
    $stmt->execute([$uid]);
    $stats['events'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM squad_membres WHERE user_id=?");
    $stmt->execute([$uid]);
    $stats['squads'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows_users WHERE follower_id=? AND statut='accepted'");
    $stmt->execute([$uid]);
    $stats['follows'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM avis WHERE user_id=?");
    $stmt->execute([$uid]);
    $stats['avis'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM economies WHERE user_id=?");
    $stmt->execute([$uid]);
    $stats['economies'] = (float)$stmt->fetchColumn();

    return $stats;
}

/** XP = 15 × events + 10 × squads + 5 × follows + 8 × avis */
function getXp(array $stats): int {
    return $stats['events']*15 + $stats['squads']*10 + $stats['follows']*5 + $stats['avis']*8;
}

/** Niveau basé sur sqrt(XP/50) */
function getLevel(int $xp): int {
    return max(1, (int)floor(sqrt($xp / 50)) + 1);
}

function xpForLevel(int $level): int {
    return ($level - 1) ** 2 * 50;
}

function checkBadges(PDO $pdo, int $uid): array {
    $stats = getUserStats($pdo, $uid);
    $unlocked = [];
    $map = [
        'first_event'   => $stats['events']    >= 1,
        'five_events'   => $stats['events']    >= 5,
        'ten_events'    => $stats['events']    >= 10,
        'first_squad'   => $stats['squads']    >= 1,
        'five_squads'   => $stats['squads']    >= 5,
        'first_follow'  => $stats['follows']   >= 1,
        'reviewer'      => $stats['avis']      >= 1,
        'saver_50'      => $stats['economies'] >= 50,
    ];
    foreach ($map as $code => $cond) {
        if ($cond) {
            // SELECT + INSERT to stay compatible with MySQL and SQLite (tests)
            $check = $pdo->prepare("SELECT 1 FROM user_badges WHERE user_id=? AND badge_code=?");
            $check->execute([$uid, $code]);
            if (!$check->fetch()) {
                $ins = $pdo->prepare("INSERT INTO user_badges (user_id, badge_code) VALUES (?,?)");
                $ins->execute([$uid, $code]);
                $unlocked[] = $code;
            }
        }
    }
    return $unlocked;
}

function getUserBadges(PDO $pdo, int $uid): array {
    $stmt = $pdo->prepare("
        SELECT b.*, ub.unlocked_at
        FROM user_badges ub
        JOIN badges b ON b.code = ub.badge_code
        WHERE ub.user_id = ?
        ORDER BY ub.unlocked_at DESC
    ");
    $stmt->execute([$uid]);
    return $stmt->fetchAll();
}

function getAllBadges(PDO $pdo): array {
    return $pdo->query("SELECT * FROM badges")->fetchAll();
}

// ─── Classement ─────────────────────────────────────────────────────────────

/**
 * Requête de base du classement : chaque étudiant et son XP, calculé en SQL.
 *
 * La formule est exactement celle de getXp() — 15 par sortie non annulée,
 * 10 par squad, 5 par abonnement accepté, 8 par avis. Elle est recalculée ici
 * plutôt qu'appelée étudiant par étudiant : getUserStats() coûte cinq requêtes,
 * soit cinq cents pour un classement de cent personnes. Les agrégats sont
 * faits une fois par table, puis joints. Un test vérifie que les deux calculs
 * donnent le même nombre.
 *
 * @param array{ids?: ?list<int>, ecole?: ?string, exclus?: list<int>} $criteres
 * @return array{0: string, 1: list<int|string>}
 */
function classementSqlBase(array $criteres): array
{
    $sql = "
        SELECT u.id, u.prenom, u.nom, u.photo, u.ecole, u.promo,
               (COALESCE(i.n, 0) * 15 + COALESCE(s.n, 0) * 10
                + COALESCE(f.n, 0) * 5 + COALESCE(a.n, 0) * 8) AS xp
        FROM users u
        LEFT JOIN (SELECT user_id, COUNT(*) AS n FROM inscriptions WHERE statut <> 'annule' GROUP BY user_id) i ON i.user_id = u.id
        LEFT JOIN (SELECT user_id, COUNT(*) AS n FROM squad_membres GROUP BY user_id) s ON s.user_id = u.id
        LEFT JOIN (SELECT follower_id AS user_id, COUNT(*) AS n FROM follows_users WHERE statut = 'accepted' GROUP BY follower_id) f ON f.user_id = u.id
        LEFT JOIN (SELECT user_id, COUNT(*) AS n FROM avis GROUP BY user_id) a ON a.user_id = u.id
        WHERE u.type = 'etudiant'";
    $params = [];

    if (isset($criteres['ids'])) {
        $ids = array_values(array_map('intval', $criteres['ids']));
        if (!$ids) {
            $ids = [0]; // aucun identifiant : le classement est vide, pas illimité
        }
        $sql .= ' AND u.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        array_push($params, ...$ids);
    }
    if (isset($criteres['ecole'])) {
        $sql .= ' AND u.ecole = ?';
        $params[] = $criteres['ecole'];
    }
    $exclus = array_values(array_map('intval', $criteres['exclus'] ?? []));
    if ($exclus) {
        $sql .= ' AND u.id NOT IN (' . implode(',', array_fill(0, count($exclus), '?')) . ')';
        array_push($params, ...$exclus);
    }

    return [$sql, $params];
}

/**
 * Les premiers du classement, avec leur rang.
 *
 * Rang « de compétition » : deux ex æquo partagent le rang, le suivant saute
 * d'autant (1, 2, 2, 4). À XP égal, l'ordre d'affichage est alphabétique.
 *
 * @param array{ids?: ?list<int>, ecole?: ?string, exclus?: list<int>} $criteres
 * @return list<array{id:int, prenom:string, nom:string, photo:?string, ecole:?string, promo:?string, xp:int, rang:int}>
 */
function classementXp(PDO $pdo, array $criteres, int $limite = 50): array
{
    [$base, $params] = classementSqlBase($criteres);
    $stmt = $pdo->prepare($base . ' ORDER BY xp DESC, u.prenom ASC, u.id ASC LIMIT ' . max(1, $limite));
    $stmt->execute($params);

    $lignes = [];
    $rang = 0;
    $precedent = null;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $i => $l) {
        $xp = (int) $l['xp'];
        if ($xp !== $precedent) {
            $rang = $i + 1;
            $precedent = $xp;
        }
        $l['id'] = (int) $l['id'];
        $l['xp'] = $xp;
        $l['rang'] = $rang;
        $lignes[] = $l;
    }
    return $lignes;
}

// ─── Classement servi depuis le cache ───────────────────────────────────────

/**
 * Tous les étudiants et leur XP, du premier au dernier — calculé une fois
 * par minute pour toute l'application.
 *
 * Mesuré sur un an d'exploitation simulé (5 000 étudiants, 40 000
 * inscriptions) : une agrégation coûte 60 à 130 ms, et la page du classement
 * en lançait deux ou trois par affichage. Recalculer la même liste pour
 * chaque visiteur, chaque seconde, ne tient pas à plusieurs centaines
 * d'utilisateurs simultanés. Une minute de retard sur l'XP des autres est
 * invisible ; celui de l'étudiant qui regarde est, lui, toujours recalculé
 * en direct (classementPourEtudiant()).
 *
 * Le cache est partagé entre tous les visiteurs : n'y figurent que prénom,
 * nom, photo, école et XP, déjà visibles de tout étudiant connecté.
 *
 * @return list<array{id:int, prenom:string, nom:string, photo:?string, ecole:?string, promo:?string, xp:int}>
 */
function classementGlobal(PDO $pdo, int $ttl = 60): array
{
    require_once __DIR__ . '/cache.php';

    return cacheRemember('classement_xp_global', $ttl, static function () use ($pdo): array {
        [$sql, $params] = classementSqlBase([]);
        $stmt = $pdo->prepare($sql . ' ORDER BY xp DESC, u.prenom ASC, u.id ASC');
        $stmt->execute($params);
        $lignes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $l['id'] = (int) $l['id'];
            $l['xp'] = (int) $l['xp'];
            $lignes[] = $l;
        }
        return $lignes;
    });
}

/**
 * Le classement d'un étudiant, dérivé de la liste globale sans nouvelle
 * requête : périmètre, rangs de compétition (1, 2, 2, 4), sa propre ligne et
 * la personne juste devant lui. Même ordre que classementXp().
 *
 * @param list<array<string,mixed>> $global      classementGlobal()
 * @param array{ids?: ?list<int>, ecole?: ?string, exclus?: list<int>} $criteres
 * @param array<string,mixed>       $moi         au moins id, prenom, nom
 * @return array{lignes: list<array<string,mixed>>, moi: ?array<string,mixed>, devant: ?array{id:int, prenom:string, xp:int}}
 */
function classementPourEtudiant(array $global, array $criteres, array $moi, int $monXp, int $limite = 50): array
{
    $ids    = isset($criteres['ids']) ? array_flip(array_map('intval', $criteres['ids'])) : null;
    $ecole  = $criteres['ecole'] ?? null;
    $exclus = array_flip(array_map('intval', $criteres['exclus'] ?? []));
    $uid    = (int) $moi['id'];

    // La liste globale est déjà triée : on filtre en gardant l'ordre, on met
    // ma ligne de côté, puis on la réinsère à sa place avec mon XP du moment.
    // Pas de tri complet : sur 5 000 étudiants, c'était l'essentiel du temps.
    $liste = [];
    $maLigneBrute = null;
    foreach ($global as $l) {
        if ($ids !== null && !isset($ids[$l['id']])) continue;
        if ($ecole !== null && (string) $l['ecole'] !== $ecole) continue;
        if ($l['id'] === $uid) {
            $maLigneBrute = $l;
            continue;
        }
        if (isset($exclus[$l['id']])) continue;
        $liste[] = $l;
    }
    $dansPerimetre = ($ids === null || isset($ids[$uid])) && ($ecole === null || (string) ($moi['ecole'] ?? '') === $ecole);
    if ($maLigneBrute === null && $dansPerimetre) {
        // Compte créé depuis le dernier calcul : on l'ajoute avec son XP réel.
        $maLigneBrute = ['id' => $uid, 'prenom' => (string) $moi['prenom'], 'nom' => (string) $moi['nom'],
                         'photo' => $moi['photo'] ?? null, 'ecole' => $moi['ecole'] ?? null, 'promo' => null];
    }
    if ($maLigneBrute !== null) {
        $maLigneBrute['xp'] = $monXp;
        $cle = [-$monXp, (string) $maLigneBrute['prenom'], $uid];
        $position = count($liste);
        foreach ($liste as $i => $l) {
            if (($cle <=> [-$l['xp'], (string) $l['prenom'], $l['id']]) < 0) {
                $position = $i;
                break;
            }
        }
        array_splice($liste, $position, 0, [$maLigneBrute]);
    }

    $rang = 0;
    $precedent = null;
    $maLigne = null;
    $devant = null;
    foreach ($liste as $i => &$l) {
        if ($l['xp'] !== $precedent) {
            $rang = $i + 1;
            $precedent = $l['xp'];
        }
        $l['rang'] = $rang;
        if ($l['id'] === $uid) {
            $maLigne = $l;
        }
    }
    unset($l);

    // Celui qu'il reste à dépasser : le plus faible XP strictement supérieur.
    if ($maLigne !== null) {
        foreach ($liste as $l) {
            if ($l['xp'] > $monXp) {
                if ($devant === null || $l['xp'] < $devant['xp']
                    || ($l['xp'] === $devant['xp'] && strcmp((string) $l['prenom'], $devant['prenom']) < 0)) {
                    $devant = ['id' => $l['id'], 'prenom' => (string) $l['prenom'], 'xp' => $l['xp']];
                }
            }
        }
    }

    return ['lignes' => array_slice($liste, 0, max(1, $limite)), 'moi' => $maLigne, 'devant' => $devant];
}
