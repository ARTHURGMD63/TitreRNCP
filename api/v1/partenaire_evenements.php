<?php
/**
 * GET  /api/v1/partenaire_evenements.php — la liste des événements de
 *      l'établissement du compte connecté.
 * POST /api/v1/partenaire_evenements.php {action:'supprimer', id} —
 *      supprimer un événement.
 *
 * Reprend partenaire/evenements.php : mêmes colonnes, même agrégation
 * d'inscrits/check-in par sous-requête, même règle de suppression scopée
 * à l'établissement (on ne peut pas supprimer l'événement de quelqu'un
 * d'autre en devinant son id).
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/crm.php';
require_once __DIR__ . '/../../includes/sponsoring.php';

$u   = apiPartenaire($pdo);
$uid = (int) $u['id'];

$etab = apiEtablissementDe($pdo, $uid);
if (!$etab) {
    apiErreur('Aucun établissement associé à ce compte.', 404, 'etablissement_absent');
}
if (!abonnementChoisi(abonnementEtablissement($pdo, (int) $etab['id']))) {
    apiErreur('Choisis une formule d’abonnement pour continuer.', 403, 'abonnement_requis');
}

$methode = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($methode === 'POST') {
    $corps  = apiCorps();
    $action = (string) ($corps['action'] ?? '');

    if ($action !== 'supprimer') {
        apiErreur('Action inconnue', 422, 'action_inconnue');
    }

    $eid = (int) ($corps['id'] ?? 0);
    if (!$eid) {
        apiErreur('Événement manquant', 422, 'id_manquant');
    }

    $pdo->prepare('DELETE FROM evenements WHERE id=? AND etablissement_id=?')
        ->execute([$eid, (int) $etab['id']]);

    apiReponse(['success' => true, 'message' => 'Événement supprimé.']);
}

apiExigerMethode('GET');

$stmt = $pdo->prepare(
    "SELECT e.*,
            (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id=e.id AND i.statut != 'annule') AS nb_inscrits,
            (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id=e.id AND i.statut='checkin') AS nb_checkin
       FROM evenements e
      WHERE e.etablissement_id=?
      ORDER BY e.date_heure DESC"
);
$stmt->execute([(int) $etab['id']]);
$evenements = $stmt->fetchAll();

apiReponse([
    'success'     => true,
    'evenements'  => array_map(
        static function (array $e): array {
            $quota    = (int) ($e['quota'] ?? 0);
            $inscrits = (int) $e['nb_inscrits'];
            $passe    = strtotime((string) $e['date_heure']) < time();
            $complet  = $quota > 0 && $inscrits >= $quota;

            return [
                'id'             => (int) $e['id'],
                'titre'          => (string) $e['titre'],
                'type'           => (string) $e['type'],
                'date_heure'     => (string) $e['date_heure'],
                'quota'          => $quota,
                'inscrits'       => $inscrits,
                'checkin'        => (int) $e['nb_checkin'],
                'reduction'      => $e['reduction'] !== null ? (int) $e['reduction'] : null,
                'is_gratuit'     => (bool) $e['is_gratuit'],
                'is_flash'       => (bool) $e['is_flash'],
                'is_sponsorise'  => (bool) $e['is_sponsorise'],
                'sponsorise_actif' => (bool) $e['is_sponsorise'] && sponsoringActif($e),
                'statut'         => $passe ? 'passe' : ($complet ? 'complet' : 'actif'),
            ];
        },
        $evenements
    ),
]);
