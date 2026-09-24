-- ============================================================
--  Linkee — migration v17
--  Notifications lues (« Tout lire »)
--
--  Les notifications ne sont pas stockées : notificationsEtudiant()
--  les recompose à chaque affichage à partir des demandes, invitations
--  et activités. Il n'y a donc pas de ligne à marquer « lue » une à une.
--  Ce qu'il faut retenir, c'est un instant : tout ce qui est arrivé avant
--  est lu, tout ce qui arrive après est nouveau.
--
--  Une table à part plutôt qu'une colonne dans `users` : MySQL 8 n'a pas
--  d'« ADD COLUMN IF NOT EXISTS », et chaque migration du dépôt doit
--  pouvoir être rejouée sans erreur. CREATE TABLE IF NOT EXISTS l'est.
--
--  Aucune ligne pour un étudiant = il n'a jamais tout lu : ses
--  notifications s'affichent comme nouvelles, exactement comme avant
--  cette migration.
-- ============================================================

SET default_storage_engine = InnoDB;

CREATE TABLE IF NOT EXISTS notifications_lues (
    user_id INT NOT NULL PRIMARY KEY,
    lues_le DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
