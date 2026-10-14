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
 *  - partenaire : un compte établissement porte un contrat, un abonnement et
 *                 des soirées auxquelles d'autres étudiants sont inscrits : il
 *                 ne s'efface pas d'un geste. La demande est transmise à
 *                 l'équipe, qui la traite.
 *                 Réponse { success, supprime: false, demande: true }.
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
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
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
    $nom = trim((string) ($u['prenom'] ?? '') . ' ' . (string) ($u['nom'] ?? ''));
    envoyerEmail(
        'contact@linkee.fr',
        'Linkee — Demande de suppression de compte partenaire',
        "Un établissement demande la suppression de son compte depuis l'application.\n\n"
        . "Compte  : #$uid — $nom\n"
        . 'Adresse : ' . (string) $u['email'] . "\n\n"
        . "À traiter : vérifier l'abonnement et les soirées à venir avant de supprimer.\n"
    );
    apiReponse(['success' => true, 'supprime' => false, 'demande' => true]);
}

apiErreur("Ce type de compte ne peut pas être supprimé depuis l'application.", 403, 'type_non_supprimable');
