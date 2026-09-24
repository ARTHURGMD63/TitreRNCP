# Politique de sécurité — Linkee

## Versions supportées

| Version | Supportée |
|---------|-----------|
| 1.x     | Oui |

---

## Signalement d'une vulnérabilité

Si vous découvrez une faille de sécurité, contactez-nous à **security@linkee.app**.  
Réponse sous 48h. Merci de ne pas publier la vulnérabilité publiquement avant correction.

---

## Mesures de sécurité implémentées

### Authentification & Sessions

| Mesure | Implémentation |
|--------|----------------|
| Hash des mots de passe | `password_hash()` avec `PASSWORD_DEFAULT` (bcrypt, cost 12) |
| Régénération de session | `session_regenerate_id(true)` après chaque login/register |
| Protection fixation de session | Session détruite et recréée à l'authentification |
| Rate limiting login | Max 5 tentatives par IP / 15 min (table `login_attempts`) |
| Mot de passe oublié | Token aléatoire SHA-256, expiration 1h, usage unique |
| Cookie de session | `HttpOnly`, `SameSite=Lax`, `Secure` dès que la requête est en HTTPS |
| Fixation de session | `session.use_strict_mode = 1` : un identifiant non émis par le serveur est refusé |
| Expiration par inactivité | 1 h pour un compte `admin`, 30 jours pour les autres (`delaiInactivite()`) |
| Longueur du mot de passe | 12 caractères pour un compte `admin`, 8 sinon (`longueurMinimaleMotDePasse()`) |

### Injections & XSS

| Mesure | Implémentation |
|--------|----------------|
| Anti-SQLi | PDO + requêtes préparées sur **toutes** les requêtes SQL |
| Anti-XSS | `htmlspecialchars()` sur toutes les sorties HTML |
| Anti-LIKE injection | Échappement `%` et `_` avant les requêtes LIKE |
| Whitelist des inputs | Intérêts validés contre une liste (`$allowedInterests`), types d'événements validés |
| Bornes sur les entiers | `max()`/`min()` sur quotas, réductions, notes |

### CSRF

| Mesure | Implémentation |
|--------|----------------|
| Token CSRF | `bin2hex(random_bytes(32))` stocké en session |
| Vérification | `hash_equals()` sur tous les formulaires POST (`csrfVerify()`) |
| Points d'API JSON | `protegerEcritureApi()` : jeton lu dans `X-CSRF-Token` ou dans le corps JSON, sur les **11 points d'écriture** |
| Publication du jeton au client | `metaCsrf()` pose `<meta name="csrf-token">` dans le `<head>` de chaque page ; `enTetesJson()` (app.js) le lit |
| Seconde couche | `origineFiable()` : `Origin`, à défaut `Referer`, doit désigner l'hôte courant |
| Troisième couche | Cookie de session en `SameSite=Lax` |
| Méthode | Une écriture hors `POST` est refusée en 405 : atteignable en `GET`, elle se déclencherait depuis une simple balise `<img>` |
| Non-régression | `tests/Unit/ApiProtectionTest.php` échoue si un point d'écriture est ajouté sans garde, si la garde arrive après la première requête SQL, ou si un `fetch` POST du JavaScript oublie le jeton |

> **Ce qui a changé, et pourquoi.** Les 11 points de `/api/` écrivaient en base
> sur la seule foi du cookie de session. `SameSite=Lax` bloque effectivement
> le POST venu d'un autre site — l'application n'était donc pas vulnérable en
> l'état — mais c'était sa **seule** défense, là où les formulaires en avaient
> deux, et elle tenait entièrement à une ligne de configuration de session. Un
> passage en `SameSite=None` pour faire fonctionner une intégration tierce, et
> onze points d'écriture s'ouvraient d'un coup, sans que rien dans leur code ne
> le signale.

### Redirections

| Mesure | Implémentation |
|--------|----------------|
| Retour après échec CSRF | `urlInterneOuDefaut()` : seuls le chemin, la requête et le fragment d'une URL du même hôte sont conservés |
| Cas couverts | Hôte étranger, hôte qui commence pareil (`linkee.example.evil.tld`), port différent, URL protocole-relatif (`//evil.tld`), `/\evil.tld`, chemin relatif |

> `csrfVerify()` renvoyait l'utilisateur vers `$_SERVER['HTTP_REFERER']` tel
> quel. Cet en-tête est posé par le navigateur d'après la page précédente, qui
> peut appartenir à n'importe qui : une page hostile pointant vers un
> formulaire de l'application avec un jeton volontairement faux récupérait le
> visiteur sur son propre domaine, avec l'application comme caution. Couvert
> par `tests/Unit/RedirectionTest.php`.

### Contrôle d'accès

| Mesure | Implémentation |
|--------|----------------|
| Authentification obligatoire | `requireLogin()` / `requireStudent()` / `requirePartner()` / `requireAdmin()` sur chaque page, avant tout effet de bord |
| IDOR (Insecure Direct Object Reference) | Vérification `etablissement.user_id = $_SESSION['user_id']` avant toute opération sur event |
| Séparation des rôles | Trois espaces cloisonnés : `/` étudiant, `/partenaire/*`, `/admin/*`. Une seule fonction décide de la destination (`accueilSelonType()`) |
| Back-office | `/admin/*` réservé au type `admin`. Comptes créés en ligne de commande seulement (`outils/creer_admin.php`) : aucun formulaire web ne fabrique d'administrateur |

### Headers HTTP

| Header | Valeur |
|--------|--------|
| `X-Frame-Options` | `DENY` (protection clickjacking) |
| `X-Content-Type-Options` | `nosniff` |
| `X-XSS-Protection` | `1; mode=block` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` (HTTPS uniquement) |
| `Content-Security-Policy` | Whitelist sources autorisées (scripts, styles, polices, images) |

### Exposition des fichiers

| Mesure | Implémentation |
|--------|----------------|
| Listing de répertoire | `Options -Indexes` à la racine |
| Dépôt Git | `/.git/` renvoie 404 — il était servi intégralement, donc tout le code source et son historique étaient téléchargeables |
| Dossiers internes | `.htaccess` refusant tout dans `includes/`, `vendor/`, `tests/`, `outils/`, `cron/` |
| Fichiers de projet | `.sql`, `.md`, `.lock`, `.neon`, `composer.json`, `phpunit.xml`… refusés |
| Uploads | `uploads/.htaccess` : moteur PHP coupé, handlers retirés, seules les images servies |
| Scripts hors-web | `cron/rappels.php`, `outils/creer_admin.php` et `outils/migrer.php` refusent toute invocation qui n'est pas CLI — un outil qui modifie le schéma n'a rien à faire derrière une URL, même protégée |

> `AllowOverride all` doit être actif pour que ces `.htaccess` s'appliquent.
> Sur un hébergement qui l'interdit, reporter ces règles dans la configuration
> du serveur — sinon elles sont silencieusement ignorées.

### Base de données

| Mesure | Implémentation |
|--------|----------------|
| Pas de mass assignment | Champs explicitement listés dans chaque INSERT/UPDATE |
| Clés étrangères | `ON DELETE CASCADE` pour maintenir l'intégrité référentielle |
| Contraintes UNIQUE | Sur `avis(user_id, evenement_id)`, `users(email)`, etc. |

### Déploiement

| Mesure | Implémentation |
|--------|----------------|
| HTTPS | Automatique via Railway (TLS Let's Encrypt) |
| Variables d'environnement | Identifiants DB et SMTP via l'environnement ou `includes/config.local.php`, non versionné (jamais dans le code) |
| Envoi d'e-mails | En-têtes RFC complets, sujet encodé, adresse d'enveloppe explicite, transport SMTP authentifié avec vérification du certificat. SPF/DKIM/DMARC à publier : voir [`docs/EMAIL.md`](docs/EMAIL.md) |
| Poids du dépôt | `tests/Unit/DepotTest.php` refuse tout binaire suivi de plus de 2 Mo |
| Séparation dev/prod | `baseUrl()` détecte l'environnement, headers HSTS conditionnels |

---

## OWASP Top 10 — Couverture

| OWASP | Risque | Statut |
|-------|--------|--------|
| A01 | Broken Access Control | IDOR fix, role checks |
| A02 | Cryptographic Failures | bcrypt, HTTPS, tokens SHA-256 |
| A03 | Injection | PDO prepared statements partout |
| A04 | Insecure Design | Rate limiting, anti-enumeration forgot password |
| A05 | Security Misconfiguration | Headers HTTP, CSP |
| A06 | Vulnerable Components | PHPStan niveau 5, dépendances minimales |
| A07 | Auth & Session Failures | Session regen, CSRF, rate limiting |
| A08 | Software Integrity Failures | Pas de CDN non fiable |
| A09 | Logging & Monitoring | Partiel : journal PHP via `logErreur()`, échecs d'envoi tracés — pas d'alerte automatique |
| A10 | SSRF | Pas de requêtes HTTP sortantes côté serveur |
