<?php
/**
 * GET  /api/v1/sport_creneau.php?id=12 — un créneau de terrain, ses joueurs
 *      et ce que je peux y faire.
 * POST /api/v1/sport_creneau.php
 *      {action:'reserver', id, amis:[ids], ouvert, niveau}
 *          réserver un créneau libre, seul ou avec des amis, en ouvrant ou
 *          non les places restantes aux inconnus ;
 *      {action:'rejoindre', id}   prendre une place dans une partie ouverte ;
 *      {action:'quitter', id}     quitter une partie — l'organisateur qui
 *                                 quitte libère le créneau pour tout le monde ;
 *      {action:'modifier', id, ouvert, niveau}  l'organisateur ouvre ou
 *                                 referme sa partie.
 *
 * Le prix est affiché et réglé au club : rien n'est encaissé ici.
 *
 * Toutes les écritures verrouillent la ligne du créneau (FOR UPDATE), comme
 * l'inscription à une soirée : deux étudiants qui prennent la dernière place
 * au même instant ne doivent pas passer tous les deux.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/creneaux.php';

$u   = apiEtudiant($pdo);
$uid = (int) $u['id'];

/**
 * Le créneau, s'il est visible de l'étudiant : libre (tout le monde peut le
 * réserver, si le club est abonné), ou partie dont il est joueur, ou partie
 * ouverte aux inconnus.
 *
 * @return array<string, mixed>|null
 */
function creneauVisible(PDO $pdo, int $id, int $uid): ?array
{
    [$visible, $params] = sqlPartieVisible('c', $uid);
    $stmt = $pdo->prepare(sqlCreneauBase() . "
         WHERE c.id = ?
           AND ((c.statut = 'libre' AND t.actif = 1 AND " . sqlClubAbonne() . ")
                OR (c.statut IN ('reserve', 'annule') AND $visible))");
    $stmt->execute(array_merge([$id], $params));

    return $stmt->fetch() ?: null;
}

/** @return array<string, mixed> le créneau mis en forme, avec les droits */
function reponseCreneau(PDO $pdo, int $id, int $uid): array
{
    $c = creneauVisible($pdo, $id, $uid);
    if (!$c) {
        apiErreur('Ce créneau n’existe plus.', 404, 'introuvable');
    }
    $forme   = apiCreneaux($pdo, [$c], $uid)[0];
    $avenir  = strtotime((string) $c['debut']) > time();

    $forme['peut_reserver']  = $avenir && $c['statut'] === 'libre';
    $forme['peut_rejoindre'] = $avenir && $c['statut'] === 'reserve' && $forme['ouvert']
        && !$forme['je_joue'] && $forme['places']['restantes'] > 0;
    $forme['peut_quitter']   = $avenir && $c['statut'] === 'reserve' && $forme['je_joue'];

    return $forme;
}

$methode = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($methode === 'GET') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        apiErreur('Identifiant manquant', 422, 'id_manquant');
    }
    apiReponse(['success' => true, 'creneau' => reponseCreneau($pdo, $id, $uid)]);
}

apiExigerMethode('POST');

$corps  = apiCorps();
$action = (string) ($corps['action'] ?? '');
$id     = (int) ($corps['id'] ?? 0);
if ($id <= 0) {
    apiErreur('Identifiant manquant', 422, 'id_manquant');
}
if (!in_array($action, ['reserver', 'rejoindre', 'quitter', 'modifier'], true)) {
    apiErreur('Action inconnue', 422, 'action_inconnue');
}

$niveau = (string) ($corps['niveau'] ?? 'tous');
if (!in_array($niveau, niveauxSport(), true)) {
    $niveau = 'tous';
}
$ouvert = !empty($corps['ouvert']) ? 1 : 0;

/**
 * Termine sur un refus en annulant la transaction en cours.
 */
function refuser(PDO $pdo, string $message, int $statut, string $code): never
{
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    apiErreur($message, $statut, $code);
}

try {
    $pdo->beginTransaction();

    // Le verrou porte sur la ligne du créneau seule : la visibilité se
    // revérifie juste après, sur la ligne verrouillée.
    $stmt = $pdo->prepare('SELECT * FROM creneaux WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $verrou = $stmt->fetch();
    $c      = $verrou ? creneauVisible($pdo, $id, $uid) : null;
    if (!$c) {
        refuser($pdo, 'Ce créneau n’existe plus.', 404, 'introuvable');
    }
    if (strtotime((string) $c['debut']) <= time()) {
        refuser($pdo, 'Ce créneau a déjà commencé.', 409, 'commence');
    }

    $stmt = $pdo->prepare('SELECT user_id FROM creneau_joueurs WHERE creneau_id = ?');
    $stmt->execute([$id]);
    $joueurs = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $places  = (int) $c['joueurs'];
    $jeJoue  = in_array($uid, $joueurs, true);
    $jOrganise = $c['organisateur_id'] !== null && (int) $c['organisateur_id'] === $uid;

    if ($action === 'reserver') {
        if ($c['statut'] !== 'libre') {
            refuser($pdo, 'Ce créneau vient d’être réservé.', 409, 'deja_reserve');
        }

        // Les amis : des personnes que je suis, avec une demande acceptée.
        // Un identifiant quelconque glissé dans la liste ne doit pas inscrire
        // un inconnu à une partie à son insu.
        $amis = array_values(array_unique(array_filter(
            array_map('intval', is_array($corps['amis'] ?? null) ? $corps['amis'] : []),
            static fn (int $a): bool => $a > 0 && $a !== $uid
        )));
        if (1 + count($amis) > $places) {
            refuser($pdo, "Ce terrain se joue à $places : retire des amis.", 422, 'trop_de_joueurs');
        }
        if ($amis) {
            $trous = implode(',', array_fill(0, count($amis), '?'));
            $stmt  = $pdo->prepare(
                "SELECT f.followed_id FROM follows_users f
                   JOIN users ua ON ua.id = f.followed_id AND ua.type = 'etudiant'
                  WHERE f.follower_id = ? AND f.statut = 'accepted' AND f.followed_id IN ($trous)
                    AND NOT EXISTS (SELECT 1 FROM user_blocks b
                                     WHERE (b.blocker_id = ? AND b.blocked_id = f.followed_id)
                                        OR (b.blocker_id = f.followed_id AND b.blocked_id = ?))"
            );
            $stmt->execute(array_merge([$uid], $amis, [$uid, $uid]));
            $valides = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            if (count($valides) !== count($amis)) {
                refuser($pdo, 'Tu ne peux ajouter que des personnes que tu suis.', 422, 'ami_invalide');
            }
        }

        // « Ouverte » est l'intention de l'organisateur, pas un état de
        // remplissage : une partie complète reste ouverte, elle sort seulement
        // du fil tant qu'aucune place ne se libère (voir sport.php).
        $pdo->prepare(
            "UPDATE creneaux SET statut = 'reserve', organisateur_id = ?, ouvert = ?, niveau = ?, reserve_le = NOW()
              WHERE id = ?"
        )->execute([$uid, $ouvert, $niveau, $id]);

        $ajout = $pdo->prepare('INSERT INTO creneau_joueurs (creneau_id, user_id, invite_par) VALUES (?, ?, ?)');
        $ajout->execute([$id, $uid, null]);
        foreach ($amis as $ami) {
            $ajout->execute([$id, $ami, $uid]);
        }
        $message = 'Terrain réservé. Le prix se règle au club.';
    } elseif ($action === 'rejoindre') {
        if ($c['statut'] !== 'reserve' || !(int) $c['ouvert']) {
            refuser($pdo, 'Cette partie n’est pas ouverte.', 409, 'fermee');
        }
        if ($jeJoue) {
            refuser($pdo, 'Tu es déjà dans cette partie.', 409, 'deja_joueur');
        }
        if (count($joueurs) >= $places) {
            refuser($pdo, 'La partie est complète.', 409, 'complet');
        }
        $pdo->prepare('INSERT INTO creneau_joueurs (creneau_id, user_id, invite_par) VALUES (?, ?, NULL)')
            ->execute([$id, $uid]);
        $message = 'Tu as ta place. Le prix se règle au club.';
    } elseif ($action === 'quitter') {
        if ($c['statut'] !== 'reserve' || !$jeJoue) {
            refuser($pdo, 'Tu ne joues pas dans cette partie.', 409, 'pas_joueur');
        }
        if ($jOrganise) {
            // L'organisateur a réservé le terrain : s'il part, la réservation
            // part avec lui, et le créneau redevient libre pour d'autres.
            $pdo->prepare('DELETE FROM creneau_joueurs WHERE creneau_id = ?')->execute([$id]);
            $pdo->prepare(
                "UPDATE creneaux SET statut = 'libre', organisateur_id = NULL, ouvert = 0, niveau = 'tous', reserve_le = NULL
                  WHERE id = ?"
            )->execute([$id]);
            $message = 'Réservation annulée, le terrain est de nouveau libre.';
        } else {
            $pdo->prepare('DELETE FROM creneau_joueurs WHERE creneau_id = ? AND user_id = ?')->execute([$id, $uid]);
            $message = 'Tu as quitté la partie.';
        }
    } else {
        if ($c['statut'] !== 'reserve' || !$jOrganise) {
            refuser($pdo, 'Seul l’organisateur peut modifier la partie.', 403, 'pas_organisateur');
        }
        $pdo->prepare('UPDATE creneaux SET ouvert = ?, niveau = ? WHERE id = ?')
            ->execute([$ouvert, $niveau, $id]);
        $message = $ouvert ? 'Partie ouverte aux autres joueurs.' : 'Partie réservée à tes invités.';
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logErreur('Créneau : écriture impossible', $e, ['creneau' => $id, 'action' => $action]);
    apiErreur('Enregistrement impossible. Réessaie dans un instant.', 500, 'erreur_serveur');
}

// Après une sortie de l'organisateur, le créneau libre reste visible : on le
// renvoie tel qu'il est désormais.
apiReponse(['success' => true, 'message' => $message, 'creneau' => reponseCreneau($pdo, $id, $uid)]);
