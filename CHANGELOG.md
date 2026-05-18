# Changelog

Toutes les modifications notables de StudentLink sont documentées dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/),
et le projet adhère au [versioning sémantique](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.5.1] - 2026-05-18

### Fixed
- Hardcoded `/partenaire/*` and `/auth/*` URLs in `dashboard.php` and `evenements.php` → `baseUrl()` (local/Railway compat)
- `fetch('/partenaire/api_scan.php')` → `fetch(BASE + '/partenaire/api_scan.php')` in QR scanner

### Added
- `partenaire/edit_event.php` — full edit form with CSRF protection, pre-populated fields, live stats bar (inscrits / check-in), flash toggle, ownership guard
- ✏️ edit button in événements table linking to edit page
- `?updated=1` success banner after saving changes

## [1.5.0] - 2026-05-18

### Added
- **Skip navigation** link "Aller au contenu principal" (visible au focus clavier)
- `docs/ACCESSIBILITE.md` — audit WCAG 2.1 AA avec tableau de conformité complet
- `prefers-reduced-motion` — toutes animations désactivées si souhaité
- `.sr-only` — classe utilitaire screen-reader
- Star widget clavier-accessible : `role="radiogroup"`, flèches, `aria-checked`

### Changed
- `<main id="main-content">` sur toutes les pages (explore, squads, wallet, profil)
- `<nav aria-label="Navigation principale">` + `aria-current="page"` sur la bottom nav
- `aria-hidden="true"` sur tous les SVG décoratifs
- Filtres pills : `onclick="window.location"` → vrais liens `<a>` + `aria-current`
- `role="alert" aria-live="assertive"` sur tous les messages d'erreur auth
- `:focus-visible` remplace `outline: none` — focus visible uniquement au clavier
- Inputs : outline bleu 2px en plus du border-color au focus
- Label `<label for="commentaire">` sur textarea avis

## [1.4.0] - 2026-05-18

### Added
- **README.md** complet (badges CI, stack, install, structure, démo)
- **docs/MCD.md** — Modèle Conceptuel de Données avec diagramme Mermaid ER (15 tables)
- **docs/UML_DIAGRAMMES.md** — 6 diagrammes UML (cas d'usage, séquences, classes)
- **docs/ARCHITECTURE.md** — Architecture technique, patterns, choix justifiés
- **docs/MANUEL_UTILISATEUR.md** — Guide complet étudiant (wallet, badges, squads, FAQ)
- **docs/API.md** — Référence de tous les endpoints `/api/*.php`

## [1.3.0] - 2026-05-18

### Added
- **CSRF protection** sur tous les formulaires POST (`csrfToken()`, `csrfField()`, `csrfVerify()`)
- **Rate limiting** anti-bruteforce sur le login (5 tentatives / 15 min par IP, table `login_attempts`)
- **Mot de passe oublié** complet : `forgot.php` + `reset.php`, tokens SHA-256 à usage unique (1h)
- **Headers de sécurité HTTP** : X-Frame-Options, X-Content-Type-Options, CSP, HSTS, Referrer-Policy
- `includes/security.php` centralisant tous les helpers sécurité
- `SECURITY.md` documentant la couverture OWASP Top 10
- Migration `db_migrations_v3.sql` (tables `login_attempts`, `password_resets`)
- Lien "Mot de passe oublié ?" sur la page de connexion

### Changed
- `auth_check.php` inclut désormais `security.php` et appelle `setSecurityHeaders()` automatiquement
- Login, Register, Avis, Create Event, Delete Event protégés par CSRF

## [1.2.0] - 2026-04-30

### Added
- Pipeline CI GitHub Actions (lint, PHPStan, PHPUnit) sur PHP 8.1 / 8.2 / 8.3
- Suite de tests PHPUnit (unitaires + intégration SQLite)
- Configuration PHPStan niveau 5
- `CHANGELOG.md` et `CONTRIBUTING.md`
- **Gamification** : système XP / niveaux / badges (9 badges débloquables)
- **Dark mode** persistant via `localStorage` avec toggle dans le profil
- **Avis & notes** 1-5★ après check-in d'un événement (`avis.php`)
- **Note moyenne** affichée sur les cartes d'événements dans `/explore.php`
- **Pages légales** : Mentions légales, CGU, Politique de confidentialité (RGPD)
- Module `includes/gamification.php` (helpers XP, niveau, badges)
- Migration `db_migrations_v2.sql` (tables `avis`, `badges`, `user_badges`, `user_settings`)

### Changed
- Section profil enrichie (barre XP, grille de badges, sélecteur de thème)
- `themeBootScript()` injecté en `<head>` pour éviter le flash en dark mode

## [1.2.0] - 2026-04-30

### Added
- **Gamification** : système XP / niveaux / badges (9 badges débloquables)
- **Dark mode** persistant via `localStorage` avec toggle dans le profil
- **Avis & notes** 1-5★ après check-in d'un événement (`avis.php`)
- **Note moyenne** affichée sur les cartes d'événements dans `/explore.php`
- **Pages légales** : Mentions légales, CGU, Politique de confidentialité (RGPD)
- Module `includes/gamification.php` (helpers XP, niveau, badges)
- Migration `db_migrations_v2.sql` (tables `avis`, `badges`, `user_badges`, `user_settings`)

### Changed
- Section profil enrichie (barre XP, grille de badges, sélecteur de thème)
- `themeBootScript()` injecté en `<head>` pour éviter le flash en dark mode

## [1.1.0] - 2026-04-29

### Added
- Création d'événements partenaire (`partenaire/create_event.php`)
- Tableau de bord partenaire avec graphiques Chart.js
- Système de squads sport (création, invitations, membres)
- Suivi d'utilisateurs (follow / followers)
- Feed social sur le profil
- Wallet étudiant avec QR codes de check-in

### Security
- Régénération de l'ID de session après login/register (anti-fixation)
- Correction IDOR sur `api/stats.php` (vérification du propriétaire de l'événement)
- Whitelist sur les centres d'intérêt utilisateur (anti-mass-assignment)
- Bornes sur les quotas de squads (`max(2, min(500, $quota))`)
- Échappement systématique des LIKE wildcards dans les requêtes de recherche

## [1.0.0] - 2026-04-15

### Added
- Authentification étudiant / partenaire (login, register, sessions)
- Exploration d'événements avec filtres (type, ville, date)
- Inscription aux événements avec gestion des places
- PWA basique (manifest, icône, theme-color)
- Déploiement Railway via Docker

[Unreleased]: https://github.com/ARTHURGMD63/TitreRNCP/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/ARTHURGMD63/TitreRNCP/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/ARTHURGMD63/TitreRNCP/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/ARTHURGMD63/TitreRNCP/releases/tag/v1.0.0
