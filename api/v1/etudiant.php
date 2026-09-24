<?php
/**
 * GET /api/v1/etudiant.php?id=12 — le profil d'un autre étudiant.
 *
 * Les lectures de view_profile.php, avec la même règle de confidentialité :
 * squads en commun et prochaines sorties ne partent que si l'abonnement est
 * accepté. Un blocage, dans un sens ou dans l'autre, rend le profil
 * introuvable, comme le site qui renvoie vers l'annuaire.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/social.php';

apiExigerMethode('GET');

$moi = apiEtudiant($pdo);
$uid = (int) $moi['id'];
$id  = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    apiErreur('Identifiant manquant', 422, 'id_manquant');
}

$stmt = $pdo->prepare("
    SELECT id, prenom, nom, ecole, promo, interests, created_at, photo,
           (SELECT statut FROM follows_users WHERE follower_id = ? AND followed_id = ?) AS follow_statut,
           (SELECT COUNT(*) FROM follows_users WHERE followed_id = ? AND statut='accepted') AS nb_followers,
           (SELECT COUNT(*) FROM follows_users WHERE follower_id = ? AND statut='accepted') AS nb_following
    FROM users WHERE id = ? AND type='etudiant'
");
$stmt->execute([$uid, $id, $id, $id, $id]);
$u = $stmt->fetch();

if (!$u || isBlockedBetween($pdo, $uid, $id)) {
    apiErreur('Étudiant introuvable.', 404, 'introuvable');
}

$etat   = $u['follow_statut'] ?: FOLLOW_NONE;
$activite = $etat === FOLLOW_ACCEPTED;

$stmt = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id=? AND blocked_id=?');
$stmt->execute([$uid, $id]);
$jeBloque = (bool) $stmt->fetchColumn();

$squadsCommuns = [];
$sorties       = [];
if ($activite) {
    $stmt = $pdo->prepare("
        SELECT s.titre, s.type, s.date_heure
        FROM squads s
        JOIN squad_membres sm1 ON sm1.squad_id = s.id
        JOIN squad_membres sm2 ON sm2.squad_id = s.id
        WHERE sm1.user_id = ? AND sm2.user_id = ? AND s.date_heure >= NOW()
    ");
    $stmt->execute([$uid, $id]);
    $squadsCommuns = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT e.titre, e.date_heure, et.nom AS etablissement_nom, et.type AS etab_type
        FROM inscriptions i
        JOIN evenements e ON e.id = i.evenement_id
        JOIN etablissements et ON et.id = e.etablissement_id
        WHERE i.user_id = ? AND i.statut = 'inscrit' AND e.date_heure >= NOW()
        ORDER BY e.date_heure ASC LIMIT 3
    ");
    $stmt->execute([$id]);
    $sorties = $stmt->fetchAll();
}

apiReponse([
    'success'  => true,
    'etudiant' => apiPersonne($u) + [
        // Tel que view_profile.php l'affiche : la colonne brute, découpée.
        'interets'       => $u['interests'] ? array_map('strval', explode(',', (string) $u['interests'])) : [],
        'nb_abonnes'     => (int) $u['nb_followers'],
        'nb_abonnements' => (int) $u['nb_following'],
        'etat_suivi'     => (string) $etat,
        'je_bloque'      => $jeBloque,
        'activite_visible' => $activite,
        'squads_communs' => array_map(static fn (array $s): array => [
            'titre' => (string) $s['titre'],
            'type'  => (string) $s['type'],
        ], $squadsCommuns),
        'sorties'        => array_map(static fn (array $e): array => [
            'titre'         => (string) $e['titre'],
            'etablissement' => (string) $e['etablissement_nom'],
            'date'          => dateFr((string) $e['date_heure'], 'j M'),
        ], $sorties),
    ],
]);
