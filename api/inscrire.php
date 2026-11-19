<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/places.php';
require_once __DIR__ . '/../includes/temps_reel.php';
require_once __DIR__ . '/../includes/evenements_temps.php';
require_once __DIR__ . '/../includes/comptes.php';
header('Content-Type: application/json');

// Ecriture : POST obligatoire, jeton CSRF et origine verifies.
// Voir protegerEcritureApi() dans includes/security.php.
protegerEcritureApi();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'etudiant') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$input        = json_decode(file_get_contents('php://input'), true);
$evenement_id = (int) ($input['evenement_id'] ?? 0);
$uid          = (int) $_SESSION['user_id'];

if (!$evenement_id) {
    echo json_encode(['success' => false, 'message' => 'Événement invalide']);
    exit;
}

try {
    $pdo->beginTransaction();

    /*
     * Le contrôle du quota et l'insertion doivent être atomiques. Auparavant
     * le comptage se faisait hors transaction : deux étudiants qui cliquaient
     * en même temps sur la dernière place lisaient le même total et passaient
     * tous les deux. FOR UPDATE verrouille la ligne de l'événement, donc les
     * inscriptions concurrentes sur un même événement se suivent au lieu de
     * se chevaucher.
     */
    // Rejoignable tant qu'elle n'est pas TERMINÉE — donc à venir, ou en cours
    // (voir evenements_temps.php) — et non plus seulement avant son début :
    // une soirée en cours et pas encore complète doit rester rejoignable.
    // Pas pour un BDE dont la validation a été refusée (PAY-09). La
    // sous-requête ne verrouille pas l'établissement : les inscriptions à ses
    // autres soirées ne s'attendent pas.
    $finEff = sqlFinEffectiveEvenement();
    $stmt = $pdo->prepare(
        "SELECT e.quota, e.prix_normal, e.reduction, e.date_heure, e.inscriptions_jusqu_au
           FROM evenements e
          WHERE e.id = ? AND $finEff >= NOW()
            AND EXISTS (SELECT 1 FROM etablissements et
                         WHERE et.id = e.etablissement_id AND " . sqlEtablissementVisible('et') . ")
          FOR UPDATE"
    );
    $stmt->execute([$evenement_id]);
    $event = $stmt->fetch();

    if (!$event) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Événement introuvable ou terminé']);
        exit;
    }

    // Clôture fixée par l'établissement, sinon le début de la soirée (v38).
    if (!evenementInscriptionsOuvertes((string) $event['date_heure'], $event['inscriptions_jusqu_au'] ?? null)) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Les inscriptions sont closes']);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM inscriptions WHERE evenement_id = ? AND statut != 'annule'"
    );
    $stmt->execute([$evenement_id]);
    $nbInscrits = (int) $stmt->fetchColumn();

    if (estComplet($nbInscrits, (int) $event['quota'])) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Complet']);
        exit;
    }

    // Plafond de pass actifs par étudiant (ETU-11). Le verrou sur la ligne
    // de l'étudiant range ses propres inscriptions simultanées l'une après
    // l'autre : sans lui, dix onglets ouverts sur dix soirées passaient tous
    // le comptage. Le pass déjà actif sur CETTE soirée (« déjà inscrit »,
    // plus bas) n'en ajoute pas.
    $pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE')->execute([$uid]);
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM inscriptions i
           JOIN evenements e ON e.id = i.evenement_id
          WHERE i.user_id = ? AND i.statut = 'inscrit' AND i.evenement_id <> ?
            AND e.fin_effective >= ?"
    );
    $stmt->execute([$uid, $evenement_id, date('Y-m-d H:i:s')]);
    if (inscriptionPlafondAtteint((int) $stmt->fetchColumn())) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Tu as déjà ' . INSCRIPTIONS_A_VENIR_MAX . ' pass à venir : annule-en un pour réserver celui-ci.',
        ]);
        exit;
    }

    // Identifiant du pass : imprévisible, et propre à ce couple étudiant/événement.
    $qr_code = bin2hex(random_bytes(32));

    // La clé unique (user_id, evenement_id) garde la ligne d'un pass annulé :
    // un INSERT échouait alors en « déjà inscrit » et l'étudiant qui s'était
    // désinscrit ne pouvait plus revenir. On ranime cette ligne, avec un code
    // neuf pour que l'ancien QR ne repasse pas au scan.
    $stmt = $pdo->prepare('SELECT id, statut FROM inscriptions WHERE user_id = ? AND evenement_id = ? FOR UPDATE');
    $stmt->execute([$uid, $evenement_id]);
    $existante = $stmt->fetch();

    if ($existante && $existante['statut'] !== 'annule') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Tu es déjà inscrit']);
        exit;
    }

    if ($existante) {
        $pdo->prepare(
            "UPDATE inscriptions SET statut = 'inscrit', qr_code = ?, checkin_le = NULL, created_at = NOW() WHERE id = ?"
        )->execute([$qr_code, (int) $existante['id']]);
    } else {
        $pdo->prepare(
            "INSERT INTO inscriptions (user_id, evenement_id, qr_code, statut)
             VALUES (?, ?, ?, 'inscrit')"
        )->execute([$uid, $evenement_id, $qr_code]);
    }

    if ($event['prix_normal'] > 0 && $event['reduction'] > 0) {
        $montant = round($event['prix_normal'] * $event['reduction'] / 100, 2);
        // Une réinscription après annulation ne compte pas deux fois la même
        // économie (badge « saver » obtenu à tort).
        $pdo->prepare('DELETE FROM economies WHERE user_id = ? AND evenement_id = ?')->execute([$uid, $evenement_id]);
        $pdo->prepare(
            "INSERT INTO economies (user_id, evenement_id, montant, date_economie)
             VALUES (?, ?, ?, CURDATE())"
        )->execute([$uid, $evenement_id, $montant]);
    }

    $pdo->commit();

    // Le compteur affiche desormais une valeur de plus : les autres onglets
    // ouverts sur cette soiree doivent l'apprendre a leur prochaine
    // interrogation, et non au prochain rechargement de page.
    fluxToucher($pdo, canalEvenement($evenement_id));

    echo json_encode(['success' => true, 'inscrits' => $nbInscrits + 1]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e->getCode() === '23000') {
        echo json_encode(['success' => false, 'message' => 'Tu es déjà inscrit']);
    } else {
        logErreur('Inscription impossible', $e, ['evenement' => $evenement_id, 'user' => $uid]);
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    }
}
