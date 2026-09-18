-- ============================================================
--  StudentLink — migration v10
--  Date de naissance et acceptation des CGU
--
--  Les CGU annoncent une application « réservée aux étudiants
--  majeurs », mais rien dans le produit ne le vérifiait : trois champs
--  suffisaient pour créer un compte donnant accès à des événements en
--  bar et en discothèque. On collecte donc la date de naissance à
--  l'inscription, et le contrôle des 18 ans se fait côté serveur.
--
--  L'horodatage d'acceptation des CGU sert de preuve : une case cochée
--  sans trace en base ne prouve rien.
--
--  Les deux colonnes sont NULL : les comptes déjà créés n'ont pas de
--  date de naissance, et une valeur inventée serait pire que l'absence.
--
--  Idempotente : relançable sans casse.
-- ============================================================

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'users'
             AND COLUMN_NAME = 'date_naissance');
SET @s := IF(@c = 0,
  'ALTER TABLE users
     ADD COLUMN date_naissance DATE NULL DEFAULT NULL AFTER promo,
     ADD COLUMN cgu_acceptees_le DATETIME NULL DEFAULT NULL AFTER date_naissance',
  'SELECT "colonnes date_naissance / cgu_acceptees_le deja presentes"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
