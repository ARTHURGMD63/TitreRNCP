# Dossier Professionnel — StudentLink

**Auteur** : Arthur Gramond
**Formation** : Concepteur Développeur d'Applications — 2ème année, Hesias
**Titre RNCP visé** : Niveau 6 — Concepteur Développeur d'Applications

---

# 1. Contexte & Problématique

## 1.1 Origine du projet

StudentLink est né d'une observation simple, tirée de mon quotidien d'étudiant.

Comme la plupart de mes camarades, je sors régulièrement — bars, soirées, afterworks, sessions sport. Et à chaque fois, c'est le même schéma : un groupe WhatsApp qui s'agite, quelqu'un qui cherche sur Instagram, un autre qui demande si "c'est payant", un troisième qui veut savoir si "c'est cher" — et au final, certains restent chez eux parce que l'info est arrivée trop tard ou parce que le prix les a refroidis.

Le problème ne vient pas du manque d'envie de sortir. Il vient du **manque d'un outil pensé pour nous**.

On veut se retrouver. On veut faire des trucs ensemble. Mais on veut aussi payer moins cher, parce qu'on est étudiants et que le budget ne suit pas toujours. Et ça, aucune application existante ne le résout vraiment pour des étudiants à Clermont-Ferrand.

## 1.2 Contexte local

Clermont-Ferrand compte **42 500 étudiants** répartis entre l'UCA, SIGMA Clermont, l'INP Ingénieurs, l'IFSI, Hesias et d'autres établissements. À l'échelle nationale, la France recense **3 012 800 étudiants** — un marché considérable, dont StudentLink adresse en premier lieu le bassin clermontois avant toute logique de déploiement national. C'est une ville étudiante à part entière, avec une vie nocturne et sportive réelle — mais sans outil numérique dédié à cette communauté.

Les établissements (bars, boîtes, restos, afterworks) communiquent avec des flyers, Instagram et des groupes Facebook qui tombent dans l'oubli. Ils organisent des soirées sans savoir combien de personnes vont venir. Ils n'ont aucune donnée sur leur audience. Et les étudiants, de leur côté, passent à côté d'offres qui leur seraient pourtant destinées.

C'est ce décalage — entre une offre qui existe et une audience qui ne la trouve pas — qui est au cœur du problème.

## 1.3 Problématique

> **Comment permettre aux étudiants de se retrouver facilement, de profiter d'offres exclusives dans les établissements locaux, et de s'organiser entre eux — le tout depuis une seule application pensée pour eux ?**

## 1.4 Solution : StudentLink

J'ai conçu et développé **StudentLink**, une plateforme web mobile-first qui s'adresse aux deux côtés du problème :

- Pour les **étudiants** : découvrir les événements du moment, obtenir des réductions exclusives via un pass numérique QR code, rejoindre des groupes sportifs (squads), suivre ses amis et être récompensé pour son engagement (système XP, niveaux, badges).

- Pour les **établissements partenaires** : créer des événements en quelques clics, suivre les inscriptions en temps réel, valider les entrées par scan QR code et analyser leur audience via un tableau de bord avec graphiques.

L'idée centrale est simple : **les étudiants veulent se retrouver et payer moins cher. Les établissements veulent remplir leurs salles et connaître leur public. StudentLink connecte les deux.**

## 1.5 Périmètre du projet

| Inclus | Exclu |
|--------|-------|
| Événements bars, boîtes, restos, afterwork | Billetterie payante en ligne (Stripe) |
| Pass QR code avec réductions exclusives | Application mobile native iOS/Android |
| Flash Events avec compte à rebours | Géolocalisation temps réel |
| Squads sportives (running, vélo, muscu) | Messagerie instantanée |
| Système de follow et réseau social étudiant | Vidéos / stories |
| Gamification (XP, niveaux, 9 badges) | IA de recommandation avancée |
| Avis et notes 1-5★ après check-in | |
| Analytics partenaire (Chart.js) | |
| Dark mode, PWA installable | |

---

# 2. Personas

## Persona 1 — Arthur, l'étudiant à l'origine du projet

*Ce persona est directement inspiré de ma propre expérience.*

**Profil**
- 20 ans, 2ème année Développement — Hesias, Clermont-Ferrand
- Vit en appartement partagé à 15 min du centre-ville
- Budget sorties : 70-80€/mois
- Téléphone : iPhone, utilise Instagram, Snapchat, Discord

**Comportement**
Arthur sort régulièrement — au moins deux fois par semaine. Il est souvent celui qui organise dans son groupe : c'est lui qui propose les plans, cherche les bons spots et envoie les liens. Il fait du sport le week-end (running, muscu) et cherche des partenaires d'entraînement parmi ses camarades. Il est attentif aux prix et cherche systématiquement s'il existe une offre étudiant avant de sortir sa carte.

**Frustrations**
- "Je loupe des soirées parce que j'entends parler de l'event la veille, voire le soir même"
- "Je paye plein tarif alors que je suis étudiant — il doit bien y avoir des réductions quelque part"
- "Pour organiser une sortie running avec de nouvelles personnes, je ne sais pas où chercher"
- "Je jongle entre Instagram, les groupes WhatsApp et les flyers pour savoir ce qui se passe — c'est épuisant"

**Objectifs**
- Avoir en un seul endroit tous les bons plans du soir avec les prix réels
- Trouver des gens avec qui faire du sport sans avoir à poster dans 10 groupes
- Arrêter de payer plein tarif alors qu'il existe des offres étudiantes

**Citation**
> *"On est tous dans la même situation — on veut sortir, se retrouver, faire des trucs. Mais on veut pas non plus claquer 30€ à chaque soirée. Y'a clairement un truc qui manque."*

**Scénario d'usage**
Arthur ouvre StudentLink en sortant de cours le jeudi après-midi. Il voit un Flash Event — "Happy Hour jusqu'à minuit, cocktails à -50%" — qui expire dans 44 minutes. Il s'inscrit directement, reçoit son QR code dans son wallet, envoie une invitation à deux amis via l'app. Ils arrivent ensemble au bar, présentent leur QR code, entrent sans queue. À la fin de la soirée, Arthur laisse un avis 4★ depuis son wallet et débloque le badge "Critique".

---

## Persona 2 — Léa, l'étudiante bien intégrée

**Profil**
- 22 ans, M1 Marketing — SIGMA Clermont
- Vit seule en studio, proche du centre
- Budget sorties : 100-120€/mois
- Téléphone : Samsung, utilise TikTok, LinkedIn, Instagram

**Comportement**
Léa sort surtout en semaine — afterworks, restos entre amis, quelques soirées le week-end. Elle est très active sur les réseaux et partage régulièrement ses sorties en story. Elle aime découvrir de nouveaux endroits et teste volontiers des adresses inconnues. Elle fait du vélo le week-end mais n'a jamais réussi à trouver d'autres étudiants avec qui partir sur des itinéraires un peu ambitieux.

**Frustrations**
- "Les applis de sortie type Time Out ou Shotgun sont pensées pour Paris, pas pour Clermont"
- "Je ne sais jamais si les restos font des réductions étudiantes — je dois appeler ou chercher sur Google"
- "Les groupes Facebook étudiants sont morts ou remplis de pub"

**Objectifs**
- Trouver des établissements qui proposent de vraies offres étudiantes dans sa ville
- Trouver des compagnons de vélo à son niveau sans passer par des clubs sportifs formels
- Voir en un coup d'œil ce que font ses amis étudiants le week-end

**Citation**
> *"Ce qu'on veut c'est une app pour nous, à Clermont. Pas un truc copié de Paris où les adresses sont à 600 km."*

---

## Persona 3 — Lucas, le nouveau à Clermont

**Profil**
- 20 ans, L3 Génie Civil — INP Ingénieurs
- Arrivé à Clermont cette année, vit en résidence (Cézeaux)
- Budget sorties : 40-50€/mois
- Téléphone : Android, utilise YouTube, Strava, WhatsApp

**Comportement**
Lucas a rejoint Clermont pour sa troisième année de licence. Il ne connaît pas encore grand monde en dehors de sa promo. Il fait de la muscu régulièrement au Basic Fit mais aimerait diversifier — running, vélo — sans trop savoir comment trouver des groupes. Pour les sorties, il suit ce que propose son entourage mais n'initie jamais rien lui-même. Son budget limité l'empêche souvent de sortir spontanément.

**Frustrations**
- "Je suis là depuis quelques mois et je ne connais toujours pas les bons spots"
- "Le running tout seul c'est démotivant. J'aimerais trouver des gens mais je ne sais pas où chercher"
- "Je rate des plans parce que personne ne pense à m'envoyer l'info"

**Objectifs**
- Rencontrer des étudiants qui partagent ses centres d'intérêt, en dehors de sa promo
- Trouver des groupes sport accessibles, sans engagement formel
- Ne plus rater les bons plans faute d'information

**Citation**
> *"À Clermont depuis 3 mois, j'ai encore du mal à m'intégrer. Une app qui me connecte aux gens et aux bons plans en même temps, c'est exactement ce qu'il me faut."*

---

## Persona 4 — Jean, le gérant partenaire

**Profil**
- 38 ans, gérant du "Bec qui Pique" — bar Place de Jaude, Clermont-Ferrand
- En activité depuis 6 ans, clientèle majoritairement étudiante
- Utilise Instagram et WhatsApp, peu à l'aise avec les outils analytics
- Budget communication mensuel : 300 à 500€

**Comportement**
Jean organise des soirées thématiques deux fois par mois. Il communique via sa page Instagram (environ 2 000 abonnés) et des flyers distribués dans les écoles. Mais le soir J, il ne sait jamais combien de personnes vont venir — il prépare trop ou pas assez. Il a testé Shotgun mais trouve l'interface trop complexe et les commissions trop élevées pour une petite structure. Il veut quelque chose de simple, directement connecté à la clientèle étudiante locale.

**Frustrations**
- "Je sais pas combien de personnes vont venir avant 22h le soir de l'event"
- "Instagram c'est bien mais mes posts disparaissent dans les fils, les étudiants ne les voient pas tous"
- "Je n'ai aucune donnée sur mon public — d'où ils viennent, quel âge, quelle école"

**Objectifs**
- Créer un événement et le diffuser à une audience étudiante qualifiée en quelques clics
- Savoir en temps réel combien d'étudiants sont inscrits pour mieux préparer sa soirée
- Valider les entrées rapidement sans file d'attente ni papier
- Comprendre son audience pour adapter ses offres

**Citation**
> *"Ce que je veux c'est simple : remplir mon bar le jeudi soir et savoir d'où viennent mes clients."*

---

# 3. Cahier des Charges

## 3.1 Besoins fonctionnels

### Module Authentification
| ID | Fonctionnalité | Priorité |
|----|----------------|----------|
| F01 | Inscription étudiant (prénom, nom, email, école, promo, mot de passe) | MUST |
| F02 | Inscription partenaire (établissement, type, ville) | MUST |
| F03 | Connexion avec email + mot de passe | MUST |
| F04 | Déconnexion sécurisée | MUST |
| F05 | Réinitialisation du mot de passe par lien email (token SHA-256, 1h) | MUST |
| F06 | Onboarding guidé après la première inscription | SHOULD |

### Module Événements (Étudiant)
| ID | Fonctionnalité | Priorité |
|----|----------------|----------|
| F07 | Consulter la liste des événements avec filtres (type, "pour moi") | MUST |
| F08 | Voir le détail d'un événement (lieu, date, réduction, places restantes) | MUST |
| F09 | S'inscrire à un événement et recevoir un QR code unique | MUST |
| F10 | Annuler son inscription | MUST |
| F11 | Flash Events avec compte à rebours visible | MUST |
| F12 | Affichage de la note moyenne de l'établissement sur les cards | SHOULD |
| F13 | Inviter ses abonnés à un événement | SHOULD |

### Module Wallet
| ID | Fonctionnalité | Priorité |
|----|----------------|----------|
| F14 | Accéder à ses pass actifs avec QR code scannable | MUST |
| F15 | Historique des pass passés (checkin, annulé) | MUST |
| F16 | Montant total économisé grâce aux réductions | SHOULD |
| F17 | Laisser un avis 1-5★ + commentaire après check-in validé | SHOULD |

### Module Social
| ID | Fonctionnalité | Priorité |
|----|----------------|----------|
| F18 | Suivre / ne plus suivre un étudiant | MUST |
| F19 | Consulter le profil public d'un étudiant | MUST |
| F20 | Rechercher des étudiants par nom, école, centres d'intérêt | MUST |
| F21 | Modifier son profil (bio, intérêts, école, promo) | MUST |

### Module Squads
| ID | Fonctionnalité | Priorité |
|----|----------------|----------|
| F22 | Créer une squad (type, niveau, quota, date, lieu) | MUST |
| F23 | Rejoindre une squad | MUST |
| F24 | Quitter une squad | MUST |
| F25 | Voir les membres d'une squad | MUST |
| F26 | Supprimer sa propre squad | SHOULD |
| F27 | Inviter ses abonnés à rejoindre une squad | SHOULD |

### Module Gamification
| ID | Fonctionnalité | Priorité |
|----|----------------|----------|
| F28 | Calcul XP automatique (events ×15, squads ×10, follows ×5, avis ×8) | SHOULD |
| F29 | Niveaux progressifs basés sur l'XP | SHOULD |
| F30 | Déblocage automatique de 9 badges | SHOULD |
| F31 | Barre XP + grille de badges sur le profil | SHOULD |

### Module Partenaire
| ID | Fonctionnalité | Priorité |
|----|----------------|----------|
| F32 | Créer un événement (titre, type, date, quota, réduction, flash) | MUST |
| F33 | Liste de ses événements avec statut (actif, complet, passé) | MUST |
| F34 | Supprimer un événement | MUST |
| F35 | Scanner un QR code pour valider un check-in | MUST |
| F36 | Dashboard avec compteurs en temps réel | MUST |
| F37 | Graphique inscriptions par jour (Chart.js) | SHOULD |
| F38 | Graphique check-in par heure | SHOULD |
| F39 | Camembert répartition par école | SHOULD |

### Module Apparence & Accessibilité
| ID | Fonctionnalité | Priorité |
|----|----------------|----------|
| F40 | Dark mode / Light mode avec persistance | COULD |
| F41 | Application installable sur mobile (PWA) | COULD |
| F42 | Navigation clavier complète, conformité WCAG 2.1 AA | MUST |

## 3.2 Besoins non fonctionnels

### Performance
- Temps de chargement < 2 secondes sur connexion 4G
- Aucune requête N+1 (jointures SQL systématiques)
- Responsive mobile-first (cible principale 375px–430px)

### Sécurité
- Sessions PHP avec régénération après connexion (`session_regenerate_id`)
- Mots de passe hashés bcrypt (`PASSWORD_DEFAULT`)
- Tokens CSRF sur tous les formulaires POST
- Rate limiting anti-bruteforce (5 tentatives / 15 min par IP)
- PDO prepared statements sur toutes les requêtes SQL
- Headers HTTP : CSP, HSTS, X-Frame-Options, X-Content-Type-Options
- Tokens de reset SHA-256, usage unique, expiration 1 heure

### Disponibilité & Déploiement
- Hébergement Railway avec HTTPS automatique (Let's Encrypt)
- Déploiement continu depuis GitHub (push → redéploiement automatique)
- Base de données MySQL managée

### Maintenabilité & Qualité
- PHPStan niveau 5 (analyse statique)
- 40 tests PHPUnit (unitaires + intégration SQLite)
- CI GitHub Actions sur PHP 8.1, 8.2 et 8.3
- Conventional commits + CHANGELOG versionné

### RGPD & Légal
- Mentions légales, CGU, Politique de confidentialité
- Données minimales collectées (pas de tracking, pas de géolocalisation)
- Droit à l'oubli sur demande via support

---

# 4. Études de cas

## Étude de cas 1 — Le Flash Event : remplir un bar en 45 minutes

**Contexte**
Jean, gérant du Bec qui Pique, se retrouve un jeudi soir à 21h avec un bar à moitié vide. Il veut déclencher une affluence rapidement, sans dépenser en communication.

**Côté partenaire**
1. Jean ouvre son dashboard StudentLink
2. Il crée un **Flash Event** : "Happy Hour jusqu'à minuit — cocktails à -50%", quota 80 personnes, expiration dans 45 minutes
3. L'événement apparaît immédiatement dans l'app avec un badge FLASH et un compte à rebours orange

**Côté étudiant**
1. Arthur ouvre StudentLink en sortant de cours
2. Le Flash Event est en tête de liste — "44:23 restantes"
3. Il s'inscrit en un clic, reçoit son QR code dans son wallet
4. Il partage l'event à deux amis via la fonction Inviter
5. Au bar, Jean scanne le QR code en 2 secondes — check-in validé

**Résultat**
- Jean remplit son bar en moins d'une heure, sans flyer, sans budget pub
- Arthur économise 5€ et débloque le badge "Premier pas"
- Jean consulte le lendemain : 47 inscrits, 38 check-ins, 81% de taux de conversion, 60% d'étudiants UCA

---

## Étude de cas 2 — La Squad : se retrouver sans connaître personne

**Contexte**
Lucas est arrivé à Clermont en septembre. Il aimerait faire du running mais ne connaît pas encore grand monde. Les clubs sportifs formels ne l'attirent pas — trop de contraintes.

**Actions**
1. Lucas ouvre l'onglet Squads
2. Il filtre par "Running" et "Intermédiaire"
3. Il trouve la squad "Puy-de-Dôme sunset" — 8km, départ Parking Royat, samedi 18h, 6 membres sur 10
4. Il rejoint la squad en un clic
5. Le samedi, il retrouve 6 autres étudiants qu'il ne connaissait pas la semaine d'avant

**Suite**
- Lucas gagne +10 XP, débloque le badge "Team player"
- Il suit Arthur sur la plateforme — +5 XP supplémentaires
- La squad se reforme 2 semaines plus tard

**Ce que ça illustre**
StudentLink n'est pas uniquement une app de sorties nocturnes. C'est un outil de lien social au quotidien, y compris pour des étudiants qui ne savent pas encore comment s'intégrer dans leur nouvelle ville.

---

## Étude de cas 3 — L'Analytics : comprendre son audience pour mieux cibler

**Contexte**
Marie, gérante du Baromètre, organise des soirées depuis 6 mois sur StudentLink. Elle sent que certaines soirées fonctionnent mieux que d'autres mais ne comprend pas pourquoi.

**Ce qu'elle découvre dans le dashboard**
- Le graphique **Inscriptions par jour** montre un pic systématique le mercredi et jeudi
- Le graphique **Check-in par heure** révèle que 70% des entrées se font entre 22h et 23h
- Le camembert **par école** indique que 60% de ses clients viennent de l'UCA, 25% de SIGMA

**Décisions prises**
- Elle décale ses soirées du vendredi au mercredi — jour de plus forte demande
- Elle crée un partenariat avec l'UCA (son premier bassin)
- Elle prévoit une animation spéciale entre 22h et 23h pour capitaliser sur le pic

**Résultat**
- +20% de taux de remplissage sur les deux soirées suivantes
- Marie dispose pour la première fois de vraies données sur ses clients — sans avoir eu à faire une seule enquête

---

## Étude de cas 4 — Les Avis : la note qui rassure les indécis

**Contexte**
Un étudiant qui ne connaît pas encore le Bec qui Pique hésite à s'inscrire. Il voit une note de 4,2★ affichée sur la card de l'événement.

**D'où vient cette note ?**
Arthur, après sa soirée check-inée, a vu apparaître dans son wallet le bouton "★ Laisser un avis". Il a donné 4★ et écrit un commentaire. Ce retour a été intégré à la note moyenne de l'établissement, visible par tous les étudiants sur la page Explore.

**Ce que ça change**
- L'indécis voit 4,2★ + 12 avis → il s'inscrit
- Jean reçoit un retour concret sur sa soirée, sans avoir à solliciter quoi que ce soit
- L'écosystème se nourrit des utilisateurs actifs pour bénéficier à tous les autres

---

# 5. Justifications des choix techniques

## PHP 8.3 vanilla plutôt que Laravel ou Symfony

Pour un projet développé seul, sur une durée définie, ajouter un framework complet aurait introduit une couche de complexité sans valeur ajoutée réelle. PHP vanilla avec PDO permet un contrôle total sur les requêtes SQL, une sécurité explicite et visible, et une lisibilité maximale du code — notamment lors de la soutenance où chaque ligne doit pouvoir être expliquée.

## Railway plutôt qu'un VPS

Railway offre un déploiement automatique depuis GitHub (push → live en 2 minutes), un MySQL managé, HTTPS automatique et un environnement de variables sécurisé. Pour un développeur solo avec des cycles de livraison fréquents, c'est le meilleur équilibre entre simplicité et fiabilité.

## JavaScript vanilla plutôt que React ou Vue

L'application est mobile-first, les interactions sont majoritairement des calls API simples (inscription, follow, squad). Ajouter React aurait introduit un bundle, un build step, et une complexité unjustified. Le JS vanilla permet une maîtrise totale, un chargement instantané et montre une compréhension réelle du DOM — sans dépendre d'un framework tiers.

## Sessions PHP plutôt que JWT

StudentLink est une web app avec rendu serveur, pas une API REST publique. Les sessions PHP avec `session_regenerate_id()` sont parfaitement adaptées à ce modèle, plus simples à sécuriser dans ce contexte, et éliminent le risque de fuite de token côté client inhérent aux JWT.

---

# 6. Bilan

## Ce qui a été réalisé

En partant d'une observation personnelle — *"on veut tous se retrouver mais en payant moins cher"* — j'ai conçu et développé de A à Z une plateforme web complète, déployée en production, qui répond à ce besoin concret.

J'ai couvert l'intégralité de la stack : modélisation de la base de données (15 tables, 3 migrations versionnées), développement backend PHP, intégration frontend mobile-first en JS vanilla, sécurisation OWASP Top 10, tests automatisés (40 tests PHPUnit), CI/CD GitHub Actions, accessibilité WCAG 2.1 AA et documentation exhaustive.

## Difficultés rencontrées

**Routing local vs production**
La principale difficulté technique a été la différence d'URL entre WAMP local (`/TitreRNCP/`) et Railway (`/`). J'ai résolu ce problème en créant une fonction `baseUrl()` côté PHP et une constante `BASE` côté JavaScript, qui détectent automatiquement l'environnement via le hostname. Cela a rendu le code entièrement portable sans configuration manuelle.

**CSRF sans framework**
Sans framework, la protection CSRF doit être implémentée à la main sur chaque formulaire. J'ai centralisé les helpers dans `includes/security.php` (`csrfToken()`, `csrfField()`, `csrfVerify()`) pour garantir une couverture systématique et éviter les oublis sur les futurs formulaires.

**Performance de la page Explore**
La page principale agrégeait plusieurs données corrélées (note moyenne, inscrits, amis présents). La première version générait une quinzaine de requêtes par page. J'ai corrigé ça en remplaçant les boucles par des sous-requêtes SQL imbriquées dans la requête principale — une seule requête pour toutes les données.

## Axes d'amélioration

- **Notifications push** (Web Push API) pour alerter les étudiants d'un Flash Event en temps réel
- **Carte interactive** Leaflet/OpenStreetMap sur la page Explore
- **Recommandation personnalisée** — score de matching events ↔ intérêts utilisateur
- **Paiement intégré** Stripe pour les événements payants
- **2FA** pour les comptes partenaires

## Conclusion

StudentLink prouve qu'un besoin simple — se retrouver entre étudiants et payer moins cher — peut donner naissance à une plateforme technique complète, sécurisée et documentée. Ce projet est pour moi la concrétisation de deux années de formation : pas une app fictive, mais une réponse réelle à un problème que je vis moi-même chaque semaine.
