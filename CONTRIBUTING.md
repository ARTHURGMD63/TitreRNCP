# Contributing — StudentLink

## Workflow Git

### Branches
- `main` : branche stable, toujours déployable. Auto-déployée sur Railway.
- `develop` : branche d'intégration des features.
- `feature/<nom-court>` : nouvelle fonctionnalité (ex. `feature/csrf-tokens`).
- `fix/<nom-court>` : correction de bug (ex. `fix/wallet-checkin`).
- `hotfix/<nom-court>` : correction urgente sur prod, mergée directement dans `main`.
- `chore/<nom-court>` : tâches non fonctionnelles (deps, CI, doc).

### Cycle de vie d'une feature

```bash
git checkout develop
git pull
git checkout -b feature/ma-feature
# ... commits ...
git push -u origin feature/ma-feature
# Ouvrir une Pull Request feature/ma-feature → develop
```

Une fois la PR validée par la CI (lint + PHPStan + PHPUnit), elle est mergée
dans `develop`. Quand `develop` est stable, elle est mergée dans `main` via
une PR de release.

## Conventional Commits

Tous les messages de commit doivent suivre la spec
[Conventional Commits 1.0](https://www.conventionalcommits.org/fr/v1.0.0/).

### Format

```
<type>(<scope>): <description courte>

<body optionnel — pourquoi, pas comment>

<footer optionnel — BREAKING CHANGE, refs issues>
```

### Types autorisés

| Type       | Usage                                                                |
|------------|----------------------------------------------------------------------|
| `feat`     | Nouvelle fonctionnalité visible utilisateur                          |
| `fix`      | Correction de bug                                                    |
| `docs`     | Documentation seule (README, CHANGELOG, commentaires)                |
| `style`    | Formatage, indentation, sans changement de comportement              |
| `refactor` | Changement de code sans ajouter de feature ni corriger de bug        |
| `perf`     | Amélioration de performance                                          |
| `test`     | Ajout ou correction de tests                                         |
| `build`    | Build system, dépendances (composer.json, Dockerfile)                |
| `ci`       | Pipeline CI/CD (.github/workflows/)                                  |
| `chore`    | Maintenance, ne change ni le code source ni les tests                |
| `security` | Correction d'une faille de sécurité                                  |

### Scopes courants

`auth`, `wallet`, `events`, `squads`, `profil`, `api`, `partner`, `gamification`, `db`, `legal`, `ui`.

### Exemples

```
feat(gamification): ajoute la barre XP et les niveaux sur le profil
fix(wallet): corrige le check-in en double quand on scanne deux fois
security(api): ajoute un token CSRF sur create_squad
refactor(db): centralise la connexion PDO dans includes/db.php
docs(readme): documente le déploiement Railway
test(gamification): couvre xpForLevel et getLevel à 100%
ci: ajoute PHPStan dans le workflow CI
```

### Breaking changes

Soit dans le type avec `!`, soit dans le footer :

```
feat(api)!: change le format de réponse de /api/follow.php

BREAKING CHANGE: la clé `count` est renommée en `followers_count`.
```

## Qualité avant push

```bash
composer install
composer ci    # = phpstan + phpunit
```

La CI bloque tout commit qui :
- ne passe pas `php -l` (syntaxe)
- déclenche une erreur PHPStan niveau 5
- casse au moins un test PHPUnit

## Tests

- Tests unitaires : `tests/Unit/` (pas de BDD, fonctions pures)
- Tests d'intégration : `tests/Integration/` (SQLite en mémoire)
- Lancer : `composer test` ou `vendor/bin/phpunit`
- Couverture : `composer test:coverage` (sortie HTML dans `coverage/`)

## Pull Requests

Une PR doit :
1. Avoir un titre en Conventional Commit (`feat(scope): ...`)
2. Référencer l'issue qu'elle résout (`Closes #12`)
3. Décrire **pourquoi** plus que **comment**
4. Inclure des tests si elle ajoute du code testable
5. Mettre à jour le `CHANGELOG.md` (section `[Unreleased]`)
6. Passer la CI
