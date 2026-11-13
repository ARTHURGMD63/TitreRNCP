<?php
/**
 * Le sport : catalogue des disciplines, formats d'événement de club, niveaux,
 * et la règle qui dit qui voit l'événement d'un étudiant.
 *
 * Une liste PHP plutôt qu'un ENUM en base, comme les styles de musique : les
 * colonnes `squads.type`, `evenements.sport` et `terrains.sport` sont des
 * VARCHAR (migration v28), et c'est cette liste qui fait foi. Ajouter le pickleball ne
 * doit pas demander une migration.
 *
 * L'application garde une copie de ces libellés (mobile/src/catalogue.ts),
 * pour afficher une carte avant d'avoir reçu la réponse : si l'une change,
 * l'autre aussi.
 */

/**
 * Les sports proposés, dans leur ordre d'affichage.
 *
 * Les sports de terrain d'abord : ce sont eux qui ont des créneaux.
 *
 * @return array<string, string> code => libellé
 */
function catalogueSports(): array
{
    return [
        'padel'     => 'Padel',
        'tennis'    => 'Tennis',
        'golf'      => 'Golf',
        'squash'    => 'Squash',
        'badminton' => 'Badminton',
        'foot5'     => 'Foot à 5',
        'basket'    => 'Basket',
        'escalade'  => 'Escalade',
        'running'   => 'Running',
        'velo'      => 'Vélo',
        'muscu'     => 'Muscu',
        'yoga'      => 'Yoga',
        'autre'     => 'Autre',
    ];
}

/** Le code existe-t-il au catalogue ? */
function sportValide(?string $code): bool
{
    return $code !== null && array_key_exists($code, catalogueSports());
}

/**
 * Le libellé d'un sport. « culture » n'est plus proposé mais des squads
 * créés avant v28 le portent encore : ils gardent un libellé lisible.
 */
function libelleSport(string $code): string
{
    return catalogueSports()[$code] ?? ($code === 'culture' ? 'Culture' : ucfirst($code));
}

/**
 * Les formats d'un événement de club.
 *
 * @return array<string, string>
 */
function formatsEvenementSport(): array
{
    return [
        'tournoi'    => 'Tournoi',
        'stage'      => 'Stage',
        'initiation' => 'Initiation',
        'cours'      => 'Cours collectif',
        'match'      => 'Match',
        'soiree'     => 'Soirée sportive',
        'autre'      => 'Autre',
    ];
}

function formatSportValide(?string $code): bool
{
    return $code !== null && array_key_exists($code, formatsEvenementSport());
}

/** @return list<string> mêmes niveaux que squads.niveau et creneaux.niveau */
function niveauxSport(): array
{
    return ['tous', 'debutant', 'inter', 'avance'];
}

/**
 * Le catalogue tel que l'application le reçoit.
 *
 * @param array<string, string> $liste code => libellé
 * @return list<array{code:string,libelle:string}>
 */
function apiCatalogue(array $liste): array
{
    return array_map(
        static fn (string $code, string $libelle): array => ['code' => $code, 'libelle' => $libelle],
        array_keys($liste),
        array_values($liste)
    );
}

/**
 * Condition SQL : le squad `$alias` est-il visible de l'étudiant `?` ?
 *
 * Un événement d'étudiant n'est visible que de ses abonnés (v28). Concrètement,
 * il l'est pour :
 *   - son créateur ;
 *   - quiconque en est déjà membre — un invité qui a accepté, ou un membre
 *     d'avant la bascule — sans quoi il perdrait de vue un squad qu'il a
 *     rejoint ;
 *   - un abonné ACCEPTÉ du créateur : une demande en attente ne vaut pas
 *     accord, c'est exactement ce qui rend un compte privé privé ;
 *   - tout le monde si le squad est public (ceux d'une association).
 * Et jamais pour quelqu'un qui a bloqué le créateur ou été bloqué par lui.
 *
 * @return array{0:string,1:list<int>} la condition et ses paramètres
 */
function sqlSquadVisible(string $alias, int $uid): array
{
    $sql = "(
        NOT EXISTS (SELECT 1 FROM user_blocks bv
                     WHERE (bv.blocker_id = ? AND bv.blocked_id = $alias.createur_id)
                        OR (bv.blocker_id = $alias.createur_id AND bv.blocked_id = ?))
        AND (
            $alias.createur_id = ?
            OR $alias.visibilite = 'public'
            OR EXISTS (SELECT 1 FROM squad_membres smv
                        WHERE smv.squad_id = $alias.id AND smv.user_id = ?)
            OR EXISTS (SELECT 1 FROM follows_users fv
                        WHERE fv.follower_id = ? AND fv.followed_id = $alias.createur_id
                          AND fv.statut = 'accepted')
        )
    )";

    return [$sql, [$uid, $uid, $uid, $uid, $uid]];
}

/** Le squad `$squadId` est-il visible de `$uid` ? Même règle, pour une ligne. */
function squadVisiblePour(PDO $pdo, int $squadId, int $uid): bool
{
    [$condition, $params] = sqlSquadVisible('s', $uid);
    $stmt = $pdo->prepare("SELECT 1 FROM squads s WHERE s.id = ? AND $condition");
    $stmt->execute(array_merge([$squadId], $params));

    return (bool) $stmt->fetchColumn();
}

/**
 * Condition SQL : la partie du créneau `$alias` est-elle visible de `?` ?
 *
 * Une partie réservée appartient à ses joueurs. Elle n'apparaît aux autres
 * que si l'organisateur l'a ouverte aux inconnus — et jamais à quelqu'un qui
 * a bloqué l'organisateur, ou que l'organisateur a bloqué.
 *
 * @return array{0:string,1:list<int>}
 */
function sqlPartieVisible(string $alias, int $uid): array
{
    $sql = "(
        EXISTS (SELECT 1 FROM creneau_joueurs cjv WHERE cjv.creneau_id = $alias.id AND cjv.user_id = ?)
        OR ($alias.ouvert = 1
            AND NOT EXISTS (SELECT 1 FROM user_blocks bp
                             WHERE (bp.blocker_id = ? AND bp.blocked_id = $alias.organisateur_id)
                                OR (bp.blocker_id = $alias.organisateur_id AND bp.blocked_id = ?)))
    )";

    return [$sql, [$uid, $uid, $uid]];
}

/**
 * L'établissement du compte, en exigeant un club abonné.
 *
 * Les écrans Terrains et Créneaux n'ont de sens que pour un club, et la
 * publication est payante : même garde `abonnement_requis` que les soirées.
 *
 * @return array<string, mixed>
 */
function apiClubDe(PDO $pdo, int $uid): array
{
    require_once __DIR__ . '/crm.php';

    $etab = apiEtablissementDe($pdo, $uid);
    if (!$etab) {
        apiErreur('Aucun établissement associé à ce compte.', 404, 'etablissement_absent');
    }
    if (($etab['type'] ?? '') !== 'club') {
        apiErreur('Réservé aux clubs et établissements sportifs.', 403, 'pas_un_club');
    }
    if (!abonnementChoisi(abonnementEtablissement($pdo, (int) $etab['id']))) {
        apiErreur('Choisis une formule d’abonnement pour continuer.', 403, 'abonnement_requis');
    }

    return $etab;
}
