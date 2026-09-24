# Montée en charge

Ce document répond à une question posée simplement : **l'application peut-elle
accueillir mille personnes connectées en même temps, et tout voir bouger en
direct ?**

Il décrit ce qui a été changé pour cela, ce que ça donne à la mesure, et ce
qui reste à faire. Les chiffres sont datés et reproductibles — chacun est
accompagné de la commande qui le produit.

---

## 1. Ce que « mille utilisateurs » veut dire

La formulation courante — « mille utilisateurs simultanés » — n'est pas une
cible mesurable. Il faut la traduire.

Mille personnes qui naviguent chargent en moyenne **une page toutes les dix
secondes** (lire une fiche, faire défiler, cliquer). Cela fait **environ
100 requêtes par seconde**, et non mille requêtes en vol au même instant. Une
requête servie en 30 ms libère son processus trente fois par seconde : une
poignée de processus suffit donc, à condition que rien ne les immobilise.

La cible retenue, et celle que mesure ce document :

| Grandeur | Cible |
| --- | --- |
| Sessions actives | 1 000 |
| Débit | ~100 requêtes/s |
| Latence médiane | < 100 ms |
| Latence p95 | < 300 ms |
| Taux d'erreur | 0 |

« Aucun ralentissement » n'existe pas : il y a toujours une latence. La
question est de savoir où elle plafonne, et c'est le p95 qui le dit.

---

## 2. Ce qui bloquait, dans l'ordre d'importance

### 2.1 Le serveur web était le serveur de développement

L'image de production lançait `php -S`, le serveur intégré de PHP. Il traite
**une requête à la fois** — il ne crée des processus de travail que si
`PHP_CLI_SERVER_WORKERS` est définie, ce qu'elle n'était pas — et la
documentation de PHP indique explicitement qu'il n'est pas destiné à la
production.

Le plafond n'était donc pas de mille utilisateurs simultanés mais **d'une
requête simultanée**. Tout le reste attendait son tour.

Remplacé par Apache et mod_php ([`Dockerfile`](../Dockerfile),
[`docker/apache.conf`](../docker/apache.conf)), dimensionné à 48 processus —
soit de l'ordre de 900 requêtes/s à 50 ms par requête, avec une empreinte
mémoire bornée à ~2,8 Go.

### 2.2 OPcache était absent

Sans lui, PHP relit, analyse et recompile chaque fichier source **à chaque
requête**. Activé dans [`docker/php.ini`](../docker/php.ini).

**Mesuré, contrairement à ce que disait une version antérieure de ce
document.** Deux conteneurs identiques, l'un avec OPcache et l'autre sans, sur
une sonde qui charge les douze fichiers de la pile applicative sans toucher à
la base (pour ne pas noyer le résultat dans la latence réseau) :

| | Médiane | p95 |
| --- | --- | --- |
| Sans OPcache | 5,0 ms | 6,4 ms |
| Avec OPcache | **3,4 ms** | 3,7 ms |

Soit **~1,6 ms par requête**, pas le « facteur 3 à 5 » annoncé de mémoire
avant d'avoir mesuré. Sur une page complète, c'est quelques pour cent du
temps total. Ça reste gratuit et ça se garde — mais ce n'était pas le levier
principal, et il fallait le vérifier pour le savoir.

> La version précédente de ce document estimait ce gain à un facteur 3 à 5,
> à partir d'une instrumentation faite sous `php -S` sur Windows, où la
> lecture des fichiers source est beaucoup plus coûteuse. L'écart entre cette
> extrapolation et la mesure est la raison pour laquelle l'image a été
> construite et exécutée.


### 2.3 GD était absente de l'image

`includes/uploads.php` redimensionne les photos avec `imagecreatetruecolor()`,
qui n'existait pas dans le conteneur : la fonction se repliait silencieusement
et les avatars partaient en pleine résolution. Ajoutée au `Dockerfile`.

### 2.4 Des requêtes qui travaillaient sur tout le catalogue

Deux pages faisaient le même genre d'erreur : demander à MySQL de calculer
quelque chose pour **chaque ligne de la table**, puis n'en afficher que vingt.

- **Le fil des soirées** sélectionnait tous les événements à venir, sans
  limite, avec quatre sous-requêtes corrélées par ligne — dont deux qui
  recalculaient la note d'un bar autant de fois qu'il avait de soirées au
  programme.
- **L'annuaire** classait les profils par intérêts communs avec un
  `FIND_IN_SET` par intérêt et par profil, sur une colonne texte. Aucun index
  ne peut servir une recherche à l'intérieur d'une chaîne : c'était
  structurel.

Les deux suivent désormais la même forme : une requête choisit les
identifiants de la tranche affichée, une seconde va chercher leur détail. Le
travail coûteux ne porte plus que sur ce qui s'affiche.

### 2.5 Aucun cache, aucune compression, aucune date d'expiration

Chaque visiteur retéléchargeait 877 Ko à chaque page — Chart.js, html5-qrcode,
le CSS, le JS — sans compression et sans en-tête de cache. Mille visiteurs,
c'est près d'un gigaoctet de trafic évitable.

Corrigé dans le [`.htaccess`](../.htaccess) : `mod_deflate`, `mod_expires`, et
`Cache-Control: immutable` sur ce qui porte déjà une empreinte dans son URL.

### 2.6 Le temps réel coûtait cher et ne servait qu'un compteur

Un seul compteur était « en direct », par une interrogation toutes les 15 s
qui exécutait deux requêtes SQL à chaque fois, y compris quand rien n'avait
changé, y compris dans un onglet en arrière-plan.

Voir la section 4.

---

## 3. Mesures

### Protocole

Deux étages, mesurés séparément parce qu'ils ne répondent pas à la même
question.

**Étage applicatif** (sections 3.1) : base jetable MariaDB 11.5 remplie par
[`outils/semer_charge.php`](../outils/semer_charge.php) — **5 000 étudiants,
1 500 événements, ~40 000 inscriptions, 4 000 avis, 12 000 abonnements**.
Application servie par `php -S`, donc sans OPcache des deux côtés : la
comparaison isole le travail applicatif.

**Étage de production** (sections 3.2 et 3.3) : l'image
[`Dockerfile`](../Dockerfile) réellement construite et exécutée — Apache,
mod_php, OPcache, `.htaccess` appliqué. La base reste sur l'hôte, atteinte par
`host.docker.internal`, ce qui ajoute plusieurs millisecondes à **chaque**
requête SQL : les latences absolues ci-dessous sont donc pessimistes par
rapport à un serveur où la base est adjacente.

```bash
php outils/semer_charge.php --etudiants=5000 --evenements=1500
docker build -t linkee:perf .
```

### 3.1 Latence d'une page, avant / après

| Page | Avant | Après | Gain |
| --- | --- | --- | --- |
| Hub, fil des soirées | 63 ms (p95 77) | **23 ms** (p95 36) | ×2,7 |
| Hub, annuaire | 65 ms (p95 77) | **33 ms** (p95 50) | ×2,0 |

Vérification que le résultat affiché n'a pas changé — mêmes profils, dans le
même ordre, sur cinq combinaisons de filtres :

| Filtre | Profils | Résultat |
| --- | --- | --- |
| aucun | 29 | identique |
| « comme moi » | 8 | identique |
| par école | 25 | identique |
| recherche texte | 25 | identique |
| par intérêt | 4 | identique |

Instrumentation de l'annuaire après réécriture : les requêtes de classement
sont passées de **33 ms à 11 ms**.

### 3.2 Tenue en charge, sur l'image de production

[`outils/charge.php`](../outils/charge.php), requêtes réellement simultanées,
1 000 requêtes par cas.

| Cas | Concurrence | Débit | Médiane | p99 | Erreurs |
| --- | --- | --- | --- | --- | --- |
| Hub, **50 sessions distinctes** | 50 | **311 pages/s** | 154 ms | 197 ms | 0 |
| Hub, 50 sessions distinctes | 100 | 301 pages/s | 317 ms | 380 ms | 0 |
| `api/live.php`, chemin 304 | 50 | **1 079 req/s** | 28 ms | 42 ms | 0 |
| Page anonyme | 50 | 1 333 req/s | 30 ms | 42 ms | 0 |

Deux choses à retenir.

**311 pages/s sur la page la plus lourde**, soit environ trois fois les
~100 req/s que représentent mille personnes en train de naviguer. Chaque
réponse pèse 9 Ko sur le réseau, comprimée depuis 147 Ko.

**À 100 requêtes en vol — le double des 48 processus Apache — le débit ne
s'effondre pas.** Il reste à 301 pages/s, la latence monte de 154 à 317 ms, et
aucune requête n'échoue. C'est le comportement recherché : la file absorbe
l'excès au lieu de rompre.

### 3.3 Le verrou de session, mesuré

Le même test, mille requêtes sur le hub à 50 en parallèle, en ne changeant
qu'une chose — le nombre de sessions :

| | Débit | Médiane |
| --- | --- | --- |
| **Une seule** session partagée | 20 req/s | 2 433 ms |
| **50 sessions** distinctes | 311 req/s | 154 ms |

Un facteur 15. PHP pose un verrou exclusif sur le fichier de session pendant
**toute** la durée de la requête : cinquante requêtes portant le même
`PHPSESSID` se suivent au lieu de se chevaucher.

**Ce n'est pas un plafond global** — de vrais utilisateurs ont des sessions
distinctes et ne se gênent donc pas entre eux. C'est un plafond *par
utilisateur*, qui se manifeste quand un même compte a plusieurs requêtes en
vol : plusieurs onglets, une navigation rapide, ou un onglet qui interroge le
serveur pendant qu'un autre charge une page.

C'est précisément pour cela qu'`api/live.php` appelle
[`sessionLectureSeule()`](../includes/session.php), qui relâche le verrou
immédiatement — et c'est ce qui lui permet de tenir 1 079 req/s là où le hub,
qui garde le verrou, tombe à 20. Généraliser ce relâchement aux autres pages
en lecture reste à faire (voir section 6) : il faut le faire page par page,
parce qu'une écriture dans `$_SESSION` après la fermeture serait perdue sans
bruit — les messages flash, notamment.

### 3.4 Requêtes SQL par appel

| Appel | `SELECT` exécutés |
| --- | --- |
| `api/live.php`, rien n'a changé (304) | **1** |
| `api/live.php`, réponse complète | 3 |
| `explore.php`, page entière | 11 |

### 3.5 Compression

Mesurée sur l'image construite, en demandant `Accept-Encoding: gzip` :

| Fichier | Brut | Comprimé |
| --- | --- | --- |
| `style.css` | 87 229 o | 21 748 o |
| `app.js` | 50 514 o | 13 996 o |
| `chart.umd.min.js` | 208 522 o | 70 706 o |
| `html5-qrcode.min.js` | 375 364 o | 108 462 o |
| **Total** | **721 629 o** | **214 912 o** |


## 4. Le temps réel

L'exigence était : ce qu'un utilisateur fait doit se voir chez les autres tout
de suite.

### Ce qui a été écarté, et pourquoi

Les **Server-Sent Events** et les **WebSockets** donnent un temps réel plus
fin, mais gardent une connexion — donc un processus PHP — ouverte par client.
Sur Apache en mod_php comme sur un mutualisé, mille clients connectés
signifient mille processus : le serveur s'arrête bien avant d'y arriver.

### Ce qui a été fait

Une **table de révisions** ([`flux_revisions`](../db_migrations_v14.sql)) :
un compteur par canal observable — `event:42`, `user:7`, `squad:3` —
incrémenté à chaque écriture qui le concerne.

[`api/live.php`](../api/live.php) lit ces compteurs, en fait un **ETag**, et
s'arrête là si le client a déjà la bonne version : `304 Not Modified`, sans
corps, sans qu'aucune requête de données ne soit exécutée. Une lecture sur
clé primaire, et c'est tout.

Trois décisions rendent l'endpoint tenable à mille onglets :

1. **Amorçage minimal** — ni `auth_check.php`, ni le jeu d'icônes, ni les
   gabarits. C'est le fichier PHP le plus appelé de l'application.
2. **Verrou de session relâché immédiatement**
   ([`sessionLectureSeule()`](../includes/session.php)). PHP garde sinon un
   verrou exclusif sur le fichier de session pendant toute la requête :
   l'interrogation d'un onglet bloquerait le chargement de page demandé dans
   un autre onglet du même utilisateur.
3. **ETag d'abord, données ensuite.**

Côté navigateur ([`assets/js/app.js`](../assets/js/app.js)), un seul
interrogateur pour toute la page — une requête par cycle quel que soit le
nombre de cartes à l'écran — et **rien ne part quand l'onglet n'est pas
regardé**. C'est la règle la plus rentable : la plupart des onglets ouverts
sur une application sont en arrière-plan. Le retour au premier plan déclenche
une interrogation immédiate.

Latence perçue : **jusqu'à 8 secondes**, le temps d'un cycle. Pour un compteur
de places et une pastille de notifications, c'est le bon compromis — le coût
devient proportionnel aux changements réels et non au nombre de spectateurs.

### Ce qui remonte en direct

| Information | Canal | Déclenché par |
| --- | --- | --- |
| Places prises sur une soirée | `event:N` | inscription, annulation, avis |
| Demandes et invitations en attente | `user:N` | demande d'abonnement, invitation |
| Membres d'une squad | `squad:N` | rejoindre, quitter |
| Nouvelle soirée publiée | `global` | création par un partenaire |

L'activité des abonnements (« Léa va au Bec qui Pique ») reste volontairement
hors du direct : la mettre sur le canal global le ferait bouger en permanence
à mille utilisateurs, et plus aucune réponse ne serait un 304.

---

## 5. Ce qui a changé dans la base

| Migration | Contenu |
| --- | --- |
| [v14](../db_migrations_v14.sql) | `flux_revisions` + 7 index manquants |
| [v15](../db_migrations_v15.sql) | `user_interets`, qui rend les goûts indexables |

Les deux sont idempotentes. Elles sont déjà incluses dans `db_setup.sql` et
`install_mutualise.sql` : une installation neuve n'a rien de plus à faire.

**Sur une base existante, il faut les passer à la main** — le déploiement FTP
n'emporte pas les fichiers SQL.

`db_setup.sql` a également reçu `SET default_storage_engine = InnoDB`, qui
manquait. Sur un serveur configuré en MyISAM, l'import échouait à la moitié
(erreur 1005 : MyISAM ignore les clés étrangères) — et s'il avait réussi,
MyISAM aurait verrouillé la **table entière** à chaque inscription, ce qui
interdit toute tenue en charge. `install_mutualise.sql` le faisait déjà.

---

## 6. Ce qui reste à faire

Par ordre d'importance pour la montée en charge :

| # | Chantier | Pourquoi |
| --- | --- | --- |
| 1 | **Quitter le mutualisé** | C'est le seul plafond qui reste, et aucun travail sur le code ne le déplacera. Une offre gratuite limite à ~10–20 processus PHP et 30 connexions MySQL. Un VPS 4 vCPU / 8 Go à ~12 €/mois supprime la limite |
| 2 | **Base adjacente à l'application** | Les mesures ci-dessus paient plusieurs millisecondes de réseau par requête SQL, soit l'essentiel de la latence restante. Sur un mutualisé, la base est effectivement sur une autre machine |
| 3 | **Relâcher le verrou de session sur les pages en lecture** | Facteur 15 par utilisateur (section 3.3). À faire page par page : une écriture dans `$_SESSION` après fermeture serait perdue sans bruit |
| 4 | **Stockage objet pour les photos** | Déjà bloquant dans SL-12. Tant que les fichiers vivent sur le disque local, impossible de mettre deux instances derrière un répartiteur |
| 5 | **Sessions dans un magasin partagé** | Même raison : des sessions en fichiers interdisent la seconde instance |
| 6 | **Mesurer avec un vrai jeu d'utilisateurs** | Les tests ci-dessus rejouent une seule page. Un scénario de navigation réaliste (hub → fiche → inscription) donnerait un chiffre plus juste |
| 7 | **File d'attente pour les e-mails** | `mail()` est synchrone. Aujourd'hui seuls le cron et la réinitialisation de mot de passe l'appellent, donc le volume est négligeable — à revoir si un e-mail entre dans un parcours courant |
| 8 | **Connexions persistantes** | Prêt et désactivé (`DB_PERSISTANT=1`). À activer sur un serveur dédié, jamais sur un mutualisé limité en connexions |
| 9 | **APCu** | Le cache applicatif l'utilise automatiquement s'il est présent, et retombe sur des fichiers sinon. L'installer est un gain gratuit |


## 7. Entretien

Le cache fichier et les canaux de temps réel laissent des traces qu'il faut
balayer, hors du chemin des requêtes :

```bash
php cron/entretien.php            # une fois par jour
php cron/entretien.php --simulation
```

Sans exécution régulière, rien ne casse : seule la place occupée augmente
lentement.

---

*Mesures du 19/09/2026 — image Docker construite et exécutée (Apache 2.4.68, PHP 8.3, OPcache), MariaDB 11.5.2, jeu de 5 000 étudiants et 1 500 événements.*
