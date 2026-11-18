<?php
/**
 * POST /api/v1/supprimer_compte.php — supprimer son compte.
 *
 * Corps JSON : mot_de_passe (la personne confirme qu'elle est bien elle : un
 * téléphone oublié ouvert ne doit pas suffire à effacer un compte).
 *
 *  - étudiant   : effacement immédiat des données personnelles, trace anonyme
 *                 conservée (includes/compte.php).
 *                 Réponse { success, supprime: true }.
 *  - partenaire : suppression immédiate (Apple 5.1.1(v)) : abonnement Stripe
 *                 résilié, établissement et soirées retirés, identité effacée ;
 *                 la fiche client et le registre financier restent, sans
 *                 coordonnées (includes/compte.php, compteSupprimerPartenaire()).
 *                 Si l'abonnement ne peut pas être résilié, rien n'est
 *                 supprimé : 503, à réessayer.
 *                 Réponse { success, supprime: true }.
 *  - admin      : refusé. Un compte fondateur ne se supprime pas depuis un
 *                 téléphone.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/compte.php';

apiExigerMethode('POST');

$u   = apiUtilisateur($pdo);
$uid = (int) $u['id'];

// Même garde que la connexion : ce point vérifie un mot de passe, il ne doit
// pas permettre d'en tester à volonté depuis un jeton volé.
$ip = ipClient();
if (isRateLimited($pdo, $ip, 5, 900)) {
    apiErreur('Trop de tentatives. Réessaie dans quinze minutes.', 429, 'trop_de_tentatives');
}

$corps = apiCorps();
$pass  = is_string($corps['mot_de_passe'] ?? null) ? $corps['mot_de_passe'] : '';

// apiUtilisateur() ne renvoie pas le hachage : il ne sort jamais de la base que
// pour cette comparaison.
$stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
$stmt->execute([$uid]);
$hash = (string) $stmt->fetchColumn();

if ($pass === '' || $hash === '' || !password_verify($pass, $hash)) {
    recordLoginAttempt($pdo, $ip, (string) $u['email']);
    apiErreur('Mot de passe incorrect.', 403, 'mot_de_passe');
}

if ($u['type'] === 'etudiant') {
    $restes = compteSupprimerEtudiant($pdo, $uid);
    compteNettoyerApres($pdo, $restes);
    apiReponse(['success' => true, 'supprime' => true]);
}

if ($u['type'] === 'partenaire') {
    try {
        $restes = compteSupprimerPartenaire($pdo, $uid);
    } catch (Throwable $e) {
        logErreur('Suppression de compte partenaire impossible', $e, ['utilisateur' => $uid]);
        apiErreur('La suppression n’a pas pu se faire : ton abonnement n’a pas pu être résilié. Réessaie dans un instant.', 503, 'suppression_impossible');
    }
    compteNettoyerApresPartenaire($pdo, $restes);
    apiReponse(['success' => true, 'supprime' => true]);
}

apiErreur("Ce type de compte ne peut pas être supprimé depuis l'application.", 403, 'type_non_supprimable');
