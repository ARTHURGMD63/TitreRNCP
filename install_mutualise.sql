-- ============================================================
--  Linkee — installation sur hébergement mutualisé
--  (InfinityFree, o2switch, OVH mutualisé…)
--
--  Import UNIQUE, sur une base DÉJÀ CRÉÉE par l'hébergeur.
--  phpMyAdmin → sélectionner la base → onglet « Importer ».
--
--  Différences avec db_setup.sql, et pourquoi :
--
--   • Pas de CREATE DATABASE ni de USE : sur un mutualisé le nom de la
--     base est imposé (« if0_xxxxxxx_linkee ») et le compte n'a pas
--     le droit d'en créer. Ces deux lignes feraient échouer l'import.
--
--   • SET default_storage_engine=InnoDB : seules 3 des 19 tables de
--     db_setup.sql déclarent leur moteur. Les 16 autres prennent celui
--     du serveur ; là où c'est MyISAM, la création de « follows_users »
--     échoue (erreur 1005/150 : InnoDB ne peut pas référencer MyISAM)
--     et l'import s'arrête au tiers. Cette ligne rend l'import portable.
--
--   • Migrations v4 et v7 écartées : leur contenu est déjà dans
--     db_setup.sql. v4 échoue sur « Nom du champ statut déjà utilisé » ;
--     v7 ne fait que recréer 10 index et des clés étrangères déjà
--     présents, au prix de procédures stockées que les hébergements
--     mutualisés n'autorisent généralement pas (CREATE ROUTINE).
--
--  Séquence vérifiée sur base vierge : 23 tables, 0 erreur.
-- ============================================================

SET NAMES utf8mb4;
SET default_storage_engine=InnoDB;

-- ============================================================
--  Linkee — Installation complète de la base de données
--  Import UNIQUE : crée la base, les 16 tables et un jeu de
--  données de démonstration (événements toujours à venir).
--
--  À importer sur une base vierge :
--   • phpMyAdmin : onglet « Importer » → choisir ce fichier
--   • ou en ligne de commande :  mysql -u root < db_setup.sql
--
--  Comptes de démo (mot de passe pour tous : « password ») :
--   • Étudiant   : arthur@uca.fr
--   • Partenaire : jean@lebecquipique.fr
-- ============================================================

-- (Hébergement type Railway : remplacer les 2 lignes ci-dessus par «USE railway;»)

-- ─────────────────────────────  SCHÉMA  ─────────────────────────────

CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(191) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    ecole VARCHAR(100),
    promo VARCHAR(10),
    -- Photo de profil : nom du fichier dans /uploads/avatars, NULL = initiale
    photo VARCHAR(255) DEFAULT NULL,
    -- Majorite verifiee a l'inscription. NULL = compte anterieur a la v10.
    date_naissance DATE DEFAULT NULL,
    -- Horodatage de l'acceptation des CGU : une case cochee sans trace
    -- en base ne prouve rien.
    cgu_acceptees_le DATETIME DEFAULT NULL,
    interests TEXT,
    type ENUM('etudiant', 'partenaire', 'admin') DEFAULT 'etudiant',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS etablissements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    nom VARCHAR(200) NOT NULL,
    type ENUM('bar', 'boite', 'resto', 'afterwork') NOT NULL,
    adresse VARCHAR(255),
    ville VARCHAR(100) DEFAULT 'Clermont-Ferrand',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS evenements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    etablissement_id INT NOT NULL,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    type ENUM('bar', 'boite', 'resto', 'afterwork') NOT NULL,
    -- Style de musique annonce (catalogue PHP : includes/musique.php).
    -- VARCHAR et non ENUM : les styles suivent les modes, en ajouter un
    -- ne doit pas demander une migration de schema.
    style_musique VARCHAR(20) NULL DEFAULT NULL,
    date_heure DATETIME NOT NULL,
    quota INT DEFAULT 100,
    reduction INT DEFAULT 0,
    prix_normal DECIMAL(8,2) DEFAULT 0,
    is_flash TINYINT(1) DEFAULT 0,
    flash_expiry DATETIME,
    is_gratuit TINYINT(1) DEFAULT 0,
    -- Mise en avant payante. Le tarif est fige dans la ligne au moment de
    -- l'achat : une grille qui evolue ne doit pas reecrire ce qui a deja ete
    -- facture. sponsor_jusqu_au borne la mise en avant, sinon un evenement
    -- sponsorise une fois resterait en tete du fil pour toujours.
    is_sponsorise TINYINT(1) NOT NULL DEFAULT 0,
    sponsor_formule ENUM('boost24','top7','premium30') DEFAULT NULL,
    sponsor_tarif DECIMAL(8,2) NOT NULL DEFAULT 0,
    sponsor_jusqu_au DATETIME DEFAULT NULL,
    lieu VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sponsor (is_sponsorise, sponsor_jusqu_au),
    INDEX idx_style_musique (style_musique, date_heure),
    FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS inscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    evenement_id INT NOT NULL,
    qr_code VARCHAR(64) NOT NULL,
    statut ENUM('inscrit', 'checkin', 'annule') DEFAULT 'inscrit',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE,
    UNIQUE KEY unique_inscription (user_id, evenement_id)
);

CREATE TABLE IF NOT EXISTS squads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    createur_id INT NOT NULL,
    type ENUM('running', 'velo', 'muscu', 'autre') NOT NULL,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    niveau ENUM('tous', 'debutant', 'inter', 'avance') DEFAULT 'tous',
    date_heure DATETIME NOT NULL,
    lieu VARCHAR(255),
    quota INT DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (createur_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS squad_membres (
    id INT PRIMARY KEY AUTO_INCREMENT,
    squad_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (squad_id) REFERENCES squads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_membre (squad_id, user_id)
);

CREATE TABLE IF NOT EXISTS economies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    evenement_id INT NOT NULL,
    montant DECIMAL(8,2) NOT NULL DEFAULT 0,
    date_economie DATE NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE
);

-- Abonnement sur demande : voir l'activite de quelqu'un (ses prochaines
-- sorties, ses squads) suppose que cette personne ait accepte. Un refus
-- supprime la ligne, pour qu'une demande reste possible plus tard.
CREATE TABLE IF NOT EXISTS follows_users (
    follower_id INT NOT NULL,
    followed_id INT NOT NULL,
    statut ENUM('pending','accepted') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (follower_id, followed_id),
    KEY idx_follows_demandes (followed_id, statut),
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (followed_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Blocage et signalement : exiges par les stores pour toute app sociale.
CREATE TABLE IF NOT EXISTS user_blocks (
    blocker_id INT NOT NULL,
    blocked_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (blocker_id, blocked_id),
    KEY k_blocked (blocked_id),
    FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reporter_id INT NOT NULL,
    reported_id INT NOT NULL,
    motif ENUM('spam','harcelement','contenu_inapproprie','usurpation','autre') NOT NULL,
    details VARCHAR(500) NULL,
    statut ENUM('nouveau','traite') NOT NULL DEFAULT 'nouveau',
    traite_par INT NULL DEFAULT NULL,
    traite_le DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY k_reported (reported_id, statut),
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Index sur les colonnes réellement filtrées ──────────────────────────
-- Sans idx_ev_date, « les événements à venir triés par date » — la requête la
-- plus fréquente de l'application — balayait la table entière.
CREATE INDEX idx_ev_date        ON evenements (date_heure);
CREATE INDEX idx_ev_etab_date   ON evenements (etablissement_id, date_heure);
CREATE INDEX idx_ev_type_date   ON evenements (type, date_heure);
CREATE INDEX idx_insc_ev_statut ON inscriptions (evenement_id, statut);
CREATE INDEX idx_insc_user      ON inscriptions (user_id, statut);
CREATE INDEX idx_insc_qr        ON inscriptions (qr_code);
CREATE INDEX idx_sq_date        ON squads (date_heure);
CREATE INDEX idx_sm_user        ON squad_membres (user_id);
CREATE INDEX idx_eco_user_date  ON economies (user_id, date_economie);
CREATE INDEX idx_etab_ville     ON etablissements (ville);

CREATE TABLE IF NOT EXISTS etablissement_photos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    etablissement_id INT NOT NULL,
    fichier VARCHAR(255) NOT NULL,
    legende VARCHAR(160) DEFAULT NULL,
    position INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE,
    INDEX idx_etab_position (etablissement_id, position)
);
CREATE TABLE IF NOT EXISTS follows_etablissements (
    user_id INT NOT NULL,
    etablissement_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, etablissement_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE
);

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

CREATE TABLE IF NOT EXISTS user_settings (
    user_id INT PRIMARY KEY,
    theme ENUM('light','dark') DEFAULT 'light',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    ip           VARCHAR(45)  NOT NULL,
    email        VARCHAR(255) NOT NULL,
    attempted_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip, attempted_at)
);

CREATE TABLE IF NOT EXISTS password_resets (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    email      VARCHAR(255) NOT NULL,
    token_hash VARCHAR(64)  NOT NULL UNIQUE,
    expires_at DATETIME     NOT NULL,
    used       TINYINT(1)   DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
);

CREATE TABLE IF NOT EXISTS invitations (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    from_user_id INT NOT NULL,
    to_user_id   INT NOT NULL,
    type         ENUM('event','squad') NOT NULL,
    target_id    INT NOT NULL,
    statut       ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Sans cet index, cliquer deux fois sur « Inviter » creait deux
    -- invitations, et le catch « deja envoyee » du code ne se declenchait
    -- jamais : il attendait une contrainte qui n'existait pas.
    UNIQUE KEY uniq_invitation (from_user_id, to_user_id, type, target_id),
    KEY k_to   (to_user_id, statut),
    KEY k_from (from_user_id)
);

-- ───────────────────────  CATALOGUE DE BADGES  ───────────────────────

-- Icônes nommées (dessinées par icon(), includes/icons.php) et couleurs en
-- jetons de la feuille : l'état de la migration v8, que ce fichier déclare
-- appliquée. Avec des emoji, les pastilles de badge s'affichaient vides.
INSERT IGNORE INTO badges (code, nom, description, icon, couleur) VALUES
('first_event',   'Premier pas',      'Ton tout premier event',      'trophee',   'var(--rouge)'),
('five_events',   'Régulier',         '5 events à ton actif',        'flamme',    'var(--orange)'),
('ten_events',    'Noctambule',       '10 events validés',           'lune',      'var(--bleu)'),
('first_squad',   'Team player',      'Rejoint ta première squad',   'drapeau',   'var(--lime)'),
('five_squads',   'Social butterfly', '5 squads rejointes',          'papillon',  'var(--rouge)'),
('first_follow',  'Connecté',         'Suivi ta première personne',  'personnes', 'var(--bleu)'),
('reviewer',      'Critique',         'Laissé ton premier avis',     'oiseau',    'var(--orange)'),
('early_bird',    'Early bird',       'Inscrit 7j avant un event',   'etoile',    'var(--lime)'),
('saver_50',      'Économe',          '50€ économisés au total',     'piece',     'var(--rouge)');

-- ─────────────────────  DONNÉES DE DÉMONSTRATION  ─────────────────────
-- Mot de passe (bcrypt) commun à tous les comptes : « password »

INSERT INTO users (nom, prenom, email, password, ecole, promo, interests, type) VALUES
('Gramond', 'Arthur', 'arthur@uca.fr',            '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'UCA', 'L2', 'Sorties,Running,Boîtes,Gaming,Mixologie,Bars', 'etudiant'),
('Dubois',  'Léa',    'lea@sigma.fr',             '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'SIGMA Clermont', 'M1', 'Running,Mixologie,Sorties', 'etudiant'),
('Moreau',  'Lucas',  'lucas@inp.fr',             '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'INP Ingénieurs', 'L3', 'Gaming,Code,Bars', 'etudiant'),
('Patron',  'Jean',   'jean@lebecquipique.fr',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, NULL, 'partenaire'),
('Gérant',  'Marie',  'marie@barometre.fr',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, NULL, 'partenaire'),
('Bonnet',  'Inès',   'ines@demo.sl',             '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'INP Ingénieurs', 'BUT2', 'Sorties,Boîtes,Techno', 'etudiant'),
('Roy',     'Maxime', 'maxime@demo.sl',           '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'UCA', 'M2', 'Running,Foot,Bars', 'etudiant'),
('Lopez',   'Sarah',  'sarah@demo.sl',            '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'UCA', 'L2', 'Mixologie,Bars,Sorties', 'etudiant'),
('Bernard', 'Hugo',   'hugo@demo.sl',             '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'SIGMA Clermont', 'M2', 'Vélo,Sorties,Muscu', 'etudiant'),
('Petit',   'Camille','camille@demo.sl',          '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'IFSI', 'L1', 'Boîtes,Techno,Musique', 'etudiant'),
('Garcia',  'Nathan', 'nathan@demo.sl',           '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'UCA', 'L3', 'Gaming,Code,Sorties', 'etudiant'),
('Lemoine', 'Chloé',  'chloe@demo.sl',            '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'INP Ingénieurs', 'BUT3', 'Sorties,Yoga,Voyage', 'etudiant'),
('Fournier','Antoine','antoine@demo.sl',          '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'UCA', 'M1', 'Foot,Running,Bars', 'etudiant'),
('Rivière', 'Manon',  'manon@demo.sl',            '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'UCA', 'L2', 'Voyage,Cinéma,Art', 'etudiant'),
('Marchand','Lucas',  'lucasm@demo.sl',           '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'SIGMA Clermont', 'L3', 'Muscu,Tennis,Gaming', 'etudiant');

INSERT INTO etablissements (user_id, nom, type, adresse, ville) VALUES
(4, 'Le Bec qui Pique', 'bar',   'Place de Jaude', 'Clermont-Ferrand'),
(5, 'Le Baromètre',     'boite', 'Montferrand',    'Clermont-Ferrand');

-- Événements toujours à venir grâce à NOW() + INTERVAL
INSERT INTO evenements (etablissement_id, titre, description, type, style_musique, date_heure, quota, reduction, prix_normal, is_flash, flash_expiry, is_gratuit, lieu) VALUES
(1, 'Happy Hour jusqu\'à minuit', 'Happy Hour prolongé exclusivement pour les membres Linkee. Cocktails à moitié prix toute la soirée !', 'bar', 'generaliste',       NOW() + INTERVAL 2 HOUR,  80,  50, 10.00, 1, NOW() + INTERVAL 3 HOUR,  0, 'Place de Jaude, Clermont-Ferrand'),
(1, 'Beer Pong Tournament',      'Tournoi de beer pong par équipes de 2. Entrée gratuite, 50€ de conso pour les gagnants.',                    'afterwork', 'generaliste', NOW() + INTERVAL 5 HOUR,  40,  0,  0.00,  1, NOW() + INTERVAL 4 HOUR,  1, 'Place de Jaude, Clermont-Ferrand'),
(1, 'Mojito Night',              '-60% sur tous les cocktails tiki entre 19h et 22h. Ambiance tropicale, DJ set live.',                          'bar', 'house',       NOW() + INTERVAL 27 HOUR, 80,  60, 10.00, 1, NOW() + INTERVAL 24 HOUR, 0, 'Place de Jaude, Clermont-Ferrand'),
(2, 'Soirée Étudiante',          'Entrée gratuite avant 1h avec ton pass Linkee. DJ set toute la nuit.',                                   'boite', 'generaliste',     NOW() + INTERVAL 26 HOUR, 150, 100, 10.00, 0, NULL, 1, 'Montferrand, Clermont-Ferrand'),
(1, 'After-work Jeudi',          'Bières à 2€, pintes à 3€ pour les étudiants munis de leur pass.',                                             'afterwork', 'pop', NOW() + INTERVAL 50 HOUR, 60,  30, 5.00,  0, NULL, 0, 'Place de Jaude, Clermont-Ferrand'),
(1, 'Blind Test Géant',          'Blind test par équipes, lots à gagner, shooters offerts pour les gagnants.',                                 'bar', 'pop',       NOW() + INTERVAL 4 DAY,   60,  20, 12.00, 0, NULL, 0, 'Place de Jaude, Clermont-Ferrand'),
(2, 'Latino Fever',              'Initiation salsa offerte à 22h30 puis reggaeton/latino jusqu\'au bout. Tequila à 4€.',                        'boite', 'latino',     NOW() + INTERVAL 5 DAY,   150, 30, 12.00, 0, NULL, 0, 'Montferrand, Clermont-Ferrand'),
(1, 'Karaoké Battle',            'Karaoké battle par équipes de 3, shots offerts pour les gagnants, +20.000 titres.',                          'bar', 'live',       NOW() + INTERVAL 6 DAY,   80,  25, 10.00, 0, NULL, 0, 'Place de Jaude, Clermont-Ferrand'),
(2, 'Techno Underground',        'Caves voûtées du centre. Line-up underground, sound system d\'enfer, late session.',                         'boite', 'techno',     NOW() + INTERVAL 7 DAY,   220, 40, 15.00, 0, NULL, 0, 'Montferrand, Clermont-Ferrand'),
(1, 'Soirée Raclette',           'Raclette à volonté avec charcuteries fermières d\'Auvergne. Idéal en groupe.',                               'resto', 'sans',     NOW() + INTERVAL 8 DAY,   30,  20, 17.00, 0, NULL, 0, 'Place de Jaude, Clermont-Ferrand'),
(2, 'Y2K Throwback',             'Total throwback années 2000. Britney, Eminem, NSYNC… Code vestimentaire : Y2K.',                             'boite', 'pop',     NOW() + INTERVAL 12 DAY,  200, 50, 10.00, 0, NULL, 0, 'Montferrand, Clermont-Ferrand');

INSERT INTO squads (createur_id, type, titre, description, niveau, date_heure, lieu, quota) VALUES
(1, 'running', 'Puy-de-Dôme sunset',      'Sortie running avec vue sur le Puy-de-Dôme au coucher du soleil. 8km à allure confortable.', 'inter',    NOW() + INTERVAL 2 DAY, 'Départ Parking Royat', 10),
(2, 'muscu',   'Push day · Basic Fit',    'Séance pectoraux, épaules, triceps. On se retrouve à l\'entrée.',                            'tous',     NOW() + INTERVAL 1 DAY, 'Basic Fit Clermont',   6),
(3, 'velo',    'Tour du lac d\'Aydat',     '22km autour du lac, 180m de dénivelé. Sortie tranquille, idéal pour découvrir le coin.',    'debutant', NOW() + INTERVAL 3 DAY, 'Parking Lac d\'Aydat', 15),
(6, 'running', 'Footing du matin',        'Petit footing tranquille au parc avant les cours. Tous niveaux bienvenus.',                 'debutant', NOW() + INTERVAL 4 DAY, 'Jardin Lecoq',         12),
(9, 'muscu',   'Pull day · Fitness Park', 'Dos et biceps, bonne ambiance, partage de séries.',                                         'inter',    NOW() + INTERVAL 5 DAY, 'Fitness Park Jaude',   6);

INSERT INTO squad_membres (squad_id, user_id) VALUES
(1, 1), (1, 2), (1, 3),
(2, 2), (2, 3),
(3, 1), (3, 2), (3, 3),
(4, 6), (4, 1),
(5, 9), (5, 6);

INSERT INTO follows_users (follower_id, followed_id, statut, responded_at) VALUES
(1, 2, 'accepted', NOW()), (1, 6, 'accepted', NOW()), (1, 7, 'accepted', NOW()), (1, 9, 'accepted', NOW()),
(6, 1, 'accepted', NOW()), (8, 1, 'accepted', NOW()), (10, 1, 'accepted', NOW()),
(2, 6, 'accepted', NOW()),
-- une demande en attente, pour que l'ecran d'acceptation soit visible en demo
(9, 1, 'pending', NULL);

INSERT INTO follows_etablissements (user_id, etablissement_id) VALUES
(1, 1);

INSERT INTO inscriptions (user_id, evenement_id, qr_code, statut) VALUES
(1, 1, SHA2(CONCAT('1-1-', NOW()), 256), 'inscrit'),
(1, 4, SHA2(CONCAT('1-4-', NOW()), 256), 'inscrit'),
(2, 1, SHA2(CONCAT('2-1-', NOW()), 256), 'inscrit'),
(3, 1, SHA2(CONCAT('3-1-', NOW()), 256), 'inscrit'),
(6, 1, SHA2(CONCAT('6-1-', NOW()), 256), 'inscrit'),
(7, 4, SHA2(CONCAT('7-4-', NOW()), 256), 'inscrit'),
(9, 4, SHA2(CONCAT('9-4-', NOW()), 256), 'inscrit'),
(6, 6, SHA2(CONCAT('6-6-', NOW()), 256), 'inscrit');

INSERT INTO economies (user_id, evenement_id, montant, date_economie) VALUES
(1, 1, 5.00, CURDATE()),
(1, 4, 10.00, DATE_SUB(CURDATE(), INTERVAL 3 DAY)),
(1, 1, 8.50, DATE_SUB(CURDATE(), INTERVAL 7 DAY));

INSERT INTO invitations (from_user_id, to_user_id, type, target_id, statut) VALUES
(6, 1, 'event', 4, 'pending'),
(2, 1, 'squad', 3, 'pending');


-- ═══════════ migration v5 ═══════════

-- ============================================================
--  Linkee — migration v5
--  Photo de profil étudiant
--
--  Les établissements pouvaient déjà téléverser des photos ; les
--  étudiants non : leur avatar était toujours l'initiale du prénom
--  dans un cercle coloré. On réutilise exactement la même mécanique
--  d'upload (storeUploadedImage), avec son propre dossier.
--
--  Idempotente : relançable sans casse.
-- ============================================================

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'users'
               AND COLUMN_NAME = 'photo');
SET @sql := IF(@col = 0,
  'ALTER TABLE users ADD COLUMN photo VARCHAR(255) NULL DEFAULT NULL AFTER promo',
  'SELECT "colonne photo deja presente"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ═══════════ migration v6 ═══════════

-- ============================================================
--  Linkee — migration v6
--  Rôle d'administration et traitement des signalements
--
--  Les signalements (table user_reports, migration v4) s'empilaient
--  sans aucun écran pour les lire. Apple attend qu'un signalement
--  soit traité sous 24 h : il faut donc pouvoir les consulter.
--
--  Idempotente : relançable sans casse.
-- ============================================================

ALTER TABLE users
  MODIFY COLUMN type ENUM('etudiant','partenaire','admin') DEFAULT 'etudiant';

-- Qui a traité le signalement, et quand.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_reports'
             AND COLUMN_NAME = 'traite_par');
SET @s := IF(@c = 0,
  'ALTER TABLE user_reports
     ADD COLUMN traite_par INT NULL DEFAULT NULL,
     ADD COLUMN traite_le DATETIME NULL DEFAULT NULL',
  'SELECT "colonnes de traitement deja presentes"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Pour promouvoir un compte en administrateur :
--   UPDATE users SET type = 'admin' WHERE email = 'ton.email@exemple.fr';


-- ═══════════ migration v8 ═══════════

-- ============================================================
--  Linkee — migration v8
--  Les badges cessent de stocker des emoji et l'ancienne palette
--
--  La colonne « icon » contenait des emoji (fête, flamme, personnes…) : dessinés par
--  chaque système d'exploitation, insensibles à la couleur demandée, et
--  désalignés de la grille typographique. Elle contient désormais une
--  CLÉ d'icône, résolue en SVG par includes/icons.php.
--
--  La colonne « couleur » portait encore la palette v1 (#E5331A, #2929E8,
--  #C8E52A, #F07820), éliminée partout ailleurs. Elle bascule sur les
--  jetons du système, qui suivent le thème clair comme le thème sombre.
--
--  Idempotente : relançable sans casse.
-- ============================================================

ALTER TABLE badges MODIFY COLUMN icon VARCHAR(32) NULL;
ALTER TABLE badges MODIFY COLUMN couleur VARCHAR(40) NULL;

UPDATE badges SET icon = 'etoile',    couleur = 'var(--lime)'   WHERE code = 'early_bird';
UPDATE badges SET icon = 'trophee',   couleur = 'var(--rouge)'  WHERE code = 'first_event';
UPDATE badges SET icon = 'personnes', couleur = 'var(--bleu)'   WHERE code = 'first_follow';
UPDATE badges SET icon = 'drapeau',   couleur = 'var(--lime)'   WHERE code = 'first_squad';
UPDATE badges SET icon = 'flamme',    couleur = 'var(--orange)' WHERE code = 'five_events';
UPDATE badges SET icon = 'papillon',  couleur = 'var(--rouge)'  WHERE code = 'five_squads';
UPDATE badges SET icon = 'oiseau',    couleur = 'var(--orange)' WHERE code = 'reviewer';
UPDATE badges SET icon = 'piece',     couleur = 'var(--rouge)'  WHERE code = 'saver_50';
UPDATE badges SET icon = 'lune',      couleur = 'var(--bleu)'   WHERE code = 'ten_events';

-- Filet : tout badge ajouté plus tard sans clé reçoit une icône neutre
-- plutôt qu'une case vide au milieu de la grille.
UPDATE badges SET icon = 'etoile' WHERE icon IS NULL OR icon = '' OR icon NOT REGEXP '^[a-z-]+$';


-- ═══════════ migration v9 ═══════════

-- ============================================================
--  Linkee — migration v9
--  Trace des rappels envoyés
--
--  Sans elle, un rappel serait renvoyé à chaque passage de la tâche
--  planifiée : la table sert de garde-fou, pas de journal décoratif.
--  La clé primaire composée rend le doublon impossible au niveau de la
--  base, quelles que soient les erreurs du script.
--
--  Idempotente : relançable sans casse.
-- ============================================================

CREATE TABLE IF NOT EXISTS rappels_envoyes (
    inscription_id INT NOT NULL,
    type ENUM('veille') NOT NULL DEFAULT 'veille',
    envoye_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (inscription_id, type),
    CONSTRAINT fk_rappel_inscription
        FOREIGN KEY (inscription_id) REFERENCES inscriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ═══════════ migration v10 ═══════════

-- ============================================================
--  Linkee — migration v10
--  Date de naissance et acceptation des CGU
--
--  Les CGU annoncent une application « réservée aux étudiants
--  majeurs », mais rien dans le produit ne le vérifiait : trois champs
--  suffisaient pour créer un compte donnant accès à des événements en
--  bar et en discothèque. On collecte donc la date de naissance à
--  l'inscription, et le contrôle des 18 ans se fait côté serveur.
--
--  L'horodatage d'acceptation des CGU sert de preuve : une case cochée
--  sans trace en base ne prouve rien.
--
--  Les deux colonnes sont NULL : les comptes déjà créés n'ont pas de
--  date de naissance, et une valeur inventée serait pire que l'absence.
--
--  Idempotente : relançable sans casse.
-- ============================================================

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'users'
             AND COLUMN_NAME = 'date_naissance');
SET @s := IF(@c = 0,
  'ALTER TABLE users
     ADD COLUMN date_naissance DATE NULL DEFAULT NULL AFTER promo,
     ADD COLUMN cgu_acceptees_le DATETIME NULL DEFAULT NULL AFTER date_naissance',
  'SELECT "colonnes date_naissance / cgu_acceptees_le deja presentes"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;


-- ═══════════ migration v11 ═══════════

-- ============================================================
--  Linkee — migration v11
--  Posts sponsorisés & invitations non dupliquées
--
--  1. Sponsoring d'événement
--     Un partenaire peut acheter une mise en avant pour un événement.
--     La formule fixe le tarif et la durée ; le tarif est figé dans la
--     ligne au moment de l'achat, car un changement de grille plus tard
--     ne doit pas réécrire ce qui a déjà été facturé.
--     `sponsor_jusqu_au` borne la mise en avant : sans date de fin, un
--     événement sponsorisé une fois resterait en tête du fil pour
--     toujours.
--
--  2. Invitations
--     La table n'avait aucune contrainte d'unicité : le code attrapait
--     une PDOException « invitation déjà envoyée » qui ne se produisait
--     jamais, et cliquer deux fois créait deux invitations. L'index
--     rend le doublon impossible au niveau de la base.
--     Les doublons existants sont supprimés d'abord — sinon l'ajout de
--     l'index échoue.
--
--  Idempotente : relançable sans casse.
-- ============================================================

-- ─── 1. Colonnes de sponsoring sur evenements ───────────────────────────────

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'evenements'
             AND COLUMN_NAME = 'is_sponsorise');
SET @s := IF(@c = 0,
  'ALTER TABLE evenements
     ADD COLUMN is_sponsorise TINYINT(1) NOT NULL DEFAULT 0 AFTER is_gratuit,
     ADD COLUMN sponsor_formule ENUM(''boost24'',''top7'',''premium30'') NULL DEFAULT NULL AFTER is_sponsorise,
     ADD COLUMN sponsor_tarif DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER sponsor_formule,
     ADD COLUMN sponsor_jusqu_au DATETIME NULL DEFAULT NULL AFTER sponsor_tarif',
  'SELECT "colonnes de sponsoring deja presentes"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Le fil trie sur la mise en avant avant la date : un index sur le couple
-- évite un tri de toute la table à chaque chargement d'Explore.
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'evenements'
             AND INDEX_NAME = 'idx_sponsor');
SET @s := IF(@i = 0,
  'ALTER TABLE evenements ADD INDEX idx_sponsor (is_sponsorise, sponsor_jusqu_au)',
  'SELECT "index idx_sponsor deja present"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ─── 2. Unicité des invitations ─────────────────────────────────────────────

-- Ménage préalable : on ne garde que la plus récente de chaque série.
DELETE i1 FROM invitations i1
JOIN invitations i2
  ON  i1.from_user_id = i2.from_user_id
  AND i1.to_user_id   = i2.to_user_id
  AND i1.type         = i2.type
  AND i1.target_id    = i2.target_id
  AND i1.id           < i2.id;

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'invitations'
             AND INDEX_NAME = 'uniq_invitation');
SET @s := IF(@i = 0,
  'ALTER TABLE invitations
     ADD UNIQUE KEY uniq_invitation (from_user_id, to_user_id, type, target_id)',
  'SELECT "index uniq_invitation deja present"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;


-- ═══════════ migration v12 ═══════════

-- ============================================================
--  Linkee — migration v12
--  Back-office fondateurs : CRM commercial et registre financier
--
--  Trois tables, et une raison pour chacune.
--
--  1. crm_clients — le compte commercial.
--     Il n'est PAS confondu avec `etablissements`. Un prospect existe
--     avant tout compte partenaire : SL-05 demande une liste de 30
--     cibles clermontoises pour le 22/09, dont aucune n'aura de compte
--     dans l'application au moment où Étienne la constitue. Lier le CRM
--     à `etablissements` aurait rendu le pipeline commercial impossible
--     à tenir avant la signature, c'est-à-dire exactement là où il sert.
--     `etablissement_id` se remplit le jour où le partenaire crée son
--     compte, et reste NULL avant.
--
--  2. crm_interactions — la trace des échanges.
--     SL-07 fixe un seuil d'alerte à 21 jours sans soirée publiée, avec
--     appel dans la semaine. Un rappel sans historique se répète ou
--     s'oublie : la fiche client doit dire qui a appelé, quand, et ce
--     qui a été promis.
--
--  3. finance_mouvements — le registre de caisse, recettes et dépenses.
--     Le MRR se déduit des abonnements ; l'encaissé, non. SL-13 demande
--     « une ligne de trésorerie par mois » : sans registre, la
--     trésorerie reste une estimation, et le plancher de 1 000 € une
--     règle invérifiable.
--
--  Idempotente : relançable sans casse.
-- ============================================================

-- ─── 1. Comptes commerciaux ─────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS crm_clients (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    -- NULL tant que le partenaire n'a pas créé son compte dans l'app.
    etablissement_id INT NULL,
    nom              VARCHAR(191) NOT NULL,
    categorie        ENUM('bar','boite','resto','afterwork','bde','autre') NOT NULL DEFAULT 'bar',
    ville            VARCHAR(100) NOT NULL DEFAULT 'Clermont-Ferrand',
    adresse          VARCHAR(255) NULL,
    contact_nom      VARCHAR(191) NULL,
    contact_role     VARCHAR(100) NULL,
    contact_email    VARCHAR(191) NULL,
    contact_tel      VARCHAR(40)  NULL,
    -- Le pipeline de SL-05 : prospect → contacté → rendez-vous → essai
    -- → actif. « pause » et « perdu » sont des sorties, pas des étapes.
    statut           ENUM('prospect','contacte','rdv','essai','actif','pause','perdu')
                     NOT NULL DEFAULT 'prospect',
    -- La grille de SL-03. `aucune` = pas encore d'abonnement.
    offre            ENUM('aucune','fondateur','essentiel','premium','bde')
                     NOT NULL DEFAULT 'aucune',
    -- Montant mensuel HT réellement facturé. Recopié depuis la grille à
    -- la signature, puis figé : le tarif fondateur est gelé (SL-03), une
    -- grille qui évolue ne doit pas réécrire un contrat en cours.
    mrr              DECIMAL(8,2) NOT NULL DEFAULT 0,
    -- Fin des 3 mois offerts, pour savoir quand la facturation démarre.
    essai_jusqu_au   DATE NULL,
    signe_le         DATE NULL,
    perdu_le         DATE NULL,
    motif_perte      VARCHAR(255) NULL,
    -- Qui porte le compte. SL-07 : « un indicateur sans responsable
    -- n'est pas suivi ».
    responsable_id   INT NULL,
    notes            TEXT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_etablissement (etablissement_id),
    KEY k_statut (statut),
    KEY k_responsable (responsable_id),
    CONSTRAINT fk_crm_client_etab
        FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE SET NULL,
    CONSTRAINT fk_crm_client_resp
        FOREIGN KEY (responsable_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 2. Interactions commerciales ───────────────────────────────────────────

CREATE TABLE IF NOT EXISTS crm_interactions (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    client_id          INT NOT NULL,
    auteur_id          INT NULL,
    type               ENUM('appel','visite','email','demo','relance','note') NOT NULL DEFAULT 'note',
    contenu            TEXT NOT NULL,
    -- La prochaine action est dans la même ligne que l'échange qui l'a
    -- produite : séparée, elle se perd.
    prochaine_action   VARCHAR(255) NULL,
    prochaine_action_le DATE NULL,
    fait               TINYINT(1) NOT NULL DEFAULT 0,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY k_client (client_id, created_at),
    KEY k_relance (prochaine_action_le, fait),
    CONSTRAINT fk_crm_inter_client
        FOREIGN KEY (client_id) REFERENCES crm_clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_crm_inter_auteur
        FOREIGN KEY (auteur_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 3. Registre financier ──────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS finance_mouvements (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    sens           ENUM('recette','depense') NOT NULL,
    -- Catégories reprises de SL-13 pour les dépenses, de SL-03 pour les
    -- recettes. Une chaîne libre aurait produit quinze orthographes du
    -- mot « hébergement » et un tableau de charges inexploitable.
    categorie      ENUM('abonnement','sponsoring','autre_recette',
                        'hebergement','banque','comptabilite','assurance',
                        'marketing','juridique','materiel','autre_depense')
                   NOT NULL,
    client_id      INT NULL,
    libelle        VARCHAR(191) NOT NULL,
    montant_ht     DECIMAL(10,2) NOT NULL,
    tva_taux       DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    date_mouvement DATE NOT NULL,
    -- `prevu` : facturé ou engagé, pas encore en banque. La distinction
    -- fait tout l'écart entre le MRR et la trésorerie.
    statut         ENUM('prevu','regle') NOT NULL DEFAULT 'regle',
    moyen          VARCHAR(40) NULL,
    note           TEXT NULL,
    cree_par       INT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY k_periode (date_mouvement, sens),
    KEY k_client (client_id),
    CONSTRAINT fk_fin_client
        FOREIGN KEY (client_id) REFERENCES crm_clients(id) ON DELETE SET NULL,
    CONSTRAINT fk_fin_auteur
        FOREIGN KEY (cree_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 4. Reprise de l'existant ───────────────────────────────────────────────
--  Chaque établissement déjà présent devient un client, sinon le CRM
--  s'ouvre vide alors que les comptes existent. Statut « essai » : ils
--  utilisent le produit sans qu'aucun abonnement n'ait été facturé.
--  INSERT ... SELECT avec NOT EXISTS : relancer la migration ne duplique rien.

INSERT INTO crm_clients (etablissement_id, nom, categorie, ville, adresse, statut, offre, mrr)
SELECT e.id, e.nom, e.type, e.ville, e.adresse, 'essai', 'aucune', 0
  FROM etablissements e
 WHERE NOT EXISTS (SELECT 1 FROM crm_clients c WHERE c.etablissement_id = e.id);


-- ═══════════ migration v13 ═══════════

-- ============================================================
--  Linkee — migration v13
--  Style de musique d'un événement
--
--  « Où sortir ce soir » se décide autant sur la musique que sur le
--  lieu : une soirée techno et un karaoké dans le même bar ne visent
--  pas les mêmes gens. Le partenaire choisit le style à la création,
--  l'étudiant filtre le fil Explore dessus.
--
--  VARCHAR et non ENUM, contrairement à `evenements.type` : les styles
--  suivent les modes, et ajouter « Amapiano » l'an prochain ne doit pas
--  demander une migration. Le catalogue qui fait foi est en PHP
--  (`includes/musique.php`), et tout ce qui vient d'un formulaire y est
--  revalidé — la colonne ne fait que stocker.
--
--  NULL est un état légitime : un restaurant ou un afterwork n'a pas
--  toujours de style à annoncer. Les soirées déjà enregistrées le
--  restent, et leurs partenaires le renseigneront à la prochaine
--  modification.
--
--  Idempotente : relançable sans casse.
-- ============================================================

-- ─── 1. La colonne ──────────────────────────────────────────────────────────

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'evenements'
             AND COLUMN_NAME = 'style_musique');
SET @s := IF(@c = 0,
  'ALTER TABLE evenements
     ADD COLUMN style_musique VARCHAR(20) NULL DEFAULT NULL AFTER type',
  'SELECT "colonne style_musique deja presente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ─── 2. L'index du filtre ───────────────────────────────────────────────────
--
-- Explore filtre sur le style ET ne montre que les soirées à venir. Le
-- couple (style, date) sert la sélection et le tri d'un seul index ;
-- sur le style seul, la base aurait relu puis trié tout un genre.

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'evenements'
             AND INDEX_NAME = 'idx_style_musique');
SET @s := IF(@i = 0,
  'ALTER TABLE evenements ADD INDEX idx_style_musique (style_musique, date_heure)',
  'SELECT "index idx_style_musique deja present"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;


-- ============================================================
--  Linkee — migration v14
--  Montée en charge : index manquants et flux de révisions
--
--  Cette migration ne change aucune fonctionnalité. Elle prépare la
--  base à servir un millier de sessions simultanées, ce que le schéma
--  actuel ne permettait pas :
--
--    1. Plusieurs requêtes du hub balayaient une table entière faute
--       d'index utilisable — `SELECT DISTINCT ecole FROM users`, la
--       note moyenne d'un établissement, les demandes d'abonnement.
--       À cent comptes, personne ne le voit. À dix mille, chaque
--       affichage lit dix mille lignes pour en montrer vingt.
--
--    2. Le temps réel repose sur `flux_revisions` : un compteur par
--       canal, incrémenté à chaque écriture. L'onglet qui interroge
--       api/live.php ne relit les données que si le compteur a bougé.
--       Sans lui, chaque interrogation refait les requêtes complètes,
--       et mille onglets ouverts suffisent à saturer la base.
--
--  Idempotente : relançable sans casse, comme les précédentes.
-- ============================================================

-- ─── 1. Le flux de révisions ────────────────────────────────────────────────
--
-- Une ligne par canal observable : `event:42`, `user:7`, `squad:3`.
-- La révision s'incrémente à chaque écriture qui concerne le canal.
-- Lire l'état d'un canal coûte une lecture sur la clé primaire, soit
-- l'opération la moins chère qu'InnoDB sache faire.
--
-- Pourquoi une table et non APCu : la mémoire d'APCu n'est pas partagée
-- entre deux serveurs. Le jour où l'application tourne sur deux
-- instances, un compteur en mémoire les ferait diverger, et la moitié
-- des utilisateurs cesserait de voir les mises à jour de l'autre moitié.

CREATE TABLE IF NOT EXISTS flux_revisions (
    canal    VARCHAR(80)         NOT NULL,
    revision BIGINT UNSIGNED     NOT NULL DEFAULT 1,
    maj_le   TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (canal),
    -- Le ramassage des canaux morts (événement passé, compte supprimé)
    -- balaie par date : sans cet index, la purge scannerait toute la table.
    KEY k_maj (maj_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 2. Les index manquants ─────────────────────────────────────────────────

-- users (type, ecole) — le menu déroulant des écoles du hub.
-- `SELECT DISTINCT ecole FROM users WHERE type='etudiant'` lisait toute
-- la table à chaque affichage. Avec cet index, MySQL parcourt l'index
-- dans l'ordre et n'a plus rien à trier.
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
             AND INDEX_NAME = 'idx_users_type_ecole');
SET @s := IF(@i = 0,
  'ALTER TABLE users ADD INDEX idx_users_type_ecole (type, ecole)',
  'SELECT "idx_users_type_ecole deja present"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- users (type, created_at) — l'annuaire trie les profils par date
-- d'inscription décroissante. Sans index, c'est un tri complet en
-- mémoire à chaque page, y compris pour n'en afficher que vingt-quatre.
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
             AND INDEX_NAME = 'idx_users_type_date');
SET @s := IF(@i = 0,
  'ALTER TABLE users ADD INDEX idx_users_type_date (type, created_at)',
  'SELECT "idx_users_type_date deja present"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- avis (evenement_id) — la note moyenne d'un établissement joint `avis`
-- sur l'événement. La seule clé existante était UNIQUE (user_id,
-- evenement_id), dont le préfixe est user_id : inutilisable pour cette
-- jointure, qui lisait donc toute la table des avis par événement affiché.
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'avis'
             AND INDEX_NAME = 'idx_avis_evenement');
SET @s := IF(@i = 0,
  'ALTER TABLE avis ADD INDEX idx_avis_evenement (evenement_id, note)',
  'SELECT "idx_avis_evenement deja present"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- follows_users (follower_id, statut, followed_id) — « qui je suis, et
-- dont la demande est acceptée ». La clé primaire commence bien par
-- follower_id, mais le filtre sur `statut` obligeait à relire chaque
-- ligne dans la table. Cet index couvre la requête entière : MySQL
-- répond sans jamais toucher aux données.
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'follows_users'
             AND INDEX_NAME = 'idx_follows_suivis');
SET @s := IF(@i = 0,
  'ALTER TABLE follows_users ADD INDEX idx_follows_suivis (follower_id, statut, followed_id)',
  'SELECT "idx_follows_suivis deja present"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- inscriptions (user_id, statut, created_at) — le fil « tes abonnements
-- sortent ce soir » borne sur les dernières 48 h. L'index existant
-- s'arrêtait à (user_id, statut) : la date était vérifiée ligne à ligne.
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inscriptions'
             AND INDEX_NAME = 'idx_insc_user_statut_date');
SET @s := IF(@i = 0,
  'ALTER TABLE inscriptions ADD INDEX idx_insc_user_statut_date (user_id, statut, created_at)',
  'SELECT "idx_insc_user_statut_date deja present"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- squad_membres (user_id, joined_at) — même raison, côté squads.
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'squad_membres'
             AND INDEX_NAME = 'idx_sm_user_date');
SET @s := IF(@i = 0,
  'ALTER TABLE squad_membres ADD INDEX idx_sm_user_date (user_id, joined_at)',
  'SELECT "idx_sm_user_date deja present"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- evenements (etablissement_id, created_at) — « ce lieu que tu suis
-- vient de publier ». Le filtre porte sur created_at, pas sur
-- date_heure : idx_ev_etab_date ne servait donc qu'à moitié.
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evenements'
             AND INDEX_NAME = 'idx_ev_etab_creation');
SET @s := IF(@i = 0,
  'ALTER TABLE evenements ADD INDEX idx_ev_etab_creation (etablissement_id, created_at)',
  'SELECT "idx_ev_etab_creation deja present"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ─── 3. Amorçage du flux ────────────────────────────────────────────────────
--
-- Un canal global, qui bouge dès qu'un événement est créé ou modifié :
-- il permet à un onglet resté ouvert sur le hub de savoir qu'il y a du
-- neuf sans interroger quoi que ce soit d'autre.

INSERT INTO flux_revisions (canal, revision) VALUES ('global', 1)
ON DUPLICATE KEY UPDATE canal = canal;


-- ============================================================
--  Linkee — migration v15
--  Les centres d'intérêt deviennent indexables
--
--  Le problème, en une phrase : `users.interests` est une chaîne
--  « techno,rock,sport », et aucun index au monde ne peut servir une
--  recherche à l'intérieur d'une chaîne.
--
--  Concrètement, l'annuaire classe les profils par nombre d'intérêts
--  communs. Écrit sur la colonne texte, cela donne un FIND_IN_SET par
--  intérêt et par profil :
--
--      (FIND_IN_SET(?, REPLACE(u.interests, ', ', ',')) > 0) + …
--
--  MySQL n'a alors pas d'autre choix que de lire les cinq mille comptes,
--  d'exécuter ces fonctions sur chacun, puis de trier le tout — pour en
--  afficher vingt-quatre. Mesuré sur 5 000 étudiants : 11 ms par requête
--  de classement, et il y en a deux. Ce coût croît linéairement avec les
--  inscriptions : à 20 000 comptes, c'est 45 ms de processeur par
--  affichage de page, multipliés par le nombre de visiteurs.
--
--  Cette table range la même information sous une forme que la base sait
--  indexer. Le score devient un comptage sur index, restreint d'emblée
--  aux profils qui partagent au moins un goût.
--
--  `users.interests` RESTE la source de vérité : l'application continue
--  de l'écrire, et cette table est tenue à jour en même temps (voir
--  synchroniserInterets() dans includes/interets.php). Deux raisons :
--  les gabarits la lisent directement pour afficher les étiquettes, et
--  une table dérivée qui se désynchronise doit pouvoir être reconstruite
--  depuis l'original — ce que fait la section 2 ci-dessous, relançable à
--  volonté.
--
--  Idempotente : relançable sans casse.
-- ============================================================

-- ─── 1. La table ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS user_interets (
    user_id INT         NOT NULL,
    interet VARCHAR(40) NOT NULL,
    PRIMARY KEY (user_id, interet),
    -- L'index qui fait tout le travail : « qui aime la techno ? » se lit
    -- ici, dans l'ordre, sans jamais toucher à la table des comptes.
    -- (interet, user_id) et non (interet) seul : la requête de score ne
    -- lit que ces deux colonnes, elle est donc entièrement couverte.
    KEY k_interet (interet, user_id),
    CONSTRAINT fk_ui_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 2. Reconstruction depuis la colonne texte ──────────────────────────────
--
-- Découpe « techno, rock, sport » en autant de lignes. La table de
-- nombres fournit les positions : n vaut 1 pour le premier élément, 2
-- pour le deuxième, et ainsi de suite. La jointure s'arrête au nombre
-- d'éléments réellement présents, calculé en comparant la longueur de la
-- chaîne avec et sans ses virgules.
--
-- 24 positions : c'est la taille du catalogue d'intérêts
-- (includes/interets.php). Personne ne peut en cocher davantage.
--
-- INSERT IGNORE : relancer cette migration ne crée pas de doublon, et
-- elle peut servir de réparation si la table dérivée dérive un jour.

INSERT IGNORE INTO user_interets (user_id, interet)
SELECT decoupe.user_id, decoupe.interet FROM (
SELECT u.id AS user_id,
       TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(u.interests, ', ', ','), ',', n.n), ',', -1)) AS interet
  FROM users u
  JOIN (
        SELECT 1 AS n UNION ALL SELECT 2  UNION ALL SELECT 3  UNION ALL SELECT 4
        UNION ALL SELECT 5  UNION ALL SELECT 6  UNION ALL SELECT 7  UNION ALL SELECT 8
        UNION ALL SELECT 9  UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
        UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16
        UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20
        UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24
       ) n
    ON n.n <= 1 + LENGTH(REPLACE(u.interests, ', ', ','))
                - LENGTH(REPLACE(REPLACE(u.interests, ', ', ','), ',', ''))
 WHERE u.interests IS NOT NULL
   AND u.interests <> ''
) AS decoupe
-- Une chaine « techno,,rock » ou une virgule finale produit un element vide :
-- il n'a rien a faire dans la table, et la cle primaire ne l'interdit pas.
WHERE decoupe.interet <> '';


-- ═══════════════════════════════════════════════════════════════════════════
--  Suivi des migrations
--
--  Ce fichier est un point de départ complet : il contient déjà le résultat
--  de toutes les migrations db_migrations_v4 à v18. Les enregistrer ici évite
--  qu'`outils/migrer.php` ne propose de les rejouer sur une base neuve — ce
--  qui échouerait sur v4, dont le contenu est intégré plus haut et qui n'est
--  pas rejouable (« Nom du champ statut déjà utilisé »).
--
--  À partir d'ici, le cycle est simple : on ajoute un fichier
--  db_migrations_v19.sql, et `php outils/migrer.php` l'applique et
--  l'enregistre. Plus rien ne se pose à la main dans phpMyAdmin.
--
--  Pour une base créée AVANT l'existence de ce suivi :
--      php outils/migrer.php --adopter
-- ═══════════════════════════════════════════════════════════════════════════

-- ═══════════════════════════════════════════════════════════════════════════
--  migration v16 — jetons d'authentification de l'application mobile
--
--  L'application native n'a pas de cookie de session : elle range un jeton et
--  le presente a chaque appel. Ce qui est stocke ici n'est pas le jeton mais
--  son empreinte SHA-256 — le jeton en clair n'existe qu'une fois, dans la
--  reponse a la connexion. Voir db_migrations_v16.sql pour le raisonnement
--  complet, et includes/api.php pour l'usage.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS api_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    appareil VARCHAR(120) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    derniere_utilisation DATETIME DEFAULT NULL,
    expire_le DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token_user (user_id, expire_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ═══════════════════════════════════════════════════════════════════════════
--  migration v17 — notifications lues (« Tout lire »)
--
--  Les notifications sont recomposées à chaque affichage : on ne retient que
--  l'instant où l'étudiant a tout lu. Voir db_migrations_v17.sql.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS notifications_lues (
    user_id INT NOT NULL PRIMARY KEY,
    lues_le DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- migration v18 (rattrapage latin1 → utf8mb4, MyISAM → InnoDB, clés
-- étrangères manquantes) : sans objet ici, tout ce fichier crée déjà ses
-- tables en utf8mb4 / InnoDB avec leurs clés. Elle est seulement enregistrée.

-- ═══════════════════════════════════════════════════════════════════════════
--  migration v19 — liste d'attente du lancement
--
--  index.php affiche un compte à rebours et un formulaire d'e-mail avant
--  l'ouverture publique. Voir db_migrations_v19.sql.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS liste_attente (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(190) NOT NULL,
    parrain_id  INT NULL DEFAULT NULL,
    cree_le     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_email (email),
    INDEX idx_parrain (parrain_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
    version     VARCHAR(20) NOT NULL,
    applique_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (version) VALUES
('v4'), ('v5'), ('v6'), ('v7'), ('v8'), ('v9'),
('v10'), ('v11'), ('v12'), ('v13'), ('v14'), ('v15'), ('v16'), ('v17'), ('v18'), ('v19'), ('v20');
