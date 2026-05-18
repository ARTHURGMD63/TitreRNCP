# Politique de sécurité — StudentLink

## Versions supportées

| Version | Supportée |
|---------|-----------|
| 1.x     | ✅        |

---

## Signalement d'une vulnérabilité

Si vous découvrez une faille de sécurité, contactez-nous à **security@studentlink.app**.  
Réponse sous 48h. Merci de ne pas publier la vulnérabilité publiquement avant correction.

---

## Mesures de sécurité implémentées

### 🔒 Authentification & Sessions

| Mesure | Implémentation |
|--------|----------------|
| Hash des mots de passe | `password_hash()` avec `PASSWORD_DEFAULT` (bcrypt, cost 12) |
| Régénération de session | `session_regenerate_id(true)` après chaque login/register |
| Protection fixation de session | Session détruite et recréée à l'authentification |
| Rate limiting login | Max 5 tentatives par IP / 15 min (table `login_attempts`) |
| Mot de passe oublié | Token aléatoire SHA-256, expiration 1h, usage unique |

### 🛡 Injections & XSS

| Mesure | Implémentation |
|--------|----------------|
| Anti-SQLi | PDO + requêtes préparées sur **toutes** les requêtes SQL |
| Anti-XSS | `htmlspecialchars()` sur toutes les sorties HTML |
| Anti-LIKE injection | Échappement `%` et `_` avant les requêtes LIKE |
| Whitelist des inputs | Intérêts validés contre une liste (`$allowedInterests`), types d'événements validés |
| Bornes sur les entiers | `max()`/`min()` sur quotas, réductions, notes |

### 🔑 CSRF

| Mesure | Implémentation |
|--------|----------------|
| Token CSRF | `bin2hex(random_bytes(32))` stocké en session |
| Vérification | `hash_equals()` sur tous les formulaires POST |
| Périmètre | Login, Register, Avis, Profil, Création event, Suppression event, Reset password |

### 🏠 Contrôle d'accès

| Mesure | Implémentation |
|--------|----------------|
| Authentification obligatoire | `requireLogin()` / `requireStudent()` / `requirePartner()` sur chaque page |
| IDOR (Insecure Direct Object Reference) | Vérification `etablissement.user_id = $_SESSION['user_id']` avant toute opération sur event |
| Séparation des rôles | Routes `/partenaire/*` inaccessibles aux étudiants et vice-versa |

### 🌐 Headers HTTP

| Header | Valeur |
|--------|--------|
| `X-Frame-Options` | `DENY` (protection clickjacking) |
| `X-Content-Type-Options` | `nosniff` |
| `X-XSS-Protection` | `1; mode=block` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` (HTTPS uniquement) |
| `Content-Security-Policy` | Whitelist sources autorisées (scripts, styles, polices, images) |

### 🗄 Base de données

| Mesure | Implémentation |
|--------|----------------|
| Pas de mass assignment | Champs explicitement listés dans chaque INSERT/UPDATE |
| Clés étrangères | `ON DELETE CASCADE` pour maintenir l'intégrité référentielle |
| Contraintes UNIQUE | Sur `avis(user_id, evenement_id)`, `users(email)`, etc. |

### 📦 Déploiement

| Mesure | Implémentation |
|--------|----------------|
| HTTPS | Automatique via Railway (TLS Let's Encrypt) |
| Variables d'environnement | Credentials DB via `$_ENV` (jamais dans le code) |
| Séparation dev/prod | `baseUrl()` détecte l'environnement, headers HSTS conditionnels |

---

## OWASP Top 10 — Couverture

| OWASP | Risque | Statut |
|-------|--------|--------|
| A01 | Broken Access Control | ✅ IDOR fix, role checks |
| A02 | Cryptographic Failures | ✅ bcrypt, HTTPS, tokens SHA-256 |
| A03 | Injection | ✅ PDO prepared statements partout |
| A04 | Insecure Design | ✅ Rate limiting, anti-enumeration forgot password |
| A05 | Security Misconfiguration | ✅ Headers HTTP, CSP |
| A06 | Vulnerable Components | ✅ PHPStan niveau 5, dépendances minimales |
| A07 | Auth & Session Failures | ✅ Session regen, CSRF, rate limiting |
| A08 | Software Integrity Failures | ✅ Pas de CDN non fiable |
| A09 | Logging & Monitoring | ⚠️ Logs Railway (à améliorer) |
| A10 | SSRF | ✅ Pas de requêtes HTTP sortantes côté serveur |
