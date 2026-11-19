-- ============================================================
--  Outsee — migration v41
--  Constats de l'audit du 2026-11 (logique métier et base).
--
--  1. evenements.fin_effective (DB-02) : la fin retenue d'un événement,
--     date_fin sinon date_heure + 12 h (EVENEMENT_DUREE_PAR_DEFAUT_HEURES,
--     includes/evenements_temps.php). Le fil filtrait sur
--     COALESCE(date_fin, DATE_ADD(date_heure, …)), qu'aucun index ne sert :
--     chaque affichage parcourait tout l'historique. Colonne ordinaire, et
--     non générée : TiDB refuse d'ajouter une colonne générée STORED à une
--     table existante. Le code la tient à jour à chaque écriture
--     (evenementMajFinEffective()).
--  2. liste_attente.code (ETU-12) : code de parrainage aléatoire. L'ancien
--     code « LK » + base36(id) se devinait ; il reste accepté pour les
--     inscriptions qui n'ont pas de code, celles qui l'ont déjà partagé.
--  3. inscriptions.checkin_mode et checkin_par (PAY-17) : qui a validé une
--     entrée, et si c'était un scan ou une validation à la main.
--  4. invitations (ARC-14) : clés étrangères vers users, après retrait des
--     invitations orphelines (expéditeur, destinataire ou cible disparus).
--  5. paiements_evenements.stripe_paiement_id (ARC-19) : unique. L'index
--     n'est posé que s'il n'existe aucun doublon : ce sont des pièces
--     comptables, on n'en efface pas. Un doublon se règle à la main (la
--     requête de contrôle est en bas du fichier), puis on rejoue v41.
--
--  Rejouable sans effet. Écrit avec PREPARE (motif de la v40) pour passer
--  sur MariaDB, MySQL 8 et TiDB : une seule modification par ALTER TABLE.
-- ============================================================

-- ── 1. Fin effective des événements ─────────────────────────────────────

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evenements'
              AND COLUMN_NAME = 'fin_effective');
SET @s := IF(@c = 0,
  'ALTER TABLE evenements ADD COLUMN fin_effective DATETIME NULL DEFAULT NULL AFTER date_fin',
  'SELECT "evenements.fin_effective déjà présente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Même règle que sqlFinEffectiveEvenement(). Recalculée pour toutes les
-- lignes : sans effet au second passage.
UPDATE evenements
   SET fin_effective = COALESCE(date_fin, DATE_ADD(date_heure, INTERVAL 12 HOUR))
 WHERE fin_effective IS NULL
    OR fin_effective <> COALESCE(date_fin, DATE_ADD(date_heure, INTERVAL 12 HOUR));

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evenements'
              AND INDEX_NAME = 'idx_ev_fin_effective');
SET @s := IF(@i = 0,
  'ALTER TABLE evenements ADD INDEX idx_ev_fin_effective (fin_effective)',
  'SELECT "idx_ev_fin_effective déjà présent"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ── 2. Code de parrainage aléatoire ─────────────────────────────────────

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'liste_attente'
              AND COLUMN_NAME = 'code');
SET @s := IF(@c = 0,
  'ALTER TABLE liste_attente ADD COLUMN code VARCHAR(16) NULL DEFAULT NULL',
  'SELECT "liste_attente.code déjà présente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'liste_attente'
              AND INDEX_NAME = 'uniq_liste_code');
SET @s := IF(@i = 0,
  'ALTER TABLE liste_attente ADD UNIQUE KEY uniq_liste_code (code)',
  'SELECT "uniq_liste_code déjà présent"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ── 3. Journal des entrées ──────────────────────────────────────────────

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inscriptions'
              AND COLUMN_NAME = 'checkin_mode');
SET @s := IF(@c = 0,
  'ALTER TABLE inscriptions ADD COLUMN checkin_mode ENUM(''scan'',''manuel'') NULL DEFAULT NULL AFTER checkin_le',
  'SELECT "inscriptions.checkin_mode déjà présente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inscriptions'
              AND COLUMN_NAME = 'checkin_par');
SET @s := IF(@c = 0,
  'ALTER TABLE inscriptions ADD COLUMN checkin_par INT NULL DEFAULT NULL AFTER checkin_mode',
  'SELECT "inscriptions.checkin_par déjà présente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ── 4. Clés étrangères des invitations ──────────────────────────────────

-- Créée sans ENGINE avant v41 : MyISAM là où c'est le moteur par défaut,
-- et MyISAM ignore les clés étrangères.
SET @e := (SELECT ENGINE FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invitations');
SET @s := IF(@e IS NOT NULL AND @e <> 'InnoDB',
  'ALTER TABLE invitations ENGINE = InnoDB',
  'SELECT "invitations déjà en InnoDB"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Orphelins : expéditeur ou destinataire disparus, puis cible disparue
-- (target_id désigne une soirée, un squad ou un événement culturel selon
-- type : aucune clé ne peut le tenir, on nettoie seulement).
DELETE FROM invitations WHERE from_user_id NOT IN (SELECT id FROM users);
DELETE FROM invitations WHERE to_user_id NOT IN (SELECT id FROM users);
DELETE FROM invitations WHERE type = 'event'   AND target_id NOT IN (SELECT id FROM evenements);
DELETE FROM invitations WHERE type = 'squad'   AND target_id NOT IN (SELECT id FROM squads);
DELETE FROM invitations WHERE type = 'culture' AND target_id NOT IN (SELECT id FROM culture_evenements);

SET @fk := (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invitations'
               AND COLUMN_NAME = 'from_user_id' AND REFERENCED_TABLE_NAME = 'users');
SET @s := IF(@fk = 0,
  'ALTER TABLE invitations ADD CONSTRAINT fk_invitation_expediteur FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE',
  'SELECT "invitations.from_user_id : clé présente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @fk := (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invitations'
               AND COLUMN_NAME = 'to_user_id' AND REFERENCED_TABLE_NAME = 'users');
SET @s := IF(@fk = 0,
  'ALTER TABLE invitations ADD CONSTRAINT fk_invitation_destinataire FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE',
  'SELECT "invitations.to_user_id : clé présente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ── 5. Un paiement Stripe, une ligne ────────────────────────────────────

SET @d := (SELECT COUNT(*) FROM (
             SELECT stripe_paiement_id FROM paiements_evenements
              WHERE stripe_paiement_id IS NOT NULL
              GROUP BY stripe_paiement_id HAVING COUNT(*) > 1) AS doublons);
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'paiements_evenements'
              AND INDEX_NAME = 'uniq_paiement_stripe');
SET @s := IF(@i = 0 AND @d = 0,
  'ALTER TABLE paiements_evenements ADD UNIQUE KEY uniq_paiement_stripe (stripe_paiement_id)',
  IF(@i = 0,
     'SELECT "paiements_evenements : doublons de stripe_paiement_id, index non posé (voir la requête de contrôle de v41)"',
     'SELECT "uniq_paiement_stripe déjà présent"'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Contrôle, à lancer à la main si l'index n'a pas été posé :
--   SELECT stripe_paiement_id, GROUP_CONCAT(id), COUNT(*)
--     FROM paiements_evenements WHERE stripe_paiement_id IS NOT NULL
--    GROUP BY stripe_paiement_id HAVING COUNT(*) > 1;
