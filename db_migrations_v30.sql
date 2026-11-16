-- ============================================================
--  Outsee — migration v30
--  La grille tarifaire partenaires : quota d'événements par mois,
--  événements supplémentaires payants, notifications des posts sponsorisés.
--
--  - evenements.supplement_tarif : prix (HT) d'un événement publié au-delà
--    du quota mensuel de la formule. Figé dans la ligne au moment de la
--    publication, comme sponsor_tarif ; 0 quand l'événement est compris
--    dans l'abonnement.
--  - sponsor_notifications : les notifications comprises dans un post
--    sponsorisé (1 pour le Coup de projecteur, 3 pour le Top du fil), dont
--    l'établissement choisit le moment. Les abonnés du lieu les reçoivent
--    dans la cloche à l'heure dite (includes/notifications.php).
--  - crm_clients.mrr : alignement des abonnements en cours sur la nouvelle
--    grille (BDE 25, Club 49, Essentiel 49,99, Premium 69,99). Les
--    fondateurs ne bougent pas : leur tarif de 39 € est gelé par contrat.
--
--  Rejouable sans effet.
-- ============================================================

SET default_storage_engine = InnoDB;

ALTER TABLE evenements
    ADD COLUMN IF NOT EXISTS supplement_tarif DECIMAL(8,2) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS sponsor_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    evenement_id INT NOT NULL,
    envoyer_le DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY k_sponsor_notif_evenement (evenement_id),
    KEY k_sponsor_notif_date (envoyer_le),
    FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE crm_clients SET mrr = 25    WHERE offre = 'bde'       AND statut <> 'perdu';
UPDATE crm_clients SET mrr = 49    WHERE offre = 'club'      AND statut <> 'perdu';
UPDATE crm_clients SET mrr = 49.99 WHERE offre = 'essentiel' AND statut <> 'perdu';
UPDATE crm_clients SET mrr = 69.99 WHERE offre = 'premium'   AND statut <> 'perdu';
