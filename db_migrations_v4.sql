-- ============================================================
--  Linkee — migration v4
--  « Abonnement sur demande » + modération
--
--  Avant : suivre quelqu'un était instantané et unilatéral, et ne
--  protégeait rien — « ses prochaines sorties » étaient visibles de
--  tout inscrit. Après : suivre est une demande que la personne
--  accepte ou refuse, et c'est elle seule qui ouvre l'accès.
--
--  Idempotente : relançable sans casse.
-- ============================================================

-- ── 1. Nettoyage préalable ──────────────────────────────────
-- Des abonnements pointent peut-être vers des comptes supprimés :
-- MyISAM ignore silencieusement les clés étrangères, donc des
-- orphelins ont pu s'accumuler. InnoDB les refuserait.
DELETE f FROM follows_users f
  LEFT JOIN users u1 ON u1.id = f.follower_id
  LEFT JOIN users u2 ON u2.id = f.followed_id
  WHERE u1.id IS NULL OR u2.id IS NULL;

DELETE f FROM follows_etablissements f
  LEFT JOIN users u ON u.id = f.user_id
  LEFT JOIN etablissements e ON e.id = f.etablissement_id
  WHERE u.id IS NULL OR e.id IS NULL;

-- ── 2. Passage en InnoDB ────────────────────────────────────
-- db_setup.sql déclare déjà ON DELETE CASCADE, mais MyISAM ne
-- l'applique pas : à la suppression d'un compte les abonnements
-- survivaient (problème pour le droit à l'effacement).
ALTER TABLE users                  ENGINE = InnoDB;
ALTER TABLE follows_users          ENGINE = InnoDB;
ALTER TABLE follows_etablissements ENGINE = InnoDB;

-- ── 3. Le statut de la demande ──────────────────────────────
-- Un refus supprime la ligne (pas d'état mort qui empêcherait de
-- redemander) ; contre le harcèlement, c'est le blocage qui agit.
ALTER TABLE follows_users
  ADD COLUMN statut ENUM('pending','accepted') NOT NULL DEFAULT 'pending'
    AFTER followed_id,
  ADD COLUMN responded_at DATETIME NULL DEFAULT NULL;

-- Les abonnements déjà en place sont validés : personne ne perd ses liens.
UPDATE follows_users SET statut = 'accepted', responded_at = created_at;

CREATE INDEX idx_follows_demandes ON follows_users (followed_id, statut);

-- ── 4. Clés étrangères réellement appliquées ────────────────
ALTER TABLE follows_users
  ADD CONSTRAINT fk_follows_follower FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_follows_followed FOREIGN KEY (followed_id) REFERENCES users(id) ON DELETE CASCADE;

-- ── 5. Blocage ──────────────────────────────────────────────
-- Exigé par les stores pour toute app sociale (Apple 1.2).
CREATE TABLE IF NOT EXISTS user_blocks (
    blocker_id INT NOT NULL,
    blocked_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (blocker_id, blocked_id),
    KEY k_blocked (blocked_id),
    CONSTRAINT fk_block_blocker FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_block_blocked FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. Signalement ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reporter_id INT NOT NULL,
    reported_id INT NOT NULL,
    motif ENUM('spam','harcelement','contenu_inapproprie','usurpation','autre') NOT NULL,
    details VARCHAR(500) NULL,
    statut ENUM('nouveau','traite') NOT NULL DEFAULT 'nouveau',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY k_reported (reported_id, statut),
    CONSTRAINT fk_report_reporter FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_report_reported FOREIGN KEY (reported_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
