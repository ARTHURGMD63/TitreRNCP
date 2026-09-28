<?php
/**
 * POST /api/v1/inscription.php — créer un compte depuis l'application.
 *
 * Mêmes règles, dans le même ordre et avec les mêmes messages, que
 * auth/register.php : champs obligatoires, mot de passe de six caractères,
 * majorité calculée depuis la date de naissance, conditions acceptées. Le
 * compte créé reçoit aussitôt un jeton, comme après une connexion — le site
 * ouvre la session dans la foulée, l'application fait de même.
 *
 * Corps JSON : type, prenom, nom, email, password, date_naissance (AAAA-MM-JJ),
 * ecole, promo, ville_etudiant (étudiant, école/promo réservées aux 18-25
 * ans) ; etablissement_nom, etablissement_type, ville (partenaire) ; cgu
 * (booléen) ; appareil (facultatif).
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/agregats.php';

apiExigerMethode('POST');

// Même garde que login.php, un seuil plus large : cet endpoint répond
// différemment selon qu'un e-mail existe déjà (409) ou non (201), ce qui en
// ferait sinon un outil d'énumération des comptes à débit illimité.
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
if (isRateLimited($pdo, $ip, 20, 900)) {
    apiErreur('Trop de tentatives. Réessaie dans quinze minutes.', 429, 'trop_de_tentatives');
}

$corps = apiCorps();
$texte = static fn (string $cle): string => trim(is_scalar($corps[$cle] ?? null) ? (string) $corps[$cle] : '');

$type       = in_array($corps['type'] ?? '', ['etudiant', 'partenaire'], true) ? (string) $corps['type'] : 'etudiant';
$prenom     = $texte('prenom');
$nom        = $texte('nom');
$email      = $texte('email');
$pass       = is_string($corps['password'] ?? null) ? $corps['password'] : '';
$ecole      = $texte('ecole');
$promo      = $texte('promo');
$etudiantVille = $texte('ville_etudiant') !== '' ? $texte('ville_etudiant') : null;
$etablNom   = $texte('etablissement_nom');
$etablType  = in_array($corps['etablissement_type'] ?? '', ['bar', 'boite', 'resto', 'afterwork'], true)
    ? (string) $corps['etablissement_type'] : 'bar';
$etablVille = $texte('ville') !== '' ? $texte('ville') : 'Clermont-Ferrand';
$naissance  = $texte('date_naissance');
$cgu        = !empty($corps['cgu']);
$interets   = $type === 'etudiant' ? filtrerInterets($corps['interets'] ?? []) : [];

$age = ageEnAnnees($naissance);

// École et promo ne concernent que les 18-25 ans : voir auth/register.php.
if ($type === 'etudiant' && ($age === null || $age > 25)) {
    $ecole = '';
    $promo = '';
}

$longueurMin = longueurMinimaleMotDePasse($type);

if (!$prenom || !$nom || !$email || !$pass) {
    apiErreur('Merci de remplir tous les champs obligatoires.', 422, 'champs_manquants');
} elseif (strlen($pass) < $longueurMin) {
    apiErreur("Le mot de passe doit faire au moins $longueurMin caractères.", 422, 'mot_de_passe_court');
} elseif ($age === null) {
    apiErreur('Merci d’indiquer une date de naissance valide.', 422, 'naissance');
} elseif ($age < 18) {
    apiErreur('Linkee est réservée aux personnes majeures : l’inscription n’est pas possible avant 18 ans.', 422, 'mineur');
} elseif ($age > 120) {
    apiErreur('Cette date de naissance ne semble pas correcte.', 422, 'naissance');
} elseif (!$cgu) {
    apiErreur('Merci d’accepter les conditions générales pour continuer.', 422, 'cgu');
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO users (nom, prenom, email, password, ecole, promo, ville,
                            date_naissance, cgu_acceptees_le, type, interests)
         VALUES (?,?,?,?,?,?,?,?,NOW(),?,?)"
    );
    $stmt->execute([$nom, $prenom, $email, password_hash($pass, PASSWORD_DEFAULT),
                    $ecole ?: null, $promo ?: null, $type === 'etudiant' ? $etudiantVille : null,
                    $naissance, $type, interetsVersTexte($interets)]);
    $userId = (int) $pdo->lastInsertId();

    synchroniserInterets($pdo, $userId, $interets);
    oublierEcolesRepresentees();

    if ($type === 'partenaire' && $etablNom) {
        $pdo->prepare('INSERT INTO etablissements (user_id, nom, type, ville) VALUES (?,?,?,?)')
            ->execute([$userId, $etablNom, $etablType, $etablVille]);
    }
} catch (PDOException $e) {
    // Code MySQL 1062 (clé dupliquée), et non le texte du message : sur un
    // serveur dont la langue des erreurs n'est pas l'anglais (le nôtre en
    // développement dit « Duplicata... », pas « Duplicate... »), chercher
    // "Duplicate" ne matchait jamais — chaque email déjà pris tombait dans
    // l'erreur générique 500 au lieu du message dédié.
    if (($e->errorInfo[1] ?? null) === 1062) {
        recordLoginAttempt($pdo, $ip, $email);
        apiErreur('Cet email est déjà utilisé.', 409, 'email_pris');
    }
    apiErreur('Erreur lors de la création du compte.', 500, 'creation');
}

$stmt = $pdo->prepare('SELECT id, nom, prenom, email, ecole, promo, ville, photo, interests, type, compte_prive FROM users WHERE id = ?');
$stmt->execute([$userId]);
$u = $stmt->fetch();

$jeton = apiCreerJeton($pdo, $userId, isset($corps['appareil']) ? (string) $corps['appareil'] : null);

apiReponse([
    'success'     => true,
    'token'       => $jeton['token'],
    'expire_le'   => $jeton['expire_le'],
    'utilisateur' => apiProfil($u),
], 201);
