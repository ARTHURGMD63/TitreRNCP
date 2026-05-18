# Architecture technique — StudentLink

## Vue d'ensemble

```
┌──────────────────────────────────────────────────────────┐
│                        CLIENT                             │
│  Browser (HTML + CSS + JS vanilla + Chart.js + PWA)      │
└────────────────────────┬─────────────────────────────────┘
                         │ HTTPS (TLS Let's Encrypt)
                         ▼
┌──────────────────────────────────────────────────────────┐
│                   RAILWAY (Cloud)                         │
│                                                           │
│  ┌─────────────────────────────┐  ┌──────────────────┐   │
│  │    PHP 8.3 (Nixpacks)       │  │   MySQL 8.0      │   │
│  │    Apache/PHP-FPM           │◄─┤   (Railway DB)   │   │
│  │    /app/                    │  │   railway DB     │   │
│  └─────────────────────────────┘  └──────────────────┘   │
└──────────────────────────────────────────────────────────┘
```

## Structure des requêtes

```
Requête utilisateur
       │
       ▼
 auth_check.php (inclus par toutes les pages)
       │
       ├─ setSecurityHeaders()    → Headers HTTP (CSP, HSTS…)
       ├─ session_start()         → Session PHP
       ├─ requireStudent/Partner  → Contrôle d'accès
       │
       ▼
 Page .php (controller + view en un seul fichier)
       │
       ├─ Lecture GET/POST        → Validation + sanitisation
       ├─ db.php ($pdo)          → PDO prepared statements
       ├─ Logique métier          → PHP pur
       │
       ▼
 HTML renvoyé au client (pas de template engine)
```

## Pattern MVC simplifié

StudentLink utilise un **pattern PHP procédural MVC léger** sans framework :

| Couche | Implémentation |
|--------|---------------|
| **Model** | Requêtes PDO inline dans chaque page / helpers dans `includes/` |
| **View** | HTML inline avec `<?php ?>` |
| **Controller** | Logique en haut de chaque fichier `.php` |

Ce choix est **intentionnel** pour un projet de taille réduite : pas de surcharge d'un framework (Symfony/Laravel), meilleure lisibilité pour la soutenance.

## Endpoints API

Les appels AJAX passent par `/api/*.php` qui retournent du JSON :

```
POST /api/inscrire.php        → Inscription à un event
POST /api/annuler_pass.php    → Annulation
POST /api/follow.php          → Follow/unfollow user
POST /api/create_squad.php    → Créer une squad
POST /api/rejoindre_squad.php → Rejoindre une squad
POST /api/quitter_squad.php   → Quitter une squad
GET  /api/stats.php           → Stats event (partenaire uniquement)
GET  /api/social_feed.php     → Fil d'activité
GET  /api/squad_members.php   → Membres d'une squad
POST /api/inviter.php         → Inviter à un event/squad
```

## Authentification & Sessions

```
Inscription/Login
      │
      ▼
session_regenerate_id(true)   ← Prévient la fixation de session
      │
      ▼
$_SESSION = [
    user_id, user_prenom,
    user_nom, user_type,
    user_ecole, csrf_token
]
      │
      ▼
Toutes les pages vérifient $_SESSION via requireStudent/requirePartner
```

## Détection d'environnement

```php
// includes/auth_check.php
function baseUrl(string $path = ''): string {
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $isLocal = str_contains($host, 'localhost');
    $base    = $isLocal ? '/TitreRNCP' : '';
    return $base . $path;
}
```

| Environnement | baseUrl('/explore.php') |
|--------------|------------------------|
| Local (WAMP) | `/TitreRNCP/explore.php` |
| Railway (prod) | `/explore.php` |

## Base de données

### Connexion (`includes/db.php`)

```php
// Détection automatique Railway vs local
$host = $_ENV['MYSQLHOST'] ?? 'localhost';
$pdo  = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
]);
```

### Migrations

```
db_setup.sql          → Schéma initial + données de démo
db_migrations_v2.sql  → Avis, badges, gamification (2026-04)
db_migrations_v3.sql  → Rate limiting, password resets (2026-05)
```

## Déploiement CI/CD

```
git push origin main
       │
       ▼
GitHub Actions (.github/workflows/ci.yml)
       │
       ├─ PHP Lint (syntax check)
       ├─ PHPStan niveau 5
       └─ PHPUnit 40 tests (PHP 8.1 / 8.2 / 8.3)
             │
             ▼ (si succès)
       Railway détecte le push → redéploiement automatique
       Nixpacks détecte PHP → install composer → Apache up
```

## Choix techniques justifiés

| Choix | Alternative considérée | Justification |
|-------|----------------------|---------------|
| PHP 8.3 vanilla | Laravel / Symfony | Pas de surcharge, lisibilité maximale, RNCP évalue le code PHP direct |
| PDO + MySQL | Eloquent ORM | Contrôle total sur les requêtes, performance, sécurité visible |
| JS vanilla | React / Vue | Pas de build tool, chargement instantané, bonne maîtrise montrée |
| Chart.js | D3.js | API simple, excellents graphiques, léger (60kb) |
| Railway | Heroku / VPS | Free tier généreux, déploiement Docker automatique, MySQL inclus |
| Sessions PHP | JWT | Adapté à une app web traditionnelle, pas d'API REST publique |
| bcrypt `PASSWORD_DEFAULT` | MD5 / SHA1 | Standard de sécurité, coût adaptatif |
| PHPUnit | Pest | Plus standard, mieux documenté pour RNCP |

## Performances

- Pas de N+1 queries : jointures SQL systématiques
- Pas d'ORM : requêtes optimisées manuellement
- CSS custom properties : thème dark mode sans recalcul JavaScript
- `themeBootScript()` inline : zéro flash de contenu non stylisé
- Chart.js chargé uniquement sur `/partenaire/dashboard.php`

## Accessibilité

- `lang="fr"` sur toutes les pages
- Labels associés à chaque input (`for` + `id`)
- Focus visible (outline CSS non supprimé)
- Contrastes respectant WCAG AA sur les couleurs principales
- Navigation clavier fonctionnelle sur les formulaires
