# Changelog

Toutes les modifications notables de StudentLink sont documentées dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/),
et le projet adhère au [versioning sémantique](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- **Une seule coquille HTML, au lieu de vingt-quatre.** Le `<head>` était
  recopié dans vingt-quatre fichiers : doctype, jeu de caractères, viewport,
  titre, amorce de thème, jeton CSRF, feuille de style, icône, métas
  d'installation. Douze lignes par page, dont onze ne changent jamais.

  Une duplication ne reste pas identique, elle diverge — et personne ne relit
  douze lignes de `<head>` à chaque modification. Ce qu'elle avait réellement
  produit : **l'icône manquait sur treize pages sur vingt-quatre**, dont les
  quatre pages d'authentification ; les métas d'installation n'étaient que sur
  sept pages, si bien qu'un étudiant arrivé par `explore.php` pouvait installer
  l'application et le même arrivé par `avis.php` non ; `onboarding.php` portait
  `apple-mobile-web-app-capable` toute seule et `index.php` le manifeste sans
  les métas apple.

  `pageDebut()` ([`includes/page.php`](includes/page.php)) écrit la coquille.
  Ce qui varie légitimement reste en option — métas d'installation, viewport,
  description, scripts de tête, et le `<style>` propre à une page, capturé par
  `ob_start()` pour que le PHP qu'il contient parfois soit évalué normalement.
  L'icône, elle, n'est pas une option.

  > Vérification : le HTML rendu de **trente-neuf écrans** a été comparé avant
  > et après, sur une base jetable — neuf pages publiques, dix écrans étudiant
  > dont huit combinaisons de filtres du hub, six écrans partenaire et les sept
  > du back-office. Hors les ajouts ci-dessus, tout est identique ; les sept
  > écrans du back-office le sont à l'octet près. Une première passe avait fait
  > perdre une balise à `onboarding.php` : la comparaison l'a rattrapée.

- **L'intégration continue tourne sur toutes les branches.** Elle ne se
  déclenchait que sur `main` et `develop`, alors que le travail se fait sur des
  branches thématiques — `perf/montee-en-charge` en portait neuf commits, plus
  un lot entier non enregistré. Une CI qui ne tourne qu'après la fusion apprend
  trop tard qu'un test casse sous PHP 8.1.

- **`assets/js/router.js` retiré.** Mort depuis `b404d5c`, qui a supprimé le
  routeur SPA pour revenir à la navigation standard. Plus rien ne le
  référençait : ni une page, ni le manifeste, ni la liste de précache du
  service worker.

### Fixed
- **`outils/migrer.php --adopter` était tout ou rien.** Une base n'est pourtant
  pas forcément « à jour » ou « vierge ». Trouvé en conditions réelles sur la
  base de travail du projet : créée il y a quelques semaines, elle avait reçu
  `v4` à `v13` à la main, et ni `v14` ni `v15`. Pour ce cas — le cas courant —
  aucune commande n'était correcte. Sans option, `v4` échoue sur « Column
  already exists », car toutes les migrations ne sont pas rejouables ;
  `--adopter` la déclarait entièrement à jour, donc `v14` et `v15` n'auraient
  jamais été posées, `flux_revisions` et `user_interets` seraient restées
  absentes, et le temps réel comme l'annuaire seraient tombés en erreur sans
  que rien n'indique pourquoi.

  `--adopter=v13` raccorde jusqu'à `v13` et laisse les suivantes en attente.
  Une version inconnue est refusée en nommant celles qui existent.

  Le message d'échec affirmait par ailleurs que « les migrations sont
  idempotentes », ce qui est faux : il envoyait chercher une erreur dans le
  fichier SQL alors que la base était simplement en avance sur son suivi. Il
  nomme désormais la commande à lancer.

### Tests
- **De 183 à 242 tests** (912 assertions). Treize des vingt-six fichiers
  d'`includes/` n'étaient chargés par aucun test : la couverture suivait ce qui
  venait d'être retravaillé, pas ce qui risquait le plus. Les trois plus
  exposés sont désormais couverts.
  - `UploadsTest` — la surface la plus exposée de l'application : un fichier
    arrive d'ailleurs, avec un nom et un type que l'expéditeur choisit. Onze
    noms hostiles sont jetés contre `deleteStoredImage()`, dont quatre formes
    de traversée de répertoire, l'octet nul et la double extension, avec un
    fichier témoin hors du dossier qui doit survivre. Le code tient sur tous :
    ces tests confirment une garde plutôt qu'ils ne réparent un défaut — mais
    une garde que rien ne tient se retire un jour par inadvertance.
  - `ConfigTest` — priorité environnement → `config.local.php` → défaut. Une
    erreur de priorité ne se voit pas en développement, où les trois sources
    disent la même chose. Couvre notamment la variable définie mais vide, cas
    d'un panneau d'hébergeur.
  - `InteretsTest` — filtrage de ce qui vient du formulaire, et synchronisation
    de la table indexée, y compris quand elle n'existe pas encore.
  - `PageTest` — aucune page ne peut rouvrir sa propre coquille. Vérifié en le
    cassant : le test nomme le fichier fautif. `db.php` et `log.php` en sont
    exemptés, et c'est dit — leurs pages de secours s'affichent quand la base
    est injoignable, moment où dépendre du gabarit serait le plus mauvais des
    paris.


### Fixed
- **`db_setup.sql` était incomplet, et c'est le fichier du README.** Il lui
  manquait quatre tables que `install_mutualise.sql` contenait :
  `crm_clients`, `crm_interactions`, `finance_mouvements` et
  `rappels_envoyes`. Elles sont utilisées par les sept écrans de `/admin`, par
  `partenaire/abonnement.php` et par `cron/rappels.php` : toute installation
  faite en suivant le README — « une **seule** importation suffit » —
  produisait un back-office qui tombait en erreur au premier clic. Les deux
  fichiers créent désormais les mêmes 26 tables, vérifié par import réel sur
  base vierge.
- **Le compte partenaire de démonstration n'atteignait plus son tableau de
  bord.** Conséquence directe du point précédent : une fois les tables du CRM
  présentes, `exigerAbonnement()` renvoyait `jean@lebecquipique.fr` vers le mur
  d'abonnement, parce que la reprise de l'existant posait `offre = 'aucune'`.
  Le jeu de démonstration de `db_setup.sql` place maintenant les deux
  établissements en période d'essai — des valeurs de démonstration, qui
  n'affirment aucune règle commerciale : la grille tarifaire vit dans le
  contrat partenaire, hors du dépôt, et se saisit depuis le back-office.
- **Redirection ouverte après un échec CSRF.** `csrfVerify()` renvoyait
  l'utilisateur vers `$_SERVER['HTTP_REFERER']` tel quel. Cet en-tête est posé
  par le navigateur d'après la page précédente, qui peut appartenir à
  n'importe qui : une page hostile pointant vers un formulaire de
  l'application avec un jeton volontairement faux récupérait le visiteur sur
  son propre domaine, avec l'application comme caution. `urlInterneOuDefaut()`
  ne conserve désormais que le chemin, la requête et le fragment d'une URL du
  même hôte — hôte étranger, hôte qui commence pareil, port différent, URL
  protocole-relatif et chemin relatif retombent tous sur le défaut interne.
- **Une vue inconnue du hub rendait une page vide.** `?view=bidon` affichait la
  coquille sans aucune des deux listes, ce qui se lisait comme une panne. Un
  lien périmé retombe maintenant sur le fil des soirées.
- **`outils/migrer.php` laissait un curseur ouvert.** Corrigé avant même d'être
  livré : plusieurs migrations passent par `PREPARE`/`EXECUTE` pour rendre un
  `ALTER TABLE` conditionnel, ce qui produit un jeu de résultats que
  `PDO::exec()` ne consomme pas. L'instruction suivante échouait alors sur
  « Cannot execute queries while other unbuffered queries are active », à une
  centaine de lignes de la vraie cause.

### Security
- **Jeton CSRF sur les onze points d'écriture de l'API.** `api/follow.php`,
  `inscrire.php`, `annuler_pass.php`, `create_squad.php`, `delete_squad.php`,
  `rejoindre_squad.php`, `quitter_squad.php`, `remove_squad_member.php`,
  `inviter.php`, `moderation.php` et `partenaire/api_scan.php` écrivaient en
  base sur la seule foi du cookie de session.

  `SameSite=Lax` bloque effectivement le POST venu d'un autre site :
  l'application n'était pas vulnérable en l'état. Mais c'était sa **seule**
  défense, là où les formulaires en avaient deux, et elle tenait entièrement à
  une ligne de configuration de session — un passage en `SameSite=None` pour
  faire fonctionner une intégration tierce, et onze points d'écriture
  s'ouvraient d'un coup, sans que rien dans leur code ne le signale.

  `protegerEcritureApi()` (`includes/security.php`) exige désormais trois
  choses avant la moindre requête SQL : la méthode `POST` (405 sinon — une
  écriture atteignable en `GET` se déclenche depuis une balise `<img>`, et
  aucun jeton n'est demandé à une image), un jeton valide lu dans
  `X-CSRF-Token` ou dans le corps JSON, et une origine qui désigne bien
  l'application. Le jeton est publié par `metaCsrf()` dans le `<head>` de
  chaque page et posé côté client par `enTetesJson()` — un point de passage
  unique, parce que quatorze copies de l'objet d'en-têtes, c'est quatorze
  occasions d'oublier le jeton.
- **Contrôle de l'en-tête `Origin` en seconde couche** sur les formulaires
  comme sur l'API (`origineFiable()`). Le navigateur le pose sur toute requête
  non simple et le script d'un site tiers ne peut ni le retirer ni le
  falsifier. L'absence simultanée d'`Origin` et de `Referer` reste acceptée :
  quelques proxys d'entreprise les suppriment, et refuser rendrait
  l'application inutilisable derrière eux pour un gain nul — le jeton, lui,
  est exigé dans tous les cas.
- **Envoi d'e-mails durci.** La réinitialisation de mot de passe appelait
  `mail()` directement, avec ses propres en-têtes : le message le plus critique
  de l'application — celui sans lequel on ne récupère pas son compte — partait
  par le chemin le moins soigné, en doublon de `envoyerEmail()`. Les deux
  chemins sont fusionnés, et `includes/mail.php` pose maintenant un jeu
  d'en-têtes complet (`Date`, `Message-ID`, `Return-Path`, `MIME-Version`,
  `Auto-Submitted`…), encode le sujet selon la RFC 2047 — « Réinitialisation »
  partait en octets bruts dans un en-tête qui ne transporte que de l'ASCII —,
  encode le corps en quoted-printable, pose l'adresse d'enveloppe (`-f`) pour
  que SPF et les rebonds s'alignent sur le domaine annoncé, et journalise les
  échecs. Un transport **SMTP authentifié** optionnel a été ajouté, sans
  dépendance, avec vérification du certificat. Les enregistrements DNS, eux,
  ne peuvent pas être posés par du code : voir [`docs/EMAIL.md`](docs/EMAIL.md).

### Changed
- **`explore.php` découpé : 1 051 → 696 lignes.** C'était le fichier le plus
  complexe de l'application — dix requêtes, deux classements par affinité,
  deux paginations mêlés à six cents lignes de gabarit — et le seul qu'aucun
  test ne pouvait atteindre, puisque le charger exécutait aussi son affichage.
  La couche données vit maintenant dans [`includes/hub.php`](includes/hub.php)
  (`hubCriteres()`, `hubAnnuaire()`, `hubEvenements()`…), et le gabarit ne fait
  plus partir aucune requête. **Les requêtes elles-mêmes n'ont pas bougé** :
  le travail de montée en charge est repris tel quel, commentaires compris. Le
  rendu a été comparé avant/après sur quatorze combinaisons de filtres, et il
  est identique sur toutes — hors la vue inconnue, corrigée exprès.
- **Suivi des migrations.** Elles se posaient à la main dans phpMyAdmin, sans
  trace de ce qui était déjà passé, et deux dumps complets devaient être tenus
  à jour en parallèle. C'est ainsi qu'ils ont divergé. La table
  `schema_migrations` enregistre chaque version et sa date ;
  `outils/migrer.php` applique ce qui manque dans l'ordre numérique (`v9` avant
  `v10`, ce qu'un tri alphabétique ferait à l'envers) ; les deux dumps amorcent
  le suivi à `v4…v15`, qu'ils contiennent déjà. Le découpage SQL gère
  `DELIMITER`, faute de quoi les procédures stockées de la v7 seraient coupées
  à leur premier point-virgule interne.
- **Lecture de configuration unifiée** dans `includes/config.php` :
  environnement, puis `config.local.php`, puis défaut. `db.php` la relisait à
  sa façon et l'envoi d'e-mails en avait le même besoin — c'est exactement ce
  genre de duplication qui avait fait diverger les deux chemins d'envoi.
- **44 Mo de binaires retirés du suivi Git** : un teaser de 11 Mo, quatre
  diaporamas pour 24 Mo, sept PDF entièrement reconstructibles depuis le HTML
  versionné. Aucun n'est référencé par l'application. Un binaire ne se « diffe »
  pas : Git en garde une copie entière à chaque enregistrement, et l'historique
  ne rétrécit jamais. Les fichiers **restent sur le disque** ; le suivi passe de
  52 à 7,8 Mo. Voir [`docs/LIVRABLES.md`](docs/LIVRABLES.md).

  > Les 44 Mo déjà enregistrés restent dans l'historique : retirer un fichier
  > du suivi empêche la croissance future mais ne réécrit pas le passé. Les
  > effacer vraiment demande un `git filter-repo` et une publication forcée —
  > une décision à prendre, pas un effet de bord.

### Tests
- **De 100 à 171 tests** (768 assertions), avec l'API et le schéma enfin
  couverts. Les nouveaux tests lisent les fichiers du projet plutôt que
  d'appeler du code : ils ne vérifient pas que les onze points d'API existants
  sont corrects — cela a été fait en conditions réelles, requête par requête —
  mais que **le douzième ne pourra pas être ajouté sans sa garde**.
  - `ApiProtectionTest` — chaque point d'écriture exige jeton et origine,
    **avant** sa première requête SQL ; les points exemptés n'écrivent
    réellement rien ; chaque `fetch` POST du JavaScript porte le jeton.
  - `SchemaTest` — les deux fichiers d'installation décrivent la même base, et
    le code n'interroge aucune table absente. Vérifié en retirant une table :
    le test nomme précisément la manquante.
  - `MigrationsTest` — découpage SQL : `DELIMITER`, chaînes, quotes doublées,
    commentaires, ordre numérique, et chaque migration du dépôt.
  - `RedirectionTest` — aucune redirection vers un hôte externe, contrôle
    d'origine.
  - `HubTest` — critères d'URL du hub : page négative, style inventé, vue
    inconnue, paramètre non textuel (`?q[]=x`).
  - `MailTest` — en-têtes RFC, encodage du sujet et césure UTF-8,
    anti-injection d'en-tête.
  - `DepotTest` — aucun binaire lourd ne réapparaît dans le suivi Git, et les
    sources des PDF retirés sont toujours versionnées.

### Added
- **Une page d'accueil publique, avec le choix du public en tête.** La racine
  du site renvoyait vers le formulaire de connexion : rien n'expliquait ce
  qu'est StudentLink, et un gérant de bar n'avait aucune raison d'aller plus
  loin. `index.php` présente désormais le produit à ses deux publics —
  étudiants et établissements — derrière le même sélecteur segmenté que le hub
  de l'application. Les deux ne partagent presque rien (vocabulaire, arguments,
  prix) : plutôt qu'une page moyenne qui ne parle à personne, chaque public a
  sa page complète, avec hero, aperçu de l'écran réel, arguments, parcours en
  trois étapes et appel final — et la grille tarifaire, côté établissements,
  vient de `crmOffres()` plutôt que d'un prix recopié.
  - Le choix vit dans l'URL (`?pour=etudiants` / `?pour=etablissements`) :
    sans JavaScript le lien recharge la page sur le bon public, un lien
    partagé arrive au bon endroit, et le script ne fait qu'éviter
    l'aller-retour réseau en tenant l'historique à jour.
  - `auth/register.php` accepte `?type=partenaire` pour ouvrir l'onglet
    partenaire du formulaire. Le paramètre ne décide de rien : le type
    réellement enregistré reste celui relu du POST.
  - Un compte connecté qui arrive sur la racine part directement vers son
    écran ; `auth/register.php` fait de même au lieu de le renvoyer sur la
    vitrine, qui l'aurait aussitôt redirigé.
- **Montée en charge : l'application est préparée pour ~1 000 sessions
  simultanées, et le direct ne coûte plus rien quand rien ne bouge.**
  Mesures, protocole et limites dans [`docs/PERFORMANCE.md`](docs/PERFORMANCE.md).
  - **Le serveur de production n'était pas un serveur de production.** L'image
    lançait `php -S`, le serveur intégré de PHP, qui traite *une* requête à la
    fois faute de `PHP_CLI_SERVER_WORKERS`. Le plafond n'était pas de mille
    utilisateurs mais d'une requête simultanée. Remplacé par Apache et mod_php
    (`Dockerfile`, `docker/apache.conf`), dimensionné à 48 processus.
  - **OPcache et GD étaient absents de l'image.** Sans OPcache, PHP recompilait
    chaque fichier source à chaque requête — l'instrumentation montre 8 à 15 ms
    perdus avant la première ligne de logique. Sans GD, `storeUploadedImage()`
    se repliait sans bruit et les avatars partaient en pleine résolution.
  - **Temps réel par flux de révisions** (`api/live.php`, `includes/temps_reel.php`,
    table `flux_revisions`). Un compteur par canal observable, un ETag construit
    dessus : quand rien n'a changé — le cas courant — le serveur répond 304 sans
    corps, après *une* lecture sur clé primaire. Places prises, demandes
    d'abonnement, invitations et membres de squad remontent désormais sans
    rechargement. Les SSE et les WebSockets ont été écartés : ils gardent un
    processus PHP ouvert par client, donc mille processus à mille clients.
  - **Un seul interrogateur côté navigateur**, et rien ne part quand l'onglet
    n'est pas regardé — la règle la plus rentable, puisque la plupart des
    onglets ouverts sur une application sont en arrière-plan. Le compteur du
    tableau de bord partenaire, qui avait son propre `setInterval`, rejoint ce
    flux commun.
  - **Cache applicatif** (`includes/cache.php`) : APCu quand il est là, fichiers
    sinon — donc le même code sous WAMP, sur un mutualisé et en conteneur. Sert
    les agrégats d'affichage (`includes/agregats.php`) : note moyenne des
    établissements et liste des écoles, recalculées jusqu'ici à chaque
    affichage par des balayages de table complets.
  - **Cache HTTP et compression** dans `.htaccess`. Chaque visiteur
    retéléchargeait 877 Ko par page — Chart.js, html5-qrcode, le CSS, le JS —
    sans compression ni date d'expiration, alors que ces fichiers portent déjà
    une empreinte dans leur URL depuis `asset()`.
  - **Outils de mesure** : `outils/semer_charge.php` remplit une base jetable
    avec des volumes plausibles, `outils/charge.php` lance des requêtes
    réellement simultanées et rend la distribution des latences. Six événements
    de démonstration ne disent rien de la tenue en charge.
  - **`cron/entretien.php`** balaie le cache périmé, les canaux morts, les
    tentatives de connexion et les jetons expirés — hors du chemin des requêtes.

### Changed
- **Les deux requêtes qui travaillaient sur tout le catalogue ont été
  réécrites.** Toutes deux demandaient à MySQL de calculer quelque chose pour
  chaque ligne de la table avant de n'en afficher que vingt-quatre.
  - Le **fil des soirées** sélectionnait *tous* les événements à venir, sans
    limite, avec quatre sous-requêtes corrélées par ligne — dont deux qui
    recalculaient la note d'un bar autant de fois qu'il avait de soirées au
    programme. Il procède maintenant en deux temps : les identifiants de la
    tranche affichée, puis leur détail. 63 ms → **23 ms** sur 1 500 événements.
  - L'**annuaire** classait les profils par intérêts communs avec un
    `FIND_IN_SET` par intérêt et par profil, sur une colonne texte. Aucun index
    ne peut servir une recherche à l'intérieur d'une chaîne : c'était
    structurel, pas un réglage à trouver. La table `user_interets` (v15) range
    la même information en lignes indexables. 65 ms → **33 ms** sur 5 000
    comptes, à résultats strictement identiques (vérifié sur cinq combinaisons
    de filtres).
  - Les deux `COUNT(*)` qui ne servaient qu'à afficher ou non un bouton
    « voir plus » sont remplacés par une ligne demandée en trop — un balayage
    complet en moins à chaque page.
- **`db_setup.sql` force désormais InnoDB.** Sur un serveur configuré en
  MyISAM, l'import échouait à la moitié (erreur 1005 : MyISAM ignore les clés
  étrangères) ; et s'il avait réussi, MyISAM aurait verrouillé la table entière
  à chaque inscription, ce qui interdit toute tenue en charge.
  `install_mutualise.sql` le faisait déjà, pas le script principal.
- **Le démarrage de session vit dans `includes/session.php`.** `api/live.php` a
  besoin d'ouvrir la session sans charger les 40 Ko d'`auth_check.php`, et deux
  copies des paramètres du cookie auraient fini par diverger.
- **Connexions persistantes à MySQL, prêtes et désactivées** (`DB_PERSISTANT`).
  Le choix appartient à l'hébergement : elles font gagner une poignée de main
  par requête sur un serveur dédié, et épuisent le quota de connexions sur un
  mutualisé.

### Fixed
- **L'image Docker se construisait sans erreur et livrait GD cassée.** Le
  `Dockerfile` terminait par `apt-get purge --auto-remove` sur les paquets
  `-dev` ; `--auto-remove` emportait du même coup les bibliothèques
  d'exécution dont GD dépend, installées comme simples dépendances. Au
  démarrage : « libpng16.so.16: cannot open shared object file », et le
  redimensionnement des photos se repliait en silence — exactement la panne
  que cette image devait corriger. Les paquets restent désormais en place, et
  une étape de construction vérifie que `gd`, `pdo_mysql` et OPcache se
  chargent réellement : une extension manquante fait échouer le build.
- **634 Ko de JavaScript partaient sans compression.** Les règles
  `mod_deflate` listaient `application/javascript`, mais Apache 2.4 sert un
  `.js` en `text/javascript` : le CSS était comprimé, le JS non — soit
  l'essentiel de ce que la section devait économiser. Corrigé dans
  `.htaccess` et `docker/apache.conf` ; l'ensemble des ressources passe de
  721 Ko à 215 Ko sur le réseau.
- **`outils/charge.php` sait faire tourner plusieurs sessions** (`--cookies`).
  Sans cela, un test de page connectée mesurait le verrou de session de PHP —
  qui sérialise les requêtes d'un même `PHPSESSID` — et non le serveur :
  20 requêtes/s avec une session partagée contre 311 avec cinquante sessions
  distinctes.
- **Un TTL négatif mettait en cache pour toujours.** APCu comme le magasin
  fichier ramenaient une durée de vie négative à zéro, c'est-à-dire « sans
  expiration » : un calcul d'échéance passant sous zéro obtenait l'exact
  contraire de ce qu'il demandait. Trouvé par le test qui l'accompagne.
- **Sept index manquants** (v14), dont `avis(evenement_id)` — la seule clé
  existante commençait par `user_id`, donc la jointure qui calcule la note d'un
  établissement lisait toute la table des avis pour chaque carte affichée.

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
