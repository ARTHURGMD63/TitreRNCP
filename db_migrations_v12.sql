-- ============================================================
--  Linkee — migration v12
--  Back-office fondateurs : CRM commercial et registre financier
--
--  Trois tables, et une raison pour chacune.
--
--  1. crm_clients — le compte commercial.
--     Il n'est PAS confondu avec `etablissements`. Un prospect existe
--     avant tout compte partenaire : SL-05 demande une liste de 30
--     cibles clermontoises pour le 22/09, dont aucune n'aura de compte
--     dans l'application au moment où Étienne la constitue. Lier le CRM
--     à `etablissements` aurait rendu le pipeline commercial impossible
--     à tenir avant la signature, c'est-à-dire exactement là où il sert.
--     `etablissement_id` se remplit le jour où le partenaire crée son
--     compte, et reste NULL avant.
--
--  2. crm_interactions — la trace des échanges.
--     SL-07 fixe un seuil d'alerte à 21 jours sans soirée publiée, avec
--     appel dans la semaine. Un rappel sans historique se répète ou
--     s'oublie : la fiche client doit dire qui a appelé, quand, et ce
--     qui a été promis.
--
--  3. finance_mouvements — le registre de caisse, recettes et dépenses.
--     Le MRR se déduit des abonnements ; l'encaissé, non. SL-13 demande
--     « une ligne de trésorerie par mois » : sans registre, la
--     trésorerie reste une estimation, et le plancher de 1 000 € une
--     règle invérifiable.
--
--  Idempotente : relançable sans casse.
-- ============================================================

-- ─── 1. Comptes commerciaux ─────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS crm_clients (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    -- NULL tant que le partenaire n'a pas créé son compte dans l'app.
    etablissement_id INT NULL,
    nom              VARCHAR(191) NOT NULL,
    categorie        ENUM('bar','boite','resto','afterwork','bde','autre') NOT NULL DEFAULT 'bar',
    ville            VARCHAR(100) NOT NULL DEFAULT 'Clermont-Ferrand',
    adresse          VARCHAR(255) NULL,
    contact_nom      VARCHAR(191) NULL,
    contact_role     VARCHAR(100) NULL,
    contact_email    VARCHAR(191) NULL,
    contact_tel      VARCHAR(40)  NULL,
    -- Le pipeline de SL-05 : prospect → contacté → rendez-vous → essai
    -- → actif. « pause » et « perdu » sont des sorties, pas des étapes.
    statut           ENUM('prospect','contacte','rdv','essai','actif','pause','perdu')
                     NOT NULL DEFAULT 'prospect',
    -- La grille de SL-03. `aucune` = pas encore d'abonnement.
    offre            ENUM('aucune','fondateur','essentiel','premium','bde')
                     NOT NULL DEFAULT 'aucune',
    -- Montant mensuel HT réellement facturé. Recopié depuis la grille à
    -- la signature, puis figé : le tarif fondateur est gelé (SL-03), une
    -- grille qui évolue ne doit pas réécrire un contrat en cours.
    mrr              DECIMAL(8,2) NOT NULL DEFAULT 0,
    -- Fin des 3 mois offerts, pour savoir quand la facturation démarre.
    essai_jusqu_au   DATE NULL,
    signe_le         DATE NULL,
    perdu_le         DATE NULL,
    motif_perte      VARCHAR(255) NULL,
    -- Qui porte le compte. SL-07 : « un indicateur sans responsable
    -- n'est pas suivi ».
    responsable_id   INT NULL,
    notes            TEXT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_etablissement (etablissement_id),
    KEY k_statut (statut),
    KEY k_responsable (responsable_id),
    CONSTRAINT fk_crm_client_etab
        FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE SET NULL,
    CONSTRAINT fk_crm_client_resp
        FOREIGN KEY (responsable_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 2. Interactions commerciales ───────────────────────────────────────────

CREATE TABLE IF NOT EXISTS crm_interactions (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    client_id          INT NOT NULL,
    auteur_id          INT NULL,
    type               ENUM('appel','visite','email','demo','relance','note') NOT NULL DEFAULT 'note',
    contenu            TEXT NOT NULL,
    -- La prochaine action est dans la même ligne que l'échange qui l'a
    -- produite : séparée, elle se perd.
    prochaine_action   VARCHAR(255) NULL,
    prochaine_action_le DATE NULL,
    fait               TINYINT(1) NOT NULL DEFAULT 0,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY k_client (client_id, created_at),
    KEY k_relance (prochaine_action_le, fait),
    CONSTRAINT fk_crm_inter_client
        FOREIGN KEY (client_id) REFERENCES crm_clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_crm_inter_auteur
        FOREIGN KEY (auteur_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 3. Registre financier ──────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS finance_mouvements (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    sens           ENUM('recette','depense') NOT NULL,
    -- Catégories reprises de SL-13 pour les dépenses, de SL-03 pour les
    -- recettes. Une chaîne libre aurait produit quinze orthographes du
    -- mot « hébergement » et un tableau de charges inexploitable.
    categorie      ENUM('abonnement','sponsoring','autre_recette',
                        'hebergement','banque','comptabilite','assurance',
                        'marketing','juridique','materiel','autre_depense')
                   NOT NULL,
    client_id      INT NULL,
    libelle        VARCHAR(191) NOT NULL,
    montant_ht     DECIMAL(10,2) NOT NULL,
    tva_taux       DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    date_mouvement DATE NOT NULL,
    -- `prevu` : facturé ou engagé, pas encore en banque. La distinction
    -- fait tout l'écart entre le MRR et la trésorerie.
    statut         ENUM('prevu','regle') NOT NULL DEFAULT 'regle',
    moyen          VARCHAR(40) NULL,
    note           TEXT NULL,
    cree_par       INT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY k_periode (date_mouvement, sens),
    KEY k_client (client_id),
    CONSTRAINT fk_fin_client
        FOREIGN KEY (client_id) REFERENCES crm_clients(id) ON DELETE SET NULL,
    CONSTRAINT fk_fin_auteur
        FOREIGN KEY (cree_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 4. Reprise de l'existant ───────────────────────────────────────────────
--  Chaque établissement déjà présent devient un client, sinon le CRM
--  s'ouvre vide alors que les comptes existent. Statut « essai » : ils
--  utilisent le produit sans qu'aucun abonnement n'ait été facturé.
--  INSERT ... SELECT avec NOT EXISTS : relancer la migration ne duplique rien.

INSERT INTO crm_clients (etablissement_id, nom, categorie, ville, adresse, statut, offre, mrr)
SELECT e.id, e.nom, e.type, e.ville, e.adresse, 'essai', 'aucune', 0
  FROM etablissements e
 WHERE NOT EXISTS (SELECT 1 FROM crm_clients c WHERE c.etablissement_id = e.id);
