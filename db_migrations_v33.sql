-- ============================================================
--  Outsee — migration v33
--  Deux index sur login_attempts.
--
--  La purge des anciennes tentatives (attempted_at) parcourait toute la
--  table à chaque connexion, et le limiteur compte désormais aussi par
--  compte (email, attempted_at).
--
--  Rejouable sans effet.
-- ============================================================

CREATE INDEX IF NOT EXISTS idx_login_time ON login_attempts (attempted_at);
CREATE INDEX IF NOT EXISTS idx_login_email ON login_attempts (email(191), attempted_at);
