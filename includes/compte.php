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
    // L'essai est consommé AVANT la comparaison, par une seule écriture
    // conditionnelle. Lire le compteur, comparer, puis l'incrémenter laissait
    // N requêtes simultanées lire toutes « 0 essai » : des centaines d'essais
    // par code au lieu de cinq.
    $essai = $pdo->prepare(
        'UPDATE password_reset_codes SET essais = essais + 1
          WHERE email = ? AND essais < ? AND expires_at > NOW()'
    );
    $essai->execute([$email, CODE_REINIT_ESSAIS_MAX]);
    if ($essai->rowCount() !== 1) {
        // Code absent, expiré ou épuisé : il est détruit.
        $pdo->prepare('DELETE FROM password_reset_codes WHERE email = ?')->execute([$email]);
        return false;
    }

    $stmt = $pdo->prepare('SELECT code_hash, essais FROM password_reset_codes WHERE email = ?');
    $stmt->execute([$email]);
    $ligne = $stmt->fetch();
    if (!$ligne) {
        return false;
    }

    if (hash_equals((string) $ligne['code_hash'], codeReinitialisationEmpreinte($email, $code))) {
        return true;
    }

    if ((int) $ligne['essais'] >= CODE_REINIT_ESSAIS_MAX) {
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
        'Outsee — Ton code de réinitialisation',
        "Bonjour,\n\n"
        . "Voici ton code pour choisir un nouveau mot de passe :\n\n"
        . "    $code\n\n"
        . 'Il est valable ' . CODE_REINIT_VALIDITE_MIN . " minutes. Saisis-le dans l'application Outsee.\n\n"
        . "Si tu n'as pas fait cette demande, ignore cet e-mail : ton mot de passe reste inchangé "
        . "et personne ne peut le modifier sans ce code.\n\n"
        . "L'équipe Outsee"
    );
}

// ─── Suppression d'un compte étudiant ───────────────────────────────────────

/** Nom et prénom d'un compte supprimé, tels qu'ils apparaissent dans les anciennes listes. */
const COMPTE_SUPPRIME_PRENOM = 'Compte';
const COMPTE_SUPPRIME_NOM    = 'supprimé';

/** L'adresse factice d'un compte supprimé : unique, et d'un domaine qui ne peut pas exister. */
function compteAdresseSupprimee(int $userId): string
{
    return 'supprime-' . $userId . '@outsee.invalid';
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
            'DELETE FROM appareils_push WHERE user_id = ?',
            'DELETE FROM preferences_push WHERE user_id = ?',
            'DELETE FROM push_envois WHERE user_id = ?',
            'DELETE FROM notifications_lues WHERE user_id = ?',
            'DELETE FROM economies WHERE user_id = ?',
            'DELETE FROM avis WHERE user_id = ?',
            'DELETE FROM squad_membres WHERE user_id = ?',
            'DELETE FROM squads WHERE createur_id = ?',
            'UPDATE liste_attente SET parrain_id = NULL WHERE parrain_id = ?',
            // Oubliés jusqu'ici : la ligne users n'étant jamais supprimée,
            // aucune cascade ne joue pour eux (voir le test RGPD).
            'DELETE FROM changements_email WHERE user_id = ?',
            'DELETE FROM totp_comptes WHERE user_id = ?',
            'DELETE FROM culture_inscriptions WHERE user_id = ?',
        ];
        foreach ($parUtilisateur as $sql) {
            $pdo->prepare($sql)->execute([$uid]);
        }

        // Les terrains : ceux qu'il a réservés pour l'avenir redeviennent
        // libres (ses invités compris), et il quitte les parties à venir.
        // Les parties passées gardent leur créneau, sans organisateur.
        $maintenant = date('Y-m-d H:i:s');
        $pdo->prepare(
            'DELETE j FROM creneau_joueurs j JOIN creneaux c ON c.id = j.creneau_id
              WHERE c.organisateur_id = ? AND c.debut > ?'
        )->execute([$uid, $maintenant]);
        $pdo->prepare(
            "UPDATE creneaux SET statut = 'libre', organisateur_id = NULL, ouvert = 0, niveau = 'tous', reserve_le = NULL
              WHERE organisateur_id = ? AND debut > ?"
        )->execute([$uid, $maintenant]);
        $pdo->prepare('UPDATE creneaux SET organisateur_id = NULL WHERE organisateur_id = ?')->execute([$uid]);
        $pdo->prepare('DELETE FROM creneau_joueurs WHERE user_id = ?')->execute([$uid]);
        $pdo->prepare('UPDATE creneau_joueurs SET invite_par = NULL WHERE invite_par = ?')->execute([$uid]);

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

// ─── Suppression d'un compte établissement ─────────────────────────────────

/**
 * Supprime un compte établissement, tout de suite (Apple 5.1.1(v) : la
 * suppression se fait depuis l'application, pas par une demande à traiter).
 *
 * Dans l'ordre :
 *   1. l'abonnement Stripe est résilié immédiatement : plus aucun
 *      prélèvement ne part. S'il ne peut pas l'être (Stripe injoignable, ou
 *      clé absente alors qu'un abonnement existe), rien n'est supprimé :
 *      effacer le compte en laissant courir les prélèvements serait pire ;
 *   2. dans une transaction : l'établissement est supprimé, et avec lui ses
 *      soirées (passées et à venir), les inscriptions des étudiants à ces
 *      soirées, ses photos, ses terrains et créneaux, ses abonnés et son
 *      registre d'achats ; la ligne `users` est vidée de toute identité et
 *      passe au type « supprime », comme pour un étudiant ;
 *   3. conservé, sans coordonnées : la fiche client (crm_clients, passée à
 *      « perdu ») et le registre financier. Ce sont des pièces comptables,
 *      que l'entreprise doit garder ; elles ne désignent plus le compte.
 *
 * Les étudiants qui tenaient un pass pour une soirée à venir de
 * l'établissement sont prévenus après coup (compteNettoyerApresPartenaire()) :
 * la soirée disparaît de leur portefeuille avec le compte, et sans message ils
 * se présentaient à une soirée qui n'existait plus (DB-01, PAY-10). La
 * suppression elle-même reste immédiate et complète : c'est le droit de
 * l'établissement, et une obligation des stores.
 *
 * @return array{avatar: string|null, photos: list<string>, photos_lieu: list<string>, evenements: list<int>, annulations: list<array{user_id: int, evenement_id: int, titre: string}>}
 * @throws RuntimeException quand l'abonnement ne peut pas être résilié
 */
function compteSupprimerPartenaire(PDO $pdo, int $uid): array
{
    require_once __DIR__ . '/stripe.php';

    $stmt = $pdo->prepare("SELECT id, email, photo FROM users WHERE id = ? AND type = 'partenaire'");
    $stmt->execute([$uid]);
    $compte = $stmt->fetch();
    if (!$compte) {
        throw new RuntimeException("Aucun compte partenaire n'a l'identifiant $uid.");
    }
    $stmt = $pdo->prepare('SELECT * FROM etablissements WHERE user_id = ?');
    $stmt->execute([$uid]);
    $etab   = $stmt->fetch() ?: null;
    $client = $etab ? abonnementEtablissement($pdo, (int) $etab['id']) : null;

    // 0. Les achats du mois pas encore facturés partent sur la prochaine
    //    facture Stripe tant que le client existe : sinon la suppression les
    //    effaçait, et ils n'étaient jamais facturés.
    if ($etab && stripeActif() && !empty($client['stripe_customer_id'])) {
        try {
            stripeSynchroniserAchats($pdo, (int) $etab['id']);
        } catch (Throwable $e) {
            logErreur('Suppression partenaire : achats non transmis à Stripe', $e, ['etablissement' => $etab['id']]);
        }
    }

    // 1. Plus aucun prélèvement : l'abonnement s'arrête avant tout le reste.
    if (stripeAbonnementVivant($client)) {
        if (!stripeActif()) {
            throw new RuntimeException('Abonnement Stripe en cours, mais Stripe n’est pas configuré : résiliation impossible.');
        }
        // invoice_now : les lignes en attente (achats du mois) sont facturées
        // à la résiliation au lieu d'être abandonnées.
        stripeRequete('DELETE', 'subscriptions/' . rawurlencode((string) $client['stripe_subscription_id']), ['invoice_now' => true]);
    }

    $email      = (string) $compte['email'];
    $photos     = [];
    $photosLieu = [];
    $evenements = [];
    $annulations = [];

    $pdo->beginTransaction();
    try {
        $pdo->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE")->execute([$uid]);

        if ($etab) {
            $etabId = (int) $etab['id'];

            $stmt = $pdo->prepare('SELECT p.fichier FROM evenement_photos p JOIN evenements e ON e.id = p.evenement_id WHERE e.etablissement_id = ?');
            $stmt->execute([$etabId]);
            $photos = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            $stmt = $pdo->prepare('SELECT fichier FROM etablissement_photos WHERE etablissement_id = ?');
            $stmt->execute([$etabId]);
            $photosLieu = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            $stmt = $pdo->prepare('SELECT id FROM evenements WHERE etablissement_id = ? AND date_heure > NOW()');
            $stmt->execute([$etabId]);
            $evenements = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            // Qui prévenir : les pass encore valables pour ces soirées.
            $stmt = $pdo->prepare(
                "SELECT i.user_id, e.id AS evenement_id, e.titre
                   FROM inscriptions i JOIN evenements e ON e.id = i.evenement_id
                  WHERE e.etablissement_id = ? AND e.date_heure > NOW() AND i.statut = 'inscrit'"
            );
            $stmt->execute([$etabId]);
            $annulations = array_map(
                static fn (array $l): array => [
                    'user_id'      => (int) $l['user_id'],
                    'evenement_id' => (int) $l['evenement_id'],
                    'titre'        => (string) $l['titre'],
                ],
                $stmt->fetchAll(PDO::FETCH_ASSOC)
            );

            // La fiche client reste, sans coordonnées : pièce comptable.
            $pdo->prepare(
                "UPDATE crm_clients
                    SET offre = 'aucune', mrr = 0, statut = 'perdu', perdu_le = CURDATE(),
                        motif_perte = 'Compte supprimé par l’établissement',
                        contact_nom = NULL, contact_email = NULL, contact_tel = NULL,
                        stripe_statut = IF(stripe_subscription_id IS NULL, stripe_statut, 'canceled')
                  WHERE etablissement_id = ?"
            )->execute([$etabId]);

            // Les clés étrangères font le reste : soirées, inscriptions,
            // photos, terrains, créneaux, abonnés.
            // Pièces comptables : détachées avant la suppression, pour ne
            // jamais dépendre de la règle de la clé étrangère (TiDB ne la
            // modifie pas, voir db_migrations_v40.sql).
            $pdo->prepare('UPDATE achats_partenaires SET etablissement_id = NULL WHERE etablissement_id = ?')->execute([$etabId]);
            $pdo->prepare('UPDATE paiements_evenements SET etablissement_id = NULL WHERE etablissement_id = ?')->execute([$etabId]);
            $pdo->prepare('DELETE FROM etablissements WHERE id = ?')->execute([$etabId]);
        }

        foreach ([
            'DELETE FROM api_tokens WHERE user_id = ?',
            'DELETE FROM user_settings WHERE user_id = ?',
            'DELETE FROM appareils_push WHERE user_id = ?',
            'DELETE FROM preferences_push WHERE user_id = ?',
            'DELETE FROM push_envois WHERE user_id = ?',
            'DELETE FROM notifications_lues WHERE user_id = ?',
            'DELETE FROM follows_etablissements WHERE user_id = ?',
            'DELETE FROM user_interets WHERE user_id = ?',
            'DELETE FROM changements_email WHERE user_id = ?',
            'DELETE FROM totp_comptes WHERE user_id = ?',
        ] as $sql) {
            $pdo->prepare($sql)->execute([$uid]);
        }
        foreach ([
            'DELETE FROM user_blocks WHERE blocker_id = ? OR blocked_id = ?',
            'DELETE FROM user_reports WHERE reporter_id = ? OR reported_id = ?',
            'DELETE FROM follows_users WHERE follower_id = ? OR followed_id = ?',
        ] as $sql) {
            $pdo->prepare($sql)->execute([$uid, $uid]);
        }
        foreach ([
            'DELETE FROM login_attempts WHERE email = ?',
            'DELETE FROM password_resets WHERE email = ?',
            'DELETE FROM password_reset_codes WHERE email = ?',
        ] as $sql) {
            $pdo->prepare($sql)->execute([$email]);
        }

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
        'avatar'      => !empty($compte['photo']) ? (string) $compte['photo'] : null,
        'photos'      => $photos,
        'photos_lieu' => $photosLieu,
        'evenements'  => $evenements,
        'annulations' => $annulations,
    ];
}

/**
 * Après la suppression d'un établissement : fichiers et temps réel.
 *
 * @param array{avatar: string|null, photos: list<string>, photos_lieu: list<string>, evenements: list<int>, annulations?: list<array{user_id: int, evenement_id: int, titre: string}>} $restes
 */
function compteNettoyerApresPartenaire(PDO $pdo, array $restes): void
{
    // Les inscrits aux soirées à venir apprennent l'annulation. pushNotifier()
    // ne lève jamais : la suppression est déjà faite, elle ne doit pas
    // échouer pour une notification.
    if (!empty($restes['annulations'])) {
        require_once __DIR__ . '/push.php';
        foreach ($restes['annulations'] as $a) {
            [$titre, $corps] = pushTexteEvenementAnnule($a['titre']);
            pushNotifier($pdo, $a['user_id'], 'rappels', 'annulation:' . $a['evenement_id'], $titre, $corps);
        }
    }
    foreach ($restes['photos_lieu'] as $fichier) {
        deleteStoredImage($fichier, venuePhotoDir());
    }
    compteNettoyerApres($pdo, [
        'avatar'     => $restes['avatar'],
        'photos'     => $restes['photos'],
        'evenements' => $restes['evenements'],
    ]);
    // Des soirées viennent de quitter le fil.
    fluxToucher($pdo, CANAL_GLOBAL);
}
