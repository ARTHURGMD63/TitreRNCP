# Mettre Linkee en ligne, et l'application entre les mains de testeurs

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
| `includes/db.php` | Lit `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE` ; TLS avec `MYSQL_SSL` / `MYSQL_SSL_CA` |
| `outils/installer.php` | Installe le schéma sur **n'importe quelle** base, TiDB compris |
| `.github/workflows/mobile.yml` | Construit Linkee.apk et Linkee.ipa (non signé) |

Rien n'est à écrire en dur : la plateforme fournit les variables, le code les
lit. C'est déjà le cas depuis la refonte de `includes/config.php`.

### La voie gratuite : TiDB Cloud (base) + Render (serveur)

**TiDB Cloud Starter** (anciennement « Serverless ») est une base compatible
MySQL, avec une offre gratuite sans carte bancaire. Le code tourne tel quel :
seule l'adresse de la base change. Vérifié le 2026-09-24 : installation
complète, puis parcours de toutes les pages (étudiant, partenaire, admin) et de
l'API mobile avec le mode SQL de TiDB (`ONLY_FULL_GROUP_BY`, strict), sans
une erreur.

Deux différences avec MySQL, déjà prises en charge :

- **Connexion chiffrée obligatoire.** `includes/db.php` active TLS de lui-même
  pour un hôte `*.tidbcloud.com` (et sur demande avec `MYSQL_SSL=1`), en
  vérifiant le certificat avec les autorités du système.
- **Pas de procédures stockées.** Seules les anciennes migrations v7 et v18 en
  utilisent, et une installation neuve ne les rejoue pas. `outils/installer.php`
  exécute directement les ajouts d'index que `db_setup.sql` prépare en SQL.

**1. Créer la base.** Sur [tidbcloud.com](https://tidbcloud.com) : créer un
cluster *Starter* (région Frankfurt, la plus proche). Dans *Connect*, noter
l'hôte (`gateway01.eu-central-1.prod.aws.tidbcloud.com`), le port `4000`,
l'utilisateur (`xxxxxxxx.root`), et générer le mot de passe. Créer la base
dans l'onglet *SQL Editor* :

```sql
CREATE DATABASE linkee CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**2. Installer le schéma** depuis ce PC (PowerShell, à la racine du projet) :

```powershell
$env:MYSQLHOST = 'gateway01.eu-central-1.prod.aws.tidbcloud.com'
$env:MYSQLPORT = '4000'
$env:MYSQLUSER = 'xxxxxxxx.root'
$env:MYSQLPASSWORD = 'le-mot-de-passe'
$env:MYSQLDATABASE = 'linkee'
$env:MYSQL_SSL_CA = 'C:\chemin\vers\isrgrootx1.pem'
C:\wamp64\bin\php\php8.3.14\php.exe outils\installer.php
```

Windows n'a pas de fichier d'autorités de certification que PHP sache lire :
télécharger le certificat racine indiqué par TiDB dans *Connect* (ISRG Root X1)
et donner son chemin dans `MYSQL_SSL_CA`. Sur le serveur Linux (Render), rien
à faire : celui du système est pris automatiquement.

**3. Mettre le serveur en ligne.** Sur [render.com](https://render.com) :
*New → Web Service → Build from a Git repository*, choisir le dépôt, runtime
**Docker**, offre **Free**. Variables d'environnement :

```
APP_ENV=production
MYSQLHOST=gateway01.eu-central-1.prod.aws.tidbcloud.com
MYSQLPORT=4000
MYSQLUSER=xxxxxxxx.root
MYSQLPASSWORD=le-mot-de-passe
MYSQLDATABASE=linkee
```

Render construit l'image du `Dockerfile` et donne une adresse
`https://linkee-xxxx.onrender.com`. Le site est à la racine de cette adresse.

**4. Vérifier.**

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://VOTRE-URL/auth/login.php
curl -s -X POST https://VOTRE-URL/api/v1/login.php \
     -H 'Content-Type: application/json' \
     -d '{"email":"arthur@uca.fr","password":"password"}'
```

Le second doit renvoyer un jeton.

**Limite de l'offre gratuite de Render** : le service s'endort après 15
minutes sans visite, et le premier appel suivant attend environ une minute.
Suffisant pour des tests ; pas pour de vrais utilisateurs.

### Autre voie : Railway

*New Project → Deploy from GitHub repo*, puis *New → Database → MySQL* :
Railway injecte les variables `MYSQL*` lui-même. Installer ensuite le schéma
avec `php outils/installer.php` depuis un terminal Railway, et poser
`APP_ENV=production`. Railway n'est plus gratuit au-delà de son crédit
d'essai.

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

### Voie A — Sideloadly (iPhone) et APK (Android), gratuit

L'application est construite sur les machines de GitHub par le workflow
`.github/workflows/mobile.yml` : aucun Mac, aucun compte payant.

1. Dans le dépôt GitHub : *Settings → Secrets and variables → Actions →
   Variables → New repository variable* : `EXPO_PUBLIC_API_URL` =
   l'adresse Render, en https, sans barre finale.
2. *Actions → Application mobile → Run workflow*. Compter une vingtaine de
   minutes ; deux fichiers apparaissent en bas de la page du run :
   `linkee-android` (Linkee.apk) et `linkee-ios` (Linkee.ipa, non signé).

**Android** : envoyer l'APK aux testeurs ; à l'ouverture, autoriser
l'installation depuis cette source. Aucune expiration.

**iPhone avec Sideloadly** : brancher l'iPhone en USB au PC où tourne
[Sideloadly](https://sideloadly.io) (iTunes et iCloud installés depuis le site
d'Apple, pas depuis le Microsoft Store), glisser `Linkee.ipa`, saisir un
identifiant Apple, *Start*. Sur l'iPhone : *Réglages → Général → VPN et
gestion de l'appareil* → faire confiance au profil, puis activer le *Mode
développeur* (iOS 16 et plus).

Limites d'un identifiant Apple gratuit :

- l'application **expire au bout de 7 jours** : il faut la réinstaller avec
  Sideloadly (qui sait le refaire tout seul si le PC reste allumé, avec
  l'option d'actualisation automatique) ;
- **3 applications** installées ainsi par iPhone, au plus ;
- chaque iPhone doit passer par un PC avec Sideloadly.

L'adresse du serveur est inscrite dans l'application : la changer demande de
relancer le workflow et de réinstaller.

### Voie B — Expo Go (gratuit, sans installer l'application)

Chaque testeur installe **Expo Go** depuis l'App Store ou le Play Store, puis
ouvre un lien. Pas d'expiration à 7 jours, pas de câble : tous les modules
utilisés par l'application sont inclus dans Expo Go.

```bash
cd mobile
npx eas-cli@latest login          # compte Expo gratuit
npx eas-cli@latest update --branch preview --message "version de test"
```

avec `EXPO_PUBLIC_API_URL=https://VOTRE-URL` dans `mobile/.env` avant de
publier. Expo Go ne charge que la version du SDK d'Expo qu'il embarque : si
l'App Store propose une version plus récente que celle du projet, il faudra
mettre le projet à jour.

### Voie C — TestFlight (le vrai chemin vers l'App Store)

Nécessite le **compte développeur Apple (99 $/an)**, mais **pas de Mac** :
EAS construit l'application iOS dans le cloud depuis Windows.

```bash
cd mobile
npx eas-cli@latest build --platform ios --profile preview
npx eas-cli@latest submit --platform ios
```

TestFlight accepte jusqu'à 10 000 testeurs, les builds restent valides 90
jours, et l'installation se fait par simple lien.

---

## 3. Dans quel ordre

1. **TiDB Cloud puis Render** — sans serveur en ligne, rien d'autre n'est
   testable ailleurs que sur le réseau local.
2. **Le workflow « Application mobile »**, puis Sideloadly et l'APK.
3. **Compte Apple et TestFlight** — quand le produit mérite d'être montré à des
   gens qui ne sont pas dans la confidence.
4. **Le stockage des photos**, avant d'ouvrir à de vrais utilisateurs.
