<?php
/**
 * Export des données personnelles d'un compte (droit d'accès et de
 * portabilité, articles 15 et 20 du RGPD).
 *
 * Avant l'audit (ETU-13), aucune route ne rendait ces données : chaque
 * demande se traitait à la main, table par table. rgpdExport() rassemble ce
 * que la base sait du compte, en JSON lisible ; api/v1/mes_donnees.php le
 * sert à tout compte connecté.
 *
 * Ce qui n'en fait pas partie, volontairement : les secrets (mot de passe,
 * empreintes de jetons, code du pass, secret de double authentification,
 * jeton de notification de l'appareil), qui ne sont pas des données « sur »
 * la personne mais des clés, et dont la copie affaiblirait le compte ; et les
 * données d'autres personnes (une relation est rendue par l'identifiant de
 * l'autre compte, pas par son nom).
 */

require_once __DIR__ . '/log.php';

/** Version du format, à incrémenter si la structure change. */
const RGPD_FORMAT_EXPORT = 1;

/** Clés jamais exportées, quelle que soit la table. */
const RGPD_CLES_SECRETES = '/^(password|token_hash|code_hash|qr_code|secret|codes_secours|jeton)$/i';

/**
 * Retire d'une ligne les colonnes secrètes (voir plus haut).
 *
 * @param array<string, mixed> $ligne
 * @return array<string, mixed>
 */
function rgpdNettoyerLigne(array $ligne): array
{
    foreach (array_keys($ligne) as $cle) {
        if (preg_match(RGPD_CLES_SECRETES, (string) $cle)) {
            unset($ligne[$cle]);
        }
    }

    return $ligne;
}

/**
 * Les requêtes de l'export, section par section : [requête, paramètres].
 * Toutes portent sur le seul compte $uid (ou son établissement, ou son
 * adresse pour la liste d'attente).
 *
 * @return array<string, array{0: string, 1: list<int|string>}>
 */
function rgpdRequetes(int $uid, ?int $etablissementId, string $email): array
{
    $requetes = [
        'compte'                  => ['SELECT * FROM users WHERE id = ?', [$uid]],
        'centres_interet'         => ['SELECT interet FROM user_interets WHERE user_id = ?', [$uid]],
        'reglages'                => ['SELECT * FROM user_settings WHERE user_id = ?', [$uid]],
        'notifications_reglages'  => ['SELECT categorie, actif FROM preferences_push WHERE user_id = ?', [$uid]],
        'inscriptions'            => [
            'SELECT i.id, i.evenement_id, e.titre AS evenement, e.date_heure, et.nom AS etablissement,
                    i.statut, i.created_at AS inscrit_le, i.checkin_le
               FROM inscriptions i
               LEFT JOIN evenements e ON e.id = i.evenement_id
               LEFT JOIN etablissements et ON et.id = e.etablissement_id
              WHERE i.user_id = ? ORDER BY i.created_at',
            [$uid],
        ],
        'economies'               => ['SELECT evenement_id, montant, date_economie FROM economies WHERE user_id = ?', [$uid]],
        'avis'                    => ['SELECT evenement_id, note, commentaire, created_at FROM avis WHERE user_id = ?', [$uid]],
        'photos_de_soiree'        => ['SELECT id, evenement_id, fichier, legende, masquee, created_at FROM evenement_photos WHERE user_id = ?', [$uid]],
        'photos_signalees'        => ['SELECT photo_id, motif, details, created_at FROM evenement_photo_signalements WHERE reporter_id = ?', [$uid]],
        'squads_crees'            => ['SELECT * FROM squads WHERE createur_id = ?', [$uid]],
        'squads_rejoints'         => [
            'SELECT sm.squad_id, s.titre, s.date_heure, sm.joined_at
               FROM squad_membres sm LEFT JOIN squads s ON s.id = sm.squad_id
              WHERE sm.user_id = ?',
            [$uid],
        ],
        'abonnements'             => ['SELECT followed_id AS compte_id, statut, created_at, responded_at FROM follows_users WHERE follower_id = ?', [$uid]],
        'abonnes'                 => ['SELECT follower_id AS compte_id, statut, created_at, responded_at FROM follows_users WHERE followed_id = ?', [$uid]],
        'lieux_suivis'            => [
            'SELECT fe.etablissement_id, et.nom, fe.created_at
               FROM follows_etablissements fe LEFT JOIN etablissements et ON et.id = fe.etablissement_id
              WHERE fe.user_id = ?',
            [$uid],
        ],
        'invitations_envoyees'    => ['SELECT to_user_id AS compte_id, type, target_id, statut, created_at FROM invitations WHERE from_user_id = ?', [$uid]],
        'invitations_recues'      => ['SELECT from_user_id AS compte_id, type, target_id, statut, created_at FROM invitations WHERE to_user_id = ?', [$uid]],
        'badges'                  => ['SELECT badge_code, unlocked_at FROM user_badges WHERE user_id = ?', [$uid]],
        'culture'                 => [
            'SELECT ci.evenement_id, c.titre, c.date_heure, ci.created_at
               FROM culture_inscriptions ci LEFT JOIN culture_evenements c ON c.id = ci.evenement_id
              WHERE ci.user_id = ?',
            [$uid],
        ],
        'creneaux_organises'      => ['SELECT id, terrain_id, debut, fin, prix, statut, niveau, reserve_le FROM creneaux WHERE organisateur_id = ?', [$uid]],
        'creneaux_rejoints'       => ['SELECT creneau_id, invite_par, rejoint_le FROM creneau_joueurs WHERE user_id = ?', [$uid]],
        'blocages'                => ['SELECT blocked_id AS compte_id, created_at FROM user_blocks WHERE blocker_id = ?', [$uid]],
        'signalements_envoyes'    => ['SELECT reported_id AS compte_id, motif, details, statut, created_at FROM user_reports WHERE reporter_id = ?', [$uid]],
        'sessions'                => ['SELECT appareil, created_at, derniere_utilisation, expire_le FROM api_tokens WHERE user_id = ?', [$uid]],
        'appareils'               => ['SELECT plateforme, appareil, actif, created_at, derniere_vue FROM appareils_push WHERE user_id = ?', [$uid]],
        'notifications'           => ['SELECT categorie, titre, corps, envoyer_apres, statut, envoye_le FROM push_envois WHERE user_id = ?', [$uid]],
        'notifications_lues'      => ['SELECT lues_le FROM notifications_lues WHERE user_id = ?', [$uid]],
        'double_authentification' => ['SELECT created_at FROM totp_comptes WHERE user_id = ?', [$uid]],
        'changement_email'        => ['SELECT nouvel_email, expire_le, created_at FROM changements_email WHERE user_id = ?', [$uid]],
        'liste_attente'           => ['SELECT email, cree_le FROM liste_attente WHERE email = ?', [$email]],
    ];

    if ($etablissementId !== null) {
        $requetes['etablissement']        = ['SELECT * FROM etablissements WHERE id = ?', [$etablissementId]];
        $requetes['photos_etablissement'] = ['SELECT fichier, legende, created_at FROM etablissement_photos WHERE etablissement_id = ?', [$etablissementId]];
        $requetes['evenements_publies']   = ['SELECT id, titre, type, date_heure, date_fin, lieu, created_at FROM evenements WHERE etablissement_id = ?', [$etablissementId]];
    }

    return $requetes;
}

/**
 * Toutes les données personnelles du compte $uid, section par section.
 *
 * Une section qui échoue (table absente d'une base en retard de migration)
 * vaut null et part au journal : l'export rend le reste plutôt que rien.
 *
 * @return array<string, mixed>
 */
function rgpdExport(PDO $pdo, int $uid): array
{
    $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
    $stmt->execute([$uid]);
    $email = (string) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT id FROM etablissements WHERE user_id = ?');
    $stmt->execute([$uid]);
    $etab = $stmt->fetchColumn();

    $export = [
        'format'    => RGPD_FORMAT_EXPORT,
        'genere_le' => date('c'),
    ];
    foreach (rgpdRequetes($uid, $etab !== false ? (int) $etab : null, $email) as $section => [$sql, $params]) {
        try {
            $requete = $pdo->prepare($sql);
            $requete->execute($params);
            $lignes = array_map('rgpdNettoyerLigne', $requete->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            logErreur('Export RGPD : section illisible', $e, ['section' => $section, 'user' => $uid]);
            $lignes = null;
        }
        // Une section à ligne unique se rend comme un objet.
        $export[$section] = in_array($section, ['compte', 'etablissement', 'reglages'], true)
            ? ($lignes[0] ?? null)
            : $lignes;
    }

    // Double authentification : seulement si elle est active, jamais le secret.
    $export['double_authentification'] = ['active' => !empty($export['double_authentification'])];

    return $export;
}
