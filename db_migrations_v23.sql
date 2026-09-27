-- ============================================================
--  Linkee — migration v23
--  Check-in : horodatage, et qr_code vraiment unique
--
--  checkin_le retient QUAND une personne a ete validee a l'entree — jusqu'ici
--  seul statut='checkin' le disait, sans dire depuis quand, rendant
--  impossible tout « derniers check-in » trie dans le temps.
--
--  qr_code passe d'indexe a UNIQUE : chaque inscription en genere deja un
--  frais (bin2hex(random_bytes(32)), voir api/inscrire.php) donc une
--  collision entre deux evenements du meme etudiant est deja pratiquement
--  impossible — cette contrainte en fait une garantie de la base, pas
--  seulement de l'entropie.
-- ============================================================

SET default_storage_engine = InnoDB;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inscriptions'
             AND COLUMN_NAME = 'checkin_le');
SET @s := IF(@c = 0,
  'ALTER TABLE inscriptions ADD COLUMN checkin_le DATETIME NULL DEFAULT NULL',
  'SELECT "colonne checkin_le deja presente"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Deux ALTER TABLE distincts (retirer, puis reposer) : un DROP INDEX et un
-- ADD UNIQUE KEY dans la meme instruction s'est deja revele fragile sur
-- TiDB pour un autre cas (v20) ; ici le risque est moindre puisque qr_code
-- n'est pas une colonne nouvelle, mais autant garder le meme reflexe.
SET @retirer := (SELECT COUNT(*) FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inscriptions'
                    AND INDEX_NAME = 'idx_insc_qr' AND NON_UNIQUE = 1);
SET @s := IF(@retirer > 0,
  'ALTER TABLE inscriptions DROP INDEX idx_insc_qr',
  'SELECT "idx_insc_qr deja absent ou deja unique"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @absent := (SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inscriptions'
                   AND INDEX_NAME = 'idx_insc_qr');
SET @s := IF(@absent = 0,
  'ALTER TABLE inscriptions ADD UNIQUE KEY idx_insc_qr (qr_code)',
  'SELECT "idx_insc_qr deja present"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
