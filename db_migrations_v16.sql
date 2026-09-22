-- ============================================================
--  StudentLink — migration v16
--  Jetons d'authentification pour l'application mobile
--
--  Le site web s'authentifie par cookie de session : le navigateur le
--  renvoie tout seul, et `protegerEcritureApi()` ajoute par-dessus un
--  jeton CSRF et un contrôle d'origine, parce qu'un cookie part aussi
--  quand la requête vient d'ailleurs.
--
--  Une application native n'a rien de tout cela. Pas de cookie renvoyé
--  automatiquement, donc pas de faille CSRF à couvrir, mais pas non plus
--  de session à reprendre au lancement suivant. Il lui faut un
--  identifiant qu'elle range elle-même et présente à chaque appel :
--  c'est le rôle de cette table.
--
--  CE QUI EST STOCKÉ ICI N'EST PAS LE JETON.
--
--  C'est son empreinte SHA-256. Le jeton en clair n'existe qu'une fois,
--  dans la réponse à la connexion, et n'est plus jamais relisible côté
--  serveur — exactement le raisonnement que l'on applique déjà aux mots
--  de passe. Une copie de la base volée ne donne alors aucune session
--  utilisable : il faudrait inverser le hachage de chaque ligne.
--
--  SHA-256 et non bcrypt, contrairement aux mots de passe : un jeton est
--  256 bits tirés au hasard, pas un mot choisi par un humain. Il n'y a
--  pas de dictionnaire à lui opposer, donc rien à ralentir — et un
--  bcrypt par requête d'API coûterait 100 ms à chaque appel.
--
--  Chaque appareil a sa propre ligne : se déconnecter d'un téléphone ne
--  déconnecte pas les autres, et un appareil perdu se révoque seul.
-- ============================================================

SET default_storage_engine = InnoDB;

CREATE TABLE IF NOT EXISTS api_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,

    -- 64 caractères hexadécimaux : la longueur exacte d'un SHA-256.
    -- UNIQUE sert de garde-fou autant que d'index de recherche — c'est la
    -- colonne sur laquelle chaque requête authentifiée retombe.
    token_hash CHAR(64) NOT NULL UNIQUE,

    -- « iPhone de Arthur », pour que l'utilisateur reconnaisse ses
    -- appareils dans une future liste de sessions actives.
    appareil VARCHAR(120) DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Mise à jour au plus une fois par heure, pas à chaque appel : une
    -- écriture par requête d'API transformerait chaque lecture en
    -- écriture, et le verrou de ligne qui va avec.
    derniere_utilisation DATETIME DEFAULT NULL,

    -- Une session mobile qui ne s'éteint jamais est une session volée qui
    -- ne s'éteint jamais. Trente jours, prolongés à chaque usage.
    expire_le DATETIME NOT NULL,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    -- Pour révoquer tous les jetons d'un compte, et pour le balayage
    -- périodique des jetons périmés par cron/entretien.php.
    INDEX idx_token_user (user_id, expire_le)
) ENGINE=InnoDB;
