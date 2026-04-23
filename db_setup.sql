-- StudentLink Database Setup
CREATE DATABASE IF NOT EXISTS studentlink CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE studentlink;

CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(191) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    ecole VARCHAR(100),
    promo VARCHAR(10),
    type ENUM('etudiant', 'partenaire') DEFAULT 'etudiant',
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
    date_heure DATETIME NOT NULL,
    quota INT DEFAULT 100,
    reduction INT DEFAULT 0,
    prix_normal DECIMAL(8,2) DEFAULT 0,
    is_flash TINYINT(1) DEFAULT 0,
    flash_expiry DATETIME,
    is_gratuit TINYINT(1) DEFAULT 0,
    lieu VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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

CREATE TABLE IF NOT EXISTS follows_users (
    follower_id INT NOT NULL,
    followed_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, followed_id),
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (followed_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS follows_etablissements (
    user_id INT NOT NULL,
    etablissement_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, etablissement_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE
);

-- Sample data
INSERT INTO users (nom, prenom, email, password, ecole, promo, type) VALUES
('Martin', 'Arthur', 'arthur@uca.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'UCA', 'L2', 'etudiant'),
('Dubois', 'Léa', 'lea@sigma.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'SIGMA Clermont', 'M1', 'etudiant'),
('Moreau', 'Lucas', 'lucas@inp.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'INP Ingénieurs', 'L3', 'etudiant'),
('Patron', 'Jean', 'jean@lebecquipique.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'partenaire'),
('Gérant', 'Marie', 'marie@barometre.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'partenaire');
-- password for all: "password"

INSERT INTO etablissements (user_id, nom, type, adresse, ville) VALUES
(4, 'Le Bec qui Pique', 'bar', 'Place de Jaude', 'Clermont-Ferrand'),
(5, 'Le Baromètre', 'boite', 'Montferrand', 'Clermont-Ferrand');

INSERT INTO evenements (etablissement_id, titre, description, type, date_heure, quota, reduction, prix_normal, is_flash, flash_expiry, is_gratuit, lieu) VALUES
(1, 'Happy Hour jusqu\'à minuit', 'Happy Hour prolongé exclusivement pour les membres StudentLink. Cocktails à moitié prix toute la soirée !', 'bar', NOW() + INTERVAL 2 HOUR, 80, 50, 10.00, 1, NOW() + INTERVAL 42 MINUTE, 0, 'Place de Jaude, Clermont-Ferrand'),
(2, 'Soirée Étudiante', 'Entrée gratuite avant 1h avec ton pass StudentLink. DJ set toute la nuit.', 'boite', NOW() + INTERVAL 26 HOUR, 150, 100, 10.00, 0, NULL, 1, 'Montferrand, Clermont-Ferrand'),
(1, 'After-work Jeudi', 'Bières à 2€, pintes à 3€ pour les étudiants munis de leur pass.', 'afterwork', NOW() + INTERVAL 50 HOUR, 60, 30, 5.00, 0, NULL, 0, 'Place de Jaude, Clermont-Ferrand');

INSERT INTO squads (createur_id, type, titre, description, niveau, date_heure, lieu, quota) VALUES
(1, 'running', 'Puy-de-Dôme sunset', 'Sortie running avec vue sur le Puy-de-Dôme au coucher du soleil. 8km à allure confortable.', 'inter', NOW() + INTERVAL 2 DAY, 'Départ Parking Royat', 10),
(2, 'muscu', 'Push day · Basic Fit', 'Séance pectoraux, épaules, triceps. On se retrouve à l\'entrée.', 'tous', NOW() + INTERVAL 1 DAY, 'Basic Fit Clermont', 6),
(3, 'velo', 'Tour du lac d\'Aydat', '22km autour du lac, 180m de dénivelé. Sortie tranquille, idéal pour découvrir le coin.', 'debutant', NOW() + INTERVAL 3 DAY, 'Parking Lac d\'Aydat', 15);

INSERT INTO squad_membres (squad_id, user_id) VALUES
(1, 1), (1, 2), (1, 3),
(2, 2), (2, 3),
(3, 1), (3, 2), (3, 3);

INSERT INTO inscriptions (user_id, evenement_id, qr_code, statut) VALUES
(1, 1, SHA2(CONCAT('1-1-', NOW()), 256), 'inscrit'),
(1, 2, SHA2(CONCAT('1-2-', NOW()), 256), 'inscrit'),
(2, 1, SHA2(CONCAT('2-1-', NOW()), 256), 'inscrit');

INSERT INTO economies (user_id, evenement_id, montant, date_economie) VALUES
(1, 1, 5.00, CURDATE()),
(1, 2, 10.00, DATE_SUB(CURDATE(), INTERVAL 3 DAY)),
(1, 1, 8.50, DATE_SUB(CURDATE(), INTERVAL 7 DAY));
