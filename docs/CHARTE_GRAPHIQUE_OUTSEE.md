# Charte graphique Outsee

Version de référence du 2026-11-17, après la refonte de l'application (2026-11-16) et celle du site (2026-11-17).

Ce document décrit ce que le code dessine réellement. Chaque valeur est tirée d'un fichier du dépôt, cité en tête de section. Quand un autre document (kit de marque, plaquette, commentaire de code) dit autre chose, **le code fait foi** et l'écart est consigné en section 12.

Fichiers de référence :

| Domaine | Fichier |
|---|---|
| Jetons de l'application | `mobile/src/theme.ts`, `mobile/src/useTheme.tsx` |
| Composants de l'application | `mobile/src/composants/` (Design, Texte, Bouton, Elements, Formulaire, Marque, Icone, BarreOnglets, Feuille, Toast, Ecran) |
| Icône, splash | `mobile/app.json`, `mobile/assets/` |
| Jetons et composants du site | `assets/css/site.css` |
| Vitrine | `assets/css/vitrine.css`, `index.php`, `application.php` |
| Logo, icônes, gabarits du site | `includes/site.php` |
| Polices | `assets/fonts/`, `mobile/src/app/_layout.tsx` |
| Images de la vitrine | `assets/img/site/` |

## 1. Principes

1. **Deux univers, une marque.** L'**application** (Expo / React Native, `mobile/`) est le produit des étudiants et l'outil mobile des établissements ; elle a deux thèmes et suit par défaut le réglage du téléphone. Le **site** (PHP, un seul thème clair) ne sert plus que les visiteurs, avec la **vitrine** (`index.php`), et les établissements, avec l'**espace pro** (`partenaire/`), où se gèrent compte et paiements. Un étudiant qui arrive sur le site est renvoyé vers l'application (`application.php`).
2. **Une couleur par rubrique.** Le logo, le surlignage du titre d'écran, la carte principale et les boutons d'action principale prennent la couleur de la rubrique : bleu, rouge Sport ou violet Culture (`teintes()` dans `theme.ts`). Le bleu `#1D55F0` est l'accent principal des deux univers.
3. **Une police : Onest**, de 400 à 700, dans toute l'application et sur tout le site. Une seule exception : Unbounded pour les titres des pages de connexion et d'inscription du site (voir section 4).
4. **Aucun emoji, nulle part** : ni dans l'interface, ni dans les données, ni dans les documents. Un pictogramme est une icône dessinée (`Icone` côté mobile, `iconeSite()` côté site) ou un mot.
5. **Des ombres rares.** Seul ce qui flotte (barre d'onglets, toasts, menus) et la carte accent principale portent une ombre (`ombre()`, `halo()`). **Accessibilité.** La cible tactile minimale est de 44 pt (`toucheMin`). Le site affiche un anneau de focus bleu de 3 px. Les animations s'arrêtent avec `prefers-reduced-motion`.

## 2. Logo

Sources : `mobile/src/composants/Marque.tsx`, `includes/site.php` (`marqueSite()`), `assets/css/site.css` (bloc « Logo »).

### Construction

Le logo associe une **tuile** (le symbole) et un **logotype**.

| Élément | Valeur dans le code |
|---|---|
| Tuile | carré de 512 × 512, coins `rx = 112`, soit environ 22 % du côté, rempli de la couleur de la rubrique |
| Anneau gauche | cercle `cx 196.5`, `cy 255.5`, `r 88.5`, trait de 43, couleur basalte `#111116` |
| Anneau droit | cercle `cx 314.5`, `cy 255.5`, `r 88.5`, trait de 43, couleur craie `#F2F2F5` |
| Entrelacement | l'anneau basalte est redessiné sur la moitié basse (`clipPath` de y = 255.5 à 512) : en haut, la craie passe devant ; en bas, le basalte repasse devant |
| Logotype | « Outs » à l'encre du thème, puis « ee » à la couleur de la rubrique |
| Police du logotype | Onest 700 (`police.display` dans l'app, `.marque__mot` sur le site), interlettrage -0,035 em |
| Suffixe éventuel (« pro ») | Onest 400, gris, interlettrage -0,03 em (`.marque__suffixe`, `police.displayRegular`) |
| Proportions | tuile = 1,6 × le corps du mot ; écart tuile-mot = 0,4 × le corps |
| Interligne du mot (app) | 1,15 × le corps |

Le dessin n'existe qu'à deux endroits : `Marque.tsx` (app) et `marqueSite()` (site). Le commentaire de `Marque.tsx` le dit : la charte interdit de le recomposer ailleurs.

Variantes de l'application : `Marque` (tuile et mot) ; `Anneaux` (la tuile seule : splash d'introduction `IntroSplash.tsx`, 150 px) ; `AnneauxSeuls` (les anneaux sans tuile, sur un aplat coloré qui sert déjà de fond : onboarding, 190 px de large).

### Couleur par rubrique

| Rubrique | Écrans | Couleur du carré et du « ee » |
|---|---|---|
| `bleu` (par défaut) | Explorer, Personnes, Pass, Moi, espace partenaire, tout le site | `#1D55F0` |
| `sport` | Sport (`squads`), créneaux, terrains du partenaire club | `#E02D24` |
| `culture` | Culture | `#7B4DF2` |

Sur le site, `marqueSite($suffixe, $href, $couleur = '#1D55F0')` accepte une autre couleur, mais toutes les pages emploient la valeur par défaut. Sur fond sombre, le mot passe en blanc (`.sombre .marque`, `.marque--claire`) et le suffixe en `--gris-pale`.

### Tailles relevées

| Contexte | Corps du mot | Tuile |
|---|---|---|
| App, valeur par défaut (`fs[6]`) | 20 pt | 32 pt |
| App, en-tête d'écran (`EnTeteApp`, `fs[5] + 2`) | 18 pt | 28,8 pt |
| Site, `.marque` | 22 px | 35,2 px |
| Vitrine, barre de navigation | 20 px (17 px sous 600 px) | 32 px (27,2 px) |

Le code ne fixe ni taille minimale ni zone de protection. La plus petite occurrence, environ 27 px de tuile, sert de minimum de fait.

### Usages interdits

D'après les commentaires de `Marque.tsx` et `site.php` :
- recomposer le logo ailleurs que dans `Marque.tsx` ou `marqueSite()` ; inverser l'entrelacement ou peindre les anneaux autrement qu'en basalte et craie ;
- écrire « OutSee », « outsee » ou « Linkee » : le logotype s'écrit « Outsee », O majuscule depuis la refonte ; colorer le « ee » autrement que le carré ;
- éclaircir le bleu du logo en mode sombre : la teinte reste exacte, choix de marque assumé (commentaire de `fixe.logo`).

### Icône d'application et splash

Source : `mobile/app.json`.

| Élément | Valeur |
|---|---|
| Icône iOS / générale | `./assets/icon.png` |
| Icône adaptative Android | fond `#1D55F0`, avant-plan `android-icon-foreground.png`, image de fond `android-icon-background.png`, version monochrome `android-icon-monochrome.png` |
| Splash | fond `#111013` en clair comme en sombre, image `splash-icon.png`, 250 px de large, `contain` ; `backgroundColor` de l'app `#111013` ; `userInterfaceStyle: automatic` |
| Favicons | app web `./assets/favicon.png` ; site `/Logo.png` (`siteDebut()`), avec `theme-color` `#111013` |

## 3. Couleurs

### 3.1 Application, jetons de marque (identiques dans les deux thèmes)

Source : `mobile/src/theme.ts`, objet `marque`. Les noms de jetons sont historiques : `rouge` vaut le bleu Outsee. Ils sont conservés pour ne pas réécrire les écrans.

| Jeton | Hex | Usage |
|---|---|---|
| `rouge` | `#1D55F0` | Accent principal : bouton `primaire`, focus des champs, cases cochées, compteur de tuile active |
| `rougeDeep` | `#1A47C8` | Bleu foncé |
| `bleu` | `#5B8CFF` | « Dôme » : fond d'avatar sans photo par défaut |
| `bleuDeep` | `#2A56C0` | Dôme foncé |
| `lime` | `#C8F547` | « Volt » : fond du toast de succès, point de l'encart `ok` |
| `limeDeep` | `#4D6B00` | Volt foncé |
| `orange` | `#FFC23D` | « Moutarde » |
| `orangeDeep` | `#8A5A00` | Moutarde foncée |
| `alerteVif` | `#FF4D6A` | Bouton `alerte` (« Supprimer définitivement »), toast d'erreur |
| `surLave` | `#111116` | Texte basalte sur un aplat vif et clair |

### 3.2 Application, thème clair

Source : `theme.ts`, objet `clair`.

| Jeton | Hex | Usage |
|---|---|---|
| `bg` | `#F2F2F5` | Fond d'écran |
| `blanc` | `#FFFFFF` | Surface : cartes, boutons d'en-tête, chips au repos |
| `surface2` | `#EDEDF1` | Surface secondaire : champs, options de filtre, bouton désactivé |
| `noir` | `#111116` | Encre : texte, élément actif (chip, tuile, segment) |
| `grisFonce` | `#3C3C46` | Texte secondaire appuyé, libellé d'un bouton désactivé |
| `gris` | `#5A5A66` | Texte secondaire, étiquettes, surtitres |
| `grisClair` | `#E4E4EA` | Filets de carte, carré d'icône désactivé |
| `line2` | `#D4D4DC` | Filets de champ, contours, séparateurs, poignée de feuille |
| `surEncre` | `#FFFFFF` | Texte posé sur un élément à l'encre |
| `surRougeClair` | `#1A47C8` | Texte d'accent (bouton `texte`) |
| `surBleuClair` | `#2A56C0` | Texte dôme |
| `surLimeClair` | `#137A44` | Texte volt |
| `surOrangeClair` | `#8A5A00` | Texte moutarde |
| `rougeClair` | `#E3EAFF` | Fond bleu pâle, anneau de focus des champs |
| `bleuClair` | `#E3EBFF` | Fond dôme pâle |
| `limeClair` | `#EEF9CF` | Fond volt pâle |
| `orangeClair` | `#FFF1D1` | Fond moutarde pâle |

### 3.3 Application, thème sombre

Source : `theme.ts`, objet `sombre`. Le jeton `noir` y vaut la craie : c'est l'encre du thème, pas une couleur fixe. Pour une couleur qui ne doit pas bouger, on passe par `fixe`.

| Jeton | Hex | Usage |
|---|---|---|
| `bg` | `#0E0E12` | Fond d'écran |
| `blanc` | `#1C1C22` | Surface |
| `surface2` | `#2A2A32` | Surface secondaire |
| `noir` | `#F2F2F5` | Encre |
| `grisFonce` | `#C9C9D3` | Texte secondaire appuyé |
| `gris` | `#A7A7B3` | Texte secondaire |
| `grisClair` | `#2A2A32` | Filets |
| `line2` | `#3A3A44` | Filets de champ, contours |
| `surEncre` | `#111116` | Texte sur élément à l'encre |
| `surRougeClair` | `#8FA9FF` | Texte d'accent lisible sur fond sombre |
| `surBleuClair` | `#5B8CFF` | Texte dôme |
| `surLimeClair` | `#3DDC84` | Texte volt |
| `surOrangeClair` | `#FFC23D` | Texte moutarde |
| `rougeClair` | `#18213F` | Fond bleu sourd, anneau de focus |
| `bleuClair` | `#18223D` | Fond dôme sourd |
| `limeClair` | `#273011` | Fond volt sourd |
| `orangeClair` | `#33280F` | Fond moutarde sourd |

Choix du thème (`useTheme.tsx`) : trois préférences, `systeme` (défaut), `clair` et `sombre`, réglées dans Moi › Apparence et enregistrées sous la clé `outsee.theme`. L'ancienne clé `linkee.theme` est encore lue une fois, pour la migration. Si le téléphone ne dit pas son réglage, l'app prend le sombre.

### 3.4 Couleurs d'état

| Jeton app | Clair | Sombre | Jeton site | Site |
|---|---|---|---|---|
| `succes` | `#137A44` | `#3DDC84` | `--succes` | `#137A44` |
| `succesClair` | `#D9F7E6` | `#123222` | `--succes-doux` | `#D9F7E6` |
| `danger` | `#C4203F` | `#FF4D6A` | `--danger` | `#C4203F` |
| `dangerClair` | `#FFE4E9` | `#3A151D` | `--danger-doux` | `#FFE4E9` |
| `alerte` | `#8A5A00` | `#FFC23D` | `--alerte` | `#8A5A00` |
| `alerteClair` | `#FFF1D1` | `#33280F` | `--alerte-doux` | `#FFF1D1` |

Les états sont identiques sur le site et dans l'app en thème clair.

### 3.5 Teintes de rubrique (`teintes(rubrique, mode)`, hook `useRubrique()`)

Source : `theme.ts` (`TEINTES`), `Design.tsx`.

| Rubrique | Mode | `accent` (aplat) | `surAccent2` (texte secondaire sur l'aplat) | `doux` (fond de pastille) | `texteDoux` (texte de pastille) |
|---|---|---|---|---|---|
| bleu | clair | `#1D55F0` | `#E3EAFF` | `#E3EAFF` | `#1A47C8` |
| bleu | sombre | `#1D55F0` | `#E3EAFF` | `#1D2647` | `#A9BDFF` |
| sport | clair | `#E02D24` | `#FDE3E1` | `#FDE3E1` | `#A3160F` |
| sport | sombre | `#E02D24` | `#FDE3E1` | `#3D1A18` | `#FF9C96` |
| culture | clair | `#7B4DF2` | `#EDE5FF` | `#EDE5FF` | `#5B3BB5` |
| culture | sombre | `#7B4DF2` | `#EDE5FF` | `#2C2250` | `#C7B3FF` |

L'accent ne change pas d'un thème à l'autre. Ce qu'on pose dessus est blanc (`fixe.surAccent`).

### 3.6 Couleurs fixes de l'application (`fixe`, indépendantes du thème)

| Jeton | Valeur | Usage |
|---|---|---|
| `qrSombre` / `qrClair` | `#111116` / `#FFFFFF` | Modules et fond d'un QR code, toujours sombre sur clair |
| `basalte` | `#111116` | Anneau du logo, bouton `basalte`, fond du bouton retour sur média, fond du splash animé |
| `craie` | `#F2F2F5` | Anneau du logo, texte du bouton `basalte` |
| `surLave` | `#111116` | Texte sur aplat vif et clair (volt, moutarde, alerte) |
| `surAccent` | `#FFFFFF` | Texte sur l'aplat d'accent |
| `surMedia` | `#FFFFFF` | Texte posé sur une photo |
| `logo` | `#1D55F0` | Couleur par défaut de la tuile `Anneaux` |
| `barre` | `#16161C` | Barre d'onglets flottante, dans les deux thèmes |
| `barreIcone` | `#A7A7B3` | Icônes et libellés des onglets inactifs |
| `enCours` | `#2BD67B` | Point « En cours » |
| `notif` | `#FF3B30` | Pastille de notification non lue |
| `squadCommun` | `#FFE58A` | Repère « Squad commun » |
| `voile` | `rgba(17,17,22,0.45)` | Voile derrière une feuille (panneau Filtres) |

`encreSur(fond)` choisit l'encre à poser sur un aplat variable (avatar, badge, bouton central de la barre) : blanc ou basalte, selon le meilleur contraste WCAG.

### 3.7 Couleurs de catégorie et d'ambiance

Source : `theme.ts` (`couleursCategorie`, `couleursConfettis`), `Design.tsx` (`TEINTES_AVATAR`).

| Catégorie (code API) | Couleur du petit carré de chip |
|---|---|
| `pour-moi`, `spectacle` | `#FFB020` |
| `carte`, `tennis` | `#22B573` |
| `bar`, `squash` | `#FF8A3D` |
| `boite`, `concert` | `#8B5CF6` |
| `resto`, `expo` | `#F0508A` |
| `padel` | `#3B82F6` |
| `golf`, `patrimoine` | `#14B8A6` |

Confettis : `#FF8A3D`, `#FFB020`, `#22B573`, `#F0508A`, `#8B5CF6`, plus l'accent de la rubrique. Teintes douces des avatars sans photo (`teinteAvatar()`, stable par prénom) : `#CDBBF7`, `#F9C6D3`, `#FFD9A0`, `#C7DCFF`, `#C9F0D6`, `#FFE58A`, `#D9D9E0`.

La vitrine reprend les mêmes couleurs de catégorie pour ses pastilles flottantes (section 8).

### 3.8 Site, jetons `:root`

Source : `assets/css/site.css`. Le site n'a pas de mode sombre : il n'y a aucune règle `prefers-color-scheme`.

| Jeton | Valeur | Usage |
|---|---|---|
| `--bleu` | `#1D55F0` | Accent : bouton plein, liens, focus, jauges, carte accent |
| `--bleu-fonce` | `#1A47C8` | Survol du bouton plein, texte de pastille et de menu actif |
| `--bleu-doux` | `#E3EAFF` | Fond de pastille, de menu actif, d'avatar, de message d'info |
| `--bleu-clair` | `#8FA9FF` | Mot en `<em>` et icônes sur fond basalte (pages de connexion) |
| `--sport` | `#E02D24` | Rouge Sport |
| `--sport-doux` | `#FDE3E1` | Fond de `.pastille--sport` |
| `--culture` | `#7B4DF2` | Violet Culture |
| `--culture-doux` | `#EDE5FF` | Fond de `.pastille--culture` |
| `--basalte` | `#111013` | Panneau visuel des pages de connexion, `.carte--sombre`, modale de scan |
| `--basalte-2` | `#1B1A1F` | Ligne d'invité dans la modale de scan |
| `--basalte-3` | `#26252B` | Résultat de scan neutre, champ de recherche de la modale |
| `--craie` | `#F2F2F5` | Fond de page, survol de menu, piste de jauge |
| `--blanc` | `#FFFFFF` | Surfaces : cartes, menu latéral, champs |
| `--encre` | `#111116` | Texte, bouton encre, onglet actif |
| `--gris-fonce` | `#3C3C46` | Libellés de champ, liens du menu |
| `--gris` | `#5A5A66` | Texte secondaire, surtitres |
| `--gris-pale` | `#A7A7B3` | Titre de groupe du menu, suffixe sur fond sombre |
| `--ligne` | `#E4E4EA` | Filets de carte, de tableau, de menu |
| `--ligne-2` | `#D4D4DC` | Bordure des champs et des boutons clairs |
| `--succes` … `--alerte-doux` | voir 3.4 | États |
| `--f-display` | `'Unbounded', 'Arial Black', sans-serif` | Titres des pages de connexion |
| `--f-sans` | `'Onest', system-ui, -apple-system, 'Segoe UI', sans-serif` | Tout le reste |
| `--r-xs` / `--r-sm` / `--r-bouton` / `--r-carte` / `--r-xl` / `--r-pill` | 8 / 12 / 16 / 20 / 28 / 999 px | Rayons |
| `--ombre-flottante` | `0 16px 40px rgba(17,17,22,.12)` | Menu pro en tiroir |
| `--gouttiere` | 24 px (16 px sous 640 px) | Marge latérale de `.conteneur` |
| `--largeur` | 1180 px | Largeur maximale de `.conteneur` |

Couleurs écrites en dur dans `site.css` : `#2BD67B` (point « en direct »), `#A3160F` et `#5B3BB5` (texte des pastilles Sport et Culture, les mêmes que `texteDoux` dans l'app), `#E3EAFF` (texte secondaire sur une carte accent), `#C9C9D3` (liste à puces des pages de connexion), `#FAFAFC` (survol d'une ligne de tableau).

## 4. Typographie

Sources : `theme.ts`, `Texte.tsx`, `site.css`, `vitrine.css`, `assets/fonts/`, `mobile/src/app/_layout.tsx`.

### 4.1 Familles

| Famille | Graisses | Où | Chargement |
|---|---|---|---|
| **Onest** | 400, 500, 600, 700 | Toute l'application ; tout le site (vitrine, espace pro, corps des pages de connexion) | App : `@expo-google-fonts/onest` via `useFonts` (`_layout.tsx`). Site : `assets/fonts/Onest_400Regular.ttf`, `_500Medium`, `_600SemiBold`, `_700Bold` en `@font-face`, `font-display: swap`. La 400 est préchargée (`<link rel="preload">` dans `siteDebut()`). Licence : `Onest-OFL.txt` |
| **Unbounded** | 200 à 900 (fichier variable) ; 800 employée | Titres `.auth__titre` et `.auth__visuel h2` (connexion, inscription, mot de passe oublié, réinitialisation) | `assets/fonts/unbounded-latin.woff2`, `font-display: swap` |

React Native ne synthétise pas les graisses : chaque graisse est une famille nommée (`police` dans `theme.ts`), et `sans(poids)` renvoie la bonne. Rôles de `police` : `display` et `sans700` → `Onest_700Bold` ; `displayRegular` et `sans400` → `Onest_400Regular` ; `sans500`, `mono400` et `mono500` → `Onest_500Medium` ; `sans600`, `sans600Italique` et `mono600` → `Onest_600SemiBold`. Il n'y a donc plus ni monospace ni italique dans l'app.

### 4.2 Échelle de l'application

Échelle historique `fs` (en points) :

| `fs[1]` | `fs[2]` | `fs[3]` | `fs[4]` | `fs[5]` | `fs[6]` | `fs[7]` | `fs[8]` | `fs[9]` | `fs[10]` |
|---|---|---|---|---|---|---|---|---|---|
| 11 | 12 | 13 | 14 | 16 | 20 | 24 | 32 | 36 | 48 |

Tailles de la refonte (`taille`), resserrées le 2026-11-16 pour qu'un téléphone de 390 pt montre plus d'une carte :

| Jeton | Taille | Rôle |
|---|---|---|
| `titreEcran` | 27 | Grand titre d'écran (`TitreEcran`) |
| `titreCarte`, `titreCarteSport` | 18 | Titre de carte |
| `section` | 17 | Titre de section (`TitreSection`) |
| `chiffre` | 22 | Chiffre clé |
| `secondaire` | 14 | Texte secondaire |
| `surtitre` | 11 | Surtitre |

Interlignes (`lh`, multiples du corps) : `display` 1,2 ; `tight` 1,2 ; `snug` 1,3 ; `normal` 1,5 ; `relaxed` 1,6.

Interlettrages (`lsEm`, en em, à multiplier par le corps avec `ls()`) : `display` -0,035 ; `carte` -0,03 ; `tight` -0,02 ; `normal` 0 ; `wide` 0,06 ; `label` 0,06.

### 4.3 Les voix de l'application (`Texte.tsx`)

| Composant | Police | Corps | Interligne | Interlettrage | Couleur par défaut |
|---|---|---|---|---|---|
| `Display` | Onest 700 | `fs[8]` (32) | 1,2 | -0,035 em | `c.noir` |
| `Mono` (surtitre) | Onest 600 (`mono(600)`) | `fs[2]` (12) | — | +0,06 em, capitales | `c.gris` |
| `T` (texte courant) | Onest 400 à 700 | `fs[4]` (14) | libre | 0 | `c.noir` |
| `TitreEcran` | Display | 27 | 1,2 | -0,035 em | encre ; mot clé surligné dans une pastille de la rubrique (texte blanc, rayon 10, marge 0 10 3) |
| `TitreSection` | Onest 700 | 17 | — | -0,02 em | `c.noir` |
| Libellé de bouton | Onest 700 | 15 (`fs[4] + 1`) | — | — | selon la variante |
| Titre « Filtres » d'une feuille | Onest 700 | 24 | — | -0,03 em | `c.noir` |

### 4.4 Échelle du site (`site.css`)

| Élément | Police, graisse | Taille | Interligne | Interlettrage |
|---|---|---|---|---|
| `body` | Onest 400 | 16 px | 1,5 | — |
| `.auth__titre` | Unbounded 800 | `clamp(30px, 3.4vw, 42px)` | 1,05 | -0,045 em |
| `.auth__visuel h2` | Unbounded 800 | `clamp(28px, 3vw, 40px)` | 1,05 | -0,04 em |
| `.titre-pro` (titre de page pro) | Onest 700 | `clamp(26px, 2.6vw, 34px)` | 1,12 | -0,035 em |
| `.tuile__valeur` (chiffre clé) | Onest 700 | 30 px (26 px sous 640 px) | 1,1 | -0,04 em |
| `.modale__tete h2` | Onest 700 | 20 px | — | — |
| `.vide h3` | Onest 700 | 18 px | — | — |
| `.carte__titre` | Onest 700 | 17 px | — | -0,02 em |
| `.bouton` | Onest 600 | 15 px (16 grand, 14 petit) | 1,2 | — |
| `.champ label` | Onest 600 | 14 px | — | — |
| `.pastille` | Onest 600 | 12 px | 1,4 | +0,02 em |
| `.surtitre` | Onest 600, capitales | 12 px | — | +0,08 em |
| `.tableau th` | Onest 600, capitales | 12 px | — | +0,06 em |
| `.pro-nav__titre` | Onest 600, capitales | 11 px | — | +0,08 em |

Dans un titre, le mot mis en valeur s'écrit en `<em>` : il est droit et coloré en `--bleu`, ou en `--bleu-clair` sur fond basalte (`.auth__titre em`, `.titre-pro em`). C'est l'équivalent web du surlignage de `TitreEcran`.

L'échelle de la vitrine est en section 8.

## 5. Formes et espacements

### 5.1 Rayons

Application (`rayon` dans `theme.ts`) :

| Jeton | Valeur | Emploi |
|---|---|---|
| `xs` | 8 | Pastille fine, carré de chip (26 px) |
| `sm` | 12 | Boutons d'en-tête et de retour, chips, champs, encarts d'état, `BoutonAction` fin |
| `md` | 16 | `Bouton`, `BoutonAction`, `BoutonContour`, tuiles, encarts `ok` et `info`, bouton central de la barre |
| `base`, `lg` | 24 | `Carte` (style historique `.card`) |
| `xl` | 32 | — |
| `pill` | 999 | Badges, segments, jauges, toasts |
| `carte` | 20 | `CarteRefonte` (carte d'écran) |
| `personne`, `tuile` | 18 | Carte de personne, tuile |
| `bouton` | 16 | Options de filtre, chips d'intérêt, champs (cadre du focus) |
| `grandBouton` | 18 | — |
| `mini` | 10 | Pastille du `TitreEcran`, carré d'icône du `BoutonAction` |
| `barre`, `panneau` | 28 | Barre d'onglets, haut des feuilles |

Autres rayons écrits en dur : `CarreIcone` 14 ; case d'onglet 18 ; case à cocher 6.

Site : `--r-xs` 8, `--r-sm` 12, `--r-bouton` 16, `--r-carte` 20, `--r-xl` 28, `--r-pill` 999. Écrits en dur : champs, messages, menu et onglets à 14 ; tuiles, carrés « vide » et lecteur de scan à 18 ; grand bouton à 18 ; modale à 24. La vitrine emploie 32 pour le cadre et les grands panneaux, 28 pour les tuiles, 26 pour les tarifs et 22 pour le verre et la navigation.

### 5.2 Ombres et halos

| Nom | Valeur | Emploi |
|---|---|---|
| `ombre(mode, 'lg')`, clair | couleur `#111116`, décalage 0 / 16, opacité 0,14, flou 22, élévation 10 | Barre d'onglets, toast |
| `ombre(mode, 'lg')`, sombre | couleur `#000`, décalage 0 / 18, opacité 0,5, flou 24, élévation 12 | idem |
| `ombre(mode, 'sm' / 'base')` | rien | Les cartes n'ont plus d'ombre |
| `halo(couleur)` | couleur de l'accent, décalage 0 / 14, opacité 0,3, flou 22, élévation 10 | Carte accent principale (`CarteRefonte` avec `accent`) |
| `haloLave` | `halo('#1D55F0')` | Carte flash, pass actif |
| Site `.carte--accent` | `0 14px 30px rgba(29,85,240,.25)` | Carte accent bleue |
| Site `--ombre-flottante` | `0 16px 40px rgba(17,17,22,.12)` | Menu pro en tiroir |
| Site, focus des champs | `0 0 0 4px rgba(29,85,240,.14)` et bordure `--bleu` | Champs |
| Site `:focus-visible` | contour `3px solid var(--bleu)`, décalage 2 px | Tout élément focalisable |

### 5.3 Marges, grilles et points de rupture

**Application.** Gouttière `gutter` de 18 ; cible tactile `toucheMin` de 44. Contenu d'écran limité à 440 de large et centré (`Ecran.tsx`), en-tête de page à 24 en haut et 12 en bas. Réserve basse sous la barre d'onglets : `ecartBasBarre + 62 + 16` (`reserveBarre()`). Rangée de chips : écart de 8, la dernière déborde et se coupe (`rangeeChips`). `CarteRefonte` : padding 14, écart interne 8, 16 dessous. Feuille : padding horizontal 22, 36 en bas plus la zone sûre, 90 % de l'écran au plus.

**Site.** `.conteneur` : 1180 px au plus, gouttière de 24 px. Grilles à 20 px d'écart : `.grille-2`, `.grille-3`, `.grille-tdb` (1,6fr / 1fr), `.pile`. `.tuiles` : `auto-fit`, 170 px au moins, écart 16 ; `.photos` : `auto-fill`, 180 px au moins, écart 14. `.formulaire` à 20 d'écart, `.formulaire__ligne` en 2 colonnes (3 avec `--3`), écart 16.

| Point de rupture | Fichier | Effet |
|---|---|---|
| 1100 px | vitrine | Bulles masquées ; tarifs sur 2 colonnes ; scène et réseau social sur 1 colonne |
| 1080 px | site | Tableau de bord sur 1 colonne ; `.grille-3` sur 2 colonnes |
| 960 px | site | Connexion sur 1 colonne, panneau visuel masqué ; menu pro en tiroir et barre haute de 64 px |
| 900 px | vitrine | Liens de navigation masqués ; bento sur 2 colonnes ; étapes sur 1 colonne |
| 640 px | site | Gouttière à 16 px ; grilles et formulaires sur 1 colonne ; actions d'en-tête pleine largeur |
| 600 px | vitrine | Cadre à 8 px des bords et rayon 24 ; anneaux, étiquettes et bouton « Télécharger » de la barre masqués ; tout sur 1 colonne |

Barre d'onglets de l'app : passe en mode compact (libellé court, corps de 10) quand une case fait moins de 64 de large.

## 6. Composants

### 6.1 Boutons

**Application, `Bouton` (`Bouton.tsx`).** Hauteur minimale de 44 (46 avec `plein`), padding 10 / 18, rayon 16, filet de 1,5, Onest 700 à 15, icône de 16 à gauche ou à droite.

| Variante | Fond | Texte | Filet | Rôle |
|---|---|---|---|---|
| `primaire` | `accent` de la rubrique, sinon `c.rouge` | blanc | = fond | Action principale, une seule par écran |
| `bleu` | `#E02D24` | blanc | = fond | Action principale de Sport (le nom est historique, la couleur est rouge) |
| `encre` | `c.noir` | `c.surEncre` | `c.noir` | Action forte neutre |
| `contour` | transparent | `c.noir` | `c.line2` | Secondaire |
| `basalte` | `#111116` | `#F2F2F5` | basalte | Pilule basalte posée sur un aplat d'accent |
| `texte` | transparent | `c.surRougeClair` | — | Tertiaire, padding horizontal de 4 |
| `alerte` | `c.alerteVif` | `#111116` | — | « Supprimer définitivement » |

Désactivé, un bouton plein sort de la couleur : fond `surface2`, texte `grisFonce`, filet `grisClair`. `contour` et `texte` passent à 50 % d'opacité. En chargement, un indicateur d'activité remplace le libellé.

**Application, `BoutonAction` (`Design.tsx`).** C'est l'action d'une carte (« S'inscrire »). Hauteur 44, rayon 16, et un carré d'icône de 34 à rayon 10. La version `fin` fait 40 de haut, avec un carré de 30 à rayon 9 et un rayon de 12.
Sur carte blanche : fond encre, carré blanc. Sur carte accent (`surAccent`) : fond blanc, texte et carré de l'accent. `fait` (« Inscrit ») : fond de l'accent, carré blanc avec une coche.

Des confettis partent du bouton, aux couleurs de l'accent, après une action réussie.

**Application, autres boutons.**
- `BoutonContour` : filet de 1,5 (`line2`, ou blanc à 45 % sur un accent), rayon 16, 44 de haut ; carré de 40 en version icône seule.
- `BoutonIcone` (en-tête) et `BoutonRetour` : carré de 42, rayon 12, fond `c.blanc`, icône de 19 ou 20. Une pastille rouge `fixe.notif` de 9 signale du non-lu. Action d'en-tête (« + Créer ») : 42 de haut, rayon 12, fond de l'accent, Onest 700 à 14 en blanc.

**Site, `.bouton` (`site.css`).** Hauteur minimale 44 px, padding 10 / 18, rayon 16, filet de 1,5 px, Onest 600 à 15 px, `scale(.98)` à l'appui.

| Modificateur | Rendu |
|---|---|
| `--plein` | fond `--bleu`, texte blanc ; survol `--bleu-fonce` |
| `--encre` | fond `--encre`, texte blanc ; survol `--gris-fonce` |
| `--clair` | fond blanc, filet `--ligne-2` ; survol filet encre |
| `--fantome` | transparent, filet `currentColor` |
| `--danger` | fond blanc, texte `--danger`, filet `--danger-doux` |
| `--texte` | lien bleu sans fond, souligné au survol |
| `--grand` / `--petit` / `--bloc` | 54 px, padding 14 / 24, 16 px, rayon 18 / 36 px, padding 6 / 12, 14 px, rayon 12 / pleine largeur |
| `[disabled]`, `.desactive` | 50 % d'opacité, non cliquable |

Le site a aussi `.bouton-icone` : carré de 38 px, rayon 12, filet `--ligne`, qui passe au rouge `--danger` au survol avec `--danger`. Les pilules et les boutons des stores de la vitrine sont décrits en section 8.

### 6.2 Cartes et tuiles

| Composant | Rendu |
|---|---|
| App `CarteRefonte` | Surface `c.blanc`, rayon 20, padding 14. Avec `accent` : aplat de l'accent et `halo()`. Seule la première carte d'une liste est en accent |
| App `Carte` (historique) | Surface, rayon 24, filet `grisClair`, sans ombre |
| App `Tuile` | 78 de haut au moins, padding 12, rayon 16, icône de 20, compteur dans une pilule de 24. Active, elle passe à l'encre et son compteur prend l'accent |
| App `CarreIcone` | Carré de 44, rayon 14, fond doux de la rubrique, icône à 48 % de la taille |
| Site `.carte` | Fond blanc, rayon 20, filet `--ligne`, padding 24. `--accent` : fond bleu et ombre bleue. `--sombre` : fond basalte |
| Site `.tuile` | Fond blanc, filet, rayon 18, padding 18 / 20. Libellé de 13 px, valeur de 30 px. `--accent` : fond bleu |
| Site `.jauge` | 8 px de haut, piste `--craie`, remplissage `--bleu` (blanc sur carte accent, `--danger` avec `--danger`) |
| Site `.histo` | Histogramme de 140 px, barres bleues arrondies à 10 / 10 / 4 / 4 |
| Site `.vide` | État vide : carré de 56 à rayon 18 en `--bleu-doux`, titre de 18 px |

### 6.3 Pastilles, badges, chips

| Composant | Rendu |
|---|---|
| App `Pastille` | Rayon 8, padding 3 / 8, Onest 500 à 12. `grande` : rayon 12, padding 6 / 10, 13. Peut porter un point (« En cours », `#2BD67B`), une icône ou un filet. Couleurs : `doux` et `texteDoux` de la rubrique |
| App `Badge` | Pilule, padding 4 / 10, Onest 500 à 11, capitales espacées de 0,06 em |
| App `Pilule` (chip de filtre) | 40 de haut au moins, rayon 12, filet de 1,5. Au repos : fond `c.blanc`. Active : fond et filet `c.noir`. `carre` : carré de 26 à rayon 8 aux couleurs de la catégorie, icône blanche de 15 |
| App `Segments` | Fond `c.blanc` en pilule, padding 5. Segment actif à l'encre, texte `c.bg`, Onest 600 à 14 |
| App `Jauge` | 6 de haut, en pilule |
| App `AvatarsEmpiles` | Cercles de 28 qui se chevauchent de 8, contour de 2 de la couleur du fond. Les places libres sont en pointillés avec un « + » |
| Site `.pastille` | Pilule, padding 4 / 10, Onest 600 à 12. Par défaut bleu doux sur bleu foncé. Variantes `--neutre`, `--succes`, `--danger`, `--alerte`, `--sport`, `--culture`, et `--direct` (point vert `#2BD67B`) |
| Site `.avatar` | 38 px, `--bleu-doux` sur `--bleu-fonce`, initiales en 700 |

### 6.4 Encarts, messages et toasts

| Composant | Rendu |
|---|---|
| App `Encart` `ok` | Surface, filet `grisClair`, rayon 16, point volt `c.lime` de 9 |
| App `Encart` `info` | `surface2`, filet en pointillés `line2`, rayon 16 |
| App `Encart` `erreur`, `alerte`, `succes` | Fond `*Clair`, filet et texte de l'état, rayon 12, padding 13 / 16 |
| App toast (`Toast.tsx`) | Pilule au-dessus de la barre d'onglets, Onest 600 à 13, ombre `lg`. Succès : fond volt `c.lime`. Erreur : `c.alerteVif`. Neutre : `c.noir`. Texte basalte sur les aplats vifs |
| Site `.message` | Rayon 14, padding 14 / 16, icône à gauche. Par défaut bleu doux. Variantes `--erreur`, `--succes`, `--alerte`. Les messages flash passent par `messageFlashPro()` |

### 6.5 Barre d'onglets (application)

Source : `BarreOnglets.tsx`.
- Barre flottante de 62 de haut, rayon 28, fond `#16161C` dans les deux thèmes, ombre `lg` ; 16 de marge de chaque côté, 440 de large au plus, environ 20 au-dessus du bas (`ecartBasBarre`).
- Cases de même largeur, qui ne bougent jamais : 50 de haut, rayon 18, icône de 20, libellé en Onest 11. Actif : case blanche, encre `#111116`, Onest 700. Inactifs : `#A7A7B3`, Onest 500.
- Onglets étudiant, dans l'ordre : Explorer (`loupe`), Personnes (`personnes`, libellé court « Réseau »), Sport (`trophee`), Culture (`musee`), Pass (`carte`), Moi (`personne`). Onglets partenaire : Tableau de bord (`appareil`, court « Accueil »), Événements (`calendrier`, court « Soirées »), Moi. Pour un club : Tableau, Événements, Terrains (`trophee`), Moi.
- Bouton central optionnel (« + Créer une soirée ») : carré de 46, rayon 16, icône de 22 au trait de 2,5. Un écran secondaire allume l'onglet dont il dépend : Notifications et Carte allument Explorer ; Classement et Abonnements allument Moi.

### 6.6 En-têtes de page

| Contexte | Rendu |
|---|---|
| App `EnTeteApp` | Logo à gauche (rubrique, corps de 18) ; à droite l'action éventuelle, puis Recherche (`loupe`) et Notifications (`cloche`). Écart de 12, 14 sous l'en-tête |
| App `TitreEcran` | Voir 4.3 : « Les bons plans » suivi du mot clé surligné dans la couleur de la rubrique |
| Site `.pro-entete` | Surtitre (12 px, capitales), `h1.titre-pro` avec un `<em>` bleu, sous-titre gris, actions à droite. 28 px sous l'en-tête |
| Site `.retour` | Lien gris, 14 px, 600, flèche |

### 6.7 Formulaires

| Élément | Application (`Formulaire.tsx`) | Site (`site.css`) |
|---|---|---|
| Étiquette | Onest 500, 14, `c.gris`, 8 au-dessous | `.champ label`, Onest 600, 14 px, `--gris-fonce` |
| Champ | Fond `surface2`, filet de 1 `line2`, rayon 12, padding 11 / 14, Onest 400 à 15, 46 de haut (88 en multiligne) | Fond blanc, filet de 1,5 px `--ligne-2`, rayon 14, padding 12 / 16, 16 px, 50 px de haut (zone de texte : 120 px) |
| Focus | Filet `c.rouge` et anneau de 3 en `c.rougeClair` autour du champ, sans décaler la mise en page | Bordure `--bleu` et halo de 4 px `rgba(29,85,240,.14)` |
| Erreur | Encart `erreur` | `.champ--erreur`, bordure `--danger` |
| Aide | `fs[2]`, gris, interligne 1,5 | `.champ__aide`, 13 px, gris |
| Liste de choix | Ouvre une feuille listant les choix ; le choix actif est à l'encre | `select` natif stylé comme un champ |
| Case à cocher | Carré de 19, rayon 6, cochée en `c.rouge` | `.case`, 20 px, `accent-color: --bleu` |
| Choix multiple | Chips d'intérêt, rayon 16, actives à l'encre | `.choix` : cartes de 150 px au moins, rayon 14 ; choisie, bordure bleue et fond `--bleu-doux` |
| Interrupteur | — | `.interrupteur` : 48 × 28, bouton blanc de 22, `--bleu` une fois coché |

Feuilles de l'app (`Feuille.tsx`) : elles montent du bas en 300 ms, sur le voile `fixe.voile`. Surface `c.blanc`, rayon 28 en haut, poignée de 40 × 5. Le `PanneauFiltres` y place le titre « Filtres » (24 / 700), une croix dans un carré de 44, le titre de section en capitales et les options en pilules (actives à l'encre). « Appliquer » est de la couleur de la rubrique, en 46 de haut et rayon 16.

## 7. Iconographie

### 7.1 Règles communes

Grille de 24 × 24, trait de 2, bouts et jonctions arrondis, sans remplissage ; le trait passe à 2,5 ou 3 dans quelques boutons (`trait` côté app, `stroke-width` côté site).

- **App** : composant `Icone` (`mobile/src/composants/Icone.tsx`), tracés SVG rendus par `react-native-svg`, sans police d'icônes tierce. `estIcone(nom)` vérifie qu'un nom venu de la base existe.
- **Site** : `iconeSite($nom, $classes, $titre)` et la table `ICONES_SITE` (`includes/site.php`). Décorative (`aria-hidden`) sauf si `$titre` est donné (`role="img"`). Tailles : `.icon` 20 px, `.icon-sm` 16 px, `.icon-lg` 24 px. L'ancien jeu `includes/icons.php` reste celui du back-office.

### 7.2 Icônes de l'application (`NomIcone`, 61 noms)

Tirées de l'ancien `icons.php` : `trophee`, `flamme`, `etoile`, `epingle`, `personnes`, `personne`, `drapeau`, `papillon`, `lune`, `oiseau`, `piece`, `billet`, `appareil`, `valide`, `echec`, `outil`, `loupe`, `vide`, `fleche-g`, `fleche-d`, `fleche-b`, `check`, `croix`, `musique`, `calendrier`.

Tirées des gabarits : `cloche`, `carte`, `etoile-fine`, `horloge`, `eclair`, `marque-page`, `engrenage`, `ajout-personne`, `ticket`, `interdit`, `activite`, `chevron-b`, `chevron-g`, `chevron-d`.

Ajoutées à la refonte 2026-11 : `reglages`, `balle`, `raquette`, `verre`, `micro`, `crayon`, `televerser`, `portefeuille`, `terrain`, `club`, `partage`, `info`, `image`, `cadenas`, `envoi`, `soleil`, `soleil-petit`, `deconnexion`, `musee`, `lien`, `qr`, `plus`.

### 7.3 Icônes du site (`ICONES_SITE`, 37 noms)

`tableau`, `calendrier`, `plus`, `plus-rond`, `qr`, `photo`, `carte`, `graphique`, `personnes`, `personne`, `sortie`, `menu`, `croix`, `fleche-d`, `fleche-g`, `check`, `crayon`, `poubelle`, `oeil`, `telephone`, `verrou`, `info`, `alerte`, `horloge`, `lieu`, `eclair`, `etoile`, `musique`, `verre`, `ballon`, `palette`, `billet`, `megaphone`, `cible`, `facture`, `reglages`, `telecharger`.

Équivalences entre les deux jeux quand le nom diffère : `cadenas` (app) correspond à `verrou` (site), `deconnexion` à `sortie`, `epingle` à `lieu`, `image` à `photo`, `balle` à `ballon`. Certains noms communs aux deux jeux désignent des **dessins différents** : voir section 12.

## 8. Landing page (vitrine)

Sources : `index.php`, `assets/css/vitrine.css`, `includes/site.php`, `application.php`.

### 8.1 Direction

D'après l'en-tête de `vitrine.css` (2026-11-17) : page claire, avec de grands cadres blancs arrondis posés sur un fond gris. Le titre central est fin et très grand. Le décor mêle anneaux concentriques, pastilles flottantes, cartes en verre dépoli et boutons noirs en pilule. Une seule police, Onest ; l'accent reste le bleu Outsee. La feuille se charge après `site.css` et en reprend les jetons. La page porte la classe `body.vitrine`.

### 8.2 Palette propre à la vitrine (valeurs écrites en dur)

| Valeur | Emploi |
|---|---|
| `#E9E9EC` | Fond de page derrière les cadres |
| `#FFFFFF` (`--blanc`) | Cadres `.lp-cadre` |
| `#A2A2AD` | Seconde moitié grisée des titres (`.lp-titre span`, `.lp-h2 span`) |
| `#ECECF0` | Anneaux concentriques ; arcs bleus `rgba(29,85,240,.55)` et `.45` |
| `#9A9AA5`, `#8A8A96` | Légendes, bande des catégories, bas du pied de page |
| `#F4F4F7` / `#EDEDF1` | Fond et filet des tuiles du bento |
| `#FDECEB` / `#FBE0DE` | Tuile Sport |
| `#F1ECFF` / `#E8E0FF` | Tuile Culture |
| `#DCE5FF`, `#B9CAFF` | Texte secondaire sur bleu, seconde moitié du titre final |
| `#2A2A32` | Survol de la pilule noire |
| `#2C2C34` | Texte de la scène Établissements |
| `#A9A9B2` → `#D9D9DE` → `#F3F3F5` | Dégradé de la scène Établissements |
| `#DCE5FF`, `#EEF1FA`, `#F6F6F8` | Fond de la scène du réseau social |
| `rgba(255,255,255,.55)` + flou de 16 px | Cartes en verre (`.lp-verre`) |

Les pastilles flottantes et les avatars de démonstration reprennent les couleurs de catégorie de l'app : `#FF8A3D`, `#8B5CF6`, `#3B82F6`, `#F0508A`, `#111116`, `#1D55F0`, `#FFB020`, `#7B4DF2`, `#E02D24`.

### 8.3 Typographie de la vitrine (Onest)

| Élément | Taille | Graisse | Interligne | Interlettrage |
|---|---|---|---|---|
| `.lp-titre` (h1) | `clamp(42px, 6.4vw, 88px)` | 600, seconde moitié en 500 grisée | 0,98 | -0,055 em |
| `.lp-scene__titre` | `clamp(44px, 6vw, 84px)` | 600 | 0,95 | -0,06 em |
| `.lp-chiffre` | `clamp(56px, 7vw, 88px)` | 600 | 1 | -0,06 em |
| `.lp-h2` | `clamp(32px, 4.4vw, 56px)` | 600 | 1,02 | -0,05 em |
| `.lp-tarif__prix` | 42 px | 600 | 1 | -0,05 em |
| `.lp-verre > strong` | 34 px | 600 | 1 | -0,05 em |
| `.lp-tuile h3` | 24 px | 600 | 1,15 | -0,035 em |
| `.lp-etape h3` | 20 px | 600 | — | -0,03 em |
| `.lp-sous`, `.lp-entete p` | 18 px | 400 | 1,6 | — |
| `.lp-surtitre` | 13 px, capitales, `--bleu` | 600 | — | +0,06 em |

Les titres s'écrivent en deux temps : une affirmation à l'encre, puis une suite grisée dans un `<span>` (« Vois où sortent tes potes *et rejoins-les ce soir.* »).

### 8.4 Boutons de la vitrine

| Classe | Rendu |
|---|---|
| `.pilule` | Pilule de 46 px de haut, padding 10 / 20, Onest 600 à 15 |
| `--noire` | Fond `--encre`, survol `#2A2A32` avec une ombre : appel principal (« Télécharger ») |
| `--claire` | Fond blanc, filet `--ligne-2` : appel secondaire |
| `--grise` | Fond `--craie` : « Espace pro » dans la barre |
| `--bleue` | Fond `--bleu` : formule vedette, « Rejoindre » |
| `--petite` | 38 px de haut, 14 px |
| `.bouton-store` | 56 px de haut, rayon 16, fond encre, icône `telephone` de 24 ; petite ligne de 11 px (« Télécharger sur ») et nom du store en 17 / 700 |
| `.bouton-store--bientot` | Fond blanc, filet `--ligne-2`, « Bientôt sur », non cliquable |
| Sur fond bleu (`.lp-sur-bleu`) | Boutons stores blancs ; « bientôt » en verre blanc à 12 % |

`boutonsStores()` lit `APP_STORE_URL` et `PLAY_STORE_URL` (réglages) : une URL vide affiche « Bientôt ».

### 8.5 Structure, section par section

La page tient dans trois cadres `.lp-cadre` : 1440 px au plus, rayon 32, 16 px de marge, ombre `0 30px 80px rgba(17,17,22,.06)`.

| # | Section (cadre) | Contenu et appels à l'action |
|---|---|---|
| 1 | Navigation `.lp-nav` (cadre 1) | Barre en verre (blanc à 80 %, flou 12 px, rayon 22) : logo ; ancres Tes potes, L'application, Comment ça marche, Établissements, Tarifs ; « Espace pro » (pilule grise petite, `/auth/login.php`) et « Télécharger » (pilule noire petite, `#telecharger`) |
| 2 | Accroche `.lp-hero` (cadre 1) | Trame de points, quatre anneaux concentriques (560 à 1340 px), huit bulles flottantes animées sur 7 s (`verre`, `musique`, `ballon`, `palette`, `qr`, `lieu`, `etoile`, `billet`) ; note « Gratuit » ; h1 « Vois où sortent tes potes / et rejoins-les ce soir. » ; « Télécharger l'application » (pilule noire) et « Je suis un établissement » (pilule claire, `/auth/register.php`) ; pile de trois notifications d'exemple |
| 3 | Bande des catégories (cadre 1) | Bars, Boîtes, Restos, Afterworks, Padel et five, Expos, Concerts, chacun avec son icône |
| 4 | Réseau social `#potes` (cadre 2) | Texte à gauche, liste à icônes (`personnes`, `oeil`, `megaphone`) dans des carrés `--bleu-doux` de 44 ; à droite, scène de trois cartes en verre (événement « Mojito Night » avec Inviter / Rejoindre, liste d'invitation, « Manon te suit ») |
| 5 | L'application `#appli` (cadre 2) | Bento de 6 colonnes : bons plans (tuile large), réductions (tuile bleue), Sport, Culture, Carte, Personnes, Pass (carte bleue avec un faux QR, non lisible). Captures `assets/img/site/telephone-1.jpg` à `-8.jpg`, en 469 × 872 |
| 6 | Comment ça marche `#comment` (cadre 2) | Trois étapes numérotées dans des ronds encre de 36 px |
| 7 | Établissements `#etablissements` (cadre 3) | Scène en dégradé gris, « Remplissez votre salle+ », trois points cochés ; « Créer mon espace pro » (pilule noire, inscription) et lien « Voir les tarifs » ; panneau bleu avec capture et trois cartes en verre |
| 8 | Tarifs `#tarifs` (cadre 3) | Formules lues dans le code (`crmOffres()`, `grilleCapacites()`) : Essentiel, Premium (vedette : fond encre, badge « Le plus complet », bouton bleu), Club, BDE ; « Choisir … » vers `/auth/register.php?formule=…` ; note sur l'événement supplémentaire, les mises en avant et l'offre Fondateur |
| 9 | Dernier appel `#telecharger` (cadre 3) | Panneau `--bleu` à trame de points blancs, « Ce soir, tu sors où ? », les deux boutons des stores |
| 10 | Pied de page `piedSite()` (cadre 3) | Logo ; Mentions légales, Confidentialité, Conditions d'utilisation, Espace établissement ; « © année Outsee. Les sorties étudiantes, au même endroit. » |

Sous 600 px, la barre ne garde que « Espace pro », puisque l'accroche porte déjà « Télécharger ».

### 8.6 Page « C'est dans l'appli » (`application.php`)

Même cadre et même navigation (avec « Espace pro » seul). Titre « Outsee, c'est *dans l'appli.* », boutons des stores, et le lien « Ouvre l'espace pro » pour les gérants. À droite, un visuel bleu avec `telephone-1.jpg`. Tout étudiant qui passe par le site y est renvoyé, et sa session web est fermée.

## 9. Espace pro (site)

Sources : `includes/site.php` (`proDebut()`, `menuPro()`, `modaleScanPro()`), `assets/css/site.css`, `partenaire/*.php`, `auth/*.php`.

### 9.1 Pages de connexion et d'inscription (`.auth`)

Grille en deux colonnes égales sur fond `--craie`. À gauche, le formulaire : 440 px au plus, logo `marqueSite('pro')`, titre Unbounded 800 avec un `<em>` bleu (« Content de vous *revoir.* », « Créez votre *espace pro.* »), champs à 18 px d'écart. À droite, le panneau `.auth__visuel` : fond basalte, halo radial bleu `rgba(29,85,240,.6)`, titre Unbounded avec un `<em>` en `--bleu-clair`, liste à icônes en `#C9C9D3`. Sous 960 px, le panneau visuel disparaît.

### 9.2 Gabarit des pages

- `.pro` : grille avec une colonne latérale de 264 px et le contenu (`minmax(0, 1fr)`), sur fond `--craie`.
- **Menu latéral** `.pro-cote` : collant, pleine hauteur, fond blanc, filet droit `--ligne`, padding 24 / 16, écart de 28. De haut en bas : le logo `marqueSite('pro')` (vers le tableau de bord) ; le bloc établissement `.pro-etab` (fond craie, rayon 16, initiale ou photo dans un carré bleu de 40 à rayon 12, nom en 15 / 700, ville en 13 gris) ; la navigation `.pro-nav` sous le titre de groupe « Gérer » (11 px, capitales, `--gris-pale`), avec des liens de 44 px, rayon 14, Onest 500 à 15 en `--gris-fonce`, et le lien actif en `--bleu-doux` sur `--bleu-fonce`, 600, `aria-current="page"` ; enfin « Voir le site » (`oeil`) et « Se déconnecter » (`sortie`), en 14 gris.
- **Contenu** `.pro-main` : padding 32 / `clamp(20px, 3vw, 40px)` / 64 ; `.pro-contenu` limité à 1120 px.
- **Sous 960 px** : une barre haute `.pro-haut` de 64 px (logo et bouton `menu`). Le menu latéral devient un tiroir de `min(300px, 86vw)` avec `--ombre-flottante`, et un voile `rgba(17,16,19,.4)` couvre la page.

### 9.3 Menu (`menuPro()`)

| Clé | Page | Libellé | Icône |
|---|---|---|---|
| `tableau` | `/partenaire/dashboard.php` | Tableau de bord | `tableau` |
| `evenements` | `/partenaire/evenements.php` | Mes événements | `calendrier` |
| `creer` | `/partenaire/create_event.php` | Créer un événement | `plus-rond` |
| `photos` | `/partenaire/photos.php` | Photos | `photo` |
| `abonnement` | `/partenaire/abonnement.php` | Formule et achats | `carte` |

### 9.4 Éléments propres à l'espace pro

- **Tableau** `.tableau` : en-têtes en capitales de 12 px, lignes de 14 px de padding, survol `#FAFAFC`, actions en `.bouton-icone`. **Onglets** `.onglets` : cadre blanc de rayon 14, onglet actif à l'encre en texte blanc. **Photos** : grille de 180 px au moins, vignettes carrées de rayon 16.
- **Modale de scan** (`modaleScanPro()`) : boîte basalte de 460 px au plus, rayon 24, lecteur noir de rayon 18 ; résultat neutre `--basalte-3`, valide `--succes`, refusé `--danger` ; validation manuelle par nom possible.
- **Titre de page** : `<title>` sous la forme « Page — Outsee pro ».

## 10. Ton et microcopie

Relevé dans le code :

| Lieu | Adresse | Exemples tirés du code |
|---|---|---|
| Application, côté étudiant | **Tutoiement** | « Reviens d'ici quelques jours. », « Outsee a besoin de ta position pour te situer sur la carte des soirées. » (`app.json`) |
| Application, côté partenaire | **Tutoiement** | « ton espace s'ouvrira ici », « Il compte toujours dans ton quota du mois. » |
| Vitrine, adressée aux étudiants | **Tutoiement** | « Vois où sortent tes potes et rejoins-les ce soir. », « Ce soir, tu sors où ? » |
| Vitrine, adressée aux établissements | **Vouvoiement** | « Remplissez votre salle », « Vous savez qui vient, d'où, et à quelle heure. » |
| Pages de connexion et espace pro (site) | **Vouvoiement** | « Content de vous revoir. », « Publiez un événement : il apparaîtra dans le fil des étudiants de votre ville. » |
| Lien vers un étudiant égaré sur l'espace pro | **Tutoiement** | « Vous êtes étudiant ? Inscris-toi dans l'application. » (`auth/register.php`) |

On ne compte aucune forme de vouvoiement dans `partenaire/*.php`, et aucune de tutoiement dans les phrases de l'espace pro web.

Constantes de style :
- phrases courtes et concrètes, au présent (on parle de **potes**, de **soirées**, de **pass**) ; titres en deux temps, une affirmation puis une chute mise en valeur (mot surligné dans l'app, `<em>` bleu dans l'espace pro, `<span>` grisé sur la vitrine) ;
- nombres à la française (`eurosSite()` : « 49,99 € », « 70 € » ; « -30 % » ; « HT / mois ») ; pas d'emoji ; boutons à l'infinitif ou à l'impératif (« Télécharger l'application », « Choisir Premium », « Appliquer », « Valider ») ;
- les données d'illustration le disent : « Réductions exemples : elles varient selon les soirées. »

## 11. Ce qui n'est plus utilisé

| Élément abandonné | Remplacé par | Traces restantes |
|---|---|---|
| Marque **Linkee** et sa charte « basalte / lave / craie » (lave violette `#8E6BFF`, lave foncée `#5B3BD6`, craie chaude `#F5F1E8`, gris `#4B4751` / `#67626D`) | Outsee, bleu `#1D55F0`, craie froide `#F2F2F5` | `assets/css/style.css` (back-office et anciennes pages via `includes/page.php`) ; PDF `docs/*_Linkee.pdf` ; clé `linkee.theme` lue pour la migration |
| **Pantone 2728 C / `#0047BB`** comme bleu du logo et accent | `#1D55F0` (refonte du 2026-11-16) | Commentaires de `fixe.logo` et `fixe.surAccent` dans `theme.ts` |
| **Unbounded** pour le logotype, les titres et les chiffres de l'app | Onest 700 | `unbounded-latin.woff2`, gardé pour les titres des pages de connexion du site |
| **Instrument Sans** (texte) et **JetBrains Mono** (étiquettes) | Onest | `instrument-sans-latin.woff2`, `jetbrains-mono-latin.woff2` (chargés par `style.css`) ; rôles `mono*` de `police`, qui pointent vers Onest |
| Thème sombre « la nuit » imposé par défaut | Préférence « Système » | Commentaire d'en-tête de `theme.ts` |
| Ombres `sm` et `base` sur les cartes | Pas d'ombre, sauf ce qui flotte et le halo | `ombre()` renvoie `{}` pour ces niveaux |
| Onglet actif qui s'élargissait à son libellé | Cases de largeur fixe (2026-11-16) | — |
| Boutons « lave » en pilule | Boutons à rayon 16, de la couleur de la rubrique | Commentaires de `Bouton.tsx` (« lave », « pilule ») |

## 12. Écarts relevés entre le code et les documents

Partout ci-dessous, le code fait foi.

**Écarts entre les règles de marque énoncées et le code**

1. **Unbounded sur la vitrine.** On attendait Unbounded pour les titres de la vitrine. En réalité, `vitrine.css` dit « Une seule police, Onest » et aucun titre `.lp-*` ne déclare `--f-display`. Unbounded ne sert qu'à `.auth__titre` et `.auth__visuel h2` (connexion, inscription, mot de passe). L'en-tête de `site.css` (« Unbounded : les grands titres de la vitrine ») est donc dépassé.
2. **Volt `#C8F547` sur le site.** On l'attendait comme couleur du site, mais il n'apparaît dans aucun fichier du site. Il n'existe que dans l'app (`marque.lime`), pour le toast de succès et le point de l'encart `ok`.
3. **Ionicons.** On attendait des Ionicons côté mobile, mais `@expo/vector-icons` est une dépendance de `package.json` jamais importée dans `mobile/src`. L'app dessine ses propres tracés SVG (`Icone.tsx`). Le commentaire de `ICONES_SITE` (« dans l'esprit des Ionicons de l'application ») est donc trompeur.

**Écarts avec `docs/Kit_Marque_Outsee.html` (charte v1.0, antérieure à la refonte)**

4. Le kit présente comme accent la lave violette `#8E6BFF`. Le code n'emploie plus que le bleu `#1D55F0`, et le violet Culture est `#7B4DF2`.
5. Le kit donne craie `#F5F1E8`, basalte `#111013`, gris foncé `#4B4751` et gris `#67626D`. Le code emploie `#F2F2F5`, `#111116`, `#3C3C46` et `#5A5A66`.
6. Le kit prévoit trois familles (Unbounded, Instrument Sans, JetBrains Mono) et un logotype en « Unbounded 800, -0,045 em ». Le code emploie Onest 700 à -0,035 em.
7. Le kit pose la règle « texte sur un aplat vif : toujours basalte ». Le code pose du blanc sur l'accent bleu (`fixe.surAccent`). Le basalte reste réservé aux aplats clairs : volt, moutarde, alerte.
8. Le kit annonce « boutons et pastilles en forme de pilule » et un sombre par défaut. Le code donne aux boutons de l'app et de l'espace pro un rayon de 16, à la pastille de l'app un rayon de 8, et choisit le thème « Système » par défaut. Seules les pilules de la vitrine sont arrondies.

**Incohérences internes au code**

9. **Deux basaltes.** `#111013` sert au site (`--basalte`), au splash, à `backgroundColor`, à `theme-color` et au bouton `basalte` désactivé. `#111116` sert à l'app (`fixe.basalte`, `noir`), à l'encre du site (`--encre`) et au logo.
10. **Commentaires de `theme.ts`.** `fixe.logo` dit « Pantone 2728 C (sRGB #0047BB) » alors que la valeur est `#1D55F0`. `fixe.surAccent` annonce plus de 7:1 pour la craie sur l'accent ; avec `#1D55F0`, le calcul WCAG donne environ 5,8:1 pour le blanc et 3,2:1 pour le basalte. L'en-tête dit que chaque valeur est « reprise d'assets/css/style.css », alors que ce fichier garde la palette Linkee.
11. **Commentaires de composants qui ne correspondent pas aux valeurs.** `BoutonIcone` est annoncé à 46 px et en fait 42. La `Pilule` est annoncée à rayon 16 et 44 px ; elle a un rayon de 12 et 40 de haut. La `Tuile` est annoncée à rayon 18 et emploie `rayon.md` (16). `Ecran.tsx` parle d'une gouttière de 20 ; `gutter` vaut 18. `Avatar` dit l'initiale en Unbounded ; elle est en Onest 700. L'`Etiquette` de champ est décrite comme une « micro-étiquette mono en capitales » ; elle est en Onest 500 à 14, sans capitales.
12. **Variante `bleu` de `Bouton`.** Elle rend le rouge Sport `#E02D24`. Le nom est historique et piège à la lecture.
13. **Icônes homonymes de dessins différents** entre l'app et le site. `reglages` : curseurs dans l'app, roue dentée sur le site (l'`engrenage` de l'app). `billet` : rectangle et rond dans l'app, ticket échancré sur le site (le `ticket` de l'app). `verre` : coupe triangulaire dans l'app, verre arrondi sur le site. `qr` : tracés légèrement différents. Par ailleurs, `envoi` et `televerser` ont le même tracé dans l'app, et `site.css` renvoie à `includes/icons.php` alors que le site emploie `ICONES_SITE`.
14. **Espacement des surtitres.** L'app emploie +0,06 em (`Mono`, `lsEm.label`, `.lp-surtitre`) ; le site emploie +0,08 em (`.surtitre`, `.pro-nav__titre`).
15. **Ton côté établissement.** L'espace partenaire de l'application tutoie (« ton quota », « ta formule »), alors que l'espace pro web et la vitrine vouvoient les établissements. Le même public est donc tutoyé sur mobile et vouvoyé sur le web.
16. **Images inutilisées.** Les captures `assets/img/site/ecran-1.jpg` à `ecran-8.jpg` ne sont référencées par aucun fichier : la vitrine emploie `telephone-*.jpg`.
