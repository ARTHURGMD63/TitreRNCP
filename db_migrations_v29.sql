-- ============================================================
--  Outsee — migration v29
--  L'espace Culture : les événements mis en avant par la ville.
--
--  Une exposition, un concert au kiosque, une nuit des musées : ce ne sont
--  pas des soirées d'établissement. Ils n'ont ni partenaire, ni réduction, ni
--  pass à scanner, et une exposition dure des semaines quand une soirée dure
--  quelques heures. Les ranger dans `evenements` aurait obligé à inventer un
--  faux établissement « Ville » et à neutraliser la moitié des colonnes ; ils
--  ont donc leurs propres tables.
--
--  Ce qu'ils partagent avec les soirées passe par les mécanismes existants :
--  l'invitation d'un ami réutilise `invitations` (type « culture »), donc la
--  cloche et la page Notifications les montrent sans nouvelle source.
--
--  Rejouable sans effet : CREATE … IF NOT EXISTS, et redéclarer la même
--  liste de valeurs d'un ENUM ne change rien.
-- ============================================================

SET default_storage_engine = InnoDB;

CREATE TABLE IF NOT EXISTS culture_evenements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    -- Catalogue PHP : includes/culture.php (CULTURE_CATEGORIES).
    categorie ENUM('expo','concert','spectacle','festival','patrimoine','cinema','atelier','conference','autre') NOT NULL DEFAULT 'autre',
    -- Qui porte l'événement : la ville, un musée municipal, l'office de
    -- tourisme. Affiché sur la carte, à la place de l'établissement.
    organisateur VARCHAR(150) NOT NULL DEFAULT 'Ville de Clermont-Ferrand',
    lieu VARCHAR(255) NOT NULL,
    ville VARCHAR(100) NOT NULL DEFAULT 'Clermont-Ferrand',
    date_heure DATETIME NOT NULL,
    -- Fin annoncée. Indispensable pour une exposition, qui reste « en cours »
    -- jusqu'à sa fermeture ; NULL pour un concert, qui se termine trois
    -- heures après son début (cultureSqlFin()).
    date_fin DATETIME NULL DEFAULT NULL,
    -- 0 = entrée libre, sans limite de places (même règle que remplissage()).
    quota INT NOT NULL DEFAULT 0,
    prix DECIMAL(8,2) NOT NULL DEFAULT 0,
    -- Page officielle de l'événement, quand la ville en publie une.
    lien VARCHAR(500) NULL DEFAULT NULL,
    -- « À la une » : passe en tête de l'espace Culture.
    mis_en_avant TINYINT(1) NOT NULL DEFAULT 0,
    cree_par INT NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_culture_date (date_heure),
    INDEX idx_culture_categorie (categorie, date_heure),
    FOREIGN KEY (cree_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pas de QR ni de statut « checkin » : personne ne scanne l'entrée d'une
-- exposition municipale. S'inscrire dit « j'y vais » à ses amis et réserve
-- une place quand il y a un quota ; se désinscrire supprime la ligne.
CREATE TABLE IF NOT EXISTS culture_inscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    evenement_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_culture_inscription (user_id, evenement_id),
    KEY k_culture_evenement (evenement_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (evenement_id) REFERENCES culture_evenements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE invitations
    MODIFY COLUMN type ENUM('event','squad','culture') NOT NULL;
