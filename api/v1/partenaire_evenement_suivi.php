<?php
/**
 * GET /api/v1/partenaire_evenement_suivi.php?id=12 — le suivi d'un événement
 * de l'établissement : remplissage, présence, courbe des inscriptions,
 * profil de salle, mise en avant et ce que l'événement coûte au mois.
 *
 * Rien n'est estimé ni inventé : chaque chiffre vient des inscriptions ou de
 * la ligne de l'événement.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/crm.php';
require_once __DIR__ . '/../../includes/comptes.php';
require_once __DIR__ . '/../../includes/sponsoring.php';
require_once __DIR__ . '/../../includes/places.php';
require_once __DIR__ . '/../../includes/evenements_temps.php';
require_once __DIR__ . '/../../includes/telephone.php';

apiExigerMethode('GET');

$u   = apiPartenaire($pdo);
$uid = (int) $u['id'];

$etab = apiEtablissementDe($pdo, $uid);
if (!$etab) {
    apiErreur('Aucun établissement associé à ce compte.', 404, 'etablissement_absent');
}
exigerAbonnementValide($pdo, $etab);

$eid  = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM evenements WHERE id = ? AND etablissement_id = ?');
$stmt->execute([$eid, (int) $etab['id']]);
$e = $stmt->fetch();
if (!$e) {
    apiErreur('Cet événement n’existe plus.', 404, 'introuvable');
}

$stmt = $pdo->prepare(
    "SELECT SUM(statut = 'inscrit') AS inscrits, SUM(statut = 'checkin') AS presents, SUM(statut = 'annule') AS annules
       FROM inscriptions WHERE evenement_id = ?"
);
$stmt->execute([$eid]);
$c = $stmt->fetch() ?: [];
$presents = (int) ($c['presents'] ?? 0);
$inscrits = (int) ($c['inscrits'] ?? 0) + $presents;
$annules  = (int) ($c['annules'] ?? 0);
$quota    = (int) ($e['quota'] ?? 0);

// Inscriptions par jour, cumulées (60 jours au plus) : on lit la montée en
// charge d'un coup d'œil.
$stmt = $pdo->prepare(
    "SELECT DATE(created_at) AS jour, COUNT(*) AS n FROM inscriptions
      WHERE evenement_id = ? AND statut <> 'annule' GROUP BY DATE(created_at) ORDER BY jour"
);
$stmt->execute([$eid]);
$courbe = [];
$cumul  = 0;
foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $jour => $n) {
    $cumul += (int) $n;
    $courbe[] = ['jour' => (string) $jour, 'inscriptions' => (int) $n, 'cumul' => $cumul];
}
$courbe = array_slice($courbe, -60);

$stmt = $pdo->prepare(
    "SELECT u.ecole, COUNT(*) AS n FROM inscriptions i JOIN users u ON u.id = i.user_id
      WHERE i.evenement_id = ? AND i.statut <> 'annule' AND u.ecole IS NOT NULL AND u.ecole <> ''
      GROUP BY u.ecole ORDER BY n DESC LIMIT 6"
);
$stmt->execute([$eid]);
$lignesEcoles = $stmt->fetchAll();
$totalEcoles  = array_sum(array_map(static fn ($r) => (int) $r['n'], $lignesEcoles));
$ecoles = array_map(
    static fn (array $r): array => [
        'nom' => (string) $r['ecole'], 'nombre' => (int) $r['n'],
        'pourcentage' => $totalEcoles > 0 ? (int) round(((int) $r['n']) / $totalEcoles * 100) : 0,
    ],
    $lignesEcoles
);

// Les inscrits eux-mêmes (nom, école, présence) : seul l'établissement
// propriétaire de l'événement les voit (filtre plus haut). Plus leur
// e-mail (PAY-08) : l'entrée se gère sans, et la politique de
// confidentialité ne le confie plus aux établissements (minimisation).
// Leur numéro de téléphone (v43), oui : pour joindre un inscrit au sujet de
// cet événement, et seulement jusqu'à 30 jours après sa fin (voir
// includes/telephone.php et la politique de confidentialité).
$telVisibles = telephoneVisiblePourEtablissement(
    isset($e['date_fin']) ? (string) $e['date_fin'] : null,
    (string) $e['date_heure']
);
$stmt = $pdo->prepare(
    "SELECT u.id, u.prenom, u.nom, u.ecole, u.promo, u.telephone, i.statut, i.created_at
       FROM inscriptions i JOIN users u ON u.id = i.user_id
      WHERE i.evenement_id = ? AND i.statut <> 'annule'
      ORDER BY u.nom, u.prenom"
);
$stmt->execute([$eid]);
$participants = array_map(
    static fn (array $r): array => [
        'id'         => (int) $r['id'],
        'prenom'     => (string) $r['prenom'],
        'nom'        => (string) $r['nom'],
        'ecole'      => $r['ecole'] !== null && $r['ecole'] !== '' ? (string) $r['ecole'] : null,
        'promo'      => $r['promo'] !== null && $r['promo'] !== '' ? (string) $r['promo'] : null,
        'present'    => $r['statut'] === 'checkin',
        'inscrit_le' => (string) $r['created_at'],
        'telephone'  => $telVisibles && !empty($r['telephone']) ? (string) $r['telephone'] : null,
    ],
    $stmt->fetchAll()
);

$stmt = $pdo->prepare('SELECT envoyer_le FROM sponsor_notifications WHERE evenement_id = ? ORDER BY envoyer_le');
$stmt->execute([$eid]);
$maintenant = date('Y-m-d H:i:s');
$notifs = array_map(
    static fn ($h): array => ['envoyer_le' => (string) $h, 'envoyee' => (string) $h <= $maintenant],
    $stmt->fetchAll(PDO::FETCH_COLUMN)
);

$sponsorTarif = (float) ($e['sponsor_tarif'] ?? 0);
$supplement   = (float) ($e['supplement_tarif'] ?? 0);
$formuleCode  = $e['sponsor_formule'] ?? null;
$termine      = evenementTermine((string) $e['date_heure'], $e['date_fin'] ?? null);
$chrono       = sponsoringChrono($e);

apiReponse([
    'success'   => true,
    'evenement' => [
        'id'           => (int) $e['id'],
        'titre'        => (string) $e['titre'],
        'type'         => (string) $e['type'],
        'date_heure'   => (string) $e['date_heure'],
        'date_fin'     => $e['date_fin'] !== null ? (string) $e['date_fin'] : null,
        'lieu'         => (string) ($e['lieu'] ?? ''),
        'statut'       => $termine ? 'passe' : (remplissage($quota, $inscrits)['complet'] ? 'complet' : 'actif'),
        'is_flash'     => (bool) $e['is_flash'],
        'flash_expiry' => $e['flash_expiry'] !== null ? (string) $e['flash_expiry'] : null,
        'is_gratuit'   => (bool) $e['is_gratuit'],
        'publie_le'    => (string) $e['created_at'],
    ],
    'chiffres' => [
        'quota'            => $quota,
        'inscrits'         => $inscrits,
        'presents'         => $presents,
        'annules'          => $annules,
        'places_restantes' => $quota > 0 ? max(0, $quota - $inscrits) : null,
        'taux_remplissage' => $quota > 0 ? (int) round($inscrits / $quota * 100) : null,
        'taux_presence'    => $inscrits > 0 ? (int) round($presents / $inscrits * 100) : null,
    ],
    'courbe' => $courbe,
    'ecoles' => $ecoles,
    'participants' => $participants,
    // Jusqu'à quand les numéros restent affichés (heure de Paris).
    'telephones_jusqu_au' => date('Y-m-d H:i:s', (int) strtotime((string) ($e['date_fin'] ?: $e['date_heure'])) + TELEPHONE_VISIBILITE_JOURS * 86400),
    'mise_en_avant' => [
        'active'                 => (bool) $e['is_sponsorise'],
        'en_cours'               => sponsoringActif($e),
        'formule'                => $formuleCode !== null ? (string) $formuleCode : null,
        'libelle'                => $e['is_sponsorise'] ? libelleSponsoring($formuleCode) : null,
        'offerte'                => (bool) $e['is_sponsorise'] && $sponsorTarif == 0.0,
        'jusqu_au'               => $e['sponsor_jusqu_au'] !== null ? (string) $e['sponsor_jusqu_au'] : null,
        // Le déroulé, calculé ici (heures de Paris) : l'application décompte à partir de `restant_s`.
        'debut'                  => $chrono['debut'] ?? null,
        'restant_s'              => $chrono['restant_s'] ?? null,
        'duree_s'                => $chrono['duree_s'] ?? null,
        'ecoule_pct'             => $chrono['ecoule_pct'] ?? null,
        'notifications'          => $notifs,
        'notifications_incluses' => notificationsSponsoring($formuleCode),
    ],
    'cout' => [
        'supplement' => $supplement,
        'sponsoring' => $sponsorTarif,
        'total'      => round($supplement + $sponsorTarif, 2),
    ],
]);
