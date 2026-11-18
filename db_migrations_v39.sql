-- ============================================================
--  Outsee — migration v39
--  Les événements en collaboration.
--
--  Un établissement qui crée un événement peut inviter un autre
--  établissement (un bar, un club, un BDE…) à le co-organiser. L'invité
--  accepte ou refuse depuis son espace : tant qu'il n'a pas accepté, rien
--  ne s'affiche côté étudiant. Une fois acceptée, la collaboration apparaît
--  sur la carte et la fiche de l'événement (« avec … »).
--
--  L'événement reste celui de l'hôte (etablissement_id d'evenements) : ses
--  quotas, sa mise en avant, ses inscrits et son scan ne bougent pas.
--
--  statut
--    en_attente  invitation envoyée, pas encore de réponse
--    acceptee    l'invité co-organise : affiché aux étudiants
--    refusee     l'invité a décliné ; l'hôte peut inviter quelqu'un d'autre
--
--  Rejouable sans effet.
-- ============================================================

SET default_storage_engine = InnoDB;

CREATE TABLE IF NOT EXISTS evenement_collaborations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    evenement_id INT NOT NULL,
    etablissement_id INT NOT NULL,
    statut ENUM('en_attente','acceptee','refusee') NOT NULL DEFAULT 'en_attente',
    invite_le DATETIME NOT NULL,
    repondu_le DATETIME NULL DEFAULT NULL,
    UNIQUE KEY uniq_collab (evenement_id, etablissement_id),
    KEY k_collab_invite (etablissement_id, statut),
    FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE,
    FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
