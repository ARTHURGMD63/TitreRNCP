-- ============================================================
--  Outsee — migration v31
--  Un registre des achats qui ne se contourne pas, et des comptes
--  rangés par famille.
--
--  - achats_partenaires : chaque événement publié, chaque offre flash,
--    chaque mise en avant est écrit ici au moment où il est consommé, et
--    n'en sort jamais. Les quotas du mois (événements, flash, mise en
--    avant offerte) et les montants à payer se lisent dans ce registre,
--    plus dans la table `evenements` : supprimer un événement ne rend
--    donc ni quota, ni offre flash, ni mise en avant, et ne supprime pas
--    un supplément déjà dû. evenement_id passe à NULL à la suppression de
--    l'événement ; la ligne reste.
--  - crm_clients.offre_changee_le : date du dernier changement de formule.
--    Une formule se change au plus une fois par mois : sans cela, changer
--    à chaque échéance relancerait indéfiniment la période offerte.
--  - etablissements.type reçoit 'bde' : un BDE ou une association n'est
--    pas un bar. La formule BDE ne se prend que sur un compte de ce type.
--
--  Rejouable sans effet.
-- ============================================================

SET default_storage_engine = InnoDB;

ALTER TABLE etablissements
    MODIFY COLUMN type ENUM('bar', 'boite', 'resto', 'afterwork', 'club', 'bde') NOT NULL;

ALTER TABLE crm_clients
    ADD COLUMN IF NOT EXISTS offre_changee_le DATE NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS achats_partenaires (
    id INT PRIMARY KEY AUTO_INCREMENT,
    etablissement_id INT NOT NULL,
    evenement_id INT NULL DEFAULT NULL,
    nature ENUM('evenement','flash','sponsoring') NOT NULL,
    -- 'AAAA-MM' : le mois (de Paris) où l'achat a été fait.
    mois CHAR(7) NOT NULL,
    -- 0 quand c'est compris dans l'abonnement (quota, flash, mise en avant offerte).
    montant DECIMAL(8,2) NOT NULL DEFAULT 0,
    formule VARCHAR(20) NULL DEFAULT NULL,
    libelle VARCHAR(255) NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY k_achats_mois (etablissement_id, mois, nature),
    KEY k_achats_evenement (evenement_id),
    FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE,
    FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reprise de l'existant : ce qui est déjà publié est inscrit au registre.
INSERT INTO achats_partenaires (etablissement_id, evenement_id, nature, mois, montant, libelle, created_at)
SELECT e.etablissement_id, e.id, 'evenement', DATE_FORMAT(e.created_at, '%Y-%m'), e.supplement_tarif, e.titre, e.created_at
  FROM evenements e
 WHERE NOT EXISTS (SELECT 1 FROM achats_partenaires a WHERE a.evenement_id = e.id AND a.nature = 'evenement');

INSERT INTO achats_partenaires (etablissement_id, evenement_id, nature, mois, montant, libelle, created_at)
SELECT e.etablissement_id, e.id, 'flash', DATE_FORMAT(e.created_at, '%Y-%m'), 0, e.titre, e.created_at
  FROM evenements e
 WHERE e.is_flash = 1
   AND NOT EXISTS (SELECT 1 FROM achats_partenaires a WHERE a.evenement_id = e.id AND a.nature = 'flash');

INSERT INTO achats_partenaires (etablissement_id, evenement_id, nature, mois, montant, formule, libelle, created_at)
SELECT e.etablissement_id, e.id, 'sponsoring', DATE_FORMAT(e.created_at, '%Y-%m'), e.sponsor_tarif, e.sponsor_formule, e.titre, e.created_at
  FROM evenements e
 WHERE e.is_sponsorise = 1
   AND NOT EXISTS (SELECT 1 FROM achats_partenaires a WHERE a.evenement_id = e.id AND a.nature = 'sponsoring');
