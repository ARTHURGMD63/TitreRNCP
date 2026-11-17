-- ============================================================
--  Outsee — migration v36
--  Confirmation du changement d'e-mail, et double authentification
--  des comptes administrateurs.
--
--  Rejouable sans effet.
-- ============================================================

-- Un changement d'adresse en attente : le code part vers la NOUVELLE adresse,
-- et l'adresse du compte ne change qu'une fois le code saisi. Une ligne par
-- compte : une nouvelle demande remplace la précédente.
CREATE TABLE IF NOT EXISTS changements_email (
    user_id INT PRIMARY KEY,
    nouvel_email VARCHAR(190) NOT NULL,
    code_hash CHAR(64) NOT NULL,
    essais TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expire_le DATETIME NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Double authentification (TOTP) : un secret par administrateur. `dernier_pas`
-- empêche de rejouer deux fois le même code ; les codes de secours sont
-- stockés hachés, en JSON, et se consomment un à un.
CREATE TABLE IF NOT EXISTS totp_comptes (
    user_id INT PRIMARY KEY,
    secret VARCHAR(64) NOT NULL,
    dernier_pas BIGINT NOT NULL DEFAULT 0,
    codes_secours TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
