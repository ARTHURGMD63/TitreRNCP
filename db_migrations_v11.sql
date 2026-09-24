-- ============================================================
--  Linkee — migration v11
--  Posts sponsorisés & invitations non dupliquées
--
--  1. Sponsoring d'événement
--     Un partenaire peut acheter une mise en avant pour un événement.
--     La formule fixe le tarif et la durée ; le tarif est figé dans la
--     ligne au moment de l'achat, car un changement de grille plus tard
--     ne doit pas réécrire ce qui a déjà été facturé.
--     `sponsor_jusqu_au` borne la mise en avant : sans date de fin, un
--     événement sponsorisé une fois resterait en tête du fil pour
--     toujours.
--
--  2. Invitations
--     La table n'avait aucune contrainte d'unicité : le code attrapait
--     une PDOException « invitation déjà envoyée » qui ne se produisait
--     jamais, et cliquer deux fois créait deux invitations. L'index
--     rend le doublon impossible au niveau de la base.
--     Les doublons existants sont supprimés d'abord — sinon l'ajout de
--     l'index échoue.
--
--  Idempotente : relançable sans casse.
-- ============================================================

-- ─── 1. Colonnes de sponsoring sur evenements ───────────────────────────────

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'evenements'
             AND COLUMN_NAME = 'is_sponsorise');
SET @s := IF(@c = 0,
  'ALTER TABLE evenements
     ADD COLUMN is_sponsorise TINYINT(1) NOT NULL DEFAULT 0 AFTER is_gratuit,
     ADD COLUMN sponsor_formule ENUM(''boost24'',''top7'',''premium30'') NULL DEFAULT NULL AFTER is_sponsorise,
     ADD COLUMN sponsor_tarif DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER sponsor_formule,
     ADD COLUMN sponsor_jusqu_au DATETIME NULL DEFAULT NULL AFTER sponsor_tarif',
  'SELECT "colonnes de sponsoring deja presentes"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Le fil trie sur la mise en avant avant la date : un index sur le couple
-- évite un tri de toute la table à chaque chargement d'Explore.
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'evenements'
             AND INDEX_NAME = 'idx_sponsor');
SET @s := IF(@i = 0,
  'ALTER TABLE evenements ADD INDEX idx_sponsor (is_sponsorise, sponsor_jusqu_au)',
  'SELECT "index idx_sponsor deja present"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ─── 2. Unicité des invitations ─────────────────────────────────────────────

-- Ménage préalable : on ne garde que la plus récente de chaque série.
DELETE i1 FROM invitations i1
JOIN invitations i2
  ON  i1.from_user_id = i2.from_user_id
  AND i1.to_user_id   = i2.to_user_id
  AND i1.type         = i2.type
  AND i1.target_id    = i2.target_id
  AND i1.id           < i2.id;

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'invitations'
             AND INDEX_NAME = 'uniq_invitation');
SET @s := IF(@i = 0,
  'ALTER TABLE invitations
     ADD UNIQUE KEY uniq_invitation (from_user_id, to_user_id, type, target_id)',
  'SELECT "index uniq_invitation deja present"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
