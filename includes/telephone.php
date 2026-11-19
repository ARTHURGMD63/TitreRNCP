<?php
/**
 * Numéro de téléphone des étudiants (migration v43).
 *
 * Demandé à l'inscription dans l'application et vérifié par un code SMS de
 * six chiffres, avant même la création du compte : un numéro, un compte.
 * C'est ce qui freine les comptes jetables (réserver tous les terrains,
 * gonfler un classement), qu'une adresse e-mail ne freine pas.
 *
 * Tant que l'envoi de SMS n'est pas configuré, le numéro est seulement
 * saisi, sans code (voir telephoneVerificationRequise()).
 *
 * Mêmes règles que les codes e-mail (includes/email_changement.php) :
 * empreinte au lieu du code, cinq essais, un code par minute au plus. Le
 * code ne vit que dix minutes.
 *
 * Seuls les mobiles français (06, 07) sont acceptés : Outsee n'est ouvert
 * qu'à Clermont-Ferrand, et refuser les numéros étrangers coupe court à la
 * fraude qui fait envoyer des SMS vers des numéros surtaxés à l'étranger.
 *
 * Le numéro est confié à l'établissement qui organise un événement auquel
 * l'étudiant est inscrit, et seulement jusqu'à 30 jours après la fin de cet
 * événement (voir telephoneVisiblePourEtablissement() et les textes légaux).
 */

require_once __DIR__ . '/sms.php';

/** Durée de vie d'un code SMS, en minutes. */
const TELEPHONE_CODE_VALIDITE_MIN = 10;

/** Saisies fausses tolérées avant que le code soit détruit. */
const TELEPHONE_CODE_ESSAIS_MAX = 5;

/** Délai minimal entre deux codes pour le même numéro, en secondes. */
const TELEPHONE_CODE_DELAI_RENVOI_S = 60;

/** Combien de jours après la fin d'un événement l'établissement voit encore les numéros. */
const TELEPHONE_VISIBILITE_JOURS = 30;

/**
 * Ramène une saisie au format international, ou null si ce n'est pas un
 * mobile français.
 *
 * Acceptés : « 06 12 34 56 78 », « 06.12.34.56.78 », « +33 6 12 34 56 78 »,
 * « 0033612345678 », « +33 (0)6 12 34 56 78 ».
 */
function telephoneNormaliser(string $saisie): ?string
{
    $s = preg_replace('/[\s.\-()]/u', '', trim($saisie)) ?? '';
    if ($s === '') {
        return null;
    }

    if (str_starts_with($s, '+33')) {
        $national = substr($s, 3);
    } elseif (str_starts_with($s, '0033')) {
        $national = substr($s, 4);
    } elseif (str_starts_with($s, '0')) {
        $national = substr($s, 1);
    } else {
        return null;
    }
    // « +33 (0)6… » : le zéro entre parenthèses a perdu ses parenthèses.
    if (strlen($national) === 10 && $national[0] === '0') {
        $national = substr($national, 1);
    }

    return preg_match('/^[67]\d{8}$/', $national) === 1 ? '+33' . $national : null;
}

/** « +33612345678 » devient « 06 12 34 56 78 ». */
function telephoneAffiche(string $international): string
{
    if (!preg_match('/^\+33(\d{9})$/', $international, $m)) {
        return $international;
    }

    return trim((string) preg_replace('/(\d{2})/', '$1 ', '0' . $m[1]));
}

/**
 * Faut-il un code SMS pour enregistrer un numéro ?
 *
 * Seulement une fois l'envoi configuré (réglages OVH_SMS_*). D'ici là, en
 * production comme en local, le numéro est enregistré tel que saisi, sans
 * code : l'inscription ne doit pas attendre l'ouverture du compte OVH.
 * Pour essayer le parcours avec code en local, renseigner les réglages.
 */
function telephoneVerificationRequise(): bool
{
    return smsActif();
}

/** L'empreinte stockée à la place du code ; le numéro y entre. */
function telephoneCodeEmpreinte(string $telephone, string $code): string
{
    return hash('sha256', $telephone . '|' . $code);
}

/**
 * Enregistre un nouveau code pour ce numéro et le renvoie EN CLAIR (à
 * envoyer par SMS). Null si un code est parti il y a moins d'une minute :
 * sans ce délai, redemander un code remettrait les essais à zéro sans fin.
 */
function telephoneCodeDemander(PDO $pdo, string $telephone): ?string
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM verifications_telephone WHERE telephone = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)'
    );
    $stmt->execute([$telephone, TELEPHONE_CODE_DELAI_RENVOI_S]);
    if ($stmt->fetchColumn()) {
        return null;
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $pdo->prepare('DELETE FROM verifications_telephone WHERE telephone = ?')->execute([$telephone]);
    $pdo->prepare(
        'INSERT INTO verifications_telephone (telephone, code_hash, expire_le)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ' . TELEPHONE_CODE_VALIDITE_MIN . ' MINUTE))'
    )->execute([$telephone, telephoneCodeEmpreinte($telephone, $code)]);

    return $code;
}

/**
 * Le code saisi est-il le bon ? Ne consomme pas la demande : l'inscription
 * peut encore échouer après (adresse déjà prise), et l'étudiant ne doit pas
 * redemander un SMS pour cela. Voir telephoneCodeConsommer().
 *
 * Une saisie fausse compte comme un essai ; au cinquième, ou une fois le
 * code expiré, la demande est détruite. Comparaison en temps constant.
 */
function telephoneCodeVerifier(PDO $pdo, string $telephone, string $code): bool
{
    $code = preg_replace('/\D/', '', $code) ?? '';

    $stmt = $pdo->prepare(
        'SELECT code_hash, essais, (expire_le > NOW()) AS valide FROM verifications_telephone WHERE telephone = ?'
    );
    $stmt->execute([$telephone]);
    $ligne = $stmt->fetch();
    if (!$ligne) {
        return false;
    }

    if (!$ligne['valide'] || (int) $ligne['essais'] >= TELEPHONE_CODE_ESSAIS_MAX) {
        telephoneCodeConsommer($pdo, $telephone);

        return false;
    }

    if ($code !== '' && hash_equals((string) $ligne['code_hash'], telephoneCodeEmpreinte($telephone, $code))) {
        return true;
    }

    // L'incrément est conditionné en SQL : deux saisies simultanées ne
    // peuvent pas dépasser le plafond ensemble.
    $pdo->prepare('UPDATE verifications_telephone SET essais = essais + 1 WHERE telephone = ? AND essais < ?')
        ->execute([$telephone, TELEPHONE_CODE_ESSAIS_MAX]);

    return false;
}

/** Le code a servi : il ne doit plus pouvoir resservir. */
function telephoneCodeConsommer(PDO $pdo, string $telephone): void
{
    $pdo->prepare('DELETE FROM verifications_telephone WHERE telephone = ?')->execute([$telephone]);
}

/** Ce numéro appartient-il déjà à un compte (autre que $sauf) ? */
function telephonePris(PDO $pdo, string $telephone, ?int $sauf = null): bool
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE telephone = ? AND id <> ?');
    $stmt->execute([$telephone, $sauf ?? 0]);

    return (bool) $stmt->fetchColumn();
}

/** Le texte du SMS : court, sans lien, sans accent hors alphabet GSM. */
function telephoneMessageCode(string $code): string
{
    return "Outsee : ton code de vérification est $code. Il expire dans "
        . TELEPHONE_CODE_VALIDITE_MIN . ' minutes. Ne le communique à personne.';
}

/**
 * Crée un code et l'envoie.
 *
 * @return array{statut: 'envoye'|'recent'|'echec', code_dev?: string}
 *   « recent » : un code est parti il y a moins d'une minute ;
 *   « echec » : OVH a refusé l'envoi (la demande est alors annulée).
 *   `code_dev` n'est rempli qu'en développement.
 */
function telephoneEnvoyerCode(PDO $pdo, string $telephone): array
{
    $code = telephoneCodeDemander($pdo, $telephone);
    if ($code === null) {
        return ['statut' => 'recent'];
    }

    if (!smsEnvoyer($telephone, telephoneMessageCode($code))) {
        telephoneCodeConsommer($pdo, $telephone);

        return ['statut' => 'echec'];
    }

    $resultat = ['statut' => 'envoye'];
    if (estEnDeveloppement()) {
        $resultat['code_dev'] = $code;
    }

    return $resultat;
}

/**
 * L'établissement voit-il encore les numéros des inscrits de cet événement ?
 * Oui jusqu'à TELEPHONE_VISIBILITE_JOURS jours après sa fin (ou son début,
 * s'il n'a pas d'heure de fin).
 */
function telephoneVisiblePourEtablissement(?string $dateFin, string $dateDebut, ?int $maintenant = null): bool
{
    $fin = strtotime((string) ($dateFin ?: $dateDebut));
    if ($fin === false) {
        return false;
    }

    return ($maintenant ?? time()) <= $fin + TELEPHONE_VISIBILITE_JOURS * 86400;
}
