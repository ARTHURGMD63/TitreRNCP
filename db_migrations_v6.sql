-- ============================================================
--  StudentLink — migration v6
--  Rôle d'administration et traitement des signalements
--
--  Les signalements (table user_reports, migration v4) s'empilaient
--  sans aucun écran pour les lire. Apple attend qu'un signalement
--  soit traité sous 24 h : il faut donc pouvoir les consulter.
--
--  Idempotente : relançable sans casse.
-- ============================================================

ALTER TABLE users
  MODIFY COLUMN type ENUM('etudiant','partenaire','admin') DEFAULT 'etudiant';

-- Qui a traité le signalement, et quand.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_reports'
             AND COLUMN_NAME = 'traite_par');
SET @s := IF(@c = 0,
  'ALTER TABLE user_reports
     ADD COLUMN traite_par INT NULL DEFAULT NULL,
     ADD COLUMN traite_le DATETIME NULL DEFAULT NULL',
  'SELECT "colonnes de traitement deja presentes"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Pour promouvoir un compte en administrateur :
--   UPDATE users SET type = 'admin' WHERE email = 'ton.email@exemple.fr';
