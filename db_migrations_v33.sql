-- ============================================================
--  Outsee — migration v33
--  Le paiement des établissements par Stripe, et deux index sur
--  login_attempts.
--
--  - crm_clients reçoit le lien vers Stripe : le client (cus_…),
--    l'abonnement (sub_…), son état tel que Stripe le donne
--    (trialing, active, past_due, unpaid, canceled…), la fin de la
--    période en cours, et la date du lien. Les suppléments faits avant
--    cette date ont été facturés à la main : ils ne repartent pas.
--  - achats_partenaires.stripe_ligne : la ligne de facture (ii_…)
--    créée chez Stripe pour un achat payant. NULL = pas encore envoyé.
--  - stripe_evenements : les événements de webhook déjà traités. Stripe
--    renvoie un événement tant qu'il n'a pas eu de réponse 2xx, et
--    parfois après : chacun ne doit s'appliquer qu'une fois.
--  - login_attempts : deux index. La purge des anciennes tentatives
--    (attempted_at) parcourait toute la table à chaque connexion, et le
--    limiteur compte désormais aussi par compte (email, attempted_at).
--
--  Rejouable sans effet.
-- ============================================================

SET default_storage_engine = InnoDB;

ALTER TABLE crm_clients
    ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(64) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS stripe_subscription_id VARCHAR(64) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS stripe_statut VARCHAR(32) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS stripe_periode_fin DATE NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS stripe_lie_le DATETIME NULL DEFAULT NULL;

CREATE INDEX IF NOT EXISTS k_crm_stripe_abonnement ON crm_clients (stripe_subscription_id);

ALTER TABLE achats_partenaires
    ADD COLUMN IF NOT EXISTS stripe_ligne VARCHAR(64) NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS stripe_evenements (
    id VARCHAR(255) NOT NULL PRIMARY KEY,
    type VARCHAR(100) NOT NULL,
    recu_le TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY k_stripe_evenements_date (recu_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS idx_login_time ON login_attempts (attempted_at);
CREATE INDEX IF NOT EXISTS idx_login_email ON login_attempts (email(191), attempted_at);
