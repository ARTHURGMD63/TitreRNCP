<?php
/**
 * Cycle de vie d'un compte : récupérer l'accès, et s'en aller.
 *
 * Deux sujets que l'application mobile doit pouvoir mener seule, sans page du
 * site :
 *
 *  - le code de réinitialisation du mot de passe, saisi dans l'application ;
 *  - la suppression d'un compte étudiant, avec effacement des données
 *    personnelles — une obligation du RGPD (droit à l'effacement) et une
 *    condition de publication sur les boutiques d'applications.
 *
 * Les deux écrivent en base ; les points d'API qui les appellent
 * (api/v1/mot_de_passe_*.php, api/v1/supprimer_compte.php) ne font que lire la
 * requête, appeler ici, et répondre.
 */

require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/uploads.php';
require_once __DIR__ . '/temps_reel.php';

// ─── Code de réinitialisation du mot de passe ───────────────────────────────

/** Durée de vie d'un code, en minutes. */
const CODE_REINIT_VALIDITE_MIN = 15;

/** Saisies fausses tolérées avant que le code soit détruit. */
const CODE_REINIT_ESSAIS_MAX = 5;

/** Délai minimal entre deux codes pour la même adresse, en secondes. */
const CODE_REINIT_DELAI_RENVOI_S = 60;

/**
 * Un code à six chiffres, zéros initiaux compris.
 *
 * random_int() et non rand() : c'est le générateur cryptographique du
 * système. Un million de combinaisons ne tiennent que parce que le nombre
 * d'essais est borné (voir codeReinitialisationVerifier()).
 */
function codeReinitialisationGenerer(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * L'empreinte stockée à la place du code.
 *
 * L'adresse entre dans le calcul : deux comptes qui tireraient le même code
 * n'ont pas la même empreinte.
 */
function codeReinitialisationEmpreinte(string $email, string $code): string
{
    return hash('sha256', mb_strtolower($email) . '|' . $code);
}

/**
 * Le compte qui porte cette adresse, s'il peut encore se connecter.
 *
 * Un compte supprimé n'en est pas un : son adresse est factice et son mot de
 * passe inutilisable, mais on ne lui envoie rien pour autant.
 *
 * @return array{id:int,email:string,type:string}|null
 */
function compteParEmail(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare("SELECT id, email, type FROM users WHERE email = ? AND type <> 'supprime'");
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    return $u ? ['id' => (int) $u['id'], 'email' => (string) $u['email'], 'type' => (string) $u['type']] : null;
}

/**
 * Crée un code pour cette adresse et le renvoie EN CLAIR (à envoyer par e-mail).
 *
 * Null — sans rien dire à l'appelant d'utile à un tiers — si l'adresse ne
 * correspond à aucun compte, ou si un code est parti il y a moins d'une
 * minute. Ce second cas est ce qui borne la devinette : chaque nouveau code
 * remet le compteur d'essais à zéro, donc sans ce délai on pourrait en
 * redemander indéfiniment et épuiser cinq essais à chaque fois.
 */
function codeReinitialisationCreer(PDO $pdo, string $email): ?string
{
    $compte = compteParEmail($pdo, $email);
    if ($compte === null) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT 1 FROM password_reset_codes WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)'
    );
    $stmt->execute([$compte['email'], CODE_REINIT_DELAI_RENVOI_S]);
    if ($stmt->fetchColumn()) {
        return null;
    }

    $code = codeReinitialisationGenerer();

    $pdo->prepare('DELETE FROM password_reset_codes WHERE email = ?')->execute([$compte['email']]);
    $pdo->prepare(
        'INSERT INTO password_reset_codes (email, code_hash, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ' . CODE_REINIT_VALIDITE_MIN . ' MINUTE))'
    )->execute([$compte['email'], codeReinitialisationEmpreinte($compte['email'], $code)]);

    return $code;
}

/**
 * Le code est-il le bon ?
 *
 * Une saisie fausse compte comme un essai ; au cinquième, le code est
 * détruit et il faut en redemander un. Un code expiré est détruit à la
 * première lecture. La comparaison est en temps constant.
 *
 * Un code juste N'EST PAS consommé ici : si le nouveau mot de passe est
 * refusé (trop court), la personne ne doit pas devoir tout recommencer. C'est
 * codeReinitialisationAppliquer() qui le consomme.
 */
function codeReinitialisationVerifier(PDO $pdo, string $email, string $code): bool
{
    $stmt = $pdo->prepare(
        'SELECT code_hash, essais, (expires_at > NOW()) AS valide FROM password_reset_codes WHERE email = ?'
    );
    $stmt->execute([$email]);
    $ligne = $stmt->fetch();

    if (!$ligne) {
        return false;
    }

    if (!$ligne['valide'] || (int) $ligne['essais'] >= CODE_REINIT_ESSAIS_MAX) {
        $pdo->prepare('DELETE FROM password_reset_codes WHERE email = ?')->execute([$email]);
        return false;
    }

    if (hash_equals((string) $ligne['code_hash'], codeReinitialisationEmpreinte($email, $code))) {
        return true;
    }

    $pdo->prepare('UPDATE password_reset_codes SET essais = essais + 1 WHERE email = ?')->execute([$email]);
    if ((int) $ligne['essais'] + 1 >= CODE_REINIT_ESSAIS_MAX) {
        $pdo->prepare('DELETE FROM password_reset_codes WHERE email = ?')->execute([$email]);
    }

    return false;
}

/**
 * Pose le nouveau mot de passe et referme toutes les portes ouvertes.
 *
 * Le code est consommé, les liens de réinitialisation du site encore valables
 * pour cette adresse aussi, et surtout les jetons de l'application sont
 * révoqués : si quelqu'un d'autre tenait la session, c'est précisément ce que
 * l'on cherche à couper en changeant de mot de passe.
 */
function codeReinitialisationAppliquer(PDO $pdo, int $userId, string $email, string $motDePasse): void
{
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
            ->execute([password_hash($motDePasse, PASSWORD_DEFAULT), $userId]);
        $pdo->prepare('DELETE FROM password_reset_codes WHERE email = ?')->execute([$email]);
        $pdo->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$email]);
        $pdo->prepare('DELETE FROM api_tokens WHERE user_id = ?')->execute([$userId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Envoie le code par e-mail.
 *
 * En local rien ne part, comme pour le lien du site : l'appelant affiche le
 * code lui-même (« Mode dev »), ce qui permet de tester le parcours sans
 * serveur de messagerie.
 */
function codeReinitialisationEnvoyer(string $email, string $code): bool
{
    if (estEnLocal()) {
        return true;
    }

    return envoyerEmail(
        $email,
        'Linkee — Ton code de réinitialisation',
        "Bonjour,\n\n"
        . "Voici ton code pour choisir un nouveau mot de passe :\n\n"
        . "    $code\n\n"
        . 'Il est valable ' . CODE_REINIT_VALIDITE_MIN . " minutes. Saisis-le dans l'application Linkee.\n\n"
        . "Si tu n'as pas fait cette demande, ignore cet e-mail : ton mot de passe reste inchangé "
        . "et personne ne peut le modifier sans ce code.\n\n"
        . "L'équipe Linkee"
    );
}

// ─── Suppression d'un compte étudiant ───────────────────────────────────────

/** Nom et prénom d'un compte supprimé, tels qu'ils apparaissent dans les anciennes listes. */
const COMPTE_SUPPRIME_PRENOM = 'Compte';
const COMPTE_SUPPRIME_NOM    = 'supprimé';

/** L'adresse factice d'un compte supprimé : unique, et d'un domaine qui ne peut pas exister. */
function compteAdresseSupprimee(int $userId): string
{
    return 'supprime-' . $userId . '@linkee.invalid';
}

/**
 * Supprime un compte étudiant : efface ses données personnelles, garde sa trace
 * anonyme.
 *
 * Effacé : tout ce qui l'identifie ou le décrit — nom, e-mail, photo, ville,
 * date de naissance, centres d'intérêt —, ses abonnements et abonnés, ses
 * blocages et signalements, ses avis, ses photos de soirée, ses squads, ses
 * badges et économies, ses invitations, ses jetons de connexion, son
 * inscription éventuelle à la liste d'attente. Ses pass à venir sont annulés,
 * ce qui libère les places.
 *
 * Conservé : la ligne `users`, vidée de toute identité et passée au type
 * « supprime », avec son école et sa promotion, et ses inscriptions passées.
 * Elles alimentent les statistiques des partenaires (présences, taux de
 * remplissage, répartition par école) et ne désignent plus personne : un
 * `DELETE` les ferait disparaître des chiffres déjà communiqués. C'est la
 * règle du registre RGPD (docs/RGPD_Registre_Traitements.md, § 4, point 2),
 * et ce que la politique de confidentialité annonce.
 *
 * Tout se fait dans une transaction : un compte à moitié supprimé serait pire
 * que ni l'un ni l'autre. Les fichiers (photos) ne sont effacés du disque
 * qu'une fois la transaction validée.
 *
 * @return array{avatar: string|null, photos: list<string>, evenements: list<int>}
 *         les fichiers à retirer du disque, et les événements dont les places ont bougé
 */
function compteSupprimerEtudiant(PDO $pdo, int $uid): array
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT email, photo FROM users WHERE id = ? AND type = 'etudiant' FOR UPDATE");
        $stmt->execute([$uid]);
        $compte = $stmt->fetch();
        if (!$compte) {
            throw new RuntimeException("Aucun compte étudiant n'a l'identifiant $uid.");
        }
        $email = (string) $compte['email'];

        $stmt = $pdo->prepare('SELECT fichier FROM evenement_photos WHERE user_id = ?');
        $stmt->execute([$uid]);
        $photos = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        // Les événements dont une place va se libérer : à signaler au temps réel.
        $stmt = $pdo->prepare(
            "SELECT evenement_id FROM inscriptions
              WHERE user_id = ? AND statut = 'inscrit'
                AND evenement_id IN (SELECT id FROM evenements WHERE date_heure > NOW())"
        );
        $stmt->execute([$uid]);
        $evenements = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $pdo->prepare(
            "UPDATE inscriptions SET statut = 'annule'
              WHERE user_id = ? AND statut = 'inscrit'
                AND evenement_id IN (SELECT id FROM evenements WHERE date_heure > NOW())"
        )->execute([$uid]);

        // Ce qui appartient à la personne : rien n'a de raison de lui survivre.
        $parUtilisateur = [
            'DELETE FROM evenement_photo_signalements WHERE reporter_id = ?',
            'DELETE FROM evenement_photos WHERE user_id = ?',
            'DELETE FROM api_tokens WHERE user_id = ?',
            'DELETE FROM follows_etablissements WHERE user_id = ?',
            'DELETE FROM user_badges WHERE user_id = ?',
            'DELETE FROM user_interets WHERE user_id = ?',
            'DELETE FROM user_settings WHERE user_id = ?',
            'DELETE FROM notifications_lues WHERE user_id = ?',
            'DELETE FROM economies WHERE user_id = ?',
            'DELETE FROM avis WHERE user_id = ?',
            'DELETE FROM squad_membres WHERE user_id = ?',
            'DELETE FROM squads WHERE createur_id = ?',
            'UPDATE liste_attente SET parrain_id = NULL WHERE parrain_id = ?',
        ];
        foreach ($parUtilisateur as $sql) {
            $pdo->prepare($sql)->execute([$uid]);
        }

        // Les relations avec les autres : dans les deux sens.
        $deuxSens = [
            'DELETE FROM follows_users WHERE follower_id = ? OR followed_id = ?',
            'DELETE FROM user_blocks WHERE blocker_id = ? OR blocked_id = ?',
            'DELETE FROM user_reports WHERE reporter_id = ? OR reported_id = ?',
            'DELETE FROM invitations WHERE from_user_id = ? OR to_user_id = ?',
        ];
        foreach ($deuxSens as $sql) {
            $pdo->prepare($sql)->execute([$uid, $uid]);
        }

        // Ce qui est indexé par l'adresse, pas par l'identifiant.
        foreach ([
            'DELETE FROM liste_attente WHERE email = ?',
            'DELETE FROM login_attempts WHERE email = ?',
            'DELETE FROM password_resets WHERE email = ?',
            'DELETE FROM password_reset_codes WHERE email = ?',
        ] as $sql) {
            $pdo->prepare($sql)->execute([$email]);
        }

        // La ligne elle-même : vidée, jamais supprimée. Le mot de passe est un
        // hachage d'une valeur aléatoire que personne ne connaît : le
        // compte ne peut plus s'ouvrir, même si l'adresse factice était saisie.
        $pdo->prepare(
            "UPDATE users
                SET prenom = ?, nom = ?, email = ?, password = ?,
                    ville = NULL, photo = NULL,
                    date_naissance = NULL, cgu_acceptees_le = NULL, interests = NULL,
                    compte_prive = 1, type = 'supprime'
              WHERE id = ?"
        )->execute([
            COMPTE_SUPPRIME_PRENOM,
            COMPTE_SUPPRIME_NOM,
            compteAdresseSupprimee($uid),
            password_hash(bin2hex(random_bytes(19)), PASSWORD_DEFAULT),
            $uid,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'avatar'     => !empty($compte['photo']) ? (string) $compte['photo'] : null,
        'photos'     => $photos,
        'evenements' => $evenements,
    ];
}

/**
 * Après la validation de la transaction : fichiers et temps réel.
 *
 * Séparé de compteSupprimerEtudiant() parce que ni l'un ni l'autre ne doit
 * pouvoir annuler la suppression : un fichier qui ne s'efface pas est un
 * déchet, pas une raison de laisser les données personnelles en base.
 *
 * @param array{avatar: string|null, photos: list<string>, evenements: list<int>} $restes
 */
function compteNettoyerApres(PDO $pdo, array $restes): void
{
    if ($restes['avatar'] !== null) {
        deleteStoredImage($restes['avatar'], avatarDir());
    }
    foreach ($restes['photos'] as $fichier) {
        deleteStoredImage($fichier, evenementPhotoDir());
    }
    foreach (array_unique($restes['evenements']) as $evenementId) {
        // Une place vient de se libérer : à signaler à ceux qui attendaient.
        fluxToucher($pdo, canalEvenement($evenementId));
    }
}
