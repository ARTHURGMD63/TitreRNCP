# Changelog

Toutes les modifications notables de StudentLink sont documentées dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/),
et le projet adhère au [versioning sémantique](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Supports d'impression : flyer A6 pour les bars, affiches A3 pour la rue.**
  Le projet avait deux plaquettes A4 — un document qu'on lit assis, pas un
  support qu'on ramasse sur une table de bar ou qu'on croise à cinq mètres.
  `docs/Flyer_Bars_StudentLink.html` et `docs/Affiches_Rue_StudentLink.html`
  reprennent la charte (papier chaud, coral, Playfair/DM Sans) aux formats et
  aux distances de lecture de l'affichage.
  - Les trois affiches ne sont pas trois déclinaisons d'une même accroche mais
    trois angles — la soirée, le prix, l'arrivée dans une ville inconnue. Sur
    un même trajet, une affiche répétée cesse d'être vue au bout de deux
    passages.
  - Aucun chiffre en titre d'affiche. Le « trente-huit euros » de la plaquette
    reste dans le corps de texte, donné comme exemple : l'application n'est pas
    lancée, aucune moyenne n'est mesurée, et une accroche chiffrée se lit comme
    une promesse.
  - Les fichiers sont au format de coupe **plus 3 mm de fond perdu**, avec une
    zone sûre qui tient le texte à 8 mm du trait de coupe.
- **Générateur de QR code sans dépendance** (`docs/generate_qr.py`) : encodage
  octet, correction de niveau Q — un quart de la surface peut être déchirée ou
  recouverte sans perdre la lecture, ce qui est l'état normal d'une affiche
  collée en rue. Un générateur en ligne aurait fait dépendre plusieurs milliers
  d'exemplaires imprimés d'un service tiers qui peut fermer ou changer sa
  redirection.
- **Planche A4 auto-imposée** (`docs/generate_planche.py`) : le flyer à quatre
  exemplaires sur une A4, réduit à 88 % pour laisser la marge qu'aucune
  imprimante de bureau ne sait imprimer, avec traits de coupe. Le fichier est
  régénéré depuis le flyer et jamais édité à la main — deux copies du même
  dessin finissent toujours par diverger.
- **`docs/AVANT_IMPRESSION.md`** : ce qui reste à compléter avant un tirage
  (le domaine `studentlink.fr` n'est pas réservé et figure en toutes lettres
  sur tous les supports), les spécifications à donner à l'imprimeur, la méthode
  de dépôt en bar, et le cadre légal de l'affichage — l'affichage sauvage
  relève des articles L.581-1 et suivants du code de l'environnement, et les
  panneaux libres municipaux sont réservés aux associations sans but lucratif.
- **Souscription à l'abonnement à l'inscription d'un établissement.** Un compte
  partenaire arrivait directement sur son tableau de bord : il publiait des
  soirées et consommait l'audience sans qu'aucune décision commerciale n'ait été
  prise, et n'apparaissait dans le CRM qu'en « essai » par défaut.
  `partenaire/abonnement.php` présente la grille de SL-03 et devient un passage
  obligé : `exigerAbonnement()` renvoie chaque page partenaire vers le choix de
  formule tant qu'aucune n'est prise.
  - La souscription crée ou met à jour la fiche `crm_clients` (offre, montant,
    fin d'essai, date de signature, contact) et écrit la première échéance au
    registre financier en « prévu » — facturé n'est pas encaissé.
  - Les quinze places fondateur sont comptées et revérifiées dans la
    transaction : deux validations simultanées de la seizième place liraient
    sinon le même compteur. Le nombre est annoncé publiquement, il doit
    être exact. L'offre se ferme aussi au 31/12/2026.
  - La gratuité BDE n'est pas en libre-service : elle est inscrite au contrat
    partenaire (SL-03) et passe par un fondateur. La formule est revalidée
    côté serveur contre la liste réellement ouverte — une requête forgée ne
    souscrit ni au tarif BDE, ni à une place fondateur fermée.
  - **Aucune donnée bancaire n'est collectée.** La page enregistre
    l'engagement et la date de première facturation ; le prélèvement se met en
    place hors de l'outil. Brancher un prestataire de paiement reste à faire.
- Entrée « Abonnement » dans la barre latérale partenaire.
- **Carte « Souscriptions » sur le tableau de bord fondateurs.** Un établissement
  qui souscrit apparaissait bien dans la liste des clients, mais ne bougeait
  aucun chiffre du tableau de bord : son essai court, donc le MRR facturé reste
  à zéro. La carte liste les dernières souscriptions avec formule, montant, fin
  d'essai et contact, marque d'un « Nouveau » celles de la semaine, et distingue
  une première signature d'un changement de formule.
- La tuile MRR affiche désormais le **MRR engagé** à côté du facturé : l'un dit la
  réalité du mois, l'autre ce qui tombe à la fin des essais. Sans ça, signer un
  Premium à 149 € ne se voyait nulle part.
- **Back-office fondateurs** (`/admin/`) — espace interne réservé aux comptes
  `admin`, sur le même gabarit que l'espace partenaire.
  - *Tableau de bord* : le relevé hebdomadaire de SL-07. North Star (pass
    scannés sur 7 jours) avec sa cible du jalon en cours, indicateurs
    marketplace et business, pipeline commercial, prochaines actions. Les
    seuils d'alerte de SL-07 §3 s'affichent en tête et mènent à l'écran où
    l'on agit : ils déclenchent une action, pas une lecture.
  - *Clients* : CRM commercial. Le pipeline commence au prospect, **avant**
    tout compte partenaire — SL-05 demande une liste de 30 cibles dont aucune
    n'a de compte au moment où elle est constituée. La fiche réunit l'état
    commercial, l'historique des échanges avec leur prochaine action, et ce
    que le compte produit vraiment (soirées, présences, encaissements).
  - *Étudiants* : base séparée des clients — deux populations qui n'ont ni le
    même cycle de vie, ni le même interlocuteur. Activité, centres d'intérêt,
    économies, filtre actifs / dormants.
  - *Événements* : toutes les soirées, tous établissements, avec remplissage
    et taux de présence — la question que seuls les fondateurs se posent.
  - *Finances* : trésorerie, MRR, point mort, autonomie, registre
    recettes/dépenses et report des charges récurrentes. Le MRR se déduit des
    abonnements, l'encaissé non : les lignes « prévu » restent séparées des
    lignes « réglé », sinon une facture émise passe pour de l'argent en banque.
- Migration `db_migrations_v12.sql` — `crm_clients`, `crm_interactions`,
  `finance_mouvements`, et reprise des établissements existants en fiches.
- `includes/crm.php` — vocabulaire et calculs du back-office, source unique :
  le MRR du tableau de bord et celui de la page finances viennent de la même
  fonction. Les règles chiffrées sont isolées en fonctions pures
  (`financeCalculPointMort`, `crmCalculChurn`, `financeCalculAutonomie`,
  `tauxPresence`) pour être testables sans base.
- `outils/creer_admin.php` — création ou promotion d'un compte fondateur, en
  ligne de commande seulement : un écran web qui fabrique des administrateurs
  est une porte ouverte le jour où on l'oublie en ligne.
- 28 tests unitaires sur les règles de SL-03, SL-07 et SL-13 (grille
  tarifaire, jalons North Star, churn, point mort, autonomie) et sur les
  helpers de rendu (accord du pluriel, format monétaire, échappement des
  pastilles).
- **Centres d'intérêt à l'inscription** — le sélecteur figure dans le formulaire
  étudiant de `auth/register.php` et part avec la création du compte. Un compte
  neuf n'arrive plus sans goûts déclarés, ce qui privait de sens le classement
  par affinité d'Explore le temps qu'on pense à ouvrir son profil.
- Les intérêts s'affichent sur la page « Moi » sous le nom, chaque étiquette
  menant à Explore filtré dessus. Ils n'existaient que dans le formulaire
  d'édition : il fallait ouvrir un formulaire pour lire sa propre fiche.
- Filtre « ★ Comme moi » dans Explore › Personnes : les étudiants qui partagent
  au moins un de mes intérêts, classés par nombre d'intérêts communs. Si je n'en
  ai déclaré aucun, l'option n'apparaît pas et l'URL directe explique pourquoi
  au lieu de rendre une liste vide.
- `includes/interets.php` — catalogue unique (24 entrées), validation et rendu
  du sélecteur. La liste vivait en double dans `profil.php` (une copie pour
  l'affichage, une pour la validation du POST) : l'étiquette absente de la
  seconde disparaissait silencieusement à l'enregistrement. « Bars » et
  « Techno » rejoignent le catalogue — des comptes les portaient déjà, les
  omettre les aurait effacés au premier enregistrement de profil.
- **Post sponsorisé** — un partenaire achète une mise en avant à la création ou
  à la modification d'un événement. Trois formules tarifées (`includes/sponsoring.php` :
  Coup de projecteur 19 € / 24 h, Top du fil 49 € / 7 j, Premium 129 € / 30 j).
  Le tarif est figé dans la ligne à l'achat et n'est jamais lu depuis le formulaire.
  La mise en avant expire, et ne dépasse jamais la date de l'événement.
- Badge « Sponsorisé » sur la carte Explore, la fiche événement et la liste partenaire :
  un contenu payé s'annonce partout où il est lu.
- Migration `db_migrations_v11.sql` — colonnes de sponsoring + index unique sur `invitations`.
- Centres d'intérêt : les choix remontent en tête, et au-delà de quatre le surplus
  se replie derrière un « +N » dépliable. Replier n'est pas décocher — les
  étiquettes masquées restent envoyées avec le formulaire.
- État vide du filtre Squads (« Aucun squad dans cette catégorie »).
- **Quatre profils suggérés en tête de l'onglet Personnes**, choisis sur les
  goûts : les centres d'intérêt communs pèsent le plus, une squad partagée
  ensuite, l'école en dernier recours. Chaque vignette affiche **ce sur quoi
  repose la suggestion** (« #Muscu #Cinéma », « Squad en commun ») — sans ce
  motif, c'est un visage de plus dont on ne sait pas ce qu'il fait là. Les
  comptes déjà suivis ou en attente en sont exclus, ceux retenus sont retirés
  de la liste en dessous pour ne pas y figurer deux fois, et le bloc disparaît
  quand aucun point commun n'existe plutôt que de proposer des inconnus au
  hasard sous une étiquette « à suivre ».
- **Page `abonnements.php` : la liste de ceux qu'on suit et de ceux qui nous
  suivent**, ouverte en cliquant sur l'un des deux compteurs du profil. Un
  nombre sur lequel on ne peut pas cliquer ne dit pas qui il compte, et
  l'annuaire ne montre plus ces comptes. Les deux vues partagent le sélecteur
  segmenté du hub et la rangée compacte de l'annuaire, avec une recherche par
  nom ou école — au-delà de quelques dizaines de noms, faire défiler n'est
  plus une façon de retrouver quelqu'un. Dans « Abonnés », le bouton porte
  *mon* lien vers la personne : on s'abonne en retour sans quitter la page.
  La pagination demande une ligne de plus que la page n'en affiche : sa
  présence signale qu'il en reste, sans payer un `COUNT(*)` à chaque fois.
- **Style de musique sur les soirées, et filtre dans le fil.** « Où sortir ce
  soir » se décide autant sur la musique que sur le lieu : une soirée techno et
  un karaoké dans le même bar ne visent pas les mêmes gens. Le partenaire
  choisit le style à la création comme à la modification, l'étudiant filtre
  Explore dessus, et le style s'affiche sur la carte et sur la fiche.
  - Le catalogue vit en PHP (`includes/musique.php`) et la colonne est un
    VARCHAR, contrairement à `evenements.type` qui est un ENUM : les styles
    suivent les modes, et ajouter « Amapiano » l'an prochain ne doit pas
    demander une migration de schéma. Ce qui vient du formulaire est revalidé
    contre le catalogue : une requête forgée n'écrit pas un style inventé.
  - **Le style est facultatif** — un restaurant n'en annonce pas toujours — et
    les soirées déjà enregistrées restent valides, leurs partenaires le
    renseigneront à la prochaine modification.
  - Les deux filtres se combinent : « les boîtes techno » tient en deux clics.
    Les pilules écrivaient leur URL en dur et s'effaçaient l'une l'autre ; elles
    passent par un même constructeur de lien qui préserve l'autre dimension.
  - Un style inconnu dans l'URL ne filtre rien plutôt que de rendre une page
    vide qu'on lirait comme « aucune soirée ce soir ».
  - Migration `db_migrations_v13.sql`, index sur `(style_musique, date_heure)` :
    le fil sélectionne sur le style et trie sur la date, un seul index sert les
    deux.
- **Cloche de notifications dans l'en-tête du hub** (`includes/notifications.php`).
  Elle rassemble quatre sources en un seul fil chronologique : demandes
  d'abonnement, invitations reçues, sorties rejointes par les abonnements, et
  événements publiés par les lieux suivis. Les deux premières portent leurs
  boutons Accepter / Refuser sur place : répondre ne demande plus d'aller
  ailleurs.
  - **Aucune table de notifications.** Chaque élément se déduit de ce qui
    existe déjà — rien à écrire, donc rien à synchroniser, et une demande
    annulée disparaît d'elle-même au lieu de laisser une ligne morte.
  - La pastille compte ce qui **attend une réponse**, et ces éléments-là ne
    sont jamais coupés par la limite d'affichage : annoncer six demandes et
    n'en montrer que cinq laisserait chercher la sixième.
  - Un événement d'un lieu suivi ne compte que s'il a été **publié après
    l'abonnement**. Sans cette borne, suivre un bar déversait tout son
    catalogue dans la cloche le jour même.
  - Les nouvelles de plus de trente jours sortent du fil : « Léa va au Bec
    qui Pique » cesse d'être une nouvelle, même si la sortie est à venir.

### Changed
- **La cloche ne garde les nouvelles que 24 h** (au lieu de 30 jours). Elle dit
  ce qui vient d'arriver, pas ce qui s'est passé ce mois-ci : au-delà d'une
  journée, « Léa va au Bec qui Pique » n'est plus une nouvelle mais une ligne
  d'archive qui enterre celles du jour. **La fenêtre ne s'applique qu'à ce qui
  informe **: une demande d'abonnement et une invitation attendent une réponse
  et ne se répondent nulle part ailleurs, elles restent donc tant qu'on ne les
  a pas traitées — sans quoi une invitation reçue un vendredi soir serait
  impossible à accepter le dimanche.
- **L'analyse statique couvre enfin les pages.** `phpstan.neon` ne regardait que
  `includes/`, `api/`, `auth/`, `admin/` et `outils/` — tout l'essentiel de la
  logique était hors champ, et c'est là que dormaient les trois fonctions
  inexistantes. Les pages racine et `partenaire/` sont ajoutées au périmètre.
- **L'annuaire d'Explore ne montre plus les comptes déjà suivis.** On y vient
  pour rencontrer du monde, pas pour relire sa propre liste d'abonnements ;
  ces profils occupaient les premières places sans rien apporter. Ils se
  retrouvent désormais par les compteurs du profil. Deux exceptions
  délibérées : une **recherche par nom** les retrouve (taper le prénom d'un
  ami pour n'obtenir aucun résultat se lirait comme une panne), et les
  **demandes en attente** restent affichées, puisque c'est de là qu'on les
  annule.
- **Annuaire : le score, le classement et la tranche passent côté base.** La page
  chargeait tous les étudiants en mémoire, les triait en PHP, puis n'en gardait
  que vingt-quatre — mille lignes lues pour vingt-quatre affichées, et la
  mémoire croît avec les inscriptions. MySQL calcule désormais les intérêts
  communs, classe, et ne renvoie que la page demandée ; le « N restants » vient
  d'un `COUNT`, plus d'une liste rapatriée pour être comptée. Le menu des
  intérêts lit le catalogue au lieu de balayer la colonne de tous les comptes.
  Le classement demeure un balayage côté serveur tant que les intérêts sont
  stockés en texte séparé par des virgules : une table `user_interets` indexée
  reste la prochaine étape.
- **`search_students.php` supprimé.** Page orpheline, liée nulle part, qui
  portait une seconde copie de l'annuaire — sans les rangées compactes, sans la
  règle « ne pas montrer ceux qu'on suit ». Deux copies d'un même écran
  divergent toujours ; le manuel utilisateur renvoie maintenant vers Explore.
- **Le profil ne porte plus les nouvelles.** Demandes d'abonnement,
  invitations reçues et activité des amis y occupaient trois sections : on ne
  passe sur son profil que pour régler son compte, et une invitation
  découverte trois jours plus tard ne sert plus à rien. Tout est passé derrière
  la cloche du hub, sur la page où l'on arrive. Le profil garde ce qui le
  regarde : identité, compteurs, statistiques, réglages.
- **Le sélecteur segmenté et la rangée de personne quittent le `<style>`
  d'Explore pour la feuille commune** : deux pages les utilisent maintenant,
  et un composant copié dans deux fichiers finit toujours par diverger.
- **L'annuaire des personnes passe de cartes à des rangées** (150 px → 64 px par
  profil, soit deux fois et demie plus de monde par écran). Une carte par
  étudiant tenait tant que la promotion comptait vingt comptes ; à l'échelle
  d'une ville, parcourir l'annuaire devenait un défilement interminable. Les
  points communs tiennent désormais sur une seule ligne tronquée en dégradé
  au lieu d'une rangée de pastilles, et la cible tactile du bouton
  « suivre » (44 px) donne à elle seule la hauteur de la rangée. Sur grand
  écran la liste passe à deux colonnes larges plutôt que trois étroites, où
  le nom de l'école se tronçonnait.

### Fixed
- **Le bouton « suivre » chevauchait les badges de la carte événement.** Il
  était posé en absolu dans le coin, et le titre réservait sa place avec un
  padding deviné à la main ; la ligne de badges, elle, ne réservait rien, si
  bien que « GRATUIT » passait dessous — l'ajout du style de musique rendait
  la collision systématique. Le bouton descend sur **la ligne du lieu**, qui
  est ce qu'il fait suivre : les badges gardent leur rangée, le titre n'a plus
  de padding magique, et aucun réglage ne dépend du nombre de badges. Même
  correction sur la carte flash, où le bouton recouvrait le compte à rebours.
- **La casse du bouton changeait après un clic** : le serveur rendait
  « + SUIVRE », le JavaScript réécrivait « + Suivre ». C'est désormais la
  feuille de style qui tranche.
- **Créer ou modifier un événement renvoyait une erreur 500.** Les deux pages
  appelaient `capacitesEtablissement()`, `flashDisponible()` et
  `misesEnAvantOffertesRestantes()`, qui n'existaient nulle part : un partenaire
  abonné ne pouvait tout simplement pas publier. `includes/capacites.php` porte
  désormais la grille du contrat partenaire (SL-03) en un seul endroit : quatre
  offres flash par mois, illimitées en Premium, et la mise en avant offerte
  réservée au Premium. Le quota se compte sur le mois calendaire et l'événement
  en cours d'édition ne se compte pas lui-même.
- **`partenaire/edit_event.php` ne s'exécutait pas du tout :** une apostrophe non
  échappée dans `'Ton quota d'offres flash…'` cassait l'analyse du fichier. Une
  séquence `\u00e9` laissée telle quelle dans le message jumeau de
  `create_event.php` s'affichait en clair.
- **Majuscules accentuées.** `strtoupper()` ne connaît que l'ASCII : les noms de
  lieux et les titres de soirées sortaient en « CAFé DES SPORTS », « BOîTE »,
  « SOIRéE éTUDIANTE ». Tous les appels passent à `mb_strtoupper()`.
- **Initiale du pass wallet prise en octets.** `$nom[0]` coupe le premier octet
  et non la première lettre : un nom commençant par une majuscule accentuée
  rendait un caractère cassé sur le QR code.

### Security
- **Cookie de session sans aucun drapeau.** `Set-Cookie: PHPSESSID=…; path=/` :
  ni `HttpOnly`, ni `SameSite`, ni `Secure`. Le cookie était donc lisible en
  JavaScript, envoyé sur les requêtes inter-sites et transmis en clair — et la
  CSP autorise encore `'unsafe-inline'`. Une seule faille d'affichage, n'importe
  où dans l'application, emportait la session d'un fondateur, c'est-à-dire le
  fichier clients, les coordonnées des étudiants et la trésorerie.
  Corrigé dans `auth_check.php`, avant `session_start()`.
- **`forgot.php`, `login.php` et `reset.php` démarraient la session avant
  d'inclure `auth_check.php`** — donc avant le durcissement, et précisément sur
  les trois pages où la session naît. Le durcissement seul n'aurait rien changé.
- **`session.use_strict_mode` à 0** : un identifiant de session pouvait être fixé
  à l'avance. Passé à 1.
- **Le dépôt Git était servi par Apache.** `/.git/config` répondait 200 avec son
  contenu : tout le code source et son historique étaient téléchargeables.
  Avec le listing de répertoire actif sur `/includes/`, `/vendor/`, `/tests/`,
  et les migrations SQL et `composer.json` en accès libre, c'était le schéma de
  la base et l'inventaire des dépendances offerts sans toucher à l'application.
  `.htaccess` à la racine et dans chaque dossier interne.
- **Aucune expiration de session.** Une session de fondateur oubliée sur un écran
  restait valable indéfiniment. Désormais 1 h d'inactivité pour un compte `admin`,
  30 jours pour les autres — côté étudiant, une reconnexion permanente ferait
  fuir l'usage.
- **Plancher de mot de passe incohérent** (6 à l'inscription, 8 à la
  réinitialisation, 10 à la création d'un compte fondateur) : un fondateur
  redescendait sous sa propre règle via « mot de passe oublié ». Une seule
  source, `longueurMinimaleMotDePasse()` : 12 pour `admin`, 8 sinon.

### Fixed
- **Étiquettes du sélecteur Événements / Personnes collées à gauche.** Les deux
  onglets déclaraient `text-align: center`, mais une règle globale de cible
  tactile les passe en `inline-flex` : le texte devient alors un élément
  flexible, rangé en début d'axe, et `text-align` n'a plus prise sur lui.
  `justify-content: center` ajouté.
- **Padding des tableaux.** `.events-table th` portait `padding: 0 12px 12px` :
  zéro en haut, donc le libellé de colonne se collait au bord du cadre alors
  que les cellules en dessous respiraient sur 15px. Visible sur chaque table,
  espace partenaire compris. Les règles de bureau étaient en outre écrites
  **après** le `@media (max-width: 900px)` : sur téléphone, le padding de
  bureau écrasait celui des cartes empilées, et le survol repeignait le fond
  malgré la règle contraire. Section réordonnée, doublon supprimé, et la
  dernière cellule d'une carte ne double plus la bordure du cadre.
- **Boucle de redirection infinie pour un compte `admin`.** Chaque garde
  renvoyait vers « l'autre » espace : `requireStudent()` envoyait vers le
  tableau de bord partenaire, `requirePartner()` renvoyait vers `explore.php`.
  Le type `admin` et `requireAdmin()` existaient depuis la modération, mais
  aucun compte n'avait jamais été créé — le défaut n'avait donc jamais été
  rencontré. `accueilSelonType()` devient le seul endroit qui décide.
- `admin/moderation.php` rejoint la coquille commune : c'était le seul écran
  d'administration sans navigation.
- Lien « Trouve des étudiants à suivre » : `?view=students` au lieu de `?view=people`,
  il retombait sur le fil des événements.
- `.interest-pill` n'existait que dans le `<style>` de `view_profile.php` ; déplacée
  dans `style.css` pour être partagée avec la page « Moi ».
- Les étiquettes du sélecteur héritaient du `text-transform: uppercase` de
  `.form-group label` — elles sont des `<label>` — et sortaient en SORTIES,
  BOÎTES au milieu du formulaire d'inscription.
- **Le QR code ne validait aucun pass.** Le pass est encodé `studentlink:<code>` mais
  `api_scan.php` cherchait le texte décodé tel quel dans `inscriptions.qr_code`, qui ne
  contient que le code : tous les scans répondaient « Pass invalide ». Le préfixe est
  retiré côté serveur, et reste optionnel pour une saisie manuelle.
- **Le bouton « Inviter » ne faisait rien** après une navigation interne. Sa logique
  vivait dans un `<script>` en ligne d'`explore.php` : le routeur SPA ne rejoue que les
  scripts à `src`, donc `openInviteModal` n'existait plus dès qu'on arrivait par la barre
  du bas. Déplacée dans `app.js`, rejouée par `initApp()`. Idem pour accepter/refuser
  une invitation depuis le profil.
- **Le filtre de l'onglet Squads masquait toute la liste.** `card.parentElement.style.display`
  visait le conteneur, pas la carte : `data-type` est porté par la carte elle-même.
- Pilule de filtre active illisible sous le curseur : `.pill:hover`, écrite plus bas à
  spécificité égale, l'emportait sur `.pill.active`.
- **Couleurs des squads illisibles.** Le fond de carte prenait un aplat de marque en style
  en ligne tandis que les métadonnées gardaient leur gris de surface — entre 1.6:1 et
  2.4:1 selon le sport (blanc sur `--orange` : 2.42:1). Le type colore désormais un rail,
  une pastille, les initiales et le bouton ; la carte reste sur la surface de l'app,
  vérifiée ≥ 4.5:1 en thème clair comme en thème sombre.
- `will-change: transform` sur `.app-shell` en faisait le bloc conteneur de ses descendants
  `position: fixed` : une fenêtre modale placée dans la coquille était cadrée sur la
  colonne au lieu de couvrir l'écran. Rien ne transforme jamais `.app-shell`.
- Fenêtres modales de `squads.php` rapatriées dans `.app-shell` : hors de la coquille,
  le routeur ne les remplaçait pas et un retour sur la page laissait deux `#modal-create-squad`,
  dont `getElementById` renvoyait le périmé.
- `[hidden]` forcé en `display: none` : la feuille d'auteur l'emportait sur celle du
  navigateur pour tout élément à `display` explicite.
- `</main>` orphelin dans `squads.php` (fermé par un `</div>`).
- `api/inviter.php` : refus de l'auto-invitation, d'une cible passée, supprimée ou complète,
  d'un destinataire déjà inscrit, d'un doublon et d'un utilisateur bloqué. Le `catch`
  « invitation déjà envoyée » attendait une contrainte d'unicité qui n'existait pas.
  Le pass créé à l'acceptation utilise `random_bytes` comme l'inscription directe, au lieu
  d'un `sha256` dérivable de l'identifiant et de l'horodatage.
- Sélecteur de centres d'intérêt : l'état visuel vient de la case cochée, plus d'un
  `classList.toggle` posé à côté d'un `.click()` synthétique.

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
