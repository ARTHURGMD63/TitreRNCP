# Contributing — Linkee

## Workflow Git

### Branches

- `main` : la branche stable. **Un push sur `main` déploie en production**
  (`.github/workflows/deploiement.yml`, par FTP). On n'y pousse donc jamais
  directement : tout passe par une Pull Request.
- `<type>/<sujet-court>` : une branche par sujet, nommée comme le type de commit
  qu'elle porte — `feat/`, `fix/`, `refactor/`, `perf/`, `test/`, `docs/`,
  `ci/`, `chore/`, `hotfix/` (correction urgente de la production).
  Exemples : `feat/mobile-compte-legal`, `fix/wallet-checkin`.
- Une branche ne mélange pas les sujets : un refactor et une fonctionnalité sont
  deux branches. Si la seconde dépend de la première, on l'**empile** (elle part
  du sommet de la première) et on ouvre la PR de la première en premier.
- Une branche vit quelques jours, pas quelques mois : elle est supprimée dès
  qu'elle est fusionnée.

### Cycle de vie d'un changement

```bash
git fetch origin
git switch -c feat/ma-feature origin/main
# ... commits (voir « Conventional Commits » ci-dessous) ...
composer ci                      # PHPStan + PHPUnit
git push -u origin feat/ma-feature
# Ouvrir une Pull Request feat/ma-feature → main
```

La PR passe la CI (syntaxe, PHPStan, PHPUnit, intégration MariaDB, application
mobile, messages de commit) puis est **fusionnée en « squash »** : `main` reçoit
un seul commit par PR, dont le message est le titre de la PR. Ce titre suit donc
les Conventional Commits, et la CI le vérifie.

### Versions de l'application mobile

Une version se publie en posant une étiquette `mobile-vX.Y.Z` (semver) :

```bash
git tag mobile-v1.4.0 && git push origin mobile-v1.4.0
```

Le workflow `mobile.yml` construit alors l'APK et l'IPA (voir son en-tête).
On ne réutilise jamais une étiquette.

## Conventional Commits

Tous les messages de commit doivent suivre la spec
[Conventional Commits 1.0](https://www.conventionalcommits.org/fr/v1.0.0/).

### Format

```
<type>(<scope>): <description courte>

<body optionnel — pourquoi, pas comment>

<footer optionnel — BREAKING CHANGE, refs issues>
```

### Règles de rédaction

- **Titre** : 72 caractères au plus (GitHub le tronque au-delà), sans point final,
  à l'indicatif ou au nominal (`ajoute la barre XP`, `découpe de crm.php`).
- **Un commit, un sujet.** Si le titre demande un « et » entre deux choses sans
  rapport, ce sont deux commits.
- **Le corps dit pourquoi**, pas comment : le diff montre déjà le comment. Une
  ligne vide sépare le titre du corps.
- **Un seul type.** `ci/docs:` n'existe pas : si un changement touche la CI et la
  documentation, ce sont deux commits.
- **Pas de `fixup!` ni de `squash!`** dans une PR : fusionne-les avant
  (`git rebase -i --autosquash`).
- Les tests et la documentation d'un changement vont avec lui, dans le même commit.

Ces règles sont vérifiées par la CI sur chaque Pull Request
(`.github/workflows/commits.yml`), et se lancent aussi en local :

```bash
bash .github/scripts/verifier_commits.sh origin/main HEAD
```

Pour avoir un gabarit à l'ouverture de l'éditeur de commit :

```bash
git config commit.template .gitmessage
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
docs(readme): documente le déploiement FTP
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
- déclenche une erreur PHPStan niveau 6
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
