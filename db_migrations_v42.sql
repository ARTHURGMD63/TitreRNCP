-- ============================================================
--  Outsee — migration v42
--  Suivi de l'application par les administrateurs.
--
--  1. journal_erreurs : les erreurs du serveur (logErreur()) et les
--     plantages de l'application (api/v1/erreur_app.php), regroupés par
--     empreinte : une même erreur répétée mille fois tient sur une ligne,
--     avec son compteur. Lisible depuis l'application admin. Aucune donnée
--     personnelle : ni identifiant de compte, ni adresse e-mail en clair.
--  2. activite_heures : qui a utilisé l'application, heure par heure. Une
--     ligne au plus par compte et par heure, écrite en même temps que la
--     mise à jour horaire de api_tokens.derniere_utilisation : de quoi
--     compter les actifs du jour et voir les heures de pointe, sans écriture
--     supplémentaire à chaque appel d'API. Purgée après 120 jours.
--
--  Rejouable sans effet (CREATE TABLE IF NOT EXISTS), compatible MariaDB,
--  MySQL 8 et TiDB.
-- ============================================================

CREATE TABLE IF NOT EXISTS journal_erreurs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    empreinte CHAR(40) NOT NULL,
    source ENUM('serveur','app') NOT NULL,
    message VARCHAR(500) NOT NULL,
    detail TEXT NULL,
    contexte TEXT NULL,
    occurrences INT UNSIGNED NOT NULL DEFAULT 1,
    premiere_le DATETIME NOT NULL,
    derniere_le DATETIME NOT NULL,
    resolu_le DATETIME NULL DEFAULT NULL,
    UNIQUE KEY uniq_journal_empreinte (empreinte),
    KEY k_journal_ouvertes (resolu_le, derniere_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activite_heures (
    heure DATETIME NOT NULL,
    user_id INT NOT NULL,
    PRIMARY KEY (heure, user_id),
    KEY k_activite_user (user_id),
    CONSTRAINT fk_activite_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
