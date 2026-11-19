-- ============================================================
--  Outsee — migration v44
--  Âge minimum d'un événement, fixé par l'établissement.
--
--  evenements.age_minimum : 18 par défaut (Outsee est réservé aux
--  majeurs), jusqu'à 99. L'inscription est refusée à un étudiant qui n'a
--  pas cet âge le jour de l'événement, et le scan refuse son entrée.
--  Les événements existants reçoivent 18 : rien ne change pour eux.
--
--  Rejouable sans effet, compatible MariaDB, MySQL 8 et TiDB.
-- ============================================================

SET default_storage_engine = InnoDB;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evenements'
             AND COLUMN_NAME = 'age_minimum');
SET @s := IF(@c = 0,
  'ALTER TABLE evenements ADD COLUMN age_minimum TINYINT UNSIGNED NOT NULL DEFAULT 18',
  'SELECT "colonne age_minimum deja presente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
