-- ============================================================
--  Outsee — migration v40
--  Conservation des registres financiers et intégrité.
--
--  1. achats_partenaires et paiements_evenements ne sont plus effacés en
--     cascade avec l'établissement : ce sont des pièces comptables (à garder
--     dix ans) et la base du rapprochement avec Stripe. L'établissement
--     supprimé laisse etablissement_id à NULL.
--  2. changements_email et totp_comptes passent en InnoDB là où le moteur
--     par défaut du serveur les avait créées en MyISAM (v36 sans ENGINE) :
--     sans cela, ni clé étrangère ni transaction sur ces tables.
--  3. economies : une seule ligne par étudiant et par événement. Les
--     doublons existants (réinscription, invitation) sont retirés, en
--     gardant la plus ancienne.
--
--  Rejouable sans effet. Écrit avec PREPARE (motif des v4 à v29) pour
--  passer sur MariaDB, MySQL 8 et TiDB.
-- ============================================================

-- ── 1. Registres financiers ─────────────────────────────────────────────

ALTER TABLE achats_partenaires MODIFY etablissement_id INT NULL;
ALTER TABLE paiements_evenements MODIFY etablissement_id INT NULL;

-- TiDB annonce dans information_schema des noms de clés étrangères qu'il
-- refuse ensuite de supprimer (erreur 1091). Sur TiDB, on ne touche donc pas
-- aux clés : la conservation est assurée par le code, qui détache achats et
-- paiements avant de supprimer un établissement (compteSupprimerPartenaire()).
SET @tidb := (SELECT VERSION() LIKE '%TiDB%');

-- achats_partenaires : la clé vers etablissements, quel que soit son nom.
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'achats_partenaires'
               AND COLUMN_NAME = 'etablissement_id' AND REFERENCED_TABLE_NAME = 'etablissements'
             LIMIT 1);
SET @regle := (SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = @fk LIMIT 1);
SET @s := IF(@tidb = 0 AND @fk IS NOT NULL AND @regle <> 'SET NULL',
  CONCAT('ALTER TABLE achats_partenaires DROP FOREIGN KEY `', @fk, '`'),
  'SELECT "achats_partenaires : clé déjà en SET NULL ou absente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'achats_partenaires'
               AND COLUMN_NAME = 'etablissement_id' AND REFERENCED_TABLE_NAME = 'etablissements'
             LIMIT 1);
SET @s := IF(@tidb = 0 AND @fk IS NULL,
  'ALTER TABLE achats_partenaires ADD CONSTRAINT fk_achat_etablissement FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE SET NULL',
  'SELECT "achats_partenaires : clé présente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- paiements_evenements : même traitement.
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'paiements_evenements'
               AND COLUMN_NAME = 'etablissement_id' AND REFERENCED_TABLE_NAME = 'etablissements'
             LIMIT 1);
SET @regle := (SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = @fk LIMIT 1);
SET @s := IF(@tidb = 0 AND @fk IS NOT NULL AND @regle <> 'SET NULL',
  CONCAT('ALTER TABLE paiements_evenements DROP FOREIGN KEY `', @fk, '`'),
  'SELECT "paiements_evenements : clé déjà en SET NULL ou absente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'paiements_evenements'
               AND COLUMN_NAME = 'etablissement_id' AND REFERENCED_TABLE_NAME = 'etablissements'
             LIMIT 1);
SET @s := IF(@tidb = 0 AND @fk IS NULL,
  'ALTER TABLE paiements_evenements ADD CONSTRAINT fk_paiement_etablissement FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE SET NULL',
  'SELECT "paiements_evenements : clé présente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ── 2. Moteur InnoDB ────────────────────────────────────────────────────

SET @e := (SELECT ENGINE FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'changements_email');
SET @s := IF(@e IS NOT NULL AND @e <> 'InnoDB',
  'ALTER TABLE changements_email ENGINE = InnoDB',
  'SELECT "changements_email déjà en InnoDB"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @e := (SELECT ENGINE FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'totp_comptes');
SET @s := IF(@e IS NOT NULL AND @e <> 'InnoDB',
  'ALTER TABLE totp_comptes ENGINE = InnoDB',
  'SELECT "totp_comptes déjà en InnoDB"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ── 3. Une économie par étudiant et par événement ───────────────────────

DELETE e1 FROM economies e1
  JOIN economies e2 ON e2.user_id = e1.user_id AND e2.evenement_id = e1.evenement_id AND e2.id < e1.id;

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'economies'
              AND INDEX_NAME = 'uniq_economie');
SET @s := IF(@i = 0,
  'ALTER TABLE economies ADD UNIQUE KEY uniq_economie (user_id, evenement_id)',
  'SELECT "index uniq_economie déjà présent"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
