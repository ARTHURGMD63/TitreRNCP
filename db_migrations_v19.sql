-- ============================================================
--  Linkee — migration v19
--  Liste d'attente du lancement (page d'accueil « bientôt disponible »)
--
--  Avant l'ouverture publique, index.php n'affiche plus qu'un compte à
--  rebours et un formulaire d'e-mail : « réserve ta place ». Cette table
--  retient ces adresses, pour prévenir au jour J et pour afficher un
--  compteur honnête (« 347 / 500 ») plutôt qu'un chiffre inventé.
--
--  Un e-mail ne s'inscrit qu'une fois : la contrainte UNIQUE fait tout le
--  travail de déduplication, sans aller-retour SELECT puis INSERT qui
--  laisserait une fenêtre entre les deux à fort trafic.
-- ============================================================

SET default_storage_engine = InnoDB;

CREATE TABLE IF NOT EXISTS liste_attente (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    email     VARCHAR(190) NOT NULL,
    cree_le   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
