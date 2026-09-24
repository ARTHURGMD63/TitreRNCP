# Architecture technique — Linkee

## Vue d'ensemble

```
┌──────────────────────────────────────────────────────────┐
│                        CLIENT                             │
│  Browser (HTML + CSS + JS vanilla + Chart.js + PWA)      │
└────────────────────────┬─────────────────────────────────┘
                         │ HTTPS (TLS Let's Encrypt)
                         ▼
┌──────────────────────────────────────────────────────────┐
│                   RAILWAY (Cloud)                         │
│                                                           │
│  ┌─────────────────────────────┐  ┌──────────────────┐   │
│  │    PHP 8.3 (Nixpacks)       │  │   MySQL 8.0      │   │
│  │    Apache/PHP-FPM           │◄─┤   (Railway DB)   │   │
│  │    /app/                    │  │   railway DB     │   │
│  └─────────────────────────────┘  └──────────────────┘   │
└──────────────────────────────────────────────────────────┘
```

## Structure des requêtes

```
Requête utilisateur
       │
       ▼
 auth_check.php (inclus par toutes les pages)
       │
       ├─ setSecurityHeaders()    → Headers HTTP (CSP, HSTS…)
       ├─ session_start()         → Session PHP
       ├─ requireStudent/Partner  → Contrôle d'accès
       │
       ▼
 Page .php (controller + view en un seul fichier)
       │
       ├─ Lecture GET/POST        → Validation + sanitisation
       ├─ db.php ($pdo)          → PDO prepared statements
       ├─ Logique métier          → PHP pur
       │
       ▼
 HTML renvoyé au client (pas de template engine)
```

## Pattern MVC simplifié

Linkee utilise un **pattern PHP procédural MVC léger** sans framework :

| Couche | Implémentation |
|--------|---------------|
| **Model** | Helpers dans `includes/` ; requêtes PDO encore inline dans les pages secondaires |
| **View** | HTML inline avec `<?php ?>` |
| **Controller** | Logique en haut de chaque fichier `.php` |

Ce choix est **intentionnel** pour un projet de cette taille : pas de surcharge d'un framework (Symfony/Laravel), et un fichier par écran qui se lit de haut en bas.

### Sa limite, et où elle a été franchie

Le modèle tient tant qu'une page reste lisible d'un bout à l'autre. `explore.php` ne l'était plus : **1 051 lignes**, dix requêtes, deux classements par affinité et deux paginations mêlés à six cents lignes de gabarit. C'était le fichier le plus complexe de l'application, et le seul qu'aucun test ne pouvait atteindre — le charger exécutait aussi son affichage.

La couche données en a été extraite dans [`includes/hub.php`](../includes/hub.php), sans toucher aux requêtes elles-mêmes :

| Fonction | Rôle |
|---|---|
| `hubCriteres()` | Traduit `$_GET` en critères validés. Calcul pur, sans base ni session : c'est là que se testent page négative, style de musique inventé, vue inconnue |
| `hubAnnuaire()` | L'annuaire étudiant : suggestions, liste, filtres |
| `hubEvenements()` | Le fil des soirées |
| `hubAbonnements()`, `hubEtablissementsSuivis()`, `hubAmisParEvenement()` | Les trois lectures annexes |

`explore.php` est passé de 1 051 à **696 lignes**, dont 88 d'orchestration : lire les critères, demander les données, afficher. Aucune requête ne part plus du gabarit, et le rendu a été comparé avant/après sur quatorze combinaisons de filtres — identique sur toutes, à une exception voulue : `?view=bidon` affichait une page sans aucune des deux listes, il retombe désormais sur le fil des soirées.

Les autres pages n'ont pas été découpées : à 300–500 lignes, elles restent lisibles, et un découpage systématique coûterait plus en indirection qu'il ne rapporterait.

### Fichiers transverses de `includes/`

| Fichier | Rôle |
|---|---|
| `config.php` | Lecture des réglages : environnement, puis `config.local.php`, puis défaut. Point unique — `db.php` et `mail.php` en dépendent tous deux |
| `db.php` | Connexion PDO, requêtes réellement préparées, connexions persistantes sur demande |
| `session.php` | Démarrage de session durci ; `sessionLectureSeule()` relâche le verrou immédiatement |
| `auth_check.php` | Gardes de rôle, `baseUrl()`, `asset()`, formatage des dates |
| `security.php` | En-têtes, CSRF (formulaires **et** API), contrôle d'origine, redirections internes, rate limiting |
| `migrations.php` | Découpage des scripts SQL et suivi des versions appliquées |
| `mail.php` | Envoi : en-têtes RFC, encodage, transport `mail()` ou SMTP authentifié |
| `hub.php` | Couche données du hub étudiant |
| `temps_reel.php` | Flux de révisions et ETag |
| `cache.php`, `agregats.php` | APCu si présent, fichiers sinon ; agrégats partagés |

## Les trois espaces

Un seul code, un seul domaine, trois espaces distingués par `users.type` et
gardés par `requireStudent()`, `requirePartner()` et `requireAdmin()`.

| Espace | Racine | Coquille | Pour qui |
|--------|--------|----------|----------|
| Étudiant | `/explore.php`, `/squads.php`, `/wallet.php`, `/profil.php` | `.app-shell` + `.bottom-nav` | Les étudiants |
| Partenaire | `/partenaire/` | `.partner-shell` | Les établissements clients |
| Interne | `/admin/` | `.partner-shell` (même gabarit) | Arthur et Étienne |

`accueilSelonType()` (`includes/auth_check.php`) est le **seul** endroit qui
décide où atterrit un compte après connexion. Sans ce point unique, chaque
garde renvoyait vers « l'autre » espace et les renvois se répondaient : un
administrateur envoyé sur `explore.php` était repoussé vers le tableau de bord
partenaire, qui le repoussait vers `explore.php`. Boucle infinie.

### Back-office fondateurs (`/admin/`)

| Écran | Rôle |
|-------|------|
| `index.php` | Relevé hebdomadaire de SL-07 : North Star, marketplace, business, seuils d'alerte |
| `clients.php` / `client.php` | CRM commercial : pipeline, abonnements, historique des échanges |
| `utilisateurs.php` | Base étudiants : activité, centres d'intérêt, économies |
| `evenements.php` | Toutes les soirées, tous établissements : remplissage et taux de présence |
| `finances.php` | Trésorerie, MRR, registre recettes/dépenses, point mort |
| `moderation.php` | Signalements à traiter |

Trois tables dédiées (migration v12) :

- **`crm_clients`** — le compte commercial. Il n'est **pas** confondu avec
  `etablissements` : un prospect existe avant tout compte partenaire, et SL-05
  demande une liste de 30 cibles dont aucune n'aura de compte au moment où elle
  est constituée. `etablissement_id` reste `NULL` jusqu'à l'inscription.
- **`crm_interactions`** — appels, visites, relances, et la prochaine action
  avec son échéance, dans la même ligne que l'échange qui l'a produite.
- **`finance_mouvements`** — le registre de caisse. Le MRR se déduit des
  abonnements ; l'encaissé, non. Les lignes `prevu` (facturé, pas encore en
  banque) sont séparées des lignes `regle`, parce que confondre les deux est
  exactement ce qui fait croire qu'on a de la trésorerie.

Les calculs vivent dans `includes/crm.php`, jamais dans les pages : le MRR
affiché sur le tableau de bord et celui de la page finances doivent venir de la
même fonction. Les règles chiffrées (point mort, churn, autonomie, taux de
présence) sont isolées en fonctions pures — `financeCalculPointMort()`,
`crmCalculChurn()`… — pour être testables sans base, comme `niveauDepuisXp()`
dans `gamification.php`.

Les comptes fondateurs se créent en ligne de commande :

```bash
php outils/creer_admin.php "Prénom" "Nom" "email" "motdepasse"
php outils/creer_admin.php --promouvoir "email"
```

Pas de formulaire web : un écran qui fabrique des administrateurs est une porte
ouverte le jour où on l'oublie en ligne.

## Endpoints API

Les appels AJAX passent par `/api/*.php` qui retournent du JSON :

```
POST /api/inscrire.php        → Inscription à un event
POST /api/annuler_pass.php    → Annulation
POST /api/follow.php          → Follow/unfollow user
POST /api/create_squad.php    → Créer une squad
POST /api/rejoindre_squad.php → Rejoindre une squad
POST /api/quitter_squad.php   → Quitter une squad
GET  /api/stats.php           → Stats event (partenaire uniquement)
GET  /api/social_feed.php     → Fil d'activité
GET  /api/squad_members.php   → Membres d'une squad
POST /api/inviter.php         → Inviter à un event/squad
```

## Authentification & Sessions

```
Inscription/Login
      │
      ▼
session_regenerate_id(true)   ← Prévient la fixation de session
      │
      ▼
$_SESSION = [
    user_id, user_prenom,
    user_nom, user_type,
    user_ecole, csrf_token
]
      │
      ▼
Toutes les pages vérifient $_SESSION via requireStudent/requirePartner
```

## Détection d'environnement

```php
// includes/auth_check.php
function baseUrl(string $path = ''): string {
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $isLocal = str_contains($host, 'localhost');
    $base    = $isLocal ? '/TitreRNCP' : '';
    return $base . $path;
}
```

| Environnement | baseUrl('/explore.php') |
|--------------|------------------------|
| Local (WAMP) | `/TitreRNCP/explore.php` |
| Railway (prod) | `/explore.php` |

## Base de données

### Connexion (`includes/db.php`)

```php
// Détection automatique Railway vs local
$host = $_ENV['MYSQLHOST'] ?? 'localhost';
$pdo  = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
]);
```

### Installation de la base

```
db_setup.sql  → Installation complète en une importation :
                26 tables (schéma consolidé, migrations v4 à v15
                intégrées) + jeu de données de démonstration
                (événements générés avec NOW() + INTERVAL, donc
                toujours à venir quelle que soit la date d'import)
```

### Migrations

Les migrations se posaient à la main, fichier par fichier, dans phpMyAdmin, et rien n'enregistrait celles qui étaient déjà passées. Deux dumps complets — `db_setup.sql` et `install_mutualise.sql` — devaient en plus être tenus à jour à la main. **Ils ont divergé de quatre tables** (`crm_clients`, `crm_interactions`, `finance_mouvements`, `rappels_envoyes`) : toute installation faite en suivant le README produisait un back-office qui tombait en erreur au premier clic.

Trois choses ont changé :

1. **`schema_migrations`** enregistre chaque version appliquée, et quand. Les deux dumps l'amorcent à `v4…v15`, puisqu'ils contiennent déjà leur résultat.
2. **`outils/migrer.php`** applique ce qui manque, dans l'ordre numérique (`v9` avant `v10`, ce qu'un tri alphabétique ferait à l'envers). En ligne de commande uniquement : un outil qui modifie le schéma n'a rien à faire derrière une URL.
3. **`tests/Unit/SchemaTest.php`** échoue en intégration continue si les deux dumps cessent de décrire la même base, ou si le code interroge une table qu'aucune installation ne crée.

```bash
php outils/migrer.php --etat      # ce qui est appliqué, ce qui ne l'est pas
php outils/migrer.php             # applique ce qui manque
php outils/migrer.php --adopter     # raccorde une base déjà complètement à jour
php outils/migrer.php --adopter=v13 # raccorde jusqu'à v13 ; v14 et v15 restent en attente
```

Le découpage SQL gère `DELIMITER` — sans quoi les procédures stockées de la migration v7 seraient coupées à leur premier point-virgule interne — ainsi que les chaînes, les commentaires, et les jeux de résultats que produisent les `PREPARE`/`EXECUTE` des migrations conditionnelles.

## Déploiement CI/CD

```
git push origin main
       │
       ▼
GitHub Actions (.github/workflows/ci.yml)
       │
       ├─ PHP Lint (syntax check)
       ├─ PHPStan niveau 5
       └─ PHPUnit 40 tests (PHP 8.1 / 8.2 / 8.3)
             │
             ▼ (si succès)
       Railway détecte le push → redéploiement automatique
       Nixpacks détecte PHP → install composer → Apache up
```

## Choix techniques justifiés

| Choix | Alternative considérée | Justification |
|-------|----------------------|---------------|
| PHP 8.3 vanilla | Laravel / Symfony | Pas de surcharge, lisibilité maximale, RNCP évalue le code PHP direct |
| PDO + MySQL | Eloquent ORM | Contrôle total sur les requêtes, performance, sécurité visible |
| JS vanilla | React / Vue | Pas de build tool, chargement instantané, bonne maîtrise montrée |
| Chart.js | D3.js | API simple, excellents graphiques, léger (60kb) |
| Railway | Heroku / VPS | Free tier généreux, déploiement Docker automatique, MySQL inclus |
| Sessions PHP | JWT | Adapté à une app web traditionnelle, pas d'API REST publique |
| bcrypt `PASSWORD_DEFAULT` | MD5 / SHA1 | Standard de sécurité, coût adaptatif |
| PHPUnit | Pest | Plus standard, mieux documenté pour RNCP |

## Performances

- Pas de N+1 queries : jointures SQL systématiques
- Pas d'ORM : requêtes optimisées manuellement
- CSS custom properties : thème dark mode sans recalcul JavaScript
- `themeBootScript()` inline : zéro flash de contenu non stylisé
- Chart.js chargé uniquement sur `/partenaire/dashboard.php`

## Accessibilité

- `lang="fr"` sur toutes les pages
- Labels associés à chaque input (`for` + `id`)
- Focus visible (outline CSS non supprimé)
- Contrastes respectant WCAG AA sur les couleurs principales
- Navigation clavier fonctionnelle sur les formulaires
