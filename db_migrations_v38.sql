-- ============================================================
--  Outsee — migration v38
--  La clôture des inscriptions d'un événement.
--
--  Jusqu'ici on pouvait s'inscrire tant que l'événement n'était ni complet
--  ni commencé. L'établissement peut désormais fermer les inscriptions plus
--  tôt (préparer la liste, la porte, les commandes) : passé
--  `inscriptions_jusqu_au` (heure de Paris, comme `date_heure`), plus
--  personne ne s'inscrit et la soirée sort du fil des non-inscrits.
--  NULL : pas de clôture anticipée, la règle reste le début de l'événement.
--
--  Rejouable sans effet.
-- ============================================================

SET default_storage_engine = InnoDB;

ALTER TABLE evenements
    ADD COLUMN IF NOT EXISTS inscriptions_jusqu_au DATETIME NULL DEFAULT NULL;
