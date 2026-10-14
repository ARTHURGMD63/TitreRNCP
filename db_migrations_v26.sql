-- ============================================================
--  Linkee — migration v26
--  Mot de passe oublié par code, sans page web.
--
--  Le lien envoyé par e-mail ouvre auth/reset.php, une page du site : une
--  application qui doit se suffire à elle-même ne peut pas en dépendre. Le
--  code à six chiffres se saisit dans l'application.
--
--  Une ligne par adresse (clé primaire) : redemander un code remplace le
--  précédent. On ne stocke que l'empreinte du code, jamais le code. `essais`
--  compte les saisies fausses ; au-delà de cinq le code est détruit, ce qui
--  borne la devinette d'un code à six chiffres (voir includes/compte.php).
-- ============================================================

SET default_storage_engine = InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_codes (
    email      VARCHAR(191) NOT NULL PRIMARY KEY,
    code_hash  CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    essais     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
