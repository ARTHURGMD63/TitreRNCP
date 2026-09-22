# Mettre StudentLink en ligne, et l'application entre les mains de testeurs

Deux choses distinctes, dans cet ordre : **le serveur** (l'API et la base, sans
quoi aucun téléphone ne peut rien afficher), puis **la distribution** de
l'application.

Tout ce qui suit a été vérifié en local, image Docker construite et lancée
contre une base nommée `railway` — c'est-à-dire dans les conditions de
l'hébergeur, pas dans celles du poste de développement.

---

## 1. Le serveur

### Ce qui est déjà prêt dans le dépôt

| Fichier | Rôle |
| --- | --- |
| `Dockerfile` | Apache + mod_php, OPcache, GD. Image vérifiée : 425 Mo |
| `docker/entrypoint.sh` | Fait écouter Apache sur le `$PORT` imposé par la plateforme |
| `includes/db.php` | Lit `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE` |
| `outils/installer.php` | Installe le schéma sur **n'importe quelle** base |

Rien n'est à écrire en dur : la plateforme fournit les variables, le code les
lit. C'est déjà le cas depuis la refonte de `includes/config.php`.

### Les étapes

**1. Créer le projet.** Sur Railway : *New Project → Deploy from GitHub repo*,
choisir le dépôt. Railway détecte le `Dockerfile` et construit l'image.

**2. Ajouter la base.** *New → Database → MySQL*. Railway crée les variables
`MYSQL*` et les injecte dans le service web — `includes/db.php` les lit sans
configuration supplémentaire.

**3. Installer le schéma.** Depuis un terminal Railway (ou en local, avec les
variables de la base distante) :

```bash
php outils/installer.php --etat    # ce que contient la base
php outils/installer.php           # installe les 27 tables et le jeu de démo
```

> **Pourquoi pas `db_setup.sql` directement.** Ses deux premières lignes sont
> `CREATE DATABASE … studentlink;` et `USE studentlink;`. Sur un hébergeur, le
> nom de la base est imposé — `railway` — et le compte n'a en général pas le
> droit d'en créer une. Le fichier bascule alors sur une base inexistante, ou
> pire, sur une autre base du même serveur qui, elle, existe. Diriger `mysql`
> vers la bonne base ne suffit pas : le script bascule à la ligne 16 quoi qu'on
> ait demandé sur la ligne de commande. `outils/installer.php` neutralise ces
> deux lignes et exécute le reste sur la connexion déjà ouverte.

**4. Poser les variables d'environnement** du service web :

```
APP_ENV=production
```

`APP_ENV=production` coupe l'affichage des erreurs détaillées et active le
drapeau `secure` du cookie de session. À ne pas oublier : c'est la différence
entre une trace d'erreur affichée au visiteur et une trace journalisée.

**5. Vérifier.** Railway attribue une URL en `https://…up.railway.app`.

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://VOTRE-URL/auth/login.php
curl -s -X POST https://VOTRE-URL/api/v1/login.php \
     -H 'Content-Type: application/json' \
     -d '{"email":"arthur@uca.fr","password":"password"}'
```

Le second doit renvoyer un jeton. S'il renvoie 401 avec `jeton_absent` sur les
appels suivants, c'est qu'Apache ne transmet pas l'en-tête `Authorization` :
`api/v1/.htaccess` s'en charge, et l'image active `mod_rewrite`, mais cela se
vérifie en premier.

### Ce que l'hébergement change immédiatement

- **HTTPS**, donc le **scan des QR codes fonctionne sur téléphone** : la caméra
  est refusée hors contexte sécurisé, c'est ce qui bloquait jusqu'ici.
- Les téléphones n'ont plus besoin d'être sur le même réseau que le PC — le
  point d'accès mobile n'est plus nécessaire.

### Ce qu'il reste à régler, et ce n'est pas un détail

**Les photos envoyées disparaissent à chaque redéploiement.** Elles sont
écrites dans `uploads/`, c'est-à-dire dans le conteneur, qui est recréé à
chaque mise en ligne. Avatars et photos d'établissement seront perdus.

C'est déjà noté dans `docker/entrypoint.sh`. La correction demande un stockage
objet externe (S3, Cloudflare R2, volume Railway). À traiter avant d'inviter de
vrais utilisateurs à mettre leur photo.

---

## 2. L'application sur les téléphones

Deux voies, selon ce qu'on veut faire tester et à qui.

### Voie A — Expo Go (gratuit, immédiat)

Chaque testeur installe **Expo Go** depuis l'App Store, puis ouvre un lien.
Aucun compte développeur, aucun Mac, et les testeurs n'ont pas besoin d'être
sur le même réseau.

```bash
cd mobile
npx eas-cli@latest login          # compte Expo gratuit
npx eas-cli@latest update --branch preview --message "premiere version de test"
```

**Avant de publier**, renseigner l'adresse du serveur — sans quoi l'application
cherchera le PC de développement :

```bash
# mobile/.env
EXPO_PUBLIC_API_URL=https://VOTRE-URL.up.railway.app
```

Ce qu'Expo Go ne permet pas : les modules natifs qu'il n'embarque pas. Tout ce
que l'application utilise aujourd'hui y est (trousseau sécurisé, SVG, QR), mais
Apple Wallet et les notifications natives demanderont la voie B.

### Voie B — TestFlight (le vrai chemin vers l'App Store)

Nécessite le **compte développeur Apple (99 $/an)**. En revanche, **pas de
Mac** : EAS construit l'application iOS dans le cloud depuis Windows.

```bash
cd mobile
npx eas-cli@latest build --platform ios --profile preview
npx eas-cli@latest submit --platform ios
```

TestFlight accepte jusqu'à 10 000 testeurs, les builds restent valides 90
jours, et l'installation se fait par simple lien — sans câble ni réinstallation
tous les sept jours, contrairement au sideloading avec un compte gratuit.

C'est aussi le passage obligé vers la publication : autant y aller directement
plutôt que de faire un détour.

---

## 3. Dans quel ordre

1. **Railway** — sans serveur en ligne, rien d'autre n'est testable ailleurs
   que sur le réseau local.
2. **Expo Go** — pour faire essayer l'application dans la journée, sans
   dépenser un euro.
3. **Compte Apple et TestFlight** — quand le produit mérite d'être montré à des
   gens qui ne sont pas dans la confidence.
4. **Le stockage des photos**, avant d'ouvrir à de vrais utilisateurs.
