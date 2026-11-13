<?php
/**
 * Ce que partagent les points d'API du sport : la lecture d'un créneau de
 * terrain et sa mise en forme pour l'application.
 *
 * Inclus par api/v1/sport.php, sport_creneau.php et club_creneaux.php, après
 * leur socle (apiPhotoUrl() en vient).
 */

require_once __DIR__ . '/sports.php';

/**
 * Le début de requête commun : un créneau, son terrain, son club, son
 * organisateur et le nombre de joueurs déjà inscrits.
 */
function sqlCreneauBase(): string
{
    return "SELECT c.*, t.nom AS terrain_nom, t.sport,
                   et.id AS etab_id, et.nom AS etab_nom, et.ville AS etab_ville, et.adresse AS etab_adresse,
                   u.prenom AS orga_prenom, u.nom AS orga_nom, u.photo AS orga_photo,
                   (SELECT COUNT(*) FROM creneau_joueurs cj WHERE cj.creneau_id = c.id) AS nb_joueurs
              FROM creneaux c
              JOIN terrains t        ON t.id = c.terrain_id
              JOIN etablissements et ON et.id = t.etablissement_id
              LEFT JOIN users u      ON u.id = c.organisateur_id";
}

/**
 * Condition SQL : le club du créneau a une formule en cours.
 *
 * La publication est payante : un club qui n'a plus de formule garde ses
 * terrains, mais ses créneaux libres cessent d'apparaître aux étudiants.
 */
function sqlClubAbonne(string $aliasEtab = 'et'): string
{
    return "EXISTS (SELECT 1 FROM crm_clients cc
                     WHERE cc.etablissement_id = $aliasEtab.id
                       AND cc.offre <> 'aucune' AND cc.statut <> 'perdu')";
}

/**
 * Les joueurs de plusieurs créneaux, en une requête.
 *
 * @param list<int> $ids
 * @return array<int, list<array<string, mixed>>> creneau_id => joueurs
 */
function sportJoueurs(PDO $pdo, array $ids): array
{
    if (!$ids) {
        return [];
    }
    $trous = implode(',', array_fill(0, count($ids), '?'));
    $stmt  = $pdo->prepare(
        "SELECT cj.creneau_id, cj.invite_par, u.id, u.prenom, u.nom, u.photo
           FROM creneau_joueurs cj
           JOIN users u ON u.id = cj.user_id
          WHERE cj.creneau_id IN ($trous)
          ORDER BY cj.rejoint_le ASC, u.id ASC"
    );
    $stmt->execute($ids);

    $parCreneau = [];
    foreach ($stmt->fetchAll() as $j) {
        $parCreneau[(int) $j['creneau_id']][] = [
            'id'         => (int) $j['id'],
            'prenom'     => (string) $j['prenom'],
            'nom'        => (string) ($j['nom'] ?? ''),
            'photo_url'  => apiPhotoUrl($j['photo'] !== null ? (string) $j['photo'] : null),
            'invite_par' => $j['invite_par'] !== null ? (int) $j['invite_par'] : null,
        ];
    }

    return $parCreneau;
}

/**
 * Un créneau tel que l'application l'affiche.
 *
 * @param array<string, mixed> $c ligne de sqlCreneauBase()
 * @param list<array<string, mixed>> $joueurs
 * @return array<string, mixed>
 */
function apiCreneau(array $c, array $joueurs, int $uid): array
{
    $places  = (int) $c['joueurs'];
    $inscrits = (int) $c['nb_joueurs'];
    $prix    = (float) $c['prix'];
    $jeJoue  = false;
    foreach ($joueurs as $j) {
        if ($j['id'] === $uid) {
            $jeJoue = true;
            break;
        }
    }

    return [
        'id'       => (int) $c['id'],
        'debut'    => (string) $c['debut'],
        'fin'      => (string) $c['fin'],
        'sport'    => (string) $c['sport'],
        'terrain'  => ['id' => (int) $c['terrain_id'], 'nom' => (string) $c['terrain_nom']],
        'club'     => [
            'id'      => (int) $c['etab_id'],
            'nom'     => (string) $c['etab_nom'],
            'ville'   => (string) ($c['etab_ville'] ?? ''),
            'adresse' => (string) ($c['etab_adresse'] ?? ''),
        ],
        'prix'     => $prix,
        'statut'   => (string) $c['statut'],
        'ouvert'   => (bool) $c['ouvert'],
        'niveau'   => (string) $c['niveau'],
        'places'   => ['total' => $places, 'prises' => $inscrits, 'restantes' => max(0, $places - $inscrits)],
        'organisateur' => $c['organisateur_id'] !== null ? [
            'id'        => (int) $c['organisateur_id'],
            'prenom'    => (string) ($c['orga_prenom'] ?? ''),
            'nom'       => (string) ($c['orga_nom'] ?? ''),
            'photo_url' => apiPhotoUrl($c['orga_photo'] !== null ? (string) $c['orga_photo'] : null),
        ] : null,
        'joueurs'  => $joueurs,
        'je_joue'  => $jeJoue,
        'je_organise' => $c['organisateur_id'] !== null && (int) $c['organisateur_id'] === $uid,
    ];
}

/**
 * Met en forme une liste de lignes de créneaux, joueurs compris.
 *
 * @param list<array<string, mixed>> $lignes
 * @return list<array<string, mixed>>
 */
function apiCreneaux(PDO $pdo, array $lignes, int $uid): array
{
    $joueurs = sportJoueurs($pdo, array_map(static fn (array $c): int => (int) $c['id'], $lignes));

    return array_map(
        static fn (array $c): array => apiCreneau($c, $joueurs[(int) $c['id']] ?? [], $uid),
        $lignes
    );
}
