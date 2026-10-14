-- ============================================================
--  Linkee — migration v27
--  Suppression de compte : le type « supprime ».
--
--  Supprimer un compte étudiant efface ses données personnelles mais garde la
--  ligne `users` : ses présences passées (inscriptions, check-in) alimentent
--  les statistiques des partenaires, et une ligne qui disparaît les ferait
--  disparaître avec elle. La ligne est vidée de toute identité (voir
--  includes/compte.php) et reçoit ce type.
--
--  Le type fait le reste : l'annuaire, le classement et les compteurs
--  d'étudiants filtrent déjà sur type = 'etudiant', donc un compte supprimé
--  n'y apparaît nulle part, sans qu'aucune de ces requêtes ait à changer. Et
--  aucune garde d'accès ne l'admet.
--
--  Rejouable sans effet : redéclarer la même liste de valeurs ne change rien.
-- ============================================================

SET default_storage_engine = InnoDB;

ALTER TABLE users
    MODIFY COLUMN type ENUM('etudiant', 'partenaire', 'admin', 'supprime') DEFAULT 'etudiant';
