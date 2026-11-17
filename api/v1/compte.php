<?php
/**
 * POST /api/v1/compte.php — changer son mot de passe ou son adresse e-mail.
 *
 *   { action: "mot_de_passe", mot_de_passe: "<actuel>", nouveau: "<nouveau>" }
 *       Change le mot de passe. Les autres appareils connectés sont
 *       déconnectés : quelqu'un qui aurait eu accès au compte perd le sien.
 *       L'appareil qui fait la demande reste connecté.
 *
 *   { action: "email", mot_de_passe: "<actuel>", email: "<nouvelle adresse>" }
 *       Change l'adresse de connexion. L'ancienne adresse reçoit un avis.
 *       La confirmation par un lien envoyé à la nouvelle adresse viendra
 *       s'ajouter ici : pour l'instant, le mot de passe actuel fait office de
 *       preuve que c'est bien le titulaire qui demande.
 *
 * Dans les deux cas le mot de passe actuel est exigé, avec la même limite de
 * tentatives que la connexion : un jeton volé ne doit pas permettre de
 * prendre le compte de quelqu'un.
 *
 * Réponses : { success: true } ; 403 mot_de_passe (actuel faux) ; 409
 * email_pris ; 422 (valeur refusée) ; 429 trop_de_tentatives.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/mail.php';

apiExigerMethode('POST');

$u   = apiUtilisateur($pdo);
$uid = (int) $u['id'];

if (!in_array((string) $u['type'], ['etudiant', 'partenaire'], true)) {
    apiErreur('Ce type de compte ne se modifie pas depuis l\'application.', 403, 'type_non_modifiable');
}

$ip = ipClient();
if (isRateLimited($pdo, $ip, 5, 900)) {
    apiErreur('Trop de tentatives. Réessaie dans quinze minutes.', 429, 'trop_de_tentatives');
}

$corps  = apiCorps();
$action = (string) ($corps['action'] ?? '');
$actuel = is_string($corps['mot_de_passe'] ?? null) ? $corps['mot_de_passe'] : '';

$stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
$stmt->execute([$uid]);
$hash = (string) $stmt->fetchColumn();

if ($actuel === '' || $hash === '' || !password_verify($actuel, $hash)) {
    recordLoginAttempt($pdo, $ip, (string) $u['email']);
    apiErreur('Mot de passe actuel incorrect.', 403, 'mot_de_passe');
}

if ($action === 'mot_de_passe') {
    $nouveau = is_string($corps['nouveau'] ?? null) ? $corps['nouveau'] : '';
    $min     = longueurMinimaleMotDePasse((string) $u['type']);

    if (strlen($nouveau) < $min) {
        apiErreur("Le nouveau mot de passe doit faire au moins $min caractères.", 422, 'mot_de_passe_court');
    }
    if (hash_equals($actuel, $nouveau)) {
        apiErreur('Le nouveau mot de passe doit être différent de l\'actuel.', 422, 'mot_de_passe_identique');
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
            ->execute([password_hash($nouveau, PASSWORD_DEFAULT), $uid]);
        // Les autres appareils sont déconnectés ; celui-ci garde sa session.
        $pdo->prepare('DELETE FROM api_tokens WHERE user_id = ? AND id <> ?')
            ->execute([$uid, (int) $u['token_id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logErreur('Changement de mot de passe impossible', $e, ['utilisateur' => $uid]);
        apiErreur('Le mot de passe n\'a pas pu être changé. Réessaie dans un instant.', 500, 'changement');
    }

    apiReponse(['success' => true]);
}

if ($action === 'email') {
    $nouvelle = mb_strtolower(trim(is_string($corps['email'] ?? null) ? $corps['email'] : ''));
    $ancienne = (string) $u['email'];

    if (!filter_var($nouvelle, FILTER_VALIDATE_EMAIL) || mb_strlen($nouvelle) > 190) {
        apiErreur('Cette adresse e-mail n\'est pas valide.', 422, 'email_invalide');
    }
    if (mb_strtolower($ancienne) === $nouvelle) {
        apiErreur('C\'est déjà l\'adresse de ton compte.', 422, 'email_identique');
    }

    try {
        $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$nouvelle, $uid]);
    } catch (PDOException $e) {
        // Code MySQL 1062 (clé dupliquée) : l'adresse appartient à un autre compte.
        if (($e->errorInfo[1] ?? null) === 1062) {
            apiErreur('Cette adresse est déjà utilisée par un autre compte.', 409, 'email_pris');
        }
        logErreur('Changement d\'e-mail impossible', $e, ['utilisateur' => $uid]);
        apiErreur('L\'adresse n\'a pas pu être changée. Réessaie dans un instant.', 500, 'changement');
    }

    // Les codes de réinitialisation en cours visaient l'ancienne adresse.
    foreach (['password_resets', 'password_reset_codes'] as $table) {
        $pdo->prepare("DELETE FROM $table WHERE email = ?")->execute([$ancienne]);
    }

    // Avis à l'ancienne adresse : si ce n'était pas toi, c'est là qu'on le voit.
    try {
        envoyerEmail(
            $ancienne,
            'Outsee — L\'adresse de ton compte a changé',
            "Bonjour,\n\n"
            . "L'adresse e-mail de ton compte Outsee vient d'être remplacée par $nouvelle.\n\n"
            . "Si c'est bien toi, tu n'as rien à faire. Sinon, écris-nous tout de suite à contact@outsee.fr.\n\n"
            . "L'équipe Outsee"
        );
    } catch (Throwable $e) {
        logErreur('Avis de changement d\'e-mail non envoyé', $e, ['utilisateur' => $uid]);
    }

    apiReponse(['success' => true, 'email' => $nouvelle]);
}

apiErreur('Action inconnue.', 422, 'action_inconnue');
