<?php
/**
 * La validation des BDE — réservée à l'administrateur.
 *
 *   GET  /api/v1/admin_bde.php
 *        les comptes de type BDE : en attente d'abord, puis refusés, puis validés
 *   POST { "action": "valider",  "id": 3 }
 *   POST { "action": "refuser",  "id": 3, "note": "…" }
 *   POST { "action": "remettre", "id": 3 }   repasse un compte en attente
 *
 * Un BDE n'a ni formule ni publication tant qu'il n'est pas validé : la
 * formule BDE coûte moins cher que celles des bars, et rien ne prouve à
 * l'inscription qu'une association en est une. L'administrateur vérifie
 * (existence du bureau, lien avec une école) hors de l'outil, puis tranche ici.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/comptes.php';
require_once __DIR__ . '/../../includes/push.php';

apiExigerMethode(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 'POST' : 'GET');

$admin = apiAdmin($pdo);

/**
 * @param array<string, mixed> $r
 * @return array<string, mixed>
 */
function adminBdeLigne(array $r): array
{
    return [
        'id'            => (int) $r['id'],
        'nom'           => (string) $r['nom'],
        'ville'         => (string) ($r['ville'] ?? ''),
        'verification'  => (string) $r['verification'],
        'note'          => $r['verification_note'] !== null ? (string) $r['verification_note'] : null,
        'verifie_le'    => $r['verifie_le'] !== null ? (string) $r['verifie_le'] : null,
        'verifie_par'   => $r['verifie_par_nom'] !== null ? (string) $r['verifie_par_nom'] : null,
        'inscrit_le'    => (string) $r['inscrit_le'],
        'contact'       => [
            'prenom' => (string) $r['prenom'],
            'nom'    => (string) $r['user_nom'],
            'email'  => (string) $r['email'],
        ],
        'formule'       => $r['offre'] !== null ? (string) $r['offre'] : null,
        'evenements'    => (int) $r['nb_evenements'],
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $corps  = apiCorps();
    $action = (string) ($corps['action'] ?? '');
    $id     = (int) ($corps['id'] ?? 0);

    $stmt = $pdo->prepare("SELECT id, type FROM etablissements WHERE id = ?");
    $stmt->execute([$id]);
    $etab = $stmt->fetch();
    if (!$etab || $etab['type'] !== 'bde') {
        apiErreur("Ce compte n'est pas un BDE.", 404, 'introuvable');
    }

    switch ($action) {
        case 'valider':
            $pdo->prepare("UPDATE etablissements SET verification='validee', verifie_par=?, verifie_le=NOW(), verification_note=NULL WHERE id=?")
                ->execute([(int) $admin['id'], $id]);
            $message = 'BDE validé.';
            break;
        case 'refuser':
            $note = trim((string) ($corps['note'] ?? ''));
            if ($note === '') {
                apiErreur('Indique la raison du refus : le BDE la verra.', 422, 'note_requise');
            }
            $pdo->prepare("UPDATE etablissements SET verification='refusee', verifie_par=?, verifie_le=NOW(), verification_note=? WHERE id=?")
                ->execute([(int) $admin['id'], mb_substr($note, 0, 255), $id]);
            $message = 'BDE refusé.';
            break;
        case 'remettre':
            $pdo->prepare("UPDATE etablissements SET verification='en_attente', verifie_par=NULL, verifie_le=NULL, verification_note=NULL WHERE id=?")
                ->execute([$id]);
            $message = 'BDE remis en attente.';
            break;
        default:
            apiErreur('Action inconnue', 422, 'action_inconnue');
    }

    // Le BDE n'a aucune raison d'ouvrir l'application pour savoir où il en est :
    // la décision lui arrive sur le téléphone.
    if ($action === 'valider' || $action === 'refuser') {
        $stmt = $pdo->prepare('SELECT user_id FROM etablissements WHERE id = ?');
        $stmt->execute([$id]);
        $proprietaire = (int) $stmt->fetchColumn();
        if ($proprietaire > 0) {
            [$titre, $corps] = $action === 'valider' ? pushTexteBdeValide() : pushTexteBdeRefuse();
            pushNotifier($pdo, $proprietaire, 'compte', "bde_$action:$id:" . date('YmdHis'), $titre, $corps, ['url' => '/abonnement']);
        }
    }

    apiReponse(['success' => true, 'message' => $message]);
}

$stmt = $pdo->query(
    "SELECT e.id, e.nom, e.ville, e.verification, e.verification_note, e.verifie_le,
            u.prenom, u.nom AS user_nom, u.email, u.created_at AS inscrit_le,
            v.prenom AS verifie_par_nom,
            c.offre,
            (SELECT COUNT(*) FROM evenements ev WHERE ev.etablissement_id = e.id) AS nb_evenements
       FROM etablissements e
       JOIN users u ON u.id = e.user_id
       LEFT JOIN users v ON v.id = e.verifie_par
       LEFT JOIN crm_clients c ON c.etablissement_id = e.id
      WHERE e.type = 'bde'
      ORDER BY FIELD(e.verification, 'en_attente', 'refusee', 'validee', 'non_requise'), u.created_at DESC"
);
$lignes = array_map('adminBdeLigne', $stmt->fetchAll());

apiReponse([
    'success'    => true,
    'en_attente' => count(array_filter($lignes, static fn (array $l): bool => $l['verification'] === 'en_attente')),
    'comptes'    => $lignes,
]);
