-- ============================================================
--  Outsee — migration v28
--  Le sport : clubs, terrains, créneaux, parties à compléter, et
--  événements d'étudiants réservés à leurs abonnés.
--
--  etablissements.type reçoit 'club' : un club ou établissement sportif
--  (padel, tennis, golf…). Ce qu'il propose se range dans
--  etablissement_sports, un club pouvant avoir plusieurs disciplines.
--
--  evenements.type reçoit 'sport' : l'événement d'un club (tournoi, stage,
--  initiation) passe par la même table que les soirées pour garder le pass,
--  le scan à l'entrée et le Wallet, mais le fil Explorer l'écarte et
--  l'onglet Sport ne montre que lui. evenements.sport et evenements.format
--  le décrivent ; NULL pour une soirée.
--
--  crm_clients.offre reçoit 'club' (la formule Club) et crm_clients.categorie
--  reçoit 'club' : la souscription recopie le type de l'établissement.
--
--  squads.type passe d'ENUM à VARCHAR : le catalogue des sports vit dans
--  includes/sports.php, et en ajouter un ne doit pas demander de migration
--  (même choix que evenements.style_musique). Les valeurs existantes restent
--  valables telles quelles.
--
--  squads.visibilite : un événement d'étudiant n'est visible que de ses
--  abonnés. Ceux déjà créés basculent aussi, sauf ceux d'une association,
--  qui restent publics.
--
--  terrains, creneaux, creneau_joueurs : un club met en ligne des créneaux
--  sur ses terrains ; un étudiant en réserve un, seul ou avec des amis, et
--  peut ouvrir les places restantes aux inconnus. Le prix est affiché et
--  réglé au club, Outsee n'encaisse rien : il est figé dans le créneau à la
--  publication, comme le tarif d'un sponsoring.
--
--  Rejouable : colonnes conditionnelles, tables en IF NOT EXISTS, ENUM
--  redéclarés à l'identique.
-- ============================================================

SET default_storage_engine = InnoDB;

ALTER TABLE etablissements
    MODIFY COLUMN type ENUM('bar', 'boite', 'resto', 'afterwork', 'club') NOT NULL;

ALTER TABLE evenements
    MODIFY COLUMN type ENUM('bar', 'boite', 'resto', 'afterwork', 'sport') NOT NULL;

ALTER TABLE crm_clients
    MODIFY COLUMN categorie ENUM('bar','boite','resto','afterwork','club','bde','autre') NOT NULL DEFAULT 'bar';

ALTER TABLE crm_clients
    MODIFY COLUMN offre ENUM('aucune','fondateur','essentiel','premium','bde','club') NOT NULL DEFAULT 'aucune';

ALTER TABLE squads
    MODIFY COLUMN type VARCHAR(20) NOT NULL;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evenements'
             AND COLUMN_NAME = 'sport');
SET @s := IF(@c = 0,
  'ALTER TABLE evenements ADD COLUMN sport VARCHAR(20) NULL DEFAULT NULL AFTER style_musique',
  'SELECT "colonne evenements.sport deja presente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evenements'
             AND COLUMN_NAME = 'format');
SET @s := IF(@c = 0,
  'ALTER TABLE evenements ADD COLUMN format VARCHAR(20) NULL DEFAULT NULL AFTER sport',
  'SELECT "colonne evenements.format deja presente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- La bascule des squads existants ne se fait qu'au moment où la colonne
-- apparaît : rejouer la migration ne doit pas rendre « abonnés » un squad
-- qu'on aurait repassé en public depuis.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'squads'
             AND COLUMN_NAME = 'visibilite');
SET @s := IF(@c = 0,
  "ALTER TABLE squads ADD COLUMN visibilite ENUM('public','abonnes') NOT NULL DEFAULT 'abonnes' AFTER cree_par_association",
  'SELECT "colonne squads.visibilite deja presente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := IF(@c = 0,
  "UPDATE squads SET visibilite = IF(cree_par_association = 1, 'public', 'abonnes')",
  'SELECT "visibilite des squads deja posee"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

CREATE TABLE IF NOT EXISTS etablissement_sports (
    etablissement_id INT NOT NULL,
    sport VARCHAR(20) NOT NULL,
    PRIMARY KEY (etablissement_id, sport),
    KEY k_sport (sport),
    FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS terrains (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etablissement_id INT NOT NULL,
    nom VARCHAR(100) NOT NULL,
    sport VARCHAR(20) NOT NULL,
    -- Joueurs pour une partie complète : 4 au padel, 2 ou 4 au tennis…
    joueurs TINYINT UNSIGNED NOT NULL DEFAULT 4,
    duree_min SMALLINT UNSIGNED NOT NULL DEFAULT 90,
    -- Prix du terrain pour un créneau, réglé au club.
    prix DECIMAL(8,2) NOT NULL DEFAULT 0,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY k_etab (etablissement_id, actif),
    FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creneaux (
    id INT AUTO_INCREMENT PRIMARY KEY,
    terrain_id INT NOT NULL,
    debut DATETIME NOT NULL,
    fin DATETIME NOT NULL,
    -- Figés à la publication : changer le prix d'un terrain ne réécrit pas
    -- ce qui est déjà en ligne, ni ce qui est déjà réservé.
    prix DECIMAL(8,2) NOT NULL DEFAULT 0,
    joueurs TINYINT UNSIGNED NOT NULL DEFAULT 4,
    statut ENUM('libre','reserve','annule') NOT NULL DEFAULT 'libre',
    organisateur_id INT NULL DEFAULT NULL,
    -- Places restantes ouvertes aux inconnus, avec le niveau demandé.
    ouvert TINYINT(1) NOT NULL DEFAULT 0,
    niveau ENUM('tous','debutant','inter','avance') NOT NULL DEFAULT 'tous',
    reserve_le DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_terrain_debut (terrain_id, debut),
    KEY k_statut_debut (statut, debut),
    KEY k_organisateur (organisateur_id),
    FOREIGN KEY (terrain_id) REFERENCES terrains(id) ON DELETE CASCADE,
    FOREIGN KEY (organisateur_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creneau_joueurs (
    creneau_id INT NOT NULL,
    user_id INT NOT NULL,
    -- Rempli quand un ami l'a ajouté à la réservation ; NULL pour
    -- l'organisateur et pour un inconnu venu compléter la partie.
    invite_par INT NULL DEFAULT NULL,
    rejoint_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (creneau_id, user_id),
    KEY k_user (user_id),
    FOREIGN KEY (creneau_id) REFERENCES creneaux(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (invite_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
