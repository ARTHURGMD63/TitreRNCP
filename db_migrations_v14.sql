-- ============================================================
--  Linkee — migration v14
--  Montée en charge : index manquants et flux de révisions
--
--  Cette migration ne change aucune fonctionnalité. Elle prépare la
--  base à servir un millier de sessions simultanées, ce que le schéma
--  actuel ne permettait pas :
--
--    1. Plusieurs requêtes du hub balayaient une table entière faute
--       d'index utilisable — `SELECT DISTINCT ecole FROM users`, la
--       note moyenne d'un établissement, les demandes d'abonnement.
--       À cent comptes, personne ne le voit. À dix mille, chaque
--       affichage lit dix mille lignes pour en montrer vingt.
--
--    2. Le temps réel repose sur `flux_revisions` : un compteur par
--       canal, incrémenté à chaque écriture. L'onglet qui interroge
--       api/live.php ne relit les données que si le compteur a bougé.
--       Sans lui, chaque interrogation refait les requêtes complètes,
--       et mille onglets ouverts suffisent à saturer la base.
--
--  Idempotente : relançable sans casse, comme les précédentes.
-- ============================================================

-- ─── 1. Le flux de révisions ────────────────────────────────────────────────
--
-- Une ligne par canal observable : `event:42`, `user:7`, `squad:3`.
-- La révision s'incrémente à chaque écriture qui concerne le canal.
-- Lire l'état d'un canal coûte une lecture sur la clé primaire, soit
-- l'opération la moins chère qu'InnoDB sache faire.
--
-- Pourquoi une table et non APCu : la mémoire d'APCu n'est pas partagée
-- entre deux serveurs. Le jour où l'application tourne sur deux
-- instances, un compteur en mémoire les ferait diverger, et la moitié
-- des utilisateurs cesserait de voir les mises à jour de l'autre moitié.

CREATE TABLE IF NOT EXISTS flux_revisions (
    canal    VARCHAR(80)         NOT NULL,
    revision BIGINT UNSIGNED     NOT NULL DEFAULT 1,
    maj_le   TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (canal),
    -- Le ramassage des canaux morts (événement passé, compte supprimé)
    -- balaie par date : sans cet index, la purge scannerait toute la table.
    KEY k_maj (maj_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 2. Les index manquants ─────────────────────────────────────────────────

-- users (type, ecole) — le menu déroulant des écoles du hub.
-- `SELECT DISTINCT ecole FROM users WHERE type='etudiant'` lisait toute
-- la table à chaque affichage. Avec cet index, MySQL parcourt l'index
-- dans l'ordre et n'a plus rien à trier.
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
             AND INDEX_NAME = 'idx_users_type_ecole');
SET @s := IF(@i = 0,
  'ALTER TABLE users ADD INDEX idx_users_type_ecole (type, ecole)',
  'SELECT "idx_users_type_ecole deja present"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- users (type, created_at) — l'annuaire trie les profils par date
-- d'inscription décroissante. Sans index, c'est un tri complet en
-- mémoire à chaque page, y compris pour n'en afficher que vingt-quatre.
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
             AND INDEX_NAME = 'idx_users_type_date');
SET @s := IF(@i = 0,
  'ALTER TABLE users ADD INDEX idx_users_type_date (type, created_at)',
  'SELECT "idx_users_type_date deja present"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- avis (evenement_id) — la note moyenne d'un établissement joint `avis`
-- sur l'événement. La seule clé existante était UNIQUE (user_id,
-- evenement_id), dont le préfixe est user_id : inutilisable pour cette
-- jointure, qui lisait donc toute la table des avis par événement affiché.
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'avis'
             AND INDEX_NAME = 'idx_avis_evenement');
SET @s := IF(@i = 0,
  'ALTER TABLE avis ADD INDEX idx_avis_evenement (evenement_id, note)',
  'SELECT "idx_avis_evenement deja present"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- follows_users (follower_id, statut, followed_id) — « qui je suis, et
-- dont la demande est acceptée ». La clé primaire commence bien par
-- follower_id, mais le filtre sur `statut` obligeait à relire chaque
-- ligne dans la table. Cet index couvre la requête entière : MySQL
-- répond sans jamais toucher aux données.
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'follows_users'
             AND INDEX_NAME = 'idx_follows_suivis');
SET @s := IF(@i = 0,
  'ALTER TABLE follows_users ADD INDEX idx_follows_suivis (follower_id, statut, followed_id)',
  'SELECT "idx_follows_suivis deja present"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- inscriptions (user_id, statut, created_at) — le fil « tes abonnements
-- sortent ce soir » borne sur les dernières 48 h. L'index existant
-- s'arrêtait à (user_id, statut) : la date était vérifiée ligne à ligne.
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inscriptions'
             AND INDEX_NAME = 'idx_insc_user_statut_date');
SET @s := IF(@i = 0,
  'ALTER TABLE inscriptions ADD INDEX idx_insc_user_statut_date (user_id, statut, created_at)',
  'SELECT "idx_insc_user_statut_date deja present"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- squad_membres (user_id, joined_at) — même raison, côté squads.
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'squad_membres'
             AND INDEX_NAME = 'idx_sm_user_date');
SET @s := IF(@i = 0,
  'ALTER TABLE squad_membres ADD INDEX idx_sm_user_date (user_id, joined_at)',
  'SELECT "idx_sm_user_date deja present"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- evenements (etablissement_id, created_at) — « ce lieu que tu suis
-- vient de publier ». Le filtre porte sur created_at, pas sur
-- date_heure : idx_ev_etab_date ne servait donc qu'à moitié.
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evenements'
             AND INDEX_NAME = 'idx_ev_etab_creation');
SET @s := IF(@i = 0,
  'ALTER TABLE evenements ADD INDEX idx_ev_etab_creation (etablissement_id, created_at)',
  'SELECT "idx_ev_etab_creation deja present"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ─── 3. Amorçage du flux ────────────────────────────────────────────────────
--
-- Un canal global, qui bouge dès qu'un événement est créé ou modifié :
-- il permet à un onglet resté ouvert sur le hub de savoir qu'il y a du
-- neuf sans interroger quoi que ce soit d'autre.

INSERT INTO flux_revisions (canal, revision) VALUES ('global', 1)
ON DUPLICATE KEY UPDATE canal = canal;
