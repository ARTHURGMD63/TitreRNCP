<?php
/**
 * GET /api/v1/sport.php[?sport=padel] — le fil de l'onglet Sport.
 *
 * Cinq listes en un appel, parce que l'écran les montre ensemble et que cinq
 * allers-retours sur une connexion mobile se voient :
 *
 *   - parties    : les parties réservées dont l'organisateur a ouvert les
 *                  places restantes aux inconnus, pas encore complètes ;
 *   - libres     : les créneaux de terrain encore libres ;
 *   - evenements : les événements des clubs (tournois, stages…) ;
 *   - squads     : les événements d'étudiants que je peux voir — les miens,
 *                  ceux des personnes qui ont accepté que je les suive, et
 *                  ceux des associations (voir sqlSquadVisible()) ;
 *   - mes_parties: les créneaux où je joue, y compris ceux que le club a
 *                  annulés, pour que l'annulation se voie.
 *
 * Le filtre par sport s'applique à tout. Le filtre par niveau, lui, reste à
 * l'application : il ne retire que quelques cartes d'une liste déjà courte.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/creneaux.php';
require_once __DIR__ . '/../../includes/places.php';
require_once __DIR__ . '/../../includes/evenements_temps.php';

apiExigerMethode('GET');

$u   = apiEtudiant($pdo);
$uid = (int) $u['id'];

$sport = (string) ($_GET['sport'] ?? '');
if ($sport !== '' && !sportValide($sport)) {
    $sport = '';
}

$abonne = sqlClubAbonne();

// ─── Parties à compléter ────────────────────────────────────────────────────

[$visible, $paramsVisible] = sqlPartieVisible('c', $uid);
$sql = sqlCreneauBase() . "
     WHERE c.statut = 'reserve' AND c.ouvert = 1 AND c.debut > NOW()
       AND $abonne
       AND (SELECT COUNT(*) FROM creneau_joueurs cj2 WHERE cj2.creneau_id = c.id) < c.joueurs
       AND NOT EXISTS (SELECT 1 FROM creneau_joueurs moi WHERE moi.creneau_id = c.id AND moi.user_id = ?)
       AND $visible";
$params = array_merge([$uid], $paramsVisible);
if ($sport !== '') {
    $sql .= ' AND t.sport = ?';
    $params[] = $sport;
}
$stmt = $pdo->prepare($sql . ' ORDER BY c.debut ASC LIMIT 30');
$stmt->execute($params);
$parties = apiCreneaux($pdo, $stmt->fetchAll(), $uid);

// ─── Terrains libres ────────────────────────────────────────────────────────

$sql    = sqlCreneauBase() . " WHERE c.statut = 'libre' AND c.debut > NOW() AND t.actif = 1 AND $abonne";
$params = [];
if ($sport !== '') {
    $sql .= ' AND t.sport = ?';
    $params[] = $sport;
}
$stmt = $pdo->prepare($sql . ' ORDER BY c.debut ASC LIMIT 40');
$stmt->execute($params);
$libres = apiCreneaux($pdo, $stmt->fetchAll(), $uid);

// ─── Mes parties ────────────────────────────────────────────────────────────

$stmt = $pdo->prepare(sqlCreneauBase() . "
     JOIN creneau_joueurs moi ON moi.creneau_id = c.id AND moi.user_id = ?
     WHERE c.statut IN ('reserve', 'annule') AND c.fin > NOW()
     ORDER BY c.debut ASC LIMIT 20");
$stmt->execute([$uid]);
$mesParties = apiCreneaux($pdo, $stmt->fetchAll(), $uid);

// ─── Événements des clubs ───────────────────────────────────────────────────

$finEff = sqlFinEffectiveEvenement();
$sql = "SELECT e.*, et.nom AS etab_nom, et.ville AS etab_ville,
               (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id AND i.statut <> 'annule') AS nb_inscrits,
               EXISTS (SELECT 1 FROM inscriptions i WHERE i.evenement_id = e.id AND i.user_id = ? AND i.statut <> 'annule') AS deja_inscrit
          FROM evenements e
          JOIN etablissements et ON et.id = e.etablissement_id
         WHERE e.type = 'sport' AND $finEff >= NOW()";
$params = [$uid];
if ($sport !== '') {
    $sql .= ' AND e.sport = ?';
    $params[] = $sport;
}
$stmt = $pdo->prepare($sql . ' ORDER BY e.date_heure ASC LIMIT 30');
$stmt->execute($params);
$evenements = array_map(
    static function (array $e): array {
        $quota    = (int) ($e['quota'] ?? 0);
        $inscrits = (int) $e['nb_inscrits'];
        $r        = remplissage($quota, $inscrits);

        return [
            'id'          => (int) $e['id'],
            'titre'       => (string) $e['titre'],
            'sport'       => (string) ($e['sport'] ?? ''),
            'format'      => (string) ($e['format'] ?? ''),
            'date_heure'  => (string) $e['date_heure'],
            'date_fin'    => $e['date_fin'] !== null ? (string) $e['date_fin'] : null,
            'lieu'        => (string) ($e['lieu'] ?? ''),
            'club'        => ['id' => (int) $e['etablissement_id'], 'nom' => (string) $e['etab_nom'], 'ville' => (string) ($e['etab_ville'] ?? '')],
            'places'      => ['quota' => $quota, 'inscrits' => $inscrits, 'restantes' => $r['restantes'], 'complet' => $r['complet']],
            'prix_normal' => (float) ($e['prix_normal'] ?? 0),
            'reduction'   => (int) ($e['reduction'] ?? 0),
            'is_gratuit'  => (bool) $e['is_gratuit'],
            'deja_inscrit' => (bool) $e['deja_inscrit'],
        ];
    },
    $stmt->fetchAll()
);

// ─── Événements d'étudiants ─────────────────────────────────────────────────

[$squadVisible, $paramsSquad] = sqlSquadVisible('s', $uid);
$sql = "SELECT s.*, u.prenom AS createur_prenom, u.nom AS createur_nom, u.photo AS createur_photo,
               (SELECT COUNT(*) FROM squad_membres sm WHERE sm.squad_id = s.id) AS nb_membres,
               EXISTS (SELECT 1 FROM squad_membres sm WHERE sm.squad_id = s.id AND sm.user_id = ?) AS deja_membre,
               EXISTS (SELECT 1 FROM follows_users f
                        WHERE f.follower_id = ? AND f.followed_id = s.createur_id AND f.statut = 'accepted') AS je_suis_abonne
          FROM squads s
          JOIN users u ON u.id = s.createur_id
         WHERE s.date_heure >= NOW() AND $squadVisible";
$params = array_merge([$uid, $uid], $paramsSquad);
if ($sport !== '') {
    $sql .= ' AND s.type = ?';
    $params[] = $sport;
}
$stmt = $pdo->prepare($sql . ' ORDER BY s.date_heure ASC LIMIT 50');
$stmt->execute($params);
$lignesSquads = $stmt->fetchAll();

$membres = [];
if ($lignesSquads) {
    $ids   = array_map(static fn (array $s): int => (int) $s['id'], $lignesSquads);
    $trous = implode(',', array_fill(0, count($ids), '?'));
    $stmt  = $pdo->prepare(
        "SELECT sm.squad_id, u.id, u.prenom, u.photo
           FROM squad_membres sm JOIN users u ON u.id = sm.user_id
          WHERE sm.squad_id IN ($trous) ORDER BY sm.joined_at ASC"
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $m) {
        $membres[(int) $m['squad_id']][] = [
            'id'        => (int) $m['id'],
            'prenom'    => (string) $m['prenom'],
            'photo_url' => apiPhotoUrl($m['photo'] !== null ? (string) $m['photo'] : null),
        ];
    }
}

$squads = array_map(
    static function (array $s) use ($membres, $uid): array {
        $id    = (int) $s['id'];
        $quota = (int) ($s['quota'] ?? 0);
        $nb    = (int) $s['nb_membres'];
        $r     = remplissage($quota, $nb);

        return [
            'id'          => $id,
            'titre'       => (string) $s['titre'],
            'description' => (string) ($s['description'] ?? ''),
            'type'        => (string) $s['type'],
            'niveau'      => (string) ($s['niveau'] ?? 'tous'),
            'date_heure'  => (string) $s['date_heure'],
            'lieu'        => (string) ($s['lieu'] ?? ''),
            'visibilite'  => (string) $s['visibilite'],
            'createur'    => [
                'id'        => (int) $s['createur_id'],
                'prenom'    => (string) $s['createur_prenom'],
                'nom'       => (string) $s['createur_nom'],
                'photo_url' => apiPhotoUrl($s['createur_photo'] !== null ? (string) $s['createur_photo'] : null),
            ],
            'places'      => ['quota' => $quota, 'membres' => $nb, 'restantes' => $r['restantes'], 'complet' => $r['complet']],
            'deja_membre' => (bool) $s['deja_membre'],
            'est_createur' => (int) $s['createur_id'] === $uid,
            'je_suis_abonne_au_createur' => (bool) $s['je_suis_abonne'],
            'cree_par_association' => (bool) $s['cree_par_association'],
            'membres'     => $membres[$id] ?? [],
        ];
    },
    $lignesSquads
);

apiReponse([
    'success'     => true,
    'sports'      => apiCatalogue(catalogueSports()),
    'parties'     => $parties,
    'libres'      => $libres,
    'mes_parties' => $mesParties,
    'evenements'  => $evenements,
    'squads'      => $squads,
]);
