# Changelog

Toutes les modifications notables de StudentLink sont documentées dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/),
et le projet adhère au [versioning sémantique](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Pipeline CI GitHub Actions (lint, PHPStan, PHPUnit) sur PHP 8.1 / 8.2 / 8.3
- Suite de tests PHPUnit (unitaires + intégration SQLite)
- Configuration PHPStan niveau 5
- `CHANGELOG.md` et `CONTRIBUTING.md`

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
