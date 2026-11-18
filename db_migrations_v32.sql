-- ============================================================
--  Outsee — migration v32
--  La validation des BDE par l'administrateur.
--
--  Rien ne prouve, à l'inscription, qu'un « BDE » en est un : la formule
--  BDE (25 € par mois) coûte moins cher que l'Essentiel, et un bar
--  pourrait s'y déclarer. Un compte de type BDE reste donc en attente
--  tant qu'un administrateur ne l'a pas validé : sans validation, il n'a
--  ni formule, ni publication.
--
--  etablissements.verification :
--    non_requise  tous les types sauf BDE
--    en_attente   BDE qui vient de s'inscrire
--    validee      BDE validé par l'administrateur
--    refusee      BDE refusé (verification_note dit pourquoi)
--
--  Rejouable sans effet.
-- ============================================================

ALTER TABLE etablissements
    ADD COLUMN IF NOT EXISTS verification ENUM('non_requise','en_attente','validee','refusee') NOT NULL DEFAULT 'non_requise',
    ADD COLUMN IF NOT EXISTS verifie_par INT NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS verifie_le DATETIME NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS verification_note VARCHAR(255) NULL DEFAULT NULL;

-- Les BDE déjà inscrits attendent eux aussi la validation.
UPDATE etablissements SET verification = 'en_attente'
 WHERE type = 'bde' AND verification = 'non_requise';
