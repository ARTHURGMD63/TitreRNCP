-- ============================================================
--  StudentLink — migration v13
--  Style de musique d'un événement
--
--  « Où sortir ce soir » se décide autant sur la musique que sur le
--  lieu : une soirée techno et un karaoké dans le même bar ne visent
--  pas les mêmes gens. Le partenaire choisit le style à la création,
--  l'étudiant filtre le fil Explore dessus.
--
--  VARCHAR et non ENUM, contrairement à `evenements.type` : les styles
--  suivent les modes, et ajouter « Amapiano » l'an prochain ne doit pas
--  demander une migration. Le catalogue qui fait foi est en PHP
--  (`includes/musique.php`), et tout ce qui vient d'un formulaire y est
--  revalidé — la colonne ne fait que stocker.
--
--  NULL est un état légitime : un restaurant ou un afterwork n'a pas
--  toujours de style à annoncer. Les soirées déjà enregistrées le
--  restent, et leurs partenaires le renseigneront à la prochaine
--  modification.
--
--  Idempotente : relançable sans casse.
-- ============================================================

-- ─── 1. La colonne ──────────────────────────────────────────────────────────

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'evenements'
             AND COLUMN_NAME = 'style_musique');
SET @s := IF(@c = 0,
  'ALTER TABLE evenements
     ADD COLUMN style_musique VARCHAR(20) NULL DEFAULT NULL AFTER type',
  'SELECT "colonne style_musique deja presente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ─── 2. L'index du filtre ───────────────────────────────────────────────────
--
-- Explore filtre sur le style ET ne montre que les soirées à venir. Le
-- couple (style, date) sert la sélection et le tri d'un seul index ;
-- sur le style seul, la base aurait relu puis trié tout un genre.

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'evenements'
             AND INDEX_NAME = 'idx_style_musique');
SET @s := IF(@i = 0,
  'ALTER TABLE evenements ADD INDEX idx_style_musique (style_musique, date_heure)',
  'SELECT "index idx_style_musique deja present"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
