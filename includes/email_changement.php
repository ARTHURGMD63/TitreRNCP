<?php
/**
 * Changer l'adresse e-mail d'un compte, avec confirmation.
 *
 * Deux temps : la demande envoie un code à six chiffres à la NOUVELLE adresse,
 * et l'adresse du compte ne change qu'une fois ce code saisi. C'est ce qui
 * empêche de rattacher un compte à une adresse qu'on ne possède pas (faute
 * de frappe, ou adresse d'un tiers) et de se retrouver sans accès : le mot de
 * passe oublié part toujours vers l'adresse du compte.
 *
 * Mêmes règles que le code de réinitialisation du mot de passe (voir
 * includes/compte.php) : empreinte au lieu du code, cinq essais, quinze
 * minutes, un code par minute au plus.
 */

/** Durée de vie d'un code de confirmation, en minutes. */
const EMAIL_CHANGEMENT_VALIDITE_MIN = 15;

/** Saisies fausses tolérées avant que le code soit détruit. */
const EMAIL_CHANGEMENT_ESSAIS_MAX = 5;

/** Délai minimal entre deux codes pour le même compte, en secondes. */
const EMAIL_CHANGEMENT_DELAI_RENVOI_S = 60;

/** L'empreinte stockée à la place du code ; l'adresse visée y entre. */
function emailChangementEmpreinte(string $nouvelEmail, string $code): string
{
    return hash('sha256', mb_strtolower($nouvelEmail) . '|' . $code);
}

/**
 * Enregistre la demande et renvoie le code EN CLAIR (à envoyer par e-mail).
 *
 * Null si un code est déjà parti il y a moins d'une minute : sans ce délai,
 * redemander un code remettrait le compteur d'essais à zéro indéfiniment.
 */
function emailChangementDemander(PDO $pdo, int $userId, string $nouvelEmail): ?string
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM changements_email WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)'
    );
    $stmt->execute([$userId, EMAIL_CHANGEMENT_DELAI_RENVOI_S]);
    if ($stmt->fetchColumn()) {
        return null;
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $pdo->prepare('DELETE FROM changements_email WHERE user_id = ?')->execute([$userId]);
    $pdo->prepare(
        'INSERT INTO changements_email (user_id, nouvel_email, code_hash, expire_le)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ' . EMAIL_CHANGEMENT_VALIDITE_MIN . ' MINUTE))'
    )->execute([$userId, $nouvelEmail, emailChangementEmpreinte($nouvelEmail, $code)]);

    return $code;
}

/**
 * Vérifie le code ; s'il est juste, renvoie la nouvelle adresse (et consomme la demande).
 *
 * Une saisie fausse compte comme un essai ; au cinquième, ou une fois le code
 * expiré, la demande est détruite. Comparaison en temps constant.
 */
function emailChangementConfirmer(PDO $pdo, int $userId, string $code): ?string
{
    $stmt = $pdo->prepare(
        'SELECT nouvel_email, code_hash, essais, (expire_le > NOW()) AS valide FROM changements_email WHERE user_id = ?'
    );
    $stmt->execute([$userId]);
    $ligne = $stmt->fetch();

    if (!$ligne) {
        return null;
    }

    if (!$ligne['valide'] || (int) $ligne['essais'] >= EMAIL_CHANGEMENT_ESSAIS_MAX) {
        $pdo->prepare('DELETE FROM changements_email WHERE user_id = ?')->execute([$userId]);

        return null;
    }

    $email = (string) $ligne['nouvel_email'];
    if (hash_equals((string) $ligne['code_hash'], emailChangementEmpreinte($email, preg_replace('/\s+/', '', $code) ?? ''))) {
        $pdo->prepare('DELETE FROM changements_email WHERE user_id = ?')->execute([$userId]);

        return $email;
    }

    $pdo->prepare('UPDATE changements_email SET essais = essais + 1 WHERE user_id = ?')->execute([$userId]);
    if ((int) $ligne['essais'] + 1 >= EMAIL_CHANGEMENT_ESSAIS_MAX) {
        $pdo->prepare('DELETE FROM changements_email WHERE user_id = ?')->execute([$userId]);
    }

    return null;
}
