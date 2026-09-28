-- ============================================================
--  Linkee — migration v25
--  Carte interactive des soirées : coordonnées de l'établissement.
--
--  latitude/longitude vivent sur etablissements (le lieu ne change pas
--  d'une soirée à l'autre), pas sur evenements. NULL pour tout
--  établissement créé avant cette migration, ou tant que ni le partenaire
--  n'a réglé sa position ni le géocodage automatique n'a abouti
--  (includes/geocodage.php) — l'événement reste alors listé normalement,
--  simplement absent de la carte.
-- ============================================================

SET default_storage_engine = InnoDB;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'etablissements'
             AND COLUMN_NAME = 'latitude');
SET @s := IF(@c = 0,
  'ALTER TABLE etablissements ADD COLUMN latitude DECIMAL(10,7) DEFAULT NULL',
  'SELECT "colonne etablissements.latitude deja presente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'etablissements'
             AND COLUMN_NAME = 'longitude');
SET @s := IF(@c = 0,
  'ALTER TABLE etablissements ADD COLUMN longitude DECIMAL(10,7) DEFAULT NULL',
  'SELECT "colonne etablissements.longitude deja presente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
