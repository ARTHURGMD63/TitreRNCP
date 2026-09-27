-- ============================================================
--  Linkee — migration v22
--  Une seule photo d'événement par personne et par soirée
--
--  Reposter en pleine soirée remplace la précédente plutôt que d'en
--  accumuler : la contrainte UNIQUE rend ça vrai en base (voir
--  api/v1/evenement_photos.php, INSERT ... ON DUPLICATE KEY UPDATE), pas
--  seulement respecté par convention côté client.
-- ============================================================

SET default_storage_engine = InnoDB;

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evenement_photos'
             AND INDEX_NAME = 'unique_photo_par_personne');
SET @s := IF(@i = 0,
  'ALTER TABLE evenement_photos ADD UNIQUE KEY unique_photo_par_personne (evenement_id, user_id)',
  'SELECT "contrainte unique_photo_par_personne deja presente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
