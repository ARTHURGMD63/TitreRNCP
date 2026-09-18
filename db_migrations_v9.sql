-- ============================================================
--  StudentLink — migration v9
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
