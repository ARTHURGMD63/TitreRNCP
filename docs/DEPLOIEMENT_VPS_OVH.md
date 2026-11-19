# Mise en production sur le VPS OVH

**État au 18/11/2026 : le VPS n'est pas encore installé.** L'API de l'application tourne aujourd'hui sur Render (image Docker, Apache, `APP_ENV=production` et `APP_URL` réglés dans Render), avec une base TiDB Cloud. L'ancien hébergement mutualisé (FTP) existe encore ; son workflow ne se lance plus qu'à la main. Ce document décrit la bascule.

Ce document remplace, pour la production, l'hébergement mutualisé (FTP), Render et TiDB Cloud. Un seul serveur OVH VPS-4 (Ubuntu 24.04, 12 vCores, 48 Go) héberge le site vitrine, l'espace pro des établissements, l'API de l'application et la base MariaDB. Le domaine est chez OVH.

```
 Application (iOS, Android) ──┐
                              ├── https://<domaine>/api/v1/…       ┐
 Établissements (navigateur) ─┤   https://<domaine>/partenaire/…   ├─ nginx → PHP-FPM → MariaDB (locale)
                              └── https://<domaine>/                ┘
 Stripe ── webhooks ────────────► https://<domaine>/webhooks/stripe.php
```

Les fichiers de configuration sont dans `deploy/` ; ils ne servent qu'au serveur.

| Fichier | Rôle |
|---|---|
| `deploy/installer_serveur.sh` | Prépare le VPS neuf ; peut être relancé (mot de passe de la base et certificat conservés) |
| `deploy/appliquer_config.sh` | Réinstalle, en root, cron, nginx, PHP-FPM, logrotate et la sauvegarde depuis une version |
| `deploy/nginx/outsee.conf`, `outsee-php.conf`, `outsee-entetes.conf` | Site HTTPS, protections (remplacent les `.htaccess`), en-têtes de sécurité |
| `deploy/php/outsee-fpm.conf`, `99-outsee.ini` | PHP-FPM (80 processus), OPcache, APCu, sessions |
| `deploy/mariadb/99-outsee.cnf` | Base locale, 12 Go de cache, requêtes lentes journalisées |
| `deploy/cron/outsee` | Stripe (5 min), push, rappels, entretien, sauvegarde, surveillance ; chaque ligne sous `flock` |
| `deploy/logrotate/outsee` | Rotation hebdomadaire de `/var/log/outsee` et `/var/log/outsee-sauvegarde`, 8 semaines gardées |
| `deploy/deployer.sh` | Sauvegarde de la base, migrations, bascule atomique, retour arrière automatique |
| `deploy/sauvegarder.sh` | Sauvegarde nocturne base + photos, copie distante chiffrée (installée en `/usr/local/sbin/outsee-sauvegarde`) |
| `deploy/exclusions.txt` | Ce qui ne part jamais sur le serveur |
| `.github/workflows/deploiement-vps.yml` | Le déploiement, lancé depuis l'onglet Actions, depuis `main` seulement |

---

## 1. Commander

1. **VPS-4** chez OVH, image **Ubuntu 24.04 LTS**, avec ta clé SSH publique. Noter l'adresse IPv4 et IPv6.
2. **Domaine** chez OVH. Dans sa zone DNS :
   - `A` et `AAAA` pour `@` et `www` → les adresses du VPS ;
   - la messagerie (offre e-mail OVH ou autre) : `MX`, puis **SPF, DKIM et DMARC** comme décrit dans `docs/EMAIL.md`. Sans eux, les e-mails de réinitialisation de mot de passe arrivent en indésirables.
3. Attendre que `ping <domaine>` réponde avec l'adresse du VPS avant l'étape 3 (Let's Encrypt en a besoin).

## 2. Premier accès

```bash
ssh ubuntu@<ip-du-vps>
```

Dans `/etc/ssh/sshd_config` : `PasswordAuthentication no` et `PermitRootLogin prohibit-password`, puis `sudo systemctl reload ssh`. Garder la session ouverte et vérifier qu'une seconde connexion fonctionne avant de fermer.

## 3. Préparer le serveur

Depuis le poste de développement, copier le dossier `deploy/` sur le VPS puis lancer le script :

```bash
scp -r deploy ubuntu@<ip-du-vps>:~/deploy
```

```bash
ssh ubuntu@<ip-du-vps> "sudo bash ~/deploy/installer_serveur.sh <domaine> <email-pour-letsencrypt>"
```

Le script installe nginx, PHP 8.3-FPM (avec APCu), MariaDB et certbot. Il ferme le pare-feu sauf SSH, HTTP et HTTPS, et active fail2ban et les mises à jour de sécurité automatiques. Il crée le compte `deploy`, la base `outsee` et son utilisateur à droits limités, ainsi que `/var/www/outsee/shared/config.local.php`, avec le mot de passe de la base déjà rempli. Il obtient le certificat HTTPS, puis lance `appliquer_config.sh` : nginx, PHP-FPM, tâches planifiées, rotation des journaux, et la sauvegarde copiée en `root:root` dans `/usr/local/sbin/outsee-sauvegarde`.

Le relancer ne casse rien : si `config.local.php` existe, le mot de passe de la base est gardé ; si le certificat existe, il n'est pas redemandé.

### Mettre à jour la configuration système

`deployer.sh` ne copie que le code. Quand une version change un fichier de `deploy/` (une ligne de cron, un réglage nginx ou PHP, la sauvegarde), il le signale à la fin du déploiement (« diffère : /etc/cron.d/outsee ») sans l'installer. Après avoir relu le contenu de `deploy/` de cette version :

```bash
sudo bash /var/www/outsee/current/deploy/appliquer_config.sh <domaine>
```

Ce script n'est volontairement **pas** autorisé au compte `deploy` dans le sudoers : il écrit `/etc/cron.d` et `/usr/local/sbin`, donc pouvoir le lancer avec des fichiers qu'on a soi-même déposés reviendrait à donner root à quiconque détient la clé SSH de GitHub. Pour la même raison, la tâche root de sauvegarde n'exécute jamais un fichier de `/var/www/outsee`.

## 4. Réglages de l'application

Sur le VPS : `sudo nano /var/www/outsee/shared/config.local.php` (modèle commenté : `deploy/config.local.production.example.php`). À remplir :

- `stripe_secret`, `stripe_webhook`, `stripe_taux_tva` : voir l'étape 6 ;
- SMTP (`mail_smtp_*`) : la boîte `noreply@<domaine>` ;
- `alerte_email` : la boîte qui reçoit les alertes de panne et de sauvegarde manquée (lue chaque jour) ;
- `app_store_url`, `play_store_url` une fois l'application publiée (la vitrine affiche « Bientôt » d'ici là).

`ip_entete` vaut `aucun` : nginx reçoit le visiteur directement et transmet sa vraie adresse dans `REMOTE_ADDR`. Si Cloudflare est placé devant le VPS, activer `real_ip` dans `deploy/nginx/outsee.conf` plutôt que de toucher à ce réglage ; `ip_entete` et `proxy_confiance` ne servent que lorsqu'un proxy est placé entre nginx et PHP.

`app_env` vaut `production` et `url_publique` vaut `https://<domaine>` : ne pas les changer. Ce sont eux qui empêchent d'afficher les erreurs, et de construire un lien de réinitialisation à partir de l'en-tête `Host` envoyé par le client.

## 5. Reprendre les données de la base actuelle (TiDB Cloud)

Depuis le terminal Windows, avec les identifiants de production affichés par Render (base `linkee`) :

```bash
mysqldump -h <hote-tidb> -P 4000 -u <utilisateur> -p --ssl-mode=REQUIRED --single-transaction --skip-lock-tables --no-tablespaces --set-gtid-purged=OFF linkee > linkee_prod.sql
```

```bash
scp linkee_prod.sql ubuntu@<ip-du-vps>:~
```

```bash
ssh ubuntu@<ip-du-vps> "sudo mariadb outsee < ~/linkee_prod.sql && rm ~/linkee_prod.sql"
```

La migration v33 (Stripe et index de connexion) s'appliquera au premier déploiement.

**Avant l'ouverture, vérifier qu'aucun compte de démonstration ne subsiste.** Ces comptes ont pour mot de passe « password » et viennent de `db_setup.sql`. Pour les trouver :

```sql
SELECT id, email, type FROM users WHERE password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
```

Supprimer chacun de ces comptes ou changer son mot de passe. Ne jamais importer `db_setup.sql` sur le serveur.

**Photos.** L'offre gratuite de Render n'a pas de disque persistant : les photos envoyées jusqu'ici ont pu disparaître à chaque redéploiement. S'il en reste, les copier dans `/var/www/outsee/shared/uploads/` (en conservant les sous-dossiers) puis lancer `sudo chown -R www-data:www-data /var/www/outsee/shared/uploads`.

## 6. Stripe

Dans le tableau de bord Stripe, en **mode test** d'abord :

1. **Moyens de paiement** : activer la carte et le **prélèvement SEPA**.
2. **Taxes** : créer un taux « TVA 20 % », non inclus dans le prix (les tarifs Outsee sont HT). Son identifiant `txr_…` va dans `stripe_taux_tva`.
3. **Facturation → Espace client** : autoriser le changement de moyen de paiement, l'historique des factures et la résiliation **en fin de période**. Ne pas autoriser le changement de formule : il se fait sur le site, qui contrôle les règles (une fois par mois, place fondateur, type de compte).
4. **Facturation → Relances** : activer les relances automatiques (Smart Retries) et choisir **annuler l'abonnement** quand toutes les relances ont échoué. L'espace pro affiche l'alerte d'impayé pendant les relances, et la formule se ferme à l'annulation.
5. **Factures** : raison sociale, adresse, numéro de TVA ; activer l'envoi des factures et des reçus par e-mail.
6. **Développeurs → Webhooks** : ajouter le point de terminaison `https://<domaine>/webhooks/stripe.php` avec les événements :
   `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.expired`, `customer.subscription.created`, `customer.subscription.updated`, `customer.subscription.deleted`, `invoice.paid`, `invoice.payment_failed`.
   Son secret de signature (`whsec_…`) va dans `stripe_webhook`.
7. **Développeurs → Clés API** : la clé secrète va dans `stripe_secret`.

Essai complet en mode test :
- souscrire depuis l'espace pro avec la carte `4242 4242 4242 4242`, puis avec le prélèvement SEPA de test ;
- vérifier la formule dans l'espace pro, puis dans l'application (après rechargement de l'écran Formule) ;
- publier un événement au-delà du quota sur le site : la page de paiement Stripe s'ouvre, et l'événement n'apparaît qu'une fois la carte de test acceptée ; même chose pour une mise en avant payante ;
- résilier depuis « Gérer mon paiement et mes factures ».

Ensuite, refaire les étapes 1 à 7 en **mode production**, et remplacer les trois valeurs `sk_test` / `whsec` / `txr` par celles de production.

Le parcours côté code est décrit en tête de `includes/stripe.php`.

## 7. GitHub et premier déploiement

1. Générer la clé de déploiement sur le poste :

   ```bash
   ssh-keygen -t ed25519 -f outsee_deploy -N "" -C "deploiement-outsee"
   ```

   Ajouter le contenu de `outsee_deploy.pub` à `/home/deploy/.ssh/authorized_keys` sur le VPS.
2. Dans GitHub, **Settings → Secrets and variables → Actions** :
   - variables : `VPS_HOST` (adresse du VPS) et `VPS_PORT` (22) ;
   - secrets : `VPS_SSH_KEY` (contenu de `outsee_deploy`) et `VPS_KNOWN_HOSTS` (résultat de `ssh-keyscan -t ed25519 <ip-du-vps>`).

   Supprimer ensuite le fichier `outsee_deploy` du poste.
3. **Settings → Environments → New environment** : `production`. Y cocher **Required reviewers** (soi-même au minimum) et **Deployment branches : main** ; y déplacer de préférence les secrets `VPS_SSH_KEY` et `VPS_KNOWN_HOSTS`, qui ne sont alors lisibles que par un déploiement approuvé.
4. **Actions → Déploiement VPS → Run workflow**, sur `main` (sur une autre branche, le déploiement est sauté). Le workflow :
   - vérifie la syntaxe PHP et passe les tests unitaires (PHPUnit, suite `Unit`) ; le déploiement attend ce résultat ;
   - attend l'approbation de l'environnement `production` ;
   - copie la version sans les fichiers de `deploy/exclusions.txt` ;
   - sauvegarde la base dans `/var/www/outsee/shared/sauvegardes_deploiement/avant_<version>.sql.gz` (dix gardées) ;
   - applique les migrations (un verrou empêche deux passages simultanés) ;
   - bascule le lien `current` ;
   - recharge PHP ;
   - contrôle que `https://<domaine>/sante.php` répond 200, et sinon revient à la version qui était en service juste avant (les migrations, elles, restent : voir la restauration à l'étape 9) ;
   - signale les fichiers de configuration système à réappliquer (étape 3).
5. **Supprimer l'ancien déploiement FTP** au moment de la bascule : `.github/workflows/deploiement.yml` (commit `ci:` dédié), puis les secrets `FTP_*` et `DB_*` et les variables `FTP_APP_ENV` / `FTP_URL_PUBLIQUE`. D'ici là, il ne part plus qu'à la main.
6. Une fois le VPS en service, arrêter le service Render et la base TiDB Cloud.

## 8. Application mobile

1. Dans GitHub, variable `EXPO_PUBLIC_API_URL` = `https://<domaine>`. Elle est inscrite dans l'application au moment du build : chaque build fait avant ce changement continue d'appeler Render.
2. Construire : `git tag -a mobile-vN -m "…"` puis `git push origin mobile-vN`. Le numéro N devient le numéro de build (versionCode Android, buildNumber iOS).
3. Le workflow `mobile.yml` produit un APK et un IPA **de test**, non signés pour les stores. Pour les publier :
   - comptes Apple Developer (99 $/an) et Google Play Console (25 $). Un compte Play personnel récent doit d'abord passer un test fermé de 12 testeurs pendant 14 jours ;
   - `npx eas-cli@latest init`, puis les variables EAS `EXPO_PUBLIC_API_URL` et `GOOGLE_MAPS_ANDROID_API_KEY` ;
   - `eas build -p android --profile production` (AAB signé, Play App Signing) et `eas build -p ios --profile production` ;
   - `eas submit`, après avoir rempli `submit.production` dans `mobile/eas.json` ;
   - fiches des stores : étiquettes de confidentialité Apple et formulaire « Sécurité des données » de Play, classification du contenu, URL de confidentialité `https://<domaine>/confidentialite.php`, et un **compte de démonstration** étudiant et partenaire (avec une formule active) pour les relecteurs ;
   - les visuels Play de `docs/play_store/` sont en RGBA : les aplatir en PNG 24 bits sans transparence.

   **Builds signés : ce qui manque aujourd'hui.** `mobile.yml` n'est pas modifié tant que ces éléments n'existent pas. L'APK actuel est signé avec la clé de débogage du gabarit Expo (n'importe qui peut produire un APK « de mise à jour » signé pareil) et l'IPA n'est pas signé (`CODE_SIGNING_ALLOWED=NO`). Pour publier, il faut l'une des deux voies :
   - **EAS** (le plus simple) : un compte Expo, `extra.eas.projectId` dans `mobile/app.json`, un jeton `EXPO_TOKEN` en secret GitHub si le build part de GitHub. EAS génère et garde la clé Android (keystore d'envoi) et, avec le compte Apple Developer, le certificat de distribution et le profil de provisionnement ;
   - **construction sur GitHub, comme aujourd'hui** : Android, un keystore d'envoi (`keytool -genkeypair … -keyalg RSA -keysize 2048 -validity 10000`) gardé hors du dépôt, en secrets `ANDROID_KEYSTORE_BASE64`, `ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS`, `ANDROID_KEY_PASSWORD`, et `bundleRelease` (AAB) à la place d'`assembleRelease` ; iOS, un certificat « Apple Distribution » (.p12 et son mot de passe), un profil de provisionnement App Store et l'identifiant d'équipe, en secrets, importés dans un trousseau temporaire du runner, puis `xcodebuild -exportArchive` avec un `ExportOptions.plist` (méthode `app-store`).
   - Dans les deux cas, activer **Play App Signing** dans la Play Console (Google garde la clé de signature finale ; perdre la clé d'envoi reste alors réparable) et sauvegarder le keystore et ses mots de passe hors du VPS et hors de GitHub.
4. L'application ne vend rien : formule, événements supplémentaires et mises en avant payantes se prennent sur le site (règles Apple 3.1.1 / 3.1.3, Google Play Billing). Elle affiche l'état du compte, sans prix ni lien d'achat.

## 9. Sauvegardes et surveillance

### Sauvegarde nocturne

`/usr/local/sbin/outsee-sauvegarde` (copie `root:root` de `deploy/sauvegarder.sh`) tourne chaque nuit à 3 h 15 : base et photos, 14 jours dans `/var/backups/outsee` (lisible par root seul). Après chaque sauvegarde réussie, elle écrit la date dans `/var/lib/outsee/derniere_sauvegarde`, que lit la surveillance : au-delà de 36 heures sans sauvegarde, une alerte part. Journal : `/var/log/outsee-sauvegarde/sauvegarde.log`, dans un dossier à root : root n'écrit jamais dans `/var/log/outsee`, que le compte web contrôle. Un ancien `/var/log/outsee/sauvegarde.log` laissé par une installation précédente peut être supprimé.

Chaque déploiement fait en plus une copie de la base juste avant les migrations : `/var/www/outsee/shared/sauvegardes_deploiement/avant_<version>.sql.gz` (les dix dernières).

### Copie distante chiffrée

**Une sauvegarde qui reste sur le VPS ne protège pas d'une panne du VPS.** Sans copie distante, le journal de chaque nuit le rappelle (« ATTENTION : aucune copie distante »). Pour la régler, en root :

1. Choisir le chiffrement. De préférence **age** (`apt install age`) : générer la paire de clés **sur le poste**, jamais sur le VPS (`age-keygen -o outsee-sauvegarde.key`), et ne copier sur le VPS que la clé publique `age1…`. La clé privée se range hors du VPS (gestionnaire de mots de passe, coffre). À défaut, **gpg** symétrique : une phrase longue dans `/etc/outsee/sauvegarde.phrase` (root, 600), copiée elle aussi hors du VPS.
2. Choisir la destination : un serveur joignable en SSH (`utilisateur@hote:/chemin/`, par rsync, avec une clé SSH de root autorisée là-bas) ou un OVH Object Storage par rclone (`rclone config` en root, puis `rclone:<remote>:<conteneur>/outsee`).
3. Écrire `/etc/outsee/sauvegarde.conf` (root, 600) :

   ```bash
   DESTINATION_DISTANTE=rclone:ovh:outsee-sauvegardes
   AGE_DESTINATAIRE=age1…
   # ou, sans age :
   # PHRASE_FICHIER=/etc/outsee/sauvegarde.phrase
   ```

4. Lancer une fois à la main et lire le journal : `sudo bash /usr/local/sbin/outsee-sauvegarde`.

Seuls des fichiers chiffrés (`.age` ou `.gpg`) partent ; sans moyen de chiffrer, la copie est refusée. La durée de conservation côté distant se règle sur la destination (règle de cycle de vie de l'Object Storage, par exemple 30 jours).

### Restauration

À essayer **une fois par mois**, sur une base jetable, et à noter (date, durée, résultat) : une sauvegarde jamais restaurée n'est pas une sauvegarde.

1. Récupérer les fichiers. Sur le VPS : `/var/backups/outsee/outsee_<date>.sql.gz` et `uploads_<date>.tar.gz`. Depuis la copie distante, les déchiffrer sur le poste qui détient la clé :

   ```bash
   age -d -i outsee-sauvegarde.key -o outsee_<date>.sql.gz outsee_<date>.sql.gz.age
   # ou
   gpg --batch --pinentry-mode loopback --passphrase-file sauvegarde.phrase -o outsee_<date>.sql.gz -d outsee_<date>.sql.gz.gpg
   ```

2. Essai sans toucher à la production :

   ```bash
   sudo mariadb -e "CREATE DATABASE outsee_essai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   gunzip -c outsee_<date>.sql.gz | sudo mariadb outsee_essai
   sudo mariadb outsee_essai -e "SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM schema_migrations"
   sudo mariadb -e "DROP DATABASE outsee_essai"
   ```

3. Restauration réelle (panne, migration ratée) : mettre le site en maintenance en arrêtant PHP (`sudo systemctl stop php8.3-fpm`), faire d'abord une copie de l'état actuel (`sudo mariadb-dump --single-transaction outsee | gzip > ~/avant_restauration.sql.gz`), puis :

   ```bash
   sudo mariadb -e "DROP DATABASE outsee; CREATE DATABASE outsee CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   gunzip -c outsee_<date>.sql.gz | sudo mariadb outsee
   sudo tar -xzf uploads_<date>.tar.gz -C /var/www/outsee/shared
   sudo chown -R www-data:www-data /var/www/outsee/shared/uploads
   sudo systemctl start php8.3-fpm
   ```

   Après une migration ratée, utiliser plutôt `shared/sauvegardes_deploiement/avant_<version>.sql.gz` et remettre `current` sur la version précédente (en tête de `deploy/deployer.sh`). Les écritures faites entre la sauvegarde et la panne sont perdues : le noter pour les comptes concernés (paiements Stripe en particulier, à rapprocher depuis le tableau de bord Stripe).

### Autres protections et journaux

- Activer les **snapshots automatiques** du VPS dans l'espace client OVH.
- Journaux (tournés chaque semaine, 8 semaines gardées, `/etc/logrotate.d/outsee`) :
  - `/var/log/outsee/` : tâches planifiées et `php.log` (erreurs PHP) ;
  - `/var/log/outsee-sauvegarde/` : sauvegarde nocturne (root) ;
  - `/var/log/php8.3-fpm-outsee-lent.log` : requêtes de plus de 3 s ;
  - `/var/log/mysql/lentes.log` : requêtes SQL de plus de 200 ms ;
  - `/var/log/nginx/`.
- Une sonde externe (OVH, UptimeRobot…) sur `https://<domaine>/sante.php` (voir l'étape 12).

## 10. Montée en charge (10 000 utilisateurs simultanés)

Estimation faite sur le code (`docs/PERFORMANCE.md` pour les mesures d'origine) :
- l'application ne fait aucun rafraîchissement automatique : elle recharge un écran quand on y revient ;
- 10 000 utilisateurs actifs représentent environ 700 requêtes par seconde en moyenne, et 1 500 à 2 000 en pic ;
- les points de l'API coûtent de 4 à 8 ms de calcul ; le serveur et les réglages fournis absorbent ce trafic.

Correctifs déjà faits :
- pagination bornée ;
- classement mis en cache 5 minutes ;
- index des tentatives de connexion ;
- verrou sur l'inscription aux squads ;
- limiteur de connexion compté par compte (un campus derrière une seule adresse IP n'est plus bloqué).

**Annuaire « Personnes »** (`includes/hub.php`). Il classe tous les étudiants par affinité à chaque appel. Mesuré le 2026-11-17 sur une base de test de 20 013 étudiants (MariaDB locale, un cœur) : **134 ms** par appel sans cache, **0,25 ms** avec. Il est donc mis en cache 45 secondes par personne (`hubAnnuaireEnCache()`), et oublié dès que la personne suit, bloque ou change ses goûts. À 100 000 étudiants, le coût sans cache serait de l'ordre de 0,5 s : le cache devient indispensable, il est en place.

**Test de charge sur le VPS** (à faire avant l'ouverture, sur une base de test, jamais sur les vrais comptes) :

```bash
# 1. base de test avec un volume réaliste
MYSQLDATABASE=outsee_perf php outils/semer_charge.php --etudiants=20000
# 2. un jeton par compte, valable 2 h
MYSQLDATABASE=outsee_perf php outils/jetons_charge.php /tmp/jetons.txt --nombre=300
# 3. la charge, avec des comptes distincts
php outils/charge.php --url=https://<domaine>/api/v1/personnes.php     --bearers=/tmp/jetons.txt --concurrence=200 --total=10000
rm /tmp/jetons.txt
```

À lire : le p95 (pas la moyenne) et la ligne des codes HTTP. Tout ce qui n'est pas 200 signale que le serveur lâche. Mesure local de référence (serveur PHP intégré, une requête à la fois, donc très inférieure au VPS) : 32 requêtes/s sur 50 comptes distincts. Les chiffres du VPS (80 processus PHP-FPM) restent à relever : ils ne sont pas encore mesurés.

Un seul VPS est un point de défaillance unique. Les snapshots et les sauvegardes externes réduisent la durée d'une panne, pas son risque.

## 11. Liste de contrôle avant l'ouverture

- [ ] `https://<domaine>` s'ouvre ; `http://` et `www.` redirigent ; un autre nom d'hôte pointé sur l'IP est refusé.
- [ ] `https://<domaine>/includes/config.local.php`, `/outils/migrer.php`, `/db_setup.sql`, `/.git/config` et `/mobile/app.json` répondent 404.
- [ ] Connexion à l'espace pro, création d'un événement, page Formule.
- [ ] Paiement Stripe de bout en bout (étape 6), en test puis en production.
- [ ] Mot de passe oublié : l'e-mail arrive, le lien pointe sur `https://<domaine>`.
- [ ] Aucun compte de démonstration en base (étape 5).
- [ ] L'application, construite avec la nouvelle `EXPO_PUBLIC_API_URL`, se connecte et affiche la formule.
- [ ] Sauvegarde nocturne présente (`/var/lib/outsee/derniere_sauvegarde` à jour), copie distante chiffrée faite, essai de restauration réussi et noté (étape 9).
- [ ] `/etc/cron.d/outsee` ne lance en root que `/usr/local/sbin/outsee-sauvegarde` ; `deployer.sh` ne signale aucun fichier de configuration à réappliquer.
- [ ] Environnement GitHub `production` avec approbation obligatoire ; ancien workflow FTP supprimé avec ses secrets.
- [ ] Test de charge sur l'API (étape 10), chiffres notés.
- [ ] Sonde de disponibilité : `https://<domaine>/sante.php` répond 200 ; la tâche `cron/sante.php` tourne ; une alerte de test arrive bien à l'adresse `ALERTE_EMAIL`.
- [ ] Double authentification activée pour chaque compte administrateur (étape 12).

## 12. Surveillance et double authentification

**Surveillance.** Deux niveaux, complémentaires :
- `cron/sante.php` (toutes les 5 minutes, voir `deploy/cron/outsee`) appelle `https://<domaine>/sante.php` comme un visiteur, vérifie la fraîcheur de la dernière sauvegarde, et envoie **un e-mail par panne** (et un au rétablissement) à `ALERTE_EMAIL` (`contact@outsee.fr` par défaut). Elle tourne sur le serveur : si le serveur entier tombe, elle tombe avec lui.
- Un service **extérieur** est donc nécessaire pour ce cas : créer un contrôle gratuit (UptimeRobot, Better Stack ou Uptime Kuma) sur `https://<domaine>/sante.php`, avec alerte par e-mail ou SMS. C'est à faire à la main, avec ton compte.

**Plantages de l'application.** L'app signale ses erreurs JavaScript non rattrapées à `api/v1/erreur_app.php` (anonyme : message, pile, plateforme, version). Elles arrivent dans le journal PHP, préfixées `[app mobile]` : `grep "app mobile" /var/log/outsee/php.log`. Seuls les plantages JavaScript sont couverts, pas les plantages natifs (iOS/Android) : pour ceux-là, les rapports de plantage d'App Store Connect et de Google Play Console sont déjà fournis par les stores. À mentionner dans la politique de confidentialité.

**Double authentification des administrateurs** (TOTP, compatible Google Authenticator, Authy, 1Password…). Activation depuis le terminal du serveur, jamais depuis une page :

```bash
php outils/activer_2fa.php admin@outsee.fr            # affiche le secret et 10 codes de secours, une seule fois
php outils/activer_2fa.php admin@outsee.fr --etat
php outils/activer_2fa.php admin@outsee.fr --desactiver   # si le téléphone et les codes de secours sont perdus
```

Dès qu'un administrateur a un secret, le code à six chiffres est demandé à la connexion (site et application). Sans secret, la connexion reste celle d'avant : **activer le compte de chaque administrateur avant l'ouverture**.
