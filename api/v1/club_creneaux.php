<?php
/**
 * GET  /api/v1/club_creneaux.php[?du=2026-11-15&jours=7] — le planning des
 *      terrains du club : chaque créneau, son statut, ses joueurs.
 * POST /api/v1/club_creneaux.php
 *      {action:'publier', terrain_id, debuts:['2026-11-15 10:00', …]}
 *          met en ligne des créneaux libres. L'application calcule la liste
 *          (une journée, une semaine récurrente…) ; le serveur fixe la fin,
 *          le prix et le nombre de joueurs d'après le terrain, et ignore ce
 *          qui existe déjà au même horaire.
 *      {action:'supprimer', id}
 *          un créneau libre disparaît ; un créneau réservé passe « annulé »,
 *          pour que ses joueurs le voient au lieu de le perdre de vue.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/creneaux.php';

/** Au-delà, une publication est une erreur de saisie plus qu'un planning. */
const CRENEAUX_PAR_PUBLICATION = 200;

$u    = apiPartenaire($pdo);
$uid  = (int) $u['id'];
$etab = apiClubDe($pdo, $uid);
$eid  = (int) $etab['id'];

$methode = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($methode === 'GET') {
    $du    = (string) ($_GET['du'] ?? date('Y-m-d'));
    $du    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $du) ? $du : date('Y-m-d');
    $jours = max(1, min(31, (int) ($_GET['jours'] ?? 7)));
    $au    = date('Y-m-d', (int) strtotime("$du +$jours day"));

    $stmt = $pdo->prepare(sqlCreneauBase() . "
         WHERE et.id = ? AND c.debut >= ? AND c.debut < ?
         ORDER BY c.debut ASC, t.nom ASC");
    $stmt->execute([$eid, $du . ' 00:00:00', $au . ' 00:00:00']);

    apiReponse([
        'success'  => true,
        'du'       => $du,
        'jours'    => $jours,
        'creneaux' => apiCreneaux($pdo, $stmt->fetchAll(), $uid),
    ]);
}

apiExigerMethode('POST');
$corps  = apiCorps();
$action = (string) ($corps['action'] ?? '');

if ($action === 'supprimer') {
    $cid  = (int) ($corps['id'] ?? 0);
    $stmt = $pdo->prepare(
        'SELECT c.statut FROM creneaux c JOIN terrains t ON t.id = c.terrain_id
          WHERE c.id = ? AND t.etablissement_id = ?'
    );
    $stmt->execute([$cid, $eid]);
    $statut = $stmt->fetchColumn();
    if ($statut === false) {
        apiErreur('Ce créneau n’existe plus.', 404, 'introuvable');
    }
    if ($statut === 'reserve') {
        $pdo->prepare("UPDATE creneaux SET statut = 'annule', ouvert = 0 WHERE id = ?")->execute([$cid]);
        apiReponse(['success' => true, 'message' => 'Partie annulée. Les joueurs le verront dans l’app.']);
    }
    $pdo->prepare('DELETE FROM creneaux WHERE id = ?')->execute([$cid]);
    apiReponse(['success' => true, 'message' => 'Créneau retiré.']);
}

if ($action !== 'publier') {
    apiErreur('Action inconnue', 422, 'action_inconnue');
}

$tid  = (int) ($corps['terrain_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM terrains WHERE id = ? AND etablissement_id = ? AND actif = 1');
$stmt->execute([$tid, $eid]);
$terrain = $stmt->fetch();
if (!$terrain) {
    apiErreur('Choisis un terrain actif.', 422, 'terrain_invalide');
}

$debuts = is_array($corps['debuts'] ?? null) ? $corps['debuts'] : [];
if (!$debuts) {
    apiErreur('Aucun horaire à publier.', 422, 'aucun_horaire');
}
if (count($debuts) > CRENEAUX_PAR_PUBLICATION) {
    apiErreur('Au plus ' . CRENEAUX_PAR_PUBLICATION . ' créneaux par publication.', 422, 'trop_de_creneaux');
}

$duree  = (int) $terrain['duree_min'];
$ajout  = $pdo->prepare(
    "INSERT IGNORE INTO creneaux (terrain_id, debut, fin, prix, joueurs, statut) VALUES (?, ?, ?, ?, ?, 'libre')"
);
// Deux créneaux qui se chevauchent sur un même terrain seraient deux parties
// sur le même court : le second est écarté.
$chevauche = $pdo->prepare(
    "SELECT 1 FROM creneaux WHERE terrain_id = ? AND statut <> 'annule' AND debut < ? AND fin > ? LIMIT 1"
);
$crees  = 0;
$ignores = 0;
foreach ($debuts as $brut) {
    $ts = is_string($brut) && preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $brut) ? strtotime($brut) : false;
    // Un horaire passé ou illisible est écarté sans faire échouer les autres.
    if ($ts === false || $ts <= time()) {
        $ignores++;
        continue;
    }
    $debut = date('Y-m-d H:i:s', $ts);
    $fin   = date('Y-m-d H:i:s', $ts + $duree * 60);
    $chevauche->execute([$tid, $fin, $debut]);
    if ($chevauche->fetchColumn()) {
        $ignores++;
        continue;
    }
    $ajout->execute([$tid, $debut, $fin, $terrain['prix'], $terrain['joueurs']]);
    $ajout->rowCount() > 0 ? $crees++ : $ignores++;
}

apiReponse([
    'success' => true,
    'crees'   => $crees,
    'ignores' => $ignores,
    'message' => $crees > 0
        ? "$crees créneau" . ($crees > 1 ? 'x' : '') . ' en ligne.' . ($ignores ? " $ignores ignoré" . ($ignores > 1 ? 's' : '') . ' (passé ou déjà occupé).' : '')
        : 'Rien de nouveau : ces horaires sont passés ou déjà occupés.',
]);
