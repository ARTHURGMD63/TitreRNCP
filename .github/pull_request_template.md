## Pourquoi

<!-- Le problème ou le besoin, en deux ou trois phrases. Le « pourquoi » compte plus que le « comment » : le code dit déjà comment. -->

## Ce qui change

<!-- Une liste courte, par sujet. Pas un copier-coller des commits. -->

-

## Comment vérifier

<!-- Les étapes pour voir que ça marche, ou la commande de test. -->

## Avant de fusionner

- [ ] Le titre de la PR est un [Conventional Commit](../CONTRIBUTING.md#conventional-commits) (`feat(scope): …`)
- [ ] Chaque commit a un titre valide et traite un seul sujet
- [ ] Les tests couvrent le nouveau code, et `composer ci` passe
- [ ] `CHANGELOG.md` est à jour (section `[Unreleased]`)
- [ ] La documentation touchée est à jour (`docs/`, `README.md`)
- [ ] Aucun secret, jeton ni donnée réelle dans le diff

Closes #
