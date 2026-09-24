-- ============================================================
--  Linkee — migration v15
--  Les centres d'intérêt deviennent indexables
--
--  Le problème, en une phrase : `users.interests` est une chaîne
--  « techno,rock,sport », et aucun index au monde ne peut servir une
--  recherche à l'intérieur d'une chaîne.
--
--  Concrètement, l'annuaire classe les profils par nombre d'intérêts
--  communs. Écrit sur la colonne texte, cela donne un FIND_IN_SET par
--  intérêt et par profil :
--
--      (FIND_IN_SET(?, REPLACE(u.interests, ', ', ',')) > 0) + …
--
--  MySQL n'a alors pas d'autre choix que de lire les cinq mille comptes,
--  d'exécuter ces fonctions sur chacun, puis de trier le tout — pour en
--  afficher vingt-quatre. Mesuré sur 5 000 étudiants : 11 ms par requête
--  de classement, et il y en a deux. Ce coût croît linéairement avec les
--  inscriptions : à 20 000 comptes, c'est 45 ms de processeur par
--  affichage de page, multipliés par le nombre de visiteurs.
--
--  Cette table range la même information sous une forme que la base sait
--  indexer. Le score devient un comptage sur index, restreint d'emblée
--  aux profils qui partagent au moins un goût.
--
--  `users.interests` RESTE la source de vérité : l'application continue
--  de l'écrire, et cette table est tenue à jour en même temps (voir
--  synchroniserInterets() dans includes/interets.php). Deux raisons :
--  les gabarits la lisent directement pour afficher les étiquettes, et
--  une table dérivée qui se désynchronise doit pouvoir être reconstruite
--  depuis l'original — ce que fait la section 2 ci-dessous, relançable à
--  volonté.
--
--  Idempotente : relançable sans casse.
-- ============================================================

-- ─── 1. La table ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS user_interets (
    user_id INT         NOT NULL,
    interet VARCHAR(40) NOT NULL,
    PRIMARY KEY (user_id, interet),
    -- L'index qui fait tout le travail : « qui aime la techno ? » se lit
    -- ici, dans l'ordre, sans jamais toucher à la table des comptes.
    -- (interet, user_id) et non (interet) seul : la requête de score ne
    -- lit que ces deux colonnes, elle est donc entièrement couverte.
    KEY k_interet (interet, user_id),
    CONSTRAINT fk_ui_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 2. Reconstruction depuis la colonne texte ──────────────────────────────
--
-- Découpe « techno, rock, sport » en autant de lignes. La table de
-- nombres fournit les positions : n vaut 1 pour le premier élément, 2
-- pour le deuxième, et ainsi de suite. La jointure s'arrête au nombre
-- d'éléments réellement présents, calculé en comparant la longueur de la
-- chaîne avec et sans ses virgules.
--
-- 24 positions : c'est la taille du catalogue d'intérêts
-- (includes/interets.php). Personne ne peut en cocher davantage.
--
-- INSERT IGNORE : relancer cette migration ne crée pas de doublon, et
-- elle peut servir de réparation si la table dérivée dérive un jour.

INSERT IGNORE INTO user_interets (user_id, interet)
SELECT decoupe.user_id, decoupe.interet FROM (
SELECT u.id AS user_id,
       TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(u.interests, ', ', ','), ',', n.n), ',', -1)) AS interet
  FROM users u
  JOIN (
        SELECT 1 AS n UNION ALL SELECT 2  UNION ALL SELECT 3  UNION ALL SELECT 4
        UNION ALL SELECT 5  UNION ALL SELECT 6  UNION ALL SELECT 7  UNION ALL SELECT 8
        UNION ALL SELECT 9  UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
        UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16
        UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20
        UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24
       ) n
    ON n.n <= 1 + LENGTH(REPLACE(u.interests, ', ', ','))
                - LENGTH(REPLACE(REPLACE(u.interests, ', ', ','), ',', ''))
 WHERE u.interests IS NOT NULL
   AND u.interests <> ''
) AS decoupe
-- Une chaine « techno,,rock » ou une virgule finale produit un element vide :
-- il n'a rien a faire dans la table, et la cle primaire ne l'interdit pas.
WHERE decoupe.interet <> '';
