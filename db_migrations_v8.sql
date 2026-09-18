-- ============================================================
--  StudentLink — migration v8
--  Les badges cessent de stocker des emoji et l'ancienne palette
--
--  La colonne « icon » contenait des emoji (🎉 🔥 👥 …) : dessinés par
--  chaque système d'exploitation, insensibles à la couleur demandée, et
--  désalignés de la grille typographique. Elle contient désormais une
--  CLÉ d'icône, résolue en SVG par includes/icons.php.
--
--  La colonne « couleur » portait encore la palette v1 (#E5331A, #2929E8,
--  #C8E52A, #F07820), éliminée partout ailleurs. Elle bascule sur les
--  jetons du système, qui suivent le thème clair comme le thème sombre.
--
--  Idempotente : relançable sans casse.
-- ============================================================

ALTER TABLE badges MODIFY COLUMN icon VARCHAR(32) NULL;
ALTER TABLE badges MODIFY COLUMN couleur VARCHAR(40) NULL;

UPDATE badges SET icon = 'etoile',    couleur = 'var(--lime)'   WHERE code = 'early_bird';
UPDATE badges SET icon = 'trophee',   couleur = 'var(--rouge)'  WHERE code = 'first_event';
UPDATE badges SET icon = 'personnes', couleur = 'var(--bleu)'   WHERE code = 'first_follow';
UPDATE badges SET icon = 'drapeau',   couleur = 'var(--lime)'   WHERE code = 'first_squad';
UPDATE badges SET icon = 'flamme',    couleur = 'var(--orange)' WHERE code = 'five_events';
UPDATE badges SET icon = 'papillon',  couleur = 'var(--rouge)'  WHERE code = 'five_squads';
UPDATE badges SET icon = 'oiseau',    couleur = 'var(--orange)' WHERE code = 'reviewer';
UPDATE badges SET icon = 'piece',     couleur = 'var(--rouge)'  WHERE code = 'saver_50';
UPDATE badges SET icon = 'lune',      couleur = 'var(--bleu)'   WHERE code = 'ten_events';

-- Filet : tout badge ajouté plus tard sans clé reçoit une icône neutre
-- plutôt qu'une case vide au milieu de la grille.
UPDATE badges SET icon = 'etoile' WHERE icon IS NULL OR icon = '' OR icon NOT REGEXP '^[a-z-]+$';
