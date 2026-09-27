<?php
/**
 * GET /api/v1/partenaire_dashboard.php — le tableau de bord partenaire.
 *
 * Reprend partenaire/dashboard.php ligne pour ligne : prochain événement,
 * inscrits/check-in, âge moyen (approximé par promo, comme sur le site),
 * répartition par école, inscriptions des 6 dernières heures, dernières
 * inscriptions. Les valeurs « tendance » et « conseil » restent en partie
 * mockées côté serveur, exactement comme sur le site — ce n'est pas au
 * portage mobile d'inventer une vraie logique statistique qui n'existe pas
 * encore ailleurs.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/crm.php';

apiExigerMethode('GET');

$u   = apiPartenaire($pdo);
$uid = (int) $u['id'];

$etab = apiEtablissementDe($pdo, $uid);
if (!$etab) {
    apiErreur('Aucun établissement associé à ce compte.', 404, 'etablissement_absent');
}
if (!abonnementChoisi(abonnementEtablissement($pdo, (int) $etab['id']))) {
    apiErreur('Choisis une formule d’abonnement pour continuer.', 403, 'abonnement_requis');
}

$stmt = $pdo->prepare('SELECT * FROM evenements WHERE etablissement_id=? AND date_heure >= NOW() ORDER BY date_heure ASC LIMIT 1');
$stmt->execute([(int) $etab['id']]);
$event = $stmt->fetch();

$nbInscrits  = 0;
$nbCheckin   = 0;
$ageMoy      = 0.0;
$chartLabels = [];
$chartValues = [];
$schoolStats = [];
$recents     = [];
$nbTotal     = 0;
$tendance    = '+0%';
$conseil     = '—';

if ($event) {
    $eid = (int) $event['id'];

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE evenement_id=? AND statut='inscrit'");
    $stmt->execute([$eid]);
    $nbInscrits = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE evenement_id=? AND statut='checkin'");
    $stmt->execute([$eid]);
    $nbCheckin = (int) $stmt->fetchColumn();

    $nbTotal = $nbInscrits + $nbCheckin;

    // Âge moyen approximé par promo, comme sur le site : aucune colonne
    // d'âge réelle n'existe sur les inscriptions.
    $promoAges = ['L1' => 19, 'L2' => 20, 'L3' => 21, 'M1' => 22, 'M2' => 23, 'BUT1' => 19, 'BUT2' => 20, 'BUT3' => 21];
    $stmt = $pdo->prepare('SELECT u.promo FROM inscriptions i JOIN users u ON u.id=i.user_id WHERE i.evenement_id=? AND u.promo IS NOT NULL');
    $stmt->execute([$eid]);
    $promos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($promos) {
        $ages   = array_filter(array_map(static fn ($p) => $promoAges[$p] ?? null, $promos));
        $ageMoy = $ages ? round(array_sum($ages) / count($ages), 1) : 21.4;
    } else {
        $ageMoy = 21.4;
    }

    $stmt = $pdo->prepare('SELECT u.ecole, COUNT(*) as cnt FROM inscriptions i JOIN users u ON u.id=i.user_id WHERE i.evenement_id=? AND u.ecole IS NOT NULL GROUP BY u.ecole ORDER BY cnt DESC');
    $stmt->execute([$eid]);
    $schools     = $stmt->fetchAll();
    $totalSchool = array_sum(array_column($schools, 'cnt')) ?: 1;
    foreach ($schools as $s) {
        $schoolStats[] = ['nom' => (string) $s['ecole'], 'pourcentage' => (int) round(((int) $s['cnt']) / $totalSchool * 100)];
    }
    if (!$schoolStats) {
        $schoolStats = [
            ['nom' => 'UCA — Droit & Éco', 'pourcentage' => 42],
            ['nom' => 'SIGMA Clermont', 'pourcentage' => 28],
            ['nom' => 'INP Ingénieurs', 'pourcentage' => 18],
            ['nom' => 'Autres', 'pourcentage' => 12],
        ];
    }

    for ($h = 5; $h >= 0; $h--) {
        $chartLabels[] = date('H\hi', strtotime("-{$h} hour"));
        $from = date('Y-m-d H:i:s', strtotime('-' . ($h + 1) . ' hour'));
        $to   = date('Y-m-d H:i:s', strtotime("-{$h} hour"));
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM inscriptions WHERE evenement_id=? AND created_at BETWEEN ? AND ?');
        $stmt->execute([$eid, $from, $to]);
        $chartValues[] = (int) $stmt->fetchColumn();
    }

    $tendance = $nbTotal > 20 ? '+340% vs jeudi moy.' : '+' . random_int(50, 200) . '% vs jeudi moy.';
    $conseil  = '68% des inscrits ont déjà utilisé un deal similaire. Prépare 2 bartenders en plus entre 21h et 22h30.';

    $stmt = $pdo->prepare(
        'SELECT u.prenom, u.nom, u.ecole, u.promo, u.photo, i.created_at
           FROM inscriptions i JOIN users u ON u.id=i.user_id
          WHERE i.evenement_id=?
          ORDER BY i.created_at DESC LIMIT 5'
    );
    $stmt->execute([$eid]);
    $recents = array_map(
        static fn (array $r): array => [
            'prenom'     => (string) $r['prenom'],
            'nom'        => (string) $r['nom'],
            'ecole'      => $r['ecole'] !== null ? (string) $r['ecole'] : null,
            'promo'      => $r['promo'] !== null ? (string) $r['promo'] : null,
            'photo_url'  => apiPhotoUrl($r['photo'] ?? null),
            'heure'      => date('H\hi', strtotime((string) $r['created_at'])),
        ],
        $stmt->fetchAll()
    );
}

apiReponse([
    'success'       => true,
    'etablissement' => [
        'id'    => (int) $etab['id'],
        'nom'   => (string) $etab['nom'],
        'ville' => (string) ($etab['ville'] ?? ''),
    ],
    'evenement' => $event ? [
        'id'         => (int) $event['id'],
        'titre'      => (string) $event['titre'],
        'date_heure' => (string) $event['date_heure'],
    ] : null,
    'stats' => [
        'inscrits'      => $nbInscrits,
        'checkin'       => $nbCheckin,
        'total'         => $nbTotal,
        'age_moyen'     => $ageMoy,
        'tendance'      => $tendance,
        'conseil'       => $conseil,
        'ecoles'        => $schoolStats,
        'graphe'        => ['labels' => $chartLabels, 'valeurs' => $chartValues],
        'recentes'      => $recents,
    ],
]);
