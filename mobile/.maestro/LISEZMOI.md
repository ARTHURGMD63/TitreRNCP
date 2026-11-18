# Parcours de bout en bout (Maestro)

Six parcours pilotent l'application installée sur un émulateur ou un
téléphone Android, comme un utilisateur le ferait : connexion, inscription à
une soirée, validation d'une présence par le partenaire, déconnexion.

| Parcours | Compte de démo | Ce qu'il prouve |
|---|---|---|
| `01_connexion` | arthur@uca.fr | l'étudiant arrive sur l'accueil connecté |
| `02_connexion_refusee` | arthur@uca.fr | un mauvais mot de passe est refusé, sans session |
| `03_connexion_champs_vides` | — | rien ne part au serveur si les champs sont vides |
| `04_inscription_evenement` | camille@demo.sl | s'inscrire à « Mojito Night » et la retrouver dans Pass |
| `05_scan_validation_manuelle` | jean@lebecquipique.fr | le partenaire valide Léa Dubois à la main |
| `06_deconnexion` | arthur@uca.fr | la déconnexion tient après un redémarrage |

Le mot de passe de tous les comptes de démo est `password` (voir `db_setup.sql`).

## Pourquoi pas de scan de QR code

La caméra d'un émulateur ne lit pas de QR code. Le parcours 05 emprunte donc
la validation manuelle, qui appelle la même route (`partenaire_scan.php`) avec
l'identifiant d'inscription au lieu du code. Le décodage du QR par la caméra
reste à vérifier à la main sur un vrai téléphone.

## Lancer

1. **Une base jetable**, jamais la base de travail. Les parcours 04 et 05 écrivent
   (une inscription, un check-in) et ne se rejouent pas sur la même base :
   réimporter `db_setup.sql` avant chaque série. Attention : ce fichier contient
   `CREATE DATABASE … outsee` et `USE outsee;` en dur ; pour viser une autre base,
   retirer ces deux lignes avant l'import.
2. **Le serveur**, joignable depuis l'émulateur. Là-bas, `localhost` est
   l'émulateur lui-même ; la machine hôte s'appelle `10.0.2.2` :
   `php -S 0.0.0.0:8080 -t C:/wamp64/www`.
3. **Une build de développement** qui vise ce serveur (Expo Go ne convient pas :
   l'identifiant de l'application doit être `fr.outsee.app`) :
   ```bash
   EXPO_PUBLIC_API_URL=http://10.0.2.2:8080/TitreRNCP npx expo run:android
   ```
4. **Maestro** ([installation](https://docs.maestro.dev/getting-started/installing-maestro),
   Java 17 requis), puis, depuis `mobile/` :
   ```bash
   npm run e2e                                   # les six parcours
   maestro test .maestro/flows/01_connexion.yaml  # un seul
   maestro test --include-tags=critique .maestro  # ceux marqués « critique »
   ```

## Écrire un parcours

- Repérer un élément par son `id` (le `testID` posé dans le code) plutôt que
  par son texte quand le texte peut changer. Les `testID` en place :
  `connexion-email`, `connexion-mot-de-passe`, `connexion-valider`,
  `connexion-erreur`, `evenement-rejoindre`, `partenaire-scanner`, `scan-manuel`,
  `scan-manuel-recherche`, `scan-manuel-valider-<id>`, `deconnexion`.
- Les textes attendus par les parcours (« Email ou mot de passe incorrect. »,
  « Inscrit »…) viennent de `src/regles.ts`, que `tests/regles.test.ts` fige :
  si un texte change, le test unitaire casse avant le parcours.
- `inputText` ne saisit que de l'ASCII sur Android (limite d'adb) : chercher
  « Dubois », pas « Léa ».
- Une sous-routine commune va dans `sous-flows/`, hors de la portée de
  `config.yaml`, pour ne pas être lancée seule.
