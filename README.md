# StudentLink

> Plateforme événementielle & sociale pour les étudiants de Clermont-Ferrand.

[![CI](https://github.com/ARTHURGMD63/TitreRNCP/actions/workflows/ci.yml/badge.svg)](https://github.com/ARTHURGMD63/TitreRNCP/actions)
[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql)](https://mysql.com)
[![Railway](https://img.shields.io/badge/Deploy-Railway-0B0D0E?logo=railway)](https://railway.app)
[![License](https://img.shields.io/badge/Licence-Propriétaire-red)](#)

---

## 🎯 Présentation

StudentLink connecte les étudiants aux établissements (bars, boîtes, restos) de leur ville via un système de **pass numériques** avec réductions exclusives.

| Rôle | Fonctionnalités |
|------|----------------|
| **Étudiant** | Explorer les events, s'inscrire, scanner son QR code, rejoindre des squads sportives, suivre des amis, collecter des badges XP |
| **Partenaire** | Créer/gérer des événements, scanner les QR codes au check-in, consulter les stats (inscriptions, conversions, pics horaires) |

## ✨ Fonctionnalités principales

- 🎟 **Wallet numérique** — pass QR code générés à l'inscription
- 🔥 **Flash events** — offres limitées dans le temps avec compte à rebours
- 🏃 **Squads sportives** — groupes par activité (running, vélo, muscu)
- 👥 **Social** — suivre des étudiants, voir leurs activités
- ⭐ **Avis** — notes 1-5★ et commentaires après check-in
- 🏆 **Gamification** — XP, niveaux, 9 badges débloquables
- 📊 **Analytics partenaire** — Chart.js (inscriptions/j, check-in/h, source école)
- 🌙 **Dark mode** — persistant via localStorage
- 🔒 **CSRF + Rate limiting + Forgot password** — sécurité OWASP Top 10

## 🛠 Stack technique

| Couche | Technologie |
|--------|-------------|
| Backend | PHP 8.3, PDO / MySQL |
| Frontend | HTML5, CSS3 (custom properties), JS vanilla |
| Charts | Chart.js 4 |
| Styles | DM Sans + Playfair Display (Google Fonts) |
| Tests | PHPUnit 10, PHPStan niveau 5 |
| CI/CD | GitHub Actions (PHP 8.1 / 8.2 / 8.3) |
| Déploiement | Docker (Apache + mod_php + OPcache) ou hébergement mutualisé |
| Temps réel | Interrogation courte avec ETag et flux de révisions |
| Cache | APCu si présent, fichiers sinon |
| Base de données | MySQL 8 (local : WAMP / Railway : MySQL plugin) |
| PWA | Web App Manifest |

## 🚀 Installation locale

### Prérequis
- WAMP / XAMPP / Laragon (PHP ≥ 8.1, MySQL 8)
- Composer
- Git

### Étapes

**1. Placer le projet** dans le dossier web de WAMP, dans un dossier nommé **exactement** `TitreRNCP` :
`C:\wamp64\www\TitreRNCP`
> ⚠️ Le nom du dossier est important : en local, les liens internes utilisent le chemin `/TitreRNCP`.

**2. Créer la base de données** — une **seule** importation suffit (crée les 16 tables **et** un jeu de démonstration avec des événements toujours à venir) :
- **Via phpMyAdmin** (fourni avec WAMP) : ouvrir `http://localhost/phpmyadmin` → onglet **Importer** → choisir `db_setup.sql` → **Exécuter**.
- **Ou en ligne de commande** :
  ```bash
  mysql -u root < db_setup.sql
  ```

**3. Ouvrir l'application** :
`http://localhost/TitreRNCP/explore.php`
> Si Apache tourne sur un autre port, l'indiquer, ex. `http://localhost:8080/TitreRNCP/explore.php`.

**4. (Optionnel — uniquement pour lancer les tests)** installer les dépendances de développement :
```bash
composer install   # récupère PHPUnit / PHPStan (l'application fonctionne sans)
composer ci         # analyse statique + tests
```

### Comptes de démo

| Rôle | Email | Mot de passe |
|------|-------|-------------|
| Étudiant | arthur@uca.fr | password |
| Partenaire | jean@lebecquipique.fr | password |

## ☁️ Déploiement Railway

```bash
# Variables d'environnement Railway (configurées automatiquement)
MYSQLHOST, MYSQLPORT, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE

# Push → redéploiement automatique
git push origin main
```

Le fichier `includes/db.php` détecte automatiquement l'environnement Railway via `$_ENV`.

## 🧪 Tests

```bash
# Lancer tous les tests
composer test

# Analyse statique PHPStan
composer stan

# CI complet (stan + tests)
composer ci
```

Les tests couvrent :
- **Unitaires** : fonctions gamification (XP, niveau, badges), validation, auth helpers
- **Intégration** : persistance des badges avec SQLite in-memory

## 🗄 Schéma de base de données

Voir [`docs/MCD.md`](docs/MCD.md) pour le diagramme entité-relation complet.

**Tables principales :**

```
users → inscriptions → evenements → etablissements
      → squad_membres → squads
      → follows_users
      → avis
      → user_badges → badges
      → economies
```

## 🔒 Sécurité

Voir [`SECURITY.md`](SECURITY.md) pour la couverture OWASP Top 10 détaillée.

Points clés :
- PDO prepared statements (anti-SQLi)
- bcrypt `PASSWORD_DEFAULT` (hash mots de passe)
- CSRF tokens sur tous les POST
- Rate limiting login (5 tentatives / 15 min / IP)
- Headers HTTP : CSP, HSTS, X-Frame-Options
- Session regeneration après auth

## 📁 Structure du projet

```
TitreRNCP/
├── api/              # Endpoints JSON (inscriptions, follows, squads…)
├── assets/
│   ├── css/style.css # Design system complet
│   └── js/app.js     # JS vanilla (SPA-like, dark mode, timer)
├── auth/             # Login, register, logout, forgot, reset
├── docs/             # Documentation technique (MCD, UML, API, manuel)
├── includes/         # auth_check.php, db.php, security.php, gamification.php
├── partenaire/       # Dashboard, événements, create_event
├── tests/            # PHPUnit (Unit/ + Integration/)
├── .github/          # CI GitHub Actions
├── db_setup.sql      # Installation complète : 16 tables + données de démo
├── CHANGELOG.md
├── CONTRIBUTING.md
└── SECURITY.md
```

## 📖 Documentation

| Document | Description |
|----------|-------------|
| [`docs/MCD.md`](docs/MCD.md) | Modèle Conceptuel de Données (Mermaid ER) |
| [`docs/UML_DIAGRAMMES.md`](docs/UML_DIAGRAMMES.md) | Diagrammes UML (cas d'usage, séquence) |
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | Architecture technique & choix |
| [`docs/MANUEL_UTILISATEUR.md`](docs/MANUEL_UTILISATEUR.md) | Guide utilisateur étudiant |
| [`docs/API.md`](docs/API.md) | Référence des endpoints API |
| [`docs/PERFORMANCE.md`](docs/PERFORMANCE.md) | Montée en charge : mesures, temps réel, limites |
| [`SECURITY.md`](SECURITY.md) | Politique de sécurité & OWASP |
| [`CHANGELOG.md`](CHANGELOG.md) | Historique des versions |
| [`CONTRIBUTING.md`](CONTRIBUTING.md) | Guide de contribution |

## 📄 Licence

Projet propriétaire — Titre Professionnel Développeur Web et Web Mobile (DWWM), niveau 5.  
© 2026 Arthur Gramond — tous droits réservés.
