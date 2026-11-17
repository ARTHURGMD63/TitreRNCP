-- ============================================================
--  Outsee — migration v34
--  Les suppléments payés à la publication.
--
--  Un événement au-delà du quota du mois, ou une mise en avant payante,
--  se paie au moment de la publication, sur la page de paiement de
--  Stripe : l'événement n'est mis en ligne qu'une fois le paiement reçu.
--
--  paiements_evenements garde, le temps du paiement, ce que
--  l'établissement a saisi (valeurs déjà validées, en JSON) et ce qu'il
--  doit : au retour de Stripe (webhook ou page de retour), l'événement
--  est publié ou modifié à partir de cette ligne, une seule fois.
--
--    en_attente  page de paiement ouverte, rien de publié
--    paye        payé, événement publié (evenement_id renseigné)
--    expire      page de paiement abandonnée ou expirée
--
--  Rejouable sans effet.
-- ============================================================

SET default_storage_engine = InnoDB;

CREATE TABLE IF NOT EXISTS paiements_evenements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    etablissement_id INT NOT NULL,
    -- L'événement modifié (mise en avant ajoutée), ou celui créé une fois payé.
    evenement_id INT NULL DEFAULT NULL,
    valeurs JSON NOT NULL,
    montant_supplement DECIMAL(8,2) NOT NULL DEFAULT 0,
    montant_sponsoring DECIMAL(8,2) NOT NULL DEFAULT 0,
    stripe_session_id VARCHAR(255) NULL DEFAULT NULL,
    stripe_paiement_id VARCHAR(255) NULL DEFAULT NULL,
    statut ENUM('en_attente','paye','expire') NOT NULL DEFAULT 'en_attente',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paye_le DATETIME NULL DEFAULT NULL,
    UNIQUE KEY uniq_paiement_session (stripe_session_id),
    KEY k_paiement_etab (etablissement_id, statut),
    FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE,
    FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
