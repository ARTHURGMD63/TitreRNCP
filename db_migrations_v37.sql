-- ============================================================
--  Outsee — migration v37
--  Le moment où une mise en avant est lancée.
--
--  `sponsor_jusqu_au` dit quand elle se termine ; rien ne disait quand elle
--  avait commencé. L'établissement qui paie une mise en avant veut voir à
--  quel moment elle est partie et combien de temps il lui reste : ce moment
--  s'écrit désormais à l'achat (heure de Paris, comme `sponsor_jusqu_au`).
--  Les mises en avant d'avant cette migration n'ont pas de début connu : on
--  n'affiche alors que leur fin.
--
--  Rejouable sans effet.
-- ============================================================

SET default_storage_engine = InnoDB;

ALTER TABLE evenements
    ADD COLUMN IF NOT EXISTS sponsor_debut DATETIME NULL DEFAULT NULL;
