-- Migrations v2 : avis, badges, gamification, préférences

CREATE TABLE IF NOT EXISTS avis (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    evenement_id INT NOT NULL,
    note TINYINT NOT NULL,
    commentaire TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE,
    UNIQUE KEY unique_avis (user_id, evenement_id)
);

CREATE TABLE IF NOT EXISTS badges (
    code VARCHAR(50) PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    icon VARCHAR(10),
    couleur VARCHAR(20)
);

CREATE TABLE IF NOT EXISTS user_badges (
    user_id INT NOT NULL,
    badge_code VARCHAR(50) NOT NULL,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, badge_code),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_code) REFERENCES badges(code) ON DELETE CASCADE
);

INSERT IGNORE INTO badges (code, nom, description, icon, couleur) VALUES
('first_event',   'Premier pas',       'Ton tout premier event',              '🎉', '#E5331A'),
('five_events',   'Régulier',          '5 events à ton actif',                '🔥', '#F07820'),
('ten_events',    'Noctambule',        '10 events validés',                   '🌙', '#2929E8'),
('first_squad',   'Team player',       'Rejoint ta première squad',           '🤝', '#C8E52A'),
('five_squads',   'Social butterfly',  '5 squads rejointes',                  '🦋', '#E5331A'),
('first_follow',  'Connecté',          'Suivi ta première personne',          '👥', '#2929E8'),
('reviewer',      'Critique',          'Laissé ton premier avis',             '⭐', '#F07820'),
('early_bird',    'Early bird',        'Inscrit 7j avant un event',           '🐦', '#C8E52A'),
('saver_50',      'Économe',           '50€ économisés au total',             '💰', '#E5331A');

CREATE TABLE IF NOT EXISTS user_settings (
    user_id INT PRIMARY KEY,
    theme ENUM('light','dark') DEFAULT 'light',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
