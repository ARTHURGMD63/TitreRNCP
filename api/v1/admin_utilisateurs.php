<?php
/**
 * GET /api/v1/admin_utilisateurs.php[?q=&ecole=&etat=actifs|dormants&p=1]
 * — la base étudiants, lecture seule, paginée.
 *
 * Reprend admin/utilisateurs.php : même filtre « actif » (au moins une
 * inscription dans les 30 derniers jours), mêmes agrégats par ligne.
 */

require_once __DIR__ . '/_socle.php';

apiExigerMethode('GET');

$moi = apiAdmin($pdo);

$q       = trim((string) ($_GET['q'] ?? ''));
$fEcole  = trim((string) ($_GET['ecole'] ?? ''));
$fEtat   = (string) ($_GET['etat'] ?? '');
$page    = max(1, (int) ($_GET['p'] ?? 1));
$parPage = 40;

$where  = "u.type = 'etudiant'";
$params = [];

if ($q !== '') {
    $where .= ' AND (u.prenom LIKE ? OR u.nom LIKE ? OR u.email LIKE ? OR u.interests LIKE ?)';
    $terme  = '%' . addcslashes($q, '%_') . '%';
    array_push($params, $terme, $terme, $terme, $terme);
}
if ($fEcole !== '') {
    $where   .= ' AND u.ecole = ?';
    $params[] = $fEcole;
}

$recent = "EXISTS (SELECT 1 FROM inscriptions i WHERE i.user_id = u.id AND i.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))";
if ($fEtat === 'actifs') {
    $where .= " AND $recent";
} elseif ($fEtat === 'dormants') {
    $where .= " AND NOT $recent";
}

$stmtN = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $where");
$stmtN->execute($params);
$total = (int) $stmtN->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT u.id, u.prenom, u.nom, u.email, u.ecole, u.promo, u.photo, u.interests, u.created_at,
            (SELECT COUNT(*) FROM inscriptions i WHERE i.user_id = u.id AND i.statut <> 'annule') AS sorties,
            (SELECT COUNT(*) FROM inscriptions i WHERE i.user_id = u.id AND i.statut = 'checkin') AS presences,
            (SELECT COUNT(*) FROM squad_membres sm WHERE sm.user_id = u.id) AS squads,
            (SELECT COALESCE(SUM(e.montant), 0) FROM economies e WHERE e.user_id = u.id) AS economies,
            (SELECT MAX(i.created_at) FROM inscriptions i WHERE i.user_id = u.id) AS derniere_activite
       FROM users u
      WHERE $where
      ORDER BY u.created_at DESC
      LIMIT " . (int) $parPage . ' OFFSET ' . (int) (($page - 1) * $parPage)
);
$stmt->execute($params);
$etudiants = $stmt->fetchAll();

$ecoles = $pdo->query(
    "SELECT DISTINCT ecole FROM users WHERE type='etudiant' AND ecole IS NOT NULL AND ecole <> '' ORDER BY ecole"
)->fetchAll(PDO::FETCH_COLUMN);

apiReponse([
    'success' => true,
    'repere' => [
        'total'     => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE type='etudiant'")->fetchColumn(),
        'nouveaux_7j' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE type='etudiant' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
        'actifs'    => (int) $pdo->query("SELECT COUNT(DISTINCT user_id) FROM inscriptions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
    ],
    'filtres' => ['q' => $q, 'ecole' => $fEcole, 'etat' => $fEtat, 'ecoles_disponibles' => $ecoles],
    'pagination' => ['page' => $page, 'par_page' => $parPage, 'total' => $total, 'pages' => max(1, (int) ceil($total / $parPage))],
    'etudiants' => array_map(
        static function (array $u): array {
            return [
                'id'       => (int) $u['id'],
                'prenom'   => (string) $u['prenom'],
                'nom'      => (string) $u['nom'],
                'email'    => (string) $u['email'],
                'ecole'    => $u['ecole'] !== null ? (string) $u['ecole'] : null,
                'promo'    => $u['promo'] !== null ? (string) $u['promo'] : null,
                'photo_url' => apiPhotoUrl($u['photo'] ?? null),
                'interets' => $u['interests'] ? array_slice(array_map('trim', explode(',', (string) $u['interests'])), 0, 3) : [],
                'sorties'   => (int) $u['sorties'],
                'presences' => (int) $u['presences'],
                'squads'    => (int) $u['squads'],
                'economies' => (float) $u['economies'],
                'derniere_activite' => $u['derniere_activite'] !== null ? (string) $u['derniere_activite'] : null,
                'inscrit_le' => (string) $u['created_at'],
            ];
        },
        $etudiants
    ),
]);
