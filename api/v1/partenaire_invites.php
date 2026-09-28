<?php
/**
 * GET /api/v1/partenaire_invites.php?evenement_id=&q= — inscrits pas encore
 * check-in, pour la validation manuelle du scan.
 *
 * Quand le QR code ne fonctionne pas (lumière, code abîmé) ou que
 * l'établissement organise plusieurs soirées le même soir, le partenaire
 * cherche la personne par son prénom ou son nom et la valide sans scanner —
 * voir api/v1/partenaire_scan.php, qui accepte `inscription_id` pour ça.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/uploads.php';

apiExigerMethode('GET');

$u   = apiPartenaire($pdo);
$uid = (int) $u['id'];

$eventId = (int) ($_GET['evenement_id'] ?? 0);
$q       = trim((string) ($_GET['q'] ?? ''));

if (!$eventId) {
    apiErreur('Événement manquant', 422, 'evenement_manquant');
}

$stmt = $pdo->prepare(
    'SELECT e.id FROM evenements e
     JOIN etablissements et ON et.id = e.etablissement_id
    WHERE e.id = ? AND et.user_id = ?'
);
$stmt->execute([$eventId, $uid]);
if (!$stmt->fetch()) {
    apiErreur('Événement non autorisé', 403, 'evenement_non_autorise');
}

$sql = "SELECT i.id AS inscription_id, u.prenom, u.nom, u.ecole, u.promo, u.photo
          FROM inscriptions i
          JOIN users u ON u.id = i.user_id
         WHERE i.evenement_id = ? AND i.statut = 'inscrit'";
$params = [$eventId];

if ($q !== '') {
    $sql .= ' AND (u.prenom LIKE ? OR u.nom LIKE ?)';
    $terme = '%' . addcslashes($q, '%_') . '%';
    array_push($params, $terme, $terme);
}

$sql .= ' ORDER BY u.prenom, u.nom LIMIT 50';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

apiReponse([
    'success' => true,
    'invites' => array_map(
        static fn (array $i): array => [
            'inscription_id' => (int) $i['inscription_id'],
            'prenom'         => (string) $i['prenom'],
            'nom'            => (string) $i['nom'],
            'ecole'          => $i['ecole'] !== null ? (string) $i['ecole'] : null,
            'promo'          => $i['promo'] !== null ? (string) $i['promo'] : null,
            'photo_url'      => apiPhotoUrl($i['photo'] ?? null),
        ],
        $stmt->fetchAll()
    ),
]);
