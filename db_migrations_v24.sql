-- ============================================================
--  Linkee — migration v24
--  Ville étudiant, horaires de fin d'événement, catégorie Culture,
--  squads d'association.
--
--  users.ville : la ville choisie à l'inscription (étudiants). Distincte de
--  etablissements.ville, qui existait déjà pour les partenaires — les deux
--  vivent côte à côte, aucune des deux ne remplace l'autre.
--
--  evenements.date_fin : borne de fin, en plus de date_heure (le début).
--  NULL pour tout événement créé avant cette migration : on retombe alors sur
--  l'ancienne règle (un événement dure jusqu'à son quota ou sa date, sans fin
--  déclarée) plutôt que de casser l'affichage des soirées existantes.
--
--  squads.type accueille désormais 'culture', à côté des sports existants.
--
--  squads.cree_par_association : coché par le créateur à la création,
--  permet le filtre « Associations uniquement » de l'onglet Sport.
-- ============================================================

SET default_storage_engine = InnoDB;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
             AND COLUMN_NAME = 'ville');
SET @s := IF(@c = 0,
  'ALTER TABLE users ADD COLUMN ville VARCHAR(100) DEFAULT NULL AFTER promo',
  'SELECT "colonne users.ville deja presente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evenements'
             AND COLUMN_NAME = 'date_fin');
SET @s := IF(@c = 0,
  'ALTER TABLE evenements ADD COLUMN date_fin DATETIME NULL DEFAULT NULL AFTER date_heure',
  'SELECT "colonne evenements.date_fin deja presente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'squads'
             AND COLUMN_NAME = 'type' AND COLUMN_TYPE LIKE '%culture%');
SET @s := IF(@c = 0,
  "ALTER TABLE squads MODIFY COLUMN type ENUM('running','velo','muscu','culture','autre') NOT NULL",
  'SELECT "squads.type contient deja culture"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'squads'
             AND COLUMN_NAME = 'cree_par_association');
SET @s := IF(@c = 0,
  'ALTER TABLE squads ADD COLUMN cree_par_association TINYINT(1) NOT NULL DEFAULT 0 AFTER type',
  'SELECT "colonne squads.cree_par_association deja presente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
