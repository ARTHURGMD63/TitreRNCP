-- ============================================================
--  StudentLink — migration v7
--  Fiabilité et performance du cœur transactionnel
--
--  1. MyISAM ne connaît ni transaction ni clé étrangère et verrouille
--     la table entière à chaque écriture. Or « inscriptions » porte le
--     quota et le check-in : sans transaction, deux inscriptions
--     simultanées sur la dernière place passent toutes les deux.
--  2. « evenements » n'avait aucun index sur date_heure alors que
--     toutes les listes filtrent et trient dessus — balayage complet
--     sur la requête la plus fréquente de l'application.
--
--  Idempotente : relançable sans casse.
-- ============================================================

-- ── 1. Passage en InnoDB ────────────────────────────────────
ALTER TABLE etablissements       ENGINE = InnoDB;
ALTER TABLE evenements           ENGINE = InnoDB;
ALTER TABLE inscriptions         ENGINE = InnoDB;
ALTER TABLE squads               ENGINE = InnoDB;
ALTER TABLE squad_membres        ENGINE = InnoDB;
ALTER TABLE economies            ENGINE = InnoDB;
ALTER TABLE avis                 ENGINE = InnoDB;
ALTER TABLE badges               ENGINE = InnoDB;
ALTER TABLE user_badges          ENGINE = InnoDB;
ALTER TABLE user_settings        ENGINE = InnoDB;
ALTER TABLE etablissement_photos ENGINE = InnoDB;
ALTER TABLE login_attempts       ENGINE = InnoDB;
ALTER TABLE password_resets      ENGINE = InnoDB;

-- ── 2. Index sur les colonnes réellement filtrées ───────────
-- Procédure utilitaire : ajoute un index seulement s'il manque.
DROP PROCEDURE IF EXISTS ajouter_index;
DELIMITER //
CREATE PROCEDURE ajouter_index(IN p_table VARCHAR(64), IN p_nom VARCHAR(64), IN p_cols VARCHAR(255))
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_nom) THEN
    SET @sql = CONCAT('CREATE INDEX ', p_nom, ' ON ', p_table, ' (', p_cols, ')');
    PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END //
DELIMITER ;

-- La requête la plus chaude : « les événements à venir, du plus proche au plus loin ».
CALL ajouter_index('evenements',    'idx_ev_date',        'date_heure');
CALL ajouter_index('evenements',    'idx_ev_etab_date',   'etablissement_id, date_heure');
CALL ajouter_index('evenements',    'idx_ev_type_date',   'type, date_heure');
-- Comptage du quota et des check-in, par événement.
CALL ajouter_index('inscriptions',  'idx_insc_ev_statut', 'evenement_id, statut');
CALL ajouter_index('inscriptions',  'idx_insc_user',      'user_id, statut');
-- Le scan cherche un pass par son code.
CALL ajouter_index('inscriptions',  'idx_insc_qr',        'qr_code');
CALL ajouter_index('squads',        'idx_sq_date',        'date_heure');
CALL ajouter_index('squad_membres', 'idx_sm_user',        'user_id');
CALL ajouter_index('economies',     'idx_eco_user_date',  'user_id, date_economie');
CALL ajouter_index('etablissements','idx_etab_ville',     'ville');

DROP PROCEDURE ajouter_index;

-- ── 3. Clés étrangères réellement appliquées ────────────────
-- Même remarque qu'en v4 : elles étaient déclarées dans db_setup.sql
-- mais MyISAM les ignorait en silence.
DROP PROCEDURE IF EXISTS ajouter_fk;
DELIMITER //
CREATE PROCEDURE ajouter_fk(IN p_table VARCHAR(64), IN p_nom VARCHAR(64), IN p_def VARCHAR(500))
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table
                   AND CONSTRAINT_NAME = p_nom AND CONSTRAINT_TYPE = 'FOREIGN KEY') THEN
    SET @sql = CONCAT('ALTER TABLE ', p_table, ' ADD CONSTRAINT ', p_nom, ' ', p_def);
    PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END //
DELIMITER ;

CALL ajouter_fk('etablissements', 'fk_etab_user',
  'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
CALL ajouter_fk('evenements', 'fk_ev_etab',
  'FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE');
CALL ajouter_fk('inscriptions', 'fk_insc_user',
  'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
CALL ajouter_fk('inscriptions', 'fk_insc_ev',
  'FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE');
CALL ajouter_fk('squad_membres', 'fk_sm_squad',
  'FOREIGN KEY (squad_id) REFERENCES squads(id) ON DELETE CASCADE');
CALL ajouter_fk('squad_membres', 'fk_sm_user',
  'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
CALL ajouter_fk('economies', 'fk_eco_user',
  'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
CALL ajouter_fk('avis', 'fk_avis_user',
  'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
CALL ajouter_fk('user_badges', 'fk_ub_user',
  'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
CALL ajouter_fk('user_settings', 'fk_us_user',
  'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
CALL ajouter_fk('etablissement_photos', 'fk_photo_etab',
  'FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE');

DROP PROCEDURE ajouter_fk;
