<?php
/**
 * GET /api/v1/partenaire_checkins.php?evenement_id=12 — les entrées d'UN
 * événement de l'établissement : combien d'inscrits, combien déjà validés à
 * l'entrée, et qui.
 *
 * L'écran de scan rechargeait tout le tableau de bord pour compter les
 * entrées, et le tableau de bord ne parle que de la soirée en cours ou de la
 * prochaine : scanner pour un autre événement (deux soirées le même
 * week-end) affichait un compteur à 0 (constat MOB-15). Ce point répond pour
 * l'événement scanné, et seulement pour lui.
 *
 * Même contrôle d'appartenance que partenaire_scan.php et
 * partenaire_invites.php : l'événement doit appartenir à un établissement de
 * ce compte, sinon 403.
 */

require_once __DIR__ . '/_socle.php';

apiExigerMethode('GET');

$u   = apiPartenaire($pdo);
$uid = (int) $u['id'];

$eventId = (int) ($_GET['evenement_id'] ?? 0);
if ($eventId <= 0) {
    apiErreur('Événement manquant', 422, 'evenement_manquant');
}

$stmt = $pdo->prepare(
    'SELECT e.id, e.titre, e.quota FROM evenements e
     JOIN etablissements et ON et.id = e.etablissement_id
    WHERE e.id = ? AND et.user_id = ?'
);
$stmt->execute([$eventId, $uid]);
$evenement = $stmt->fetch();
if (!$evenement) {
    apiErreur('Événement non autorisé', 403, 'evenement_non_autorise');
}

$stmt = $pdo->prepare(
    "SELECT SUM(statut = 'inscrit') AS inscrits, SUM(statut = 'checkin') AS checkin
       FROM inscriptions WHERE evenement_id = ?"
);
$stmt->execute([$eventId]);
$compte   = $stmt->fetch() ?: [];
$inscrits = (int) ($compte['inscrits'] ?? 0);
$checkin  = (int) ($compte['checkin'] ?? 0);

// Qui a été validé à l'entrée, du plus récent scan au plus ancien : la même
// forme que `stats.checkins` du tableau de bord.
$stmt = $pdo->prepare(
    "SELECT u.id, u.prenom, u.nom, u.ecole, u.promo, u.photo, i.checkin_le
       FROM inscriptions i JOIN users u ON u.id = i.user_id
      WHERE i.evenement_id = ? AND i.statut = 'checkin'
      ORDER BY i.checkin_le DESC"
);
$stmt->execute([$eventId]);
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

// Tous les inscrits (hors annulés), entrés ou non : la liste que l'accueil
// coche au fil des scans. Triée par nom pour qu'on y retrouve quelqu'un.
$stmt = $pdo->prepare(
    "SELECT i.id AS inscription_id, i.statut, i.checkin_le, u.id, u.prenom, u.nom, u.ecole, u.promo, u.photo
       FROM inscriptions i JOIN users u ON u.id = i.user_id
      WHERE i.evenement_id = ? AND i.statut IN ('inscrit', 'checkin')
      ORDER BY u.nom, u.prenom"
);
$stmt->execute([$eventId]);
$tousInscrits = array_map(
    static fn (array $r): array => [
        'inscription_id' => (int) $r['inscription_id'],
        'id'             => (int) $r['id'],
        'prenom'         => (string) $r['prenom'],
        'nom'            => (string) $r['nom'],
        'ecole'          => $r['ecole'] !== null ? (string) $r['ecole'] : null,
        'promo'          => $r['promo'] !== null ? (string) $r['promo'] : null,
        'photo_url'      => apiPhotoUrl($r['photo'] ?? null),
        'present'        => $r['statut'] === 'checkin',
        'checkin_le'     => $r['checkin_le'] !== null ? (string) $r['checkin_le'] : null,
    ],
    $stmt->fetchAll()
);

apiReponse([
    'success'   => true,
    'evenement' => [
        'id'    => (int) $evenement['id'],
        'titre' => (string) $evenement['titre'],
    ],
    'compteurs' => [
        // `inscrits` : pas encore entrés ; `total` : tous ceux qui ont un pass.
        'inscrits' => $inscrits,
        'checkin'  => $checkin,
        'total'    => $inscrits + $checkin,
        'places'   => (int) ($evenement['quota'] ?? 0),
    ],
    'checkins' => $checkins,
    'inscrits' => $tousInscrits,
]);
