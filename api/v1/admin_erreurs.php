<?php
/**
 * GET  /api/v1/admin_erreurs.php?filtre=ouvertes|serveur|app|resolues —
 *      le journal des erreurs (journal_erreurs, migration v42), regroupées
 *      par empreinte, les plus récentes d'abord.
 * POST /api/v1/admin_erreurs.php {action:'resoudre'|'rouvrir', id} —
 *      marquer une erreur corrigée, ou la rouvrir. Une erreur corrigée qui
 *      se reproduit se rouvre d'elle-même (includes/journal_erreurs.php).
 * POST {action:'resoudre_tout', source?} — tout marquer corrigé d'un coup.
 *
 * Réservé aux administrateurs (apiAdmin). Les messages ne contiennent ni
 * identifiant de compte ni adresse e-mail en clair : ils sont nettoyés à
 * l'écriture.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/suivi.php';

apiAdmin($pdo);

$methode = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($methode === 'POST') {
    $corps  = apiCorps();
    $action = (string) ($corps['action'] ?? '');
    $id     = (int) ($corps['id'] ?? 0);

    if ($action === 'resoudre_tout') {
        $source = (string) ($corps['source'] ?? '');
        $sql    = 'UPDATE journal_erreurs SET resolu_le = ? WHERE resolu_le IS NULL';
        $params = [date('Y-m-d H:i:s')];
        if (in_array($source, ['serveur', 'app'], true)) {
            $sql     .= ' AND source = ?';
            $params[] = $source;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        apiReponse(['success' => true, 'resolues' => $stmt->rowCount()]);
    }

    if (!in_array($action, ['resoudre', 'rouvrir'], true)) {
        apiErreur('Action inconnue', 422, 'action_inconnue');
    }
    if ($id <= 0) {
        apiErreur('Erreur manquante', 422, 'id_manquant');
    }
    $stmt = $pdo->prepare('UPDATE journal_erreurs SET resolu_le = ? WHERE id = ?');
    $stmt->execute([$action === 'resoudre' ? date('Y-m-d H:i:s') : null, $id]);
    if ($stmt->rowCount() === 0) {
        $existe = $pdo->prepare('SELECT 1 FROM journal_erreurs WHERE id = ?');
        $existe->execute([$id]);
        if ($existe->fetchColumn() === false) {
            apiErreur('Cette erreur n’existe plus', 404, 'introuvable');
        }
    }

    apiReponse(['success' => true]);
}

apiExigerMethode('GET');

$filtre = (string) ($_GET['filtre'] ?? 'ouvertes');
if (!in_array($filtre, ['ouvertes', 'serveur', 'app', 'resolues'], true)) {
    $filtre = 'ouvertes';
}

try {
    $erreurs = suiviErreurs($pdo, $filtre);
} catch (PDOException $e) {
    // Base pas encore migrée (v42) : liste vide plutôt qu'un écran en erreur.
    $erreurs = [];
}

apiReponse(['success' => true, 'filtre' => $filtre, 'erreurs' => $erreurs]);
