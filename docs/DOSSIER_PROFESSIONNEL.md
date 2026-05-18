# Dossier Professionnel — StudentLink
### Titre RNCP — Concepteur Développeur d'Applications

---

# 1. Contexte & Problématique

## 1.1 Contexte général

Clermont-Ferrand est une ville universitaire avec plus de **40 000 étudiants** répartis sur plusieurs établissements : l'Université Clermont Auvergne (UCA), SIGMA Clermont, l'INP Ingénieurs, l'IFSI et de nombreuses autres écoles. Cette population étudiante représente un bassin de consommateurs jeunes, mobiles, connectés et en recherche constante de lieux de sortie et d'activités.

D'un côté, les établissements (bars, boîtes de nuit, restaurants, espaces afterwork) peinent à communiquer efficacement avec ce public cible. Les canaux traditionnels — flyers papier, pages Instagram, groupes Facebook — sont fragmentés, peu mesurables et ne permettent pas de proposer des offres personnalisées. Résultat : des soirées organisées avec des taux de remplissage décevants, une fidélisation client quasi nulle et des budgets marketing dépensés sans retour sur investissement mesurable.

De l'autre côté, les étudiants naviguent entre plusieurs applications sans cohérence : Instagram pour voir les événements, WhatsApp pour s'organiser entre amis, les SMS pour les invitations, et rien pour bénéficier d'offres exclusives ou retrouver leurs activités sportives et sociales au même endroit.

## 1.2 Problématique

> **Comment connecter les établissements locaux à leur audience étudiante de manière fluide, digitale et mesurable, tout en créant une plateforme sociale et sportive qui fidélise les étudiants sur le long terme ?**

## 1.3 Solution : StudentLink

StudentLink est une plateforme web mobile-first qui répond à cette double problématique en créant un écosystème commun :

- Pour les **étudiants** : un hub unique pour découvrir les événements, obtenir des réductions exclusives via un pass numérique QR code, rejoindre des groupes sportifs (squads), suivre leurs amis et être récompensés pour leur engagement (gamification XP/badges).
- Pour les **partenaires** (établissements) : un espace de gestion complet pour créer des événements, suivre les inscriptions en temps réel, valider les entrées par scan QR code et analyser les données de leur audience via un tableau de bord analytique.

## 1.4 Périmètre du projet

| Inclus | Exclu |
|--------|-------|
| Événements bars, boîtes, restos, afterwork | Billetterie payante en ligne (Stripe) |
| Pass QR code avec réductions | Application mobile native (iOS/Android) |
| Squads sportives (running, vélo, muscu) | Géolocalisation temps réel |
| Système de follow et réseau social | Messagerie instantanée |
| Gamification (XP, niveaux, badges) | Vidéos/stories |
| Analytics partenaire (Chart.js) | IA de recommandation avancée |
| Avis et notes 1-5★ | |
| Dark mode, PWA | |

---

# 2. Personas

## Persona 1 — Arthur, l'étudiant actif

**Profil**
- 21 ans, L2 Informatique — Université Clermont Auvergne
- Vit en colocation à 10 min du centre-ville
- Budget sorties : 80€/mois
- Téléphone : iPhone, utilise Instagram, Snapchat, Discord

**Comportement**
Arthur sort 2 à 3 fois par semaine. Il est toujours le "organisateur" dans son groupe d'amis — c'est lui qui cherche les bons plans, propose les sorties et fait le lien entre différentes personnes. Il pratique le running deux fois par semaine et cherche souvent des partenaires d'entraînement. Il est sensible aux réductions et compare systématiquement avant de sortir sa carte.

**Frustrations**
- "Je rate des soirées parce que je ne suis pas au courant à temps"
- "Je paye plein tarif alors que je suis étudiant"
- "Pour organiser une sortie running avec des gens que je ne connais pas, c'est compliqué"
- "Je dois jongler entre Instagram, les flyers et les messages pour savoir ce qui se passe"

**Objectifs**
- Trouver en un coup d'œil les bons plans du soir à prix réduit
- Organiser ou rejoindre des sessions sportives avec d'autres étudiants
- Partager ses activités avec ses amis sur la plateforme

**Citation**
> *"Je veux un endroit où tout est au même endroit — les soirées, mes amis, mes sports. Sans avoir à chercher partout."*

**Scénario d'usage**
Arthur ouvre StudentLink le jeudi soir. Il voit un Flash Event — "Happy Hour jusqu'à minuit" avec 50% de réduction, expire dans 42 minutes. Il s'inscrit, reçoit son QR code dans son wallet, invite deux amis via la plateforme et arrive au bar 30 minutes plus tard. À la sortie, il laisse un avis 4★ et débloque le badge "Critique".

---

## Persona 2 — Léa, l'étudiante connectée

**Profil**
- 23 ans, M1 Marketing — SIGMA Clermont
- Vit seule en studio, centre-ville
- Budget sorties : 120€/mois
- Téléphone : Samsung, utilise TikTok, LinkedIn, Instagram

**Comportement**
Léa est sociable et curieuse. Elle aime tester de nouveaux restaurants et afterworks, surtout en semaine. Elle est très active sur les réseaux et partage régulièrement ses sorties. Elle suit des influenceurs locaux et est sensible à l'esthétique des applications. Elle fait du vélo le week-end et cherche des personnes avec qui partager ces moments.

**Frustrations**
- "Les apps de sortie sont soit trop généralistes (Paris, Lyon) soit inexistantes pour Clermont"
- "Je ne sais jamais s'il y a des offres étudiantes dans les restos"
- "Les groupes Facebook d'étudiants sont inactifs ou pleins de spam"

**Objectifs**
- Découvrir des établissements qui proposent des offres spéciales étudiantes
- Trouver des compagnons de vélo avec le même niveau qu'elle
- Voir ce que font ses amis étudiants le week-end

**Citation**
> *"J'aimerais une app pensée pour nous, les étudiants de Clermont, pas un copié-collé de ce qui existe à Paris."*

---

## Persona 3 — Lucas, le sportif occasionnel

**Profil**
- 20 ans, L3 Génie Civil — INP Ingénieurs
- Vit en résidence universitaire (Cézeaux)
- Budget sorties : 50€/mois
- Téléphone : Android, utilise YouTube, Strava, WhatsApp

**Comportement**
Lucas vient d'emménager à Clermont pour sa troisième année. Il ne connaît pas encore beaucoup de monde en dehors de sa promo. Il fait de la muscu régulièrement mais cherche à diversifier — running, vélo — sans savoir comment trouver des groupes. Pour les sorties, il suit les propositions de ses camarades mais n'organise jamais lui-même.

**Frustrations**
- "Je suis arrivé à Clermont cette année, je ne connais pas les bons endroits"
- "J'aimerais faire du running mais c'est démotivant tout seul"
- "Je loupe des events parce que personne ne pense à m'inviter"

**Objectifs**
- Rencontrer de nouveaux étudiants partageant ses centres d'intérêt
- Trouver des squads sportives adaptées à son niveau
- Être notifié des bons plans dans les établissements proches de chez lui

**Citation**
> *"À Clermont depuis 3 mois, j'ai encore du mal à trouver des gens pour sortir ou faire du sport. Une app qui m'aide à ça, c'est exactement ce qu'il me manque."*

---

## Persona 4 — Jean, le gérant partenaire

**Profil**
- 38 ans, gérant du "Bec qui Pique" — bar Place de Jaude
- En activité depuis 6 ans
- Utilise Instagram, WhatsApp, peu à l'aise avec les outils digitaux avancés
- Budget marketing mensuel : 300-500€

**Comportement**
Jean organise des soirées thématiques 2 fois par mois. Il communique via Instagram (2 000 abonnés) et des flyers distribués dans les écoles. Il ne sait jamais combien de personnes vont venir avant le soir J. Il a déjà testé des outils comme Shotgun ou Facebook Events mais trouve les interfaces trop complexes et les commissions trop élevées. Il veut quelque chose de simple, directement connecté à sa cible.

**Frustrations**
- "Je sais pas combien de personnes vont venir, je prépare toujours trop ou pas assez"
- "Instagram c'est bien mais mes posts disparaissent dans les fils d'actualité"
- "Je n'ai aucun moyen de savoir si mes soirées touchent vraiment des étudiants"

**Objectifs**
- Créer des événements en quelques clics et les pousser à une audience étudiante qualifiée
- Voir en temps réel combien d'étudiants sont inscrits
- Valider les entrées rapidement le soir J sans file d'attente
- Analyser quelle école envoie le plus de clients

**Citation**
> *"Ce que je veux c'est simple : remplir mon bar le jeudi soir et savoir d'où viennent mes clients."*

---

# 3. Cahier des Charges

## 3.1 Besoins fonctionnels

### Module Authentication
| ID | Fonctionnalité | Priorité |
|----|---------------|----------|
| F01 | Inscription étudiant (email, école, promo, mot de passe) | MUST |
| F02 | Inscription partenaire (établissement, type, ville) | MUST |
| F03 | Connexion avec email + mot de passe | MUST |
| F04 | Déconnexion sécurisée | MUST |
| F05 | Réinitialisation du mot de passe par email (token 1h) | MUST |
| F06 | Onboarding guidé après la première inscription | SHOULD |

### Module Événements (Étudiant)
| ID | Fonctionnalité | Priorité |
|----|---------------|----------|
| F07 | Consulter la liste des événements avec filtres (type, "pour moi") | MUST |
| F08 | Voir le détail d'un événement | MUST |
| F09 | S'inscrire à un événement (génération QR code unique) | MUST |
| F10 | Annuler son inscription | MUST |
| F11 | Affichage des Flash Events avec compte à rebours | MUST |
| F12 | Voir les événements auxquels mes abonnés participent | SHOULD |
| F13 | Inviter des abonnés à un événement | SHOULD |

### Module Wallet
| ID | Fonctionnalité | Priorité |
|----|---------------|----------|
| F14 | Accéder à ses pass actifs avec QR code | MUST |
| F15 | Voir ses pass passés (historique) | MUST |
| F16 | Afficher le montant économisé grâce aux réductions | SHOULD |
| F17 | Laisser un avis (1-5★ + commentaire) après check-in | SHOULD |

### Module Social
| ID | Fonctionnalité | Priorité |
|----|---------------|----------|
| F18 | Suivre / ne plus suivre un étudiant | MUST |
| F19 | Consulter le profil public d'un étudiant | MUST |
| F20 | Rechercher des étudiants par nom, école, centres d'intérêt | MUST |
| F21 | Modifier son profil (bio, intérêts, école, promo) | MUST |

### Module Squads
| ID | Fonctionnalité | Priorité |
|----|---------------|----------|
| F22 | Créer une squad sportive (type, niveau, quota, date, lieu) | MUST |
| F23 | Rejoindre une squad | MUST |
| F24 | Quitter une squad | MUST |
| F25 | Voir les membres d'une squad | MUST |
| F26 | Supprimer sa propre squad | SHOULD |
| F27 | Inviter des abonnés à rejoindre une squad | SHOULD |

### Module Gamification
| ID | Fonctionnalité | Priorité |
|----|---------------|----------|
| F28 | Calcul XP en temps réel (events, squads, follows, avis) | SHOULD |
| F29 | Système de niveaux progressifs | SHOULD |
| F30 | Déblocage automatique de badges (9 badges) | SHOULD |
| F31 | Affichage barre XP + badges sur le profil | SHOULD |

### Module Partenaire
| ID | Fonctionnalité | Priorité |
|----|---------------|----------|
| F32 | Créer un événement (titre, type, date, quota, réduction, flash) | MUST |
| F33 | Voir la liste de ses événements avec statuts | MUST |
| F34 | Supprimer un événement | MUST |
| F35 | Scanner un QR code pour valider un check-in | MUST |
| F36 | Dashboard avec stats en temps réel (inscrits, check-in) | MUST |
| F37 | Graphique inscriptions par jour (Chart.js) | SHOULD |
| F38 | Graphique check-in par heure | SHOULD |
| F39 | Répartition par école | SHOULD |

### Module Apparence
| ID | Fonctionnalité | Priorité |
|----|---------------|----------|
| F40 | Dark mode / Light mode avec persistance localStorage | COULD |
| F41 | Application installable (PWA Manifest) | COULD |

## 3.2 Besoins non fonctionnels

### Performance
- Chargement des pages < 2 secondes sur connexion 4G
- Pas de requêtes N+1 (jointures SQL systématiques)
- Assets CSS/JS minifiés en production

### Sécurité
- Authentification par session PHP avec régénération post-login
- Mots de passe hashés bcrypt (PASSWORD_DEFAULT)
- Protection CSRF sur tous les formulaires POST
- Rate limiting anti-bruteforce sur la connexion (5 tentatives / 15 min)
- Requêtes préparées PDO sur toutes les interactions BDD
- Headers HTTP de sécurité (CSP, HSTS, X-Frame-Options)
- Tokens de reset SHA-256, usage unique, expiration 1 heure

### Disponibilité
- Hébergement cloud Railway avec HTTPS automatique (Let's Encrypt)
- Uptime cible : 99,5%
- Base de données MySQL managée (Railway plugin)

### Accessibilité
- Conformité WCAG 2.1 niveau AA
- Navigation clavier complète
- Compatibilité lecteurs d'écran (VoiceOver, NVDA)
- Support `prefers-reduced-motion`

### Compatibilité
- Mobile-first (cible principale : smartphones 375px–430px)
- Compatible Chrome, Safari, Firefox (2 dernières versions)
- PWA installable sur iOS et Android

### Maintenabilité
- PHP 8.3 avec typage strict
- PHPStan niveau 5 (analyse statique)
- Suite de tests PHPUnit (40 tests unitaires et d'intégration)
- CI/CD GitHub Actions sur PHP 8.1, 8.2 et 8.3
- Versioning sémantique + CHANGELOG

### RGPD
- Pages légales : Mentions légales, CGU, Politique de confidentialité
- Droit à l'oubli : suppression de compte sur demande (support)
- Données minimales collectées (pas de localisation, pas de tracking)
- Cookies : session uniquement, pas de tracking tiers

---

# 4. Étude de cas — Scénarios d'usage réels

## Étude de cas 1 — Le Flash Event

**Contexte**
Jean, gérant du Bec qui Pique, se retrouve un jeudi soir avec un bar à moitié vide à 21h. Il veut déclencher une affluence rapide.

**Actions**
1. Jean se connecte au dashboard partenaire
2. Il crée un **Flash Event** : "Happy Hour jusqu'à minuit — cocktails à -50%", quota 80 personnes, expiration dans 45 minutes
3. L'événement apparaît immédiatement dans l'application avec un badge FLASH orange et un compte à rebours visible

**Côté étudiant**
1. Arthur ouvre StudentLink et voit le Flash Event en tête de liste
2. Le compte à rebours — 44:23 — crée un sentiment d'urgence
3. Il s'inscrit en un clic, reçoit son QR code dans son wallet
4. Il partage l'event à 3 amis via la fonction Inviter
5. À son arrivée au bar, Jean scanne le QR code depuis `partenaire/api_scan.php` — check-in validé en 2 secondes

**Résultat**
- Jean remplit son bar en moins d'une heure
- Arthur économise 5€ et débloque le badge "Premier pas"
- Jean voit sur son dashboard : 47 inscrits, 38 check-ins, 80% de taux de conversion

---

## Étude de cas 2 — La Squad Running

**Contexte**
Lucas, arrivé à Clermont depuis 2 mois, veut faire du running mais n'a personne avec qui courir.

**Actions**
1. Lucas ouvre l'onglet Squads depuis la navigation
2. Il filtre par type "Running" et niveau "Intermédiaire"
3. Il trouve la squad "Puy-de-Dôme sunset" créée par Arthur — 8km, départ Parking Royat, samedi 18h
4. Il clique "Rejoindre" — son profil apparaît dans la liste des membres
5. Arthur reçoit une notification passive (badge sur l'onglet Squads)

**Le jour J**
- Lucas retrouve 6 autres étudiants au point de départ
- Il gagne +10 XP pour avoir rejoint sa première squad
- Il débloque le badge "Team Player" 🤝
- Sur le chemin du retour, il suit Arthur sur la plateforme → +5 XP supplémentaires

**Résultat**
- Lucas intègre un cercle social en moins de 48h
- La squad "Puy-de-Dôme sunset" atteint son quota de 10 membres
- Arthur monte au niveau 3 suite à l'accumulation d'XP

---

## Étude de cas 3 — L'Analytics Partenaire

**Contexte**
Marie, gérante du Baromètre, organise des soirées étudiantes depuis 6 mois sur StudentLink. Elle veut comprendre son audience pour mieux cibler ses prochains événements.

**Actions**
1. Elle se connecte à son dashboard partenaire
2. Elle consulte le graphique **Inscriptions par jour** — elle voit un pic le mercredi et jeudi
3. Le graphique **Check-in par heure** montre que 70% des entrées se font entre 22h et 23h
4. Le camembert **Répartition par école** révèle que 60% de ses clients viennent de l'UCA, 25% de SIGMA

**Décisions prises**
- Marie décale ses prochaines soirées au mercredi (jour de plus forte demande)
- Elle crée un partenariat ciblé avec l'UCA (son bassin principal)
- Elle planifie une animation spéciale entre 22h et 23h pour capitaliser sur le pic d'affluence

**Résultat**
- +20% de remplissage sur les 2 soirées suivantes
- Marie fidélise son audience en répondant à ses habitudes réelles

---

## Étude de cas 4 — Le Système d'Avis

**Contexte**
Après une soirée "Afterwork Jeudi" au Bec qui Pique, Arthur a son statut check-in validé dans son wallet.

**Actions**
1. Son wallet affiche un bouton "★ Laisser un avis →" sur le pass passé
2. Il clique et arrive sur la page d'avis de l'événement
3. Il sélectionne 4 étoiles (navigation clavier disponible) et tape : *"Super ambiance, cocktails au top. Un peu bruyant après minuit mais ça fait partie du jeu."*
4. Il soumet → badge "Critique ⭐" débloqué, +8 XP

**Côté partenaire**
- La note moyenne du Bec qui Pique passe de 4.1 à 4.2 étoiles
- Cette note est désormais affichée sur toutes les cards événements de l'établissement dans l'app
- Jean peut voir les avis sur son dashboard pour améliorer ses futures soirées

---

# 5. Choix technologiques — Justifications

## Pourquoi PHP 8.3 vanilla et non Laravel ou Symfony ?

Pour un projet de cette envergure (plateforme monolithique, équipe solo), ajouter un framework complet aurait introduit une surcharge inutile. PHP vanilla avec PDO permet :
- Un contrôle total sur les requêtes SQL (performances, sécurité visible)
- Une lisibilité maximale du code pour la soutenance (le jury lit du PHP, pas des annotations Doctrine)
- Un déploiement simplifié via Nixpacks sur Railway sans configuration complexe

## Pourquoi Railway et non un VPS ?

Railway offre un déploiement continu automatique depuis GitHub, un plugin MySQL managé, HTTPS automatique via Let's Encrypt et une interface simple. Pour un projet de développement solo avec des cycles de déploiement fréquents (30+ commits), c'est le meilleur rapport simplicité/fiabilité.

## Pourquoi JavaScript vanilla et non React ou Vue ?

La nature mobile-first de l'application (navigation par onglets, interactions simples) ne justifiait pas l'ajout d'un framework frontend avec son outillage (webpack, npm build, etc.). Le JS vanilla permet :
- Un chargement instantané sans bundle à télécharger
- Une maîtrise totale montrée en soutenance
- Des animations CSS natives suffisantes pour l'UX visée

## Pourquoi des sessions PHP et non des JWT ?

L'application est une web app traditionnelle avec rendu serveur. Les JWT sont adaptés aux API REST stateless — ce n'est pas le modèle ici. Les sessions PHP avec `session_regenerate_id()` offrent une sécurité équivalente pour ce type d'application, avec moins de complexité et sans risque de fuite de token côté client.

---

# 6. Architecture de la base de données — Synthèse

StudentLink repose sur **15 tables** organisées autour de l'entité centrale `users` :

```
users
 ├─ → etablissements (1-N) — compte partenaire
 │    └─ → evenements (1-N) — gestion des events
 │         ├─ → inscriptions (N-N avec users) — pass QR
 │         ├─ → economies (1-N) — savings
 │         └─ → avis (N-N avec users) — notes & commentaires
 ├─ → squad_membres (N-N avec squads)
 │    └─ → squads (1-N via createur_id)
 ├─ → follows_users (N-N autoréférentiel)
 ├─ → follows_etablissements (N-N)
 ├─ → user_badges (N-N avec badges) — gamification
 ├─ → user_settings — thème UI
 ├─ → login_attempts — sécurité rate limiting
 └─ → password_resets — récupération de compte
```

Voir le diagramme complet en `docs/MCD.md`.

---

# 7. Bilan et perspectives

## Ce qui a été réalisé

StudentLink est une application web complète, déployée en production sur Railway, qui répond à l'ensemble des besoins identifiés dans la problématique initiale. En tant que développeur solo, j'ai couvert l'intégralité de la stack : conception de la base de données, développement backend PHP, intégration frontend mobile-first, sécurisation OWASP, tests automatisés et CI/CD.

## Difficultés rencontrées

**Gestion des URL locales vs production**
Le principal défi technique a été la différence de routing entre l'environnement local (WAMP avec préfixe `/TitreRNCP`) et Railway (racine `/`). La solution a été de créer une fonction `baseUrl()` côté PHP et une constante `BASE` côté JavaScript, détectant automatiquement l'environnement via le hostname.

**Sécurité CSRF sur une app PHP procédurale**
Sans framework, la protection CSRF doit être implémentée manuellement. J'ai centralisé les helpers dans `includes/security.php` pour garantir une couverture systématique et éviter les oublis sur les futurs formulaires.

**Performance de la page Explore**
La page principale agrégeait plusieurs sous-requêtes corrélées (note moyenne, nombre d'inscrits, amis présents). J'ai résolu ce problème en remplaçant les requêtes en boucle par des sous-requêtes SQL imbriquées dans la requête principale, passant de ~15 requêtes à 1 seule.

## Axes d'amélioration futurs

- **Notifications push** — Web Push API pour alerter les étudiants d'un Flash Event en temps réel
- **Carte interactive** — Vue carte Leaflet/OpenStreetMap sur la page Explore
- **Recommandation personnalisée** — Score de matching events ↔ intérêts utilisateur
- **Paiement intégré** — Stripe pour les événements payants avec vérification
- **Application mobile native** — React Native pour accès offline et scanner QR natif
- **Mode 2FA** — Authentification à deux facteurs pour les comptes partenaires

## Conclusion

StudentLink démontre qu'il est possible de concevoir et déployer une plateforme sociale complète en PHP vanilla, sans framework, en maintenant un niveau de qualité production : sécurité OWASP, tests automatisés, CI/CD, accessibilité WCAG 2.1 AA et documentation exhaustive. Ce projet constitue une réponse concrète à un besoin réel identifié dans l'écosystème étudiant clermontois.
