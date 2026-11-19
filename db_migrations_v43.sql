-- ============================================================
--  Outsee — migration v43
--  Numéro de téléphone des étudiants, vérifié par un code SMS.
--
--  1. users.telephone : le numéro au format international (+33612345678),
--     unique (un numéro, un compte : c'est ce qui freine les comptes
--     jetables). Facultatif en base : les comptes créés avant la v43 n'en
--     ont pas, et les partenaires ne le donnent pas.
--     users.telephone_verifie_le : quand le code SMS a été saisi. NULL tant
--     que l'envoi de SMS n'est pas configuré en production (le numéro est
--     alors enregistré sans preuve).
--  2. verifications_telephone : le code envoyé, sous forme d'empreinte, avec
--     ses essais et son expiration. Une ligne par numéro, détruite une fois
--     le code utilisé, purgée par cron/entretien.php une fois expirée.
--
--  Rejouable sans effet, compatible MariaDB, MySQL 8 et TiDB.
-- ============================================================

SET default_storage_engine = InnoDB;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
             AND COLUMN_NAME = 'telephone');
SET @s := IF(@c = 0,
  'ALTER TABLE users ADD COLUMN telephone VARCHAR(20) NULL DEFAULT NULL',
  'SELECT "colonne telephone deja presente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
             AND COLUMN_NAME = 'telephone_verifie_le');
SET @s := IF(@c = 0,
  'ALTER TABLE users ADD COLUMN telephone_verifie_le DATETIME NULL DEFAULT NULL',
  'SELECT "colonne telephone_verifie_le deja presente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Index unique posé à part, après la colonne (même réflexe que la v23).
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
             AND INDEX_NAME = 'uniq_users_telephone');
SET @s := IF(@c = 0,
  'ALTER TABLE users ADD UNIQUE KEY uniq_users_telephone (telephone)',
  'SELECT "uniq_users_telephone deja present"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

CREATE TABLE IF NOT EXISTS verifications_telephone (
    telephone VARCHAR(20) NOT NULL PRIMARY KEY,
    code_hash CHAR(64) NOT NULL,
    essais TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expire_le DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY k_verif_tel_expire (expire_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
