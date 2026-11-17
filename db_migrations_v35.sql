-- ============================================================
--  Outsee — migration v35
--  Les notifications push.
--
--  L'application reçoit des notifications sur le téléphone, même fermée :
--  demandes d'abonnement, invitations, rappel de la veille, notifications
--  programmées par un établissement mis en avant, validation d'un BDE.
--  Trois tables : les appareils, les préférences, la file d'envoi.
--
--  Rejouable sans effet.
-- ============================================================

-- Appareils qui reçoivent les notifications push (jeton Expo, un par
-- installation de l'application). Un jeton appartient à UN compte à la fois :
-- quand quelqu'un d'autre se connecte sur le même téléphone, il change de
-- propriétaire (voir pushEnregistrerAppareil()).
CREATE TABLE IF NOT EXISTS appareils_push (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    jeton VARCHAR(255) NOT NULL,
    plateforme ENUM('ios','android') NOT NULL,
    appareil VARCHAR(120) NULL DEFAULT NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    derniere_vue DATETIME NULL DEFAULT NULL,
    UNIQUE KEY uniq_appareil_jeton (jeton),
    KEY k_appareil_user (user_id, actif),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ce que la personne a choisi de ne pas recevoir. Absence de ligne = reçoit :
-- une nouvelle catégorie est active pour tout le monde sans rien écrire.
CREATE TABLE IF NOT EXISTS preferences_push (
    user_id INT NOT NULL,
    categorie VARCHAR(30) NOT NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (user_id, categorie),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- File d'envoi : tout message part d'ici, qu'il soit immédiat ou programmé.
--   cle           identifie l'événement à l'origine (« invitation:12 ») : la
--                 même clé pour la même personne ne s'écrit qu'une fois, donc
--                 un rappel ou une invitation ne part jamais deux fois ;
--   envoyer_apres UTC ; reporté au matin quand le message tombe la nuit ;
--   statut        en_attente, envoye, ignore (préférence, plafond, aucun
--                 appareil) ou echec (Expo injoignable, après plusieurs essais).
CREATE TABLE IF NOT EXISTS push_envois (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    categorie VARCHAR(30) NOT NULL,
    cle VARCHAR(120) NOT NULL,
    titre VARCHAR(120) NOT NULL,
    corps VARCHAR(255) NOT NULL,
    donnees TEXT NULL,
    envoyer_apres DATETIME NOT NULL,
    statut ENUM('en_attente','envoye','ignore','echec') NOT NULL DEFAULT 'en_attente',
    tentatives TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    envoye_le DATETIME NULL DEFAULT NULL,
    UNIQUE KEY uniq_push_cle (user_id, cle),
    KEY k_push_file (statut, envoyer_apres),
    KEY k_push_user_date (user_id, statut, envoye_le),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
