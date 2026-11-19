<?php
/**
 * GET  /api/v1/partenaire_evenements.php — la liste des événements de
 *      l'établissement du compte connecté.
 * POST /api/v1/partenaire_evenements.php {action:'supprimer', id} —
 *      supprimer un événement, s'il n'a ni inscrit ni paiement (409 sinon :
 *      voir includes/evenements_suppression.php).
 * POST /api/v1/partenaire_evenements.php {action:'annuler', id, motif?} —
 *      annuler un événement pas encore terminé : ses pass sont annulés et
 *      les inscrits prévenus. Possible même sans formule valable : un
 *      établissement dont l'abonnement a pris fin doit pouvoir prévenir ses
 *      inscrits.
 *
 * Reprend partenaire/evenements.php : mêmes colonnes, même agrégation
 * d'inscrits/check-in par sous-requête, même règle de suppression scopée
 * à l'établissement (on ne peut pas supprimer l'événement de quelqu'un
 * d'autre en devinant son id).
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/places.php';
require_once __DIR__ . '/../../includes/crm.php';
require_once __DIR__ . '/../../includes/comptes.php';
require_once __DIR__ . '/../../includes/sponsoring.php';
require_once __DIR__ . '/../../includes/evenements_temps.php';
require_once __DIR__ . '/../../includes/capacites.php';
require_once __DIR__ . '/../../includes/collaborations.php';
require_once __DIR__ . '/../../includes/evenements_suppression.php';
require_once __DIR__ . '/../../includes/temps_reel.php';

$u   = apiPartenaire($pdo);
$uid = (int) $u['id'];

$etab = apiEtablissementDe($pdo, $uid);
if (!$etab) {
    apiErreur('Aucun établissement associé à ce compte.', 404, 'etablissement_absent');
}

$methode = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($methode === 'POST') {
    $corps  = apiCorps();
    $action = (string) ($corps['action'] ?? '');

    if ($action !== 'supprimer' && $action !== 'annuler') {
        apiErreur('Action inconnue', 422, 'action_inconnue');
    }

    $eid = (int) ($corps['id'] ?? 0);
    if (!$eid) {
        apiErreur('Événement manquant', 422, 'id_manquant');
    }

    if ($action === 'annuler') {
        $motif    = is_string($corps['motif'] ?? null) ? $corps['motif'] : null;
        $resultat = evenementAnnuler($pdo, $eid, (int) $etab['id'], $uid, $motif);
        if (!$resultat['ok']) {
            $statut = match ($resultat['code']) {
                'introuvable'    => 404,
                'erreur_serveur' => 500,
                default          => 409,
            };
            apiErreur($resultat['message'], $statut, $resultat['code']);
        }
        fluxToucher($pdo, CANAL_GLOBAL);

        apiReponse(['success' => true, 'message' => $resultat['message'], 'pass_annules' => $resultat['pass_annules'] ?? 0]);
    }

    exigerAbonnementValide($pdo, $etab);

    // Refusée s'il a des inscrits ou un paiement (PAY-10) : 409, avec le
    // code inscrits ou paiement et un message à afficher tel quel.
    $resultat = evenementSupprimer($pdo, $eid, (int) $etab['id']);
    if (!$resultat['ok']) {
        $statut = match ($resultat['code']) {
            'introuvable'    => 404,
            'erreur_serveur' => 500,
            default          => 409,
        };
        apiErreur($resultat['message'], $statut, $resultat['code']);
    }
    fluxToucher($pdo, CANAL_GLOBAL);

    apiReponse(['success' => true, 'message' => $resultat['message']]);
}

apiExigerMethode('GET');
exigerAbonnementValide($pdo, $etab);

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
// L'établissement invité à co-organiser chaque événement (v39), et sa réponse.
$collaborations = collaborationsDe($pdo, array_map(static fn (array $e): int => (int) $e['id'], $evenements), false);

apiReponse([
    'success'     => true,
    'usage'       => usagePartenaireMois($pdo, (int) $etab['id'], capacitesEtablissement($pdo, (int) $etab['id'])),
    'evenements'  => array_map(
        static function (array $e) use ($collaborations): array {
            $quota    = (int) ($e['quota'] ?? 0);
            $inscrits = (int) $e['nb_inscrits'];
            // Passée = terminée, pas seulement commencée : une soirée en cours
            // doit garder son bouton « Scanner ».
            $passe    = evenementTermine((string) $e['date_heure'], $e['date_fin'] ?? null);
            $complet  = remplissage($quota, $inscrits)['complet'];

            return [
                'id'             => (int) $e['id'],
                'titre'          => (string) $e['titre'],
                'type'           => (string) $e['type'],
                'sport'          => isset($e['sport']) && $e['sport'] !== null ? (string) $e['sport'] : null,
                'format'         => isset($e['format']) && $e['format'] !== null ? (string) $e['format'] : null,
                'date_heure'     => (string) $e['date_heure'],
                'quota'          => $quota,
                'inscrits'       => $inscrits,
                'checkin'        => (int) $e['nb_checkin'],
                'reduction'      => $e['reduction'] !== null ? (int) $e['reduction'] : null,
                'is_gratuit'     => (bool) $e['is_gratuit'],
                'is_flash'       => (bool) $e['is_flash'],
                'is_sponsorise'  => (bool) $e['is_sponsorise'],
                'sponsorise_actif' => (bool) $e['is_sponsorise'] && sponsoringActif($e),
                'sponsor_debut'  => sponsoringChrono($e)['debut'] ?? null,
                'sponsor_jusqu_au' => !empty($e['sponsor_jusqu_au']) ? (string) $e['sponsor_jusqu_au'] : null,
                'sponsor_restant_s' => sponsoringChrono($e)['restant_s'] ?? null,
                'supplement_tarif' => (float) ($e['supplement_tarif'] ?? 0),
                'sponsor_tarif'  => (float) ($e['sponsor_tarif'] ?? 0),
                'statut'         => $e['annule_le'] !== null ? 'annule' : ($passe ? 'passe' : ($complet ? 'complet' : 'actif')),
                'annule_le'      => $e['annule_le'] !== null ? (string) $e['annule_le'] : null,
                'annule_motif'   => $e['annule_motif'] !== null ? (string) $e['annule_motif'] : null,
                'collaboration'  => ($collaborations[(int) $e['id']] ?? [])[0] ?? null,
            ];
        },
        $evenements
    ),
]);
