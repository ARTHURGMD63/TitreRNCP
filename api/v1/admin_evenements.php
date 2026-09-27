<?php
/**
 * GET /api/v1/admin_evenements.php[?etablissement=&periode=avenir|passes|tous&type=]
 * — vue globale des soirées, tous établissements confondus, lecture seule.
 *
 * Reprend admin/evenements.php : même borne à 200 lignes, mêmes repères
 * globaux indépendants des filtres.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/crm.php';
require_once __DIR__ . '/../../includes/sponsoring.php';

apiExigerMethode('GET');

$moi = apiAdmin($pdo);

$fEtab    = (int) ($_GET['etablissement'] ?? 0);
$fPeriode = in_array($_GET['periode'] ?? '', ['avenir', 'passes', 'tous'], true) ? $_GET['periode'] : 'tous';
$fType    = in_array($_GET['type'] ?? '', ['bar', 'boite', 'resto', 'afterwork'], true) ? $_GET['type'] : '';

$sql = "SELECT e.*, et.nom AS etab_nom, et.ville
          FROM evenements e
          JOIN etablissements et ON et.id = e.etablissement_id
         WHERE 1 = 1";
$params = [];

if ($fEtab) { $sql .= ' AND et.id = ?'; $params[] = $fEtab; }
if ($fType) { $sql .= ' AND e.type = ?'; $params[] = $fType; }
if ($fPeriode === 'avenir') $sql .= ' AND e.date_heure >= NOW()';
if ($fPeriode === 'passes') $sql .= ' AND e.date_heure <  NOW()';

$sql .= ' ORDER BY e.date_heure DESC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$evenements = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id AND i.statut <> 'annule') AS inscrits,
            (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id AND i.statut = 'checkin') AS presents
       FROM evenements e WHERE e.id = ?"
);

$etabs = $pdo->query('SELECT id, nom FROM etablissements ORDER BY nom')->fetchAll();

apiReponse([
    'success' => true,
    'repere' => [
        'total'        => (int) $pdo->query('SELECT COUNT(*) FROM evenements')->fetchColumn(),
        'a_venir'      => (int) $pdo->query('SELECT COUNT(*) FROM evenements WHERE date_heure >= NOW()')->fetchColumn(),
        'nouveaux_7j'  => (int) $pdo->query("SELECT COUNT(*) FROM evenements WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
        'taux_presence_global' => tauxPresenceGlobal($pdo),
    ],
    'filtres' => ['etablissement' => $fEtab ?: null, 'periode' => $fPeriode, 'type' => $fType, 'etablissements' => $etabs],
    'evenements' => array_map(
        static function (array $e) use ($stmt): array {
            $stmt->execute([(int) $e['id']]);
            $agg      = $stmt->fetch();
            $inscrits = (int) $agg['inscrits'];
            $presents = (int) $agg['presents'];
            $passe    = strtotime((string) $e['date_heure']) < time();
            $taux     = $passe && $inscrits > 0 ? round($presents / $inscrits * 100) : null;

            return [
                'id'            => (int) $e['id'],
                'titre'         => (string) $e['titre'],
                'type'          => (string) $e['type'],
                'date_heure'    => (string) $e['date_heure'],
                'passe'         => $passe,
                'etablissement' => ['nom' => (string) $e['etab_nom'], 'ville' => (string) $e['ville']],
                'quota'         => (int) $e['quota'],
                'inscrits'      => $inscrits,
                'presents'      => $passe ? $presents : null,
                'taux_presence' => $taux,
                'reduction'     => $e['reduction'] !== null ? (int) $e['reduction'] : null,
                'is_gratuit'    => (bool) $e['is_gratuit'],
                'is_flash'      => (bool) $e['is_flash'],
                'sponsorise_actif' => sponsoringActif($e),
            ];
        },
        $evenements
    ),
]);
