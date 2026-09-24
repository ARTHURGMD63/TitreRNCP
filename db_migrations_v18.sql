-- ============================================================
--  Linkee — migration v18
--  Rattrapage d'une base ancienne : jeu de caractères, moteur et
--  clés étrangères
--
--  Une base créée avant que db_setup.sql ne fixe `utf8mb4` et InnoDB
--  garde des tables en latin1 (le jeu par défaut de MariaDB sous WAMP),
--  une table en MyISAM et quelques clés étrangères jamais posées. Constat
--  sur la base de travail, comparée à une installation neuve :
--
--    - 17 tables en latin1 : utilisateurs, événements, squads, avis…
--      L'application parle en UTF-8. Un emoji ou un caractère hors
--      latin1 (« Łódź », ou un emoji tapé par un étudiant) dans un titre, un avis ou un nom fait
--      échouer l'écriture : « Incorrect string value ».
--    - `invitations` en MyISAM : ni transaction ni contrainte.
--    - 6 clés étrangères déclarées dans db_setup.sql mais absentes :
--      rien n'empêchait une ligne orpheline.
--
--  Idempotente : chaque étape vérifie avant d'agir. Sur une base déjà
--  conforme (installation neuve, production), elle ne change rien.
-- ============================================================

-- ── 1. utf8mb4 partout ──────────────────────────────────────
-- Base : les tables créées sans jeu explicite hériteront de celui-ci.
ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS convertir_utf8mb4;
DELIMITER //
CREATE PROCEDURE convertir_utf8mb4(IN p_table VARCHAR(64))
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table
               AND TABLE_COLLATION NOT LIKE 'utf8mb4%') THEN
    -- CONVERT TO réencode les données existantes : « Inès » reste « Inès ».
    SET @sql = CONCAT('ALTER TABLE ', p_table, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END //
DELIMITER ;

CALL convertir_utf8mb4('users');
CALL convertir_utf8mb4('etablissements');
CALL convertir_utf8mb4('etablissement_photos');
CALL convertir_utf8mb4('evenements');
CALL convertir_utf8mb4('inscriptions');
CALL convertir_utf8mb4('squads');
CALL convertir_utf8mb4('squad_membres');
CALL convertir_utf8mb4('economies');
CALL convertir_utf8mb4('follows_users');
CALL convertir_utf8mb4('follows_etablissements');
CALL convertir_utf8mb4('avis');
CALL convertir_utf8mb4('badges');
CALL convertir_utf8mb4('user_badges');
CALL convertir_utf8mb4('user_settings');
CALL convertir_utf8mb4('login_attempts');
CALL convertir_utf8mb4('password_resets');
CALL convertir_utf8mb4('invitations');

DROP PROCEDURE convertir_utf8mb4;

-- ── 2. InnoDB ───────────────────────────────────────────────
DROP PROCEDURE IF EXISTS passer_innodb;
DELIMITER //
CREATE PROCEDURE passer_innodb(IN p_table VARCHAR(64))
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND ENGINE <> 'InnoDB') THEN
    SET @sql = CONCAT('ALTER TABLE ', p_table, ' ENGINE = InnoDB');
    PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END //
DELIMITER ;

CALL passer_innodb('invitations');
CALL passer_innodb('follows_users');
CALL passer_innodb('follows_etablissements');

DROP PROCEDURE passer_innodb;

-- ── 3. Clés étrangères manquantes ───────────────────────────
-- Une clé n'est ajoutée que si la colonne n'est encore visée par AUCUNE
-- clé étrangère, quel que soit son nom : une installation neuve les porte
-- déjà, sous des noms générés par MySQL, et il ne faut pas les doubler.
DROP PROCEDURE IF EXISTS ajouter_fk_colonne;
DELIMITER //
CREATE PROCEDURE ajouter_fk_colonne(IN p_table VARCHAR(64), IN p_colonne VARCHAR(64),
                                    IN p_nom VARCHAR(64), IN p_def VARCHAR(500))
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table
                   AND COLUMN_NAME = p_colonne AND REFERENCED_TABLE_NAME IS NOT NULL) THEN
    SET @sql = CONCAT('ALTER TABLE ', p_table, ' ADD CONSTRAINT ', p_nom, ' ', p_def);
    PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END //
DELIMITER ;

CALL ajouter_fk_colonne('avis', 'evenement_id', 'fk_avis_ev',
  'FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE');
CALL ajouter_fk_colonne('economies', 'evenement_id', 'fk_eco_ev',
  'FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE');
CALL ajouter_fk_colonne('follows_etablissements', 'user_id', 'fk_fe_user',
  'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
CALL ajouter_fk_colonne('follows_etablissements', 'etablissement_id', 'fk_fe_etab',
  'FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE');
CALL ajouter_fk_colonne('squads', 'createur_id', 'fk_sq_createur',
  'FOREIGN KEY (createur_id) REFERENCES users(id) ON DELETE CASCADE');
CALL ajouter_fk_colonne('user_badges', 'badge_code', 'fk_ub_badge',
  'FOREIGN KEY (badge_code) REFERENCES badges(code) ON DELETE CASCADE');

DROP PROCEDURE ajouter_fk_colonne;
