# Mise en production sur le VPS OVH

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
| `deploy/installer_serveur.sh` | Prépare le VPS neuf, une seule fois |
| `deploy/nginx/outsee.conf`, `outsee-php.conf` | Site HTTPS, protections (remplacent les `.htaccess`) |
| `deploy/php/outsee-fpm.conf`, `99-outsee.ini` | PHP-FPM (80 processus), OPcache, APCu, sessions |
| `deploy/mariadb/99-outsee.cnf` | Base locale, 12 Go de cache, requêtes lentes journalisées |
| `deploy/cron/outsee` | Stripe (5 min), rappels, entretien, sauvegarde |
| `deploy/deployer.sh` | Bascule atomique d'une version, migrations, retour arrière automatique |
| `deploy/sauvegarder.sh` | Sauvegarde nocturne base + photos |
| `deploy/exclusions.txt` | Ce qui ne part jamais sur le serveur |
| `.github/workflows/deploiement-vps.yml` | Le déploiement, lancé depuis l'onglet Actions |

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

Le script installe nginx, PHP 8.3-FPM (avec APCu), MariaDB et certbot. Il ferme le pare-feu sauf SSH, HTTP et HTTPS, et active fail2ban et les mises à jour de sécurité automatiques. Il crée le compte `deploy`, la base `outsee` et son utilisateur à droits limités, ainsi que `/var/www/outsee/shared/config.local.php`, avec le mot de passe de la base déjà rempli. Il obtient enfin le certificat HTTPS.

## 4. Réglages de l'application

Sur le VPS : `sudo nano /var/www/outsee/shared/config.local.php` (modèle commenté : `deploy/config.local.production.example.php`). À remplir :

- `stripe_secret`, `stripe_webhook`, `stripe_taux_tva` : voir l'étape 6 ;
- SMTP (`mail_smtp_*`) : la boîte `noreply@<domaine>` ;
- `app_store_url`, `play_store_url` une fois l'application publiée (la vitrine affiche « Bientôt » d'ici là).

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
3. **Actions → Déploiement VPS → Run workflow**, sur la branche à mettre en ligne. Le workflow :
   - copie la version sans les fichiers de `deploy/exclusions.txt` ;
   - applique les migrations ;
   - bascule le lien `current` ;
   - recharge PHP ;
   - contrôle que l'API répond, et revient seul à la version précédente si ce n'est pas le cas.
4. **Désactiver l'ancien déploiement FTP** (`.github/workflows/deploiement.yml`, onglet Actions → Disable workflow) : il part à chaque push sur `main`, vers l'ancien hébergement mutualisé.
5. Une fois le VPS en service, arrêter le service Render et la base TiDB Cloud.

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
4. L'application ne vend rien : formule, événements supplémentaires et mises en avant payantes se prennent sur le site (règles Apple 3.1.1 / 3.1.3, Google Play Billing). Elle affiche l'état du compte, sans prix ni lien d'achat.

## 9. Sauvegardes et surveillance

- `deploy/sauvegarder.sh` tourne chaque nuit (base et photos, 14 jours dans `/var/backups/outsee`). **Une sauvegarde qui reste sur le VPS ne protège pas d'une panne du VPS.** Pour en garder une copie ailleurs, régler `DESTINATION_DISTANTE` dans `/etc/cron.d/outsee` (OVH Backup Storage ou Object Storage, par rsync ou rclone), puis faire un essai de restauration.
- Activer les **snapshots automatiques** du VPS dans l'espace client OVH.
- Journaux :
  - `/var/log/outsee/` : tâches planifiées ;
  - `/var/log/php8.3-fpm-outsee.log` : erreurs PHP ;
  - `/var/log/php8.3-fpm-outsee-lent.log` : requêtes de plus de 3 s ;
  - `/var/log/mysql/lentes.log` : requêtes SQL de plus de 200 ms ;
  - `/var/log/nginx/`.
- Une sonde externe (OVH, UptimeRobot…) sur `https://<domaine>/api/v1/legal.php?page=cgu`.

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

Reste à surveiller : l'annuaire « Personnes » (`includes/hub.php`) classe tous les étudiants à chaque appel. Son coût grandit avec le nombre d'inscrits, et c'est le premier poste à mettre en cache si le CPU monte. **Avant l'ouverture, faire un vrai test de charge sur l'API** avec `outils/charge.php`, qui envoie aujourd'hui un cookie : il faut lui ajouter l'en-tête `Authorization: Bearer`.

Un seul VPS est un point de défaillance unique. Les snapshots et les sauvegardes externes réduisent la durée d'une panne, pas son risque.

## 11. Liste de contrôle avant l'ouverture

- [ ] `https://<domaine>` s'ouvre ; `http://` et `www.` redirigent ; un autre nom d'hôte pointé sur l'IP est refusé.
- [ ] `https://<domaine>/includes/config.local.php`, `/outils/migrer.php`, `/db_setup.sql`, `/.git/config` et `/mobile/app.json` répondent 404.
- [ ] Connexion à l'espace pro, création d'un événement, page Formule.
- [ ] Paiement Stripe de bout en bout (étape 6), en test puis en production.
- [ ] Mot de passe oublié : l'e-mail arrive, le lien pointe sur `https://<domaine>`.
- [ ] Aucun compte de démonstration en base (étape 5).
- [ ] L'application, construite avec la nouvelle `EXPO_PUBLIC_API_URL`, se connecte et affiche la formule.
- [ ] Sauvegarde nocturne présente, copie distante faite, essai de restauration réussi.
- [ ] Test de charge sur l'API.
