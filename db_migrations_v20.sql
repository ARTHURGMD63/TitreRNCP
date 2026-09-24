-- ============================================================
--  Linkee — migration v20
--  Parrainage sur la liste d'attente
--
--  « Chaque pote inscrit avec ton lien te fait gagner 25 places » : pour que
--  ce soit vrai plutôt que simulé, chaque inscription peut porter le code de
--  qui l'a parrainée. Le code lui-même n'est pas stocké : il se déduit de
--  l'id (voir codeDepuisId() dans api/liste_attente.php), pas besoin d'une
--  colonne ni d'une génération aléatoire à dédupliquer.
-- ============================================================

SET default_storage_engine = InnoDB;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'liste_attente'
             AND COLUMN_NAME = 'parrain_id');
SET @s := IF(@c = 0,
  'ALTER TABLE liste_attente
     ADD COLUMN parrain_id INT NULL DEFAULT NULL,
     ADD INDEX idx_parrain (parrain_id)',
  'SELECT "colonne parrain_id deja presente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
