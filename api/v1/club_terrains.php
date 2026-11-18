<?php
/**
 * GET  /api/v1/club_terrains.php — les terrains du club, ses sports et le
 *      catalogue.
 * POST /api/v1/club_terrains.php
 *      {action:'enregistrer', id?, nom, sport, joueurs, duree_min, prix, actif}
 *      {action:'supprimer', id}
 *      {action:'sports', sports:[codes]}  ce que le club propose
 *
 * Réservé aux clubs abonnés (apiClubDe). Tout est scopé à l'établissement du
 * compte : un identifiant de terrain deviné ne touche jamais celui d'un
 * autre club.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/sports.php';

$u    = apiPartenaire($pdo);
$etab = apiClubDe($pdo, (int) $u['id']);
$eid  = (int) $etab['id'];

/** @return array<string, mixed> */
function reponseTerrains(PDO $pdo, int $eid): array
{
    $stmt = $pdo->prepare(
        "SELECT t.*,
                (SELECT COUNT(*) FROM creneaux c WHERE c.terrain_id = t.id AND c.debut > NOW() AND c.statut = 'libre') AS a_venir_libres,
                (SELECT COUNT(*) FROM creneaux c WHERE c.terrain_id = t.id AND c.debut > NOW() AND c.statut = 'reserve') AS a_venir_reserves
           FROM terrains t WHERE t.etablissement_id = ? ORDER BY t.actif DESC, t.nom ASC"
    );
    $stmt->execute([$eid]);
    $terrains = array_map(static fn (array $t): array => [
        'id'        => (int) $t['id'],
        'nom'       => (string) $t['nom'],
        'sport'     => (string) $t['sport'],
        'joueurs'   => (int) $t['joueurs'],
        'duree_min' => (int) $t['duree_min'],
        'prix'      => (float) $t['prix'],
        'actif'     => (bool) $t['actif'],
        'creneaux_libres'   => (int) $t['a_venir_libres'],
        'creneaux_reserves' => (int) $t['a_venir_reserves'],
    ], $stmt->fetchAll());

    $stmt = $pdo->prepare('SELECT sport FROM etablissement_sports WHERE etablissement_id = ?');
    $stmt->execute([$eid]);

    return [
        'success'  => true,
        'terrains' => $terrains,
        'sports_club' => array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)),
        'sports'   => apiCatalogue(catalogueSports()),
    ];
}

$methode = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($methode === 'GET') {
    apiReponse(reponseTerrains($pdo, $eid));
}

apiExigerMethode('POST');
$corps  = apiCorps();
$action = (string) ($corps['action'] ?? '');

if ($action === 'sports') {
    $sports = array_values(array_unique(array_filter(
        array_map('strval', is_array($corps['sports'] ?? null) ? $corps['sports'] : []),
        'sportValide'
    )));
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM etablissement_sports WHERE etablissement_id = ?')->execute([$eid]);
    $ajout = $pdo->prepare('INSERT INTO etablissement_sports (etablissement_id, sport) VALUES (?, ?)');
    foreach ($sports as $s) {
        $ajout->execute([$eid, $s]);
    }
    $pdo->commit();
    apiReponse(['message' => 'Sports enregistrés.'] + reponseTerrains($pdo, $eid));
}

if ($action === 'supprimer') {
    $tid = (int) ($corps['id'] ?? 0);
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM creneaux c JOIN terrains t ON t.id = c.terrain_id
          WHERE t.id = ? AND t.etablissement_id = ? AND c.statut = 'reserve' AND c.fin > NOW()"
    );
    $stmt->execute([$tid, $eid]);
    // Supprimer le terrain emporterait les parties réservées dessus : des
    // joueurs arriveraient devant un terrain qui n'existe plus dans l'app.
    if ((int) $stmt->fetchColumn() > 0) {
        apiErreur('Des parties sont réservées sur ce terrain. Annule-les ou désactive-le.', 409, 'parties_reservees');
    }
    $pdo->prepare('DELETE FROM terrains WHERE id = ? AND etablissement_id = ?')->execute([$tid, $eid]);
    apiReponse(['message' => 'Terrain supprimé.'] + reponseTerrains($pdo, $eid));
}

if ($action !== 'enregistrer') {
    apiErreur('Action inconnue', 422, 'action_inconnue');
}

$tid     = (int) ($corps['id'] ?? 0);
$nom     = mb_substr(trim((string) ($corps['nom'] ?? '')), 0, 100);
$sport   = (string) ($corps['sport'] ?? '');
$joueurs = (int) ($corps['joueurs'] ?? 4);
$duree   = (int) ($corps['duree_min'] ?? 90);
$prix    = round((float) ($corps['prix'] ?? 0), 2);
$actif   = array_key_exists('actif', $corps) ? (!empty($corps['actif']) ? 1 : 0) : 1;

$erreurs = [];
if ($nom === '') $erreurs[] = 'Donne un nom au terrain.';
if (!sportValide($sport)) $erreurs[] = 'Choisis un sport.';
if ($joueurs < 1 || $joueurs > 30) $erreurs[] = 'Entre 1 et 30 joueurs.';
if ($duree < 15 || $duree > 600) $erreurs[] = 'Une durée entre 15 minutes et 10 heures.';
if ($prix < 0 || $prix > 10000) $erreurs[] = 'Prix invalide.';
if ($erreurs) {
    apiReponse(['success' => false, 'code' => 'invalide', 'message' => implode(' ', $erreurs), 'erreurs' => $erreurs], 422);
}

if ($tid) {
    $stmt = $pdo->prepare('UPDATE terrains SET nom=?, sport=?, joueurs=?, duree_min=?, prix=?, actif=? WHERE id=? AND etablissement_id=?');
    $stmt->execute([$nom, $sport, $joueurs, $duree, $prix, $actif, $tid, $eid]);
    // Le prix et le nombre de joueurs déjà publiés restent figés dans chaque
    // créneau : la modification vaut pour les prochaines publications.
    $message = 'Terrain mis à jour.';
} else {
    $pdo->prepare('INSERT INTO terrains (etablissement_id, nom, sport, joueurs, duree_min, prix, actif) VALUES (?,?,?,?,?,?,?)')
        ->execute([$eid, $nom, $sport, $joueurs, $duree, $prix, $actif]);
    // Le sport du terrain fait partie de ce que propose le club.
    $pdo->prepare('INSERT IGNORE INTO etablissement_sports (etablissement_id, sport) VALUES (?, ?)')->execute([$eid, $sport]);
    $message = 'Terrain ajouté.';
}

apiReponse(['message' => $message] + reponseTerrains($pdo, $eid));
