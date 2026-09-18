-- ============================================================
--  StudentLink — migration v5
--  Photo de profil étudiant
--
--  Les établissements pouvaient déjà téléverser des photos ; les
--  étudiants non : leur avatar était toujours l'initiale du prénom
--  dans un cercle coloré. On réutilise exactement la même mécanique
--  d'upload (storeUploadedImage), avec son propre dossier.
--
--  Idempotente : relançable sans casse.
-- ============================================================

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'users'
               AND COLUMN_NAME = 'photo');
SET @sql := IF(@col = 0,
  'ALTER TABLE users ADD COLUMN photo VARCHAR(255) NULL DEFAULT NULL AFTER promo',
  'SELECT "colonne photo deja presente"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
