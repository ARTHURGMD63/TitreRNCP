-- ============================================================
--  Linkee — migration v21
--  Photos d'événement + comptes publics/privés
--
--  Pendant une soirée, les participants check-in peuvent poster des photos,
--  visibles publiquement (sauf compte privé) tant que la soirée est en
--  cours ; une fois close, seuls les participants les revoient sur la fiche
--  de l'événement. Un compte privé (défaut, comme le comportement actuel de
--  follows_users) protège aussi ses photos derrière l'abonnement accepté.
-- ============================================================

SET default_storage_engine = InnoDB;

-- users.compte_prive : defaut a 1 (prive) pour ne rien changer au
-- comportement existant des demandes de suivi (toujours en attente
-- aujourd'hui) tant que la personne n'a pas choisi de passer en public.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
             AND COLUMN_NAME = 'compte_prive');
SET @s := IF(@c = 0,
  'ALTER TABLE users ADD COLUMN compte_prive TINYINT(1) NOT NULL DEFAULT 1',
  'SELECT "colonne compte_prive deja presente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

CREATE TABLE IF NOT EXISTS evenement_photos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    evenement_id INT NOT NULL,
    user_id INT NOT NULL,
    fichier VARCHAR(255) NOT NULL,
    legende VARCHAR(255) NULL DEFAULT NULL,
    -- Masquee automatiquement au 3e signalement distinct (voir
    -- evenement_photo_signalements) : retiree de l'affichage sans supprimer
    -- la ligne, pour garder la trace en cas de contestation.
    masquee TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_evphoto_event (evenement_id, created_at),
    KEY idx_evphoto_user (user_id),
    FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Signalement d'une photo : un seul par personne et par photo (UNIQUE),
-- pas de re-signalement une fois deja depose.
CREATE TABLE IF NOT EXISTS evenement_photo_signalements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    photo_id INT NOT NULL,
    reporter_id INT NOT NULL,
    motif ENUM('n_apparait_pas','contenu_inapproprie','spam','autre') NOT NULL,
    details VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_signalement (photo_id, reporter_id),
    FOREIGN KEY (photo_id) REFERENCES evenement_photos(id) ON DELETE CASCADE,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
