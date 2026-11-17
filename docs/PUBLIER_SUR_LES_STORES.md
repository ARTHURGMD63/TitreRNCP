# Publier Outsee sur l'App Store et Google Play

Ce que le code ne peut pas faire à ta place : les comptes, les clés de
signature et la soumission. Cette liste donne l'ordre et les commandes.
À lancer depuis `mobile/`, dans un terminal où tu es connecté à Expo.

## Avant le premier build

1. **Comptes.** Apple Developer Program (99 €/an) et Google Play Console
   (25 €, une fois). Les deux demandent une pièce d'identité et prennent
   parfois plusieurs jours à valider.
2. **Compte Expo** : `npx eas-cli@latest login`, puis `npx eas-cli@latest init`
   (crée le projet EAS et ajoute son identifiant dans `app.json`).
3. **Adresse du serveur.** `eas.json` fixe `EXPO_PUBLIC_API_URL` à
   `https://outsee.fr` pour `preview` et `production`. **Vérifie que c'est bien
   le domaine du VPS** : un build parti avec une mauvaise adresse ne fonctionne
   sur aucun téléphone et ne se corrige qu'avec un nouveau build.
4. **Clé Google Maps Android** : variable EAS `GOOGLE_MAPS_ANDROID_API_KEY`
   (`eas env:create`). Sans elle, le build Android de production est refusé
   exprès (`app.config.js`) : la carte serait cassée.
5. **Pages légales et informations d'entreprise** à jour (mentions légales,
   politique de confidentialité) : les deux stores les contrôlent.

## Construire

```bash
npx eas-cli@latest build --platform android --profile production   # AAB signé
npx eas-cli@latest build --platform ios --profile production       # IPA signée
```

Au premier lancement, EAS propose de **générer lui-même** la clé de signature
Android et le certificat et le profil iOS : accepte, il les garde pour les
builds suivants. Pour iOS, il te demande tes identifiants Apple.

Pour essayer sur un téléphone avant les stores : profil `preview` (APK
installable directement sur Android).

## Envoyer aux stores

```bash
npx eas-cli@latest submit --platform ios --profile production
npx eas-cli@latest submit --platform android --profile production
```

- **Google Play** : la première version doit être envoyée à la main dans la
  console (fiche du store, questionnaire de contenu, politique de
  confidentialité). `eas submit` a besoin d'une clé de compte de service.
- **App Store Connect** : créer la fiche de l'app (nom, description, captures,
  catégorie, âge, politique de confidentialité), puis lancer la relecture.

## Ce que les relecteurs vérifient pour cette app

- **Suppression de compte depuis l'app** : en place (étudiant et établissement).
- **Aucun achat dans l'app** : l'app ne vend rien, la formule et les
  suppléments se règlent sur le site. Ne pas ajouter de bouton « payer » ou de
  lien vers le paiement dans l'app.
- **Compte de démonstration** : Apple demande un identifiant et un mot de passe
  de test, étudiant *et* établissement (avec formule active et compte BDE
  validé), à renseigner dans « Informations de relecture ».
- **Notifications push** : si elles sont ajoutées, il faut les clés APNs (Apple)
  et la configuration Firebase (Android).

## Numéros de version

`autoIncrement` de `eas.json` fait monter le numéro de build à chaque envoi :
les stores refusent deux fois le même. La version affichée (`1.0.0`) se change
dans `app.json` pour une vraie nouvelle version.
