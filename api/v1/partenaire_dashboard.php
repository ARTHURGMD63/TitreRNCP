<?php
/**
 * GET /api/v1/partenaire_dashboard.php — le tableau de bord partenaire.
 *
 * Reprend partenaire/dashboard.php ligne pour ligne : prochain événement,
 * inscrits/check-in, âge moyen (approximé par promo, comme sur le site),
 * répartition par école, inscriptions des 6 dernières heures, dernières
 * inscriptions. Plus aucune valeur inventée : l'âge moyen (estimé par promo),
 * la « tendance » aléatoire, le conseil écrit en dur et la répartition par
 * école de secours ont été retirés. Le mois de l'établissement (quota
 * d'événements, flash, achats) est joint sous `usage`.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/crm.php';
require_once __DIR__ . '/../../includes/comptes.php';
require_once __DIR__ . '/../../includes/evenements_temps.php';
require_once __DIR__ . '/../../includes/capacites.php';

apiExigerMethode('GET');

$u   = apiPartenaire($pdo);
$uid = (int) $u['id'];

$etab = apiEtablissementDe($pdo, $uid);
if (!$etab) {
    apiErreur('Aucun établissement associé à ce compte.', 404, 'etablissement_absent');
}
$clientEtab = abonnementEtablissement($pdo, (int) $etab['id']);
exigerAbonnementValide($pdo, $etab);

$capacitesEtab = capacitesEtablissement($pdo, (int) $etab['id']);

// La soirée en cours d'abord, sinon la prochaine : c'est pendant la soirée
// que le partenaire suit ses entrées. L'heure vient de PHP (Europe/Paris,
// comme date_heure) et non de NOW(), à l'heure UTC de la base en ligne.
$stmt = $pdo->prepare(
    'SELECT * FROM evenements WHERE etablissement_id=? AND '
    . sqlFinEffectiveEvenement('date_heure', 'date_fin')
    . ' >= ? ORDER BY date_heure ASC LIMIT 1'
);
$stmt->execute([(int) $etab['id'], date('Y-m-d H:i:s')]);
$event = $stmt->fetch();

$nbInscrits  = 0;
$nbCheckin   = 0;
$chartLabels = [];
$chartValues = [];
$schoolStats = [];
$recents     = [];
$nbTotal     = 0;

if ($event) {
    $eid = (int) $event['id'];

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE evenement_id=? AND statut='inscrit'");
    $stmt->execute([$eid]);
    $nbInscrits = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE evenement_id=? AND statut='checkin'");
    $stmt->execute([$eid]);
    $nbCheckin = (int) $stmt->fetchColumn();

    $nbTotal = $nbInscrits + $nbCheckin;

    $stmt = $pdo->prepare('SELECT u.ecole, COUNT(*) as cnt FROM inscriptions i JOIN users u ON u.id=i.user_id WHERE i.evenement_id=? AND u.ecole IS NOT NULL GROUP BY u.ecole ORDER BY cnt DESC');
    $stmt->execute([$eid]);
    $schools     = $stmt->fetchAll();
    $totalSchool = array_sum(array_column($schools, 'cnt')) ?: 1;
    foreach ($schools as $s) {
        $schoolStats[] = ['nom' => (string) $s['ecole'], 'pourcentage' => (int) round(((int) $s['cnt']) / $totalSchool * 100)];
    }
    for ($h = 5; $h >= 0; $h--) {
        $chartLabels[] = date('H\hi', strtotime("-{$h} hour"));
        $from = date('Y-m-d H:i:s', strtotime('-' . ($h + 1) . ' hour'));
        $to   = date('Y-m-d H:i:s', strtotime("-{$h} hour"));
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM inscriptions WHERE evenement_id=? AND created_at BETWEEN ? AND ?');
        $stmt->execute([$eid, $from, $to]);
        $chartValues[] = (int) $stmt->fetchColumn();
    }

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

    // Qui a ete valide a l'entree — pas seulement combien (nbCheckin
    // ci-dessus), mais qui exactement, du plus recent scan au plus ancien.
    $stmt = $pdo->prepare(
        "SELECT u.id, u.prenom, u.nom, u.ecole, u.promo, u.photo, i.checkin_le
           FROM inscriptions i JOIN users u ON u.id=i.user_id
          WHERE i.evenement_id=? AND i.statut='checkin'
          ORDER BY i.checkin_le DESC"
    );
    $stmt->execute([$eid]);
    $checkins = array_map(
        static fn (array $r): array => [
            'id'         => (int) $r['id'],
            'prenom'     => (string) $r['prenom'],
            'nom'        => (string) $r['nom'],
            'ecole'      => $r['ecole'] !== null ? (string) $r['ecole'] : null,
            'promo'      => $r['promo'] !== null ? (string) $r['promo'] : null,
            'photo_url'  => apiPhotoUrl($r['photo'] ?? null),
            'checkin_le' => $r['checkin_le'] !== null ? (string) $r['checkin_le'] : null,
        ],
        $stmt->fetchAll()
    );
} else {
    $checkins = [];
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
    'usage' => usagePartenaireMois($pdo, (int) $etab['id'], $capacitesEtab),
    'offre' => [
        'code'          => $capacitesEtab['offre'],
        'libelle'       => crmLibelleOffre($capacitesEtab['offre']),
        'essai_jusquau' => $clientEtab['essai_jusqu_au'] ?? null,
    ],
    'stats' => [
        'inscrits'      => $nbInscrits,
        'checkin'       => $nbCheckin,
        'total'         => $nbTotal,
        'places'        => $event ? (int) $event['quota'] : 0,
        'taux_presence' => $nbTotal > 0 ? (int) round($nbCheckin / $nbTotal * 100) : null,
        'ecoles'        => $schoolStats,
        'graphe'        => ['labels' => $chartLabels, 'valeurs' => $chartValues],
        'recentes'      => $recents,
        'checkins'      => $checkins,
    ],
]);
