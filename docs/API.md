# Référence API — Linkee

Tous les endpoints JSON se trouvent dans `/api/`. Ils nécessitent une **session PHP active** (utilisateur connecté).

---

## Authentification

Tous les endpoints vérifient `$_SESSION['user_id']`. Si absent → `{"error": "Non connecté"}` (HTTP 401).

---

## Écriture : jeton CSRF obligatoire

Tout endpoint qui **écrit** exige trois choses, vérifiées par
`protegerEcritureApi()` ([`includes/security.php`](../includes/security.php))
avant la moindre requête SQL :

| Exigence | Sinon |
| --- | --- |
| Méthode `POST` | `405` + `Allow: POST` |
| En-tête `X-CSRF-Token` valide | `403` avec `"code": "csrf"` |
| `Origin` (ou à défaut `Referer`) du même hôte | `403` avec `"code": "csrf"` |

Le jeton est publié dans le `<head>` de chaque page :

```html
<meta name="csrf-token" content="…">
```

Côté client, `enTetesJson()` (`assets/js/app.js`) le lit et le pose — ne pas
recopier l'objet d'en-têtes à la main, c'est ainsi qu'on oublie le jeton :

```js
const res = await fetch(BASE + '/api/inscrire.php', {
  method: 'POST',
  headers: enTetesJson(),
  body: JSON.stringify({ evenement_id: id })
});
```

Le jeton peut aussi voyager dans le corps JSON (`"csrf_token": "…"`) pour un
appelant qui ne peut pas poser d'en-tête.

**Pourquoi, alors que le cookie est en `SameSite=Lax`.** Lax bloque
effectivement l'envoi du cookie sur un POST venu d'un autre site :
l'application n'était pas vulnérable en l'état. Mais c'était sa seule
défense, là où les formulaires en ont deux, et elle tenait entièrement à une
ligne de configuration de session — un passage en `None` pour faire
fonctionner une intégration tierce, et onze points d'écriture s'ouvraient
d'un coup sans que rien dans leur code ne le signale.

`tests/Unit/ApiProtectionTest.php` échoue si un endpoint d'écriture est
ajouté sans cette garde, ou si un `fetch` POST du JavaScript oublie le jeton.

### Endpoints en lecture seule

Sans jeton, appelables en `GET` : `live.php`, `social_feed.php`,
`squad_members.php`, `stats.php`. Ils ne modifient rien — et un test le
vérifie, pour que l'exemption ne devienne pas un trou le jour où l'un d'eux
se met à écrire.

### Réponse en cas de refus

```json
{ "success": false, "code": "csrf", "message": "Session expirée. Recharge la page." }
```

Le champ `code` permet au client de distinguer une session expirée d'une
erreur métier : `estRefusCsrf(data)` est disponible dans `app.js`.

---

## Endpoints Étudiant

### `POST /api/inscrire.php`
S'inscrire à un événement.

**Body (JSON ou form-data)**
```json
{ "event_id": 42 }
```

**Réponses**
```json
// Succès
{ "success": true, "qr_code": "abc123...", "message": "Inscrit !" }

// Complet
{ "error": "event_full" }

// Déjà inscrit
{ "error": "already_inscrit" }
```

---

### `POST /api/annuler_pass.php`
Annuler son inscription.

**Body**
```json
{ "inscription_id": 15 }
```

**Réponses**
```json
{ "success": true }
{ "error": "not_found" }
```

---

### `POST /api/follow.php`
Suivre ou ne plus suivre un utilisateur.

**Body**
```json
{ "target_id": 7 }
```

**Réponses**
```json
{ "success": true, "action": "follow",   "count": 12 }
{ "success": true, "action": "unfollow", "count": 11 }
```

---

### `POST /api/create_squad.php`
Créer une nouvelle squad.

**Body (JSON)**
```json
{
  "titre":       "Sortie running Puy-de-Dôme",
  "type":        "running",
  "description": "8km, allure tranquille",
  "niveau":      "inter",
  "date_heure":  "2026-06-15 08:00",
  "lieu":        "Parking Royat",
  "quota":       10
}
```

**Réponses**
```json
{ "success": true, "squad_id": 5 }
{ "error": "Titre requis" }
```

---

### `POST /api/rejoindre_squad.php`
Rejoindre une squad existante.

**Body**
```json
{ "squad_id": 5 }
```

**Réponses**
```json
{ "success": true }
{ "error": "full" }
{ "error": "already_member" }
```

---

### `POST /api/quitter_squad.php`
Quitter une squad.

**Body**
```json
{ "squad_id": 5 }
```

**Réponses**
```json
{ "success": true }
{ "error": "not_member" }
```

---

### `GET /api/squad_members.php?squad_id=5`
Liste des membres d'une squad.

**Réponse**
```json
[
  { "id": 1, "prenom": "Arthur", "nom": "Martin", "ecole": "UCA" },
  { "id": 2, "prenom": "Léa",    "nom": "Dubois",  "ecole": "SIGMA" }
]
```

---

### `POST /api/inviter.php`
Inviter un abonné à un événement ou une squad.

**Body**
```json
{
  "target_user_id": 3,
  "type":           "event",
  "item_id":        42
}
```

**Réponses**
```json
{ "success": true }
{ "error": "not_follower" }
```

---

### `GET /api/social_feed.php`
Fil d'activité des personnes suivies.

**Réponse**
```json
[
  {
    "type":      "inscription",
    "user":      "Léa Dubois",
    "event":     "Soirée Étudiante",
    "timestamp": "2026-05-18 21:00"
  }
]
```

---

## Endpoints Partenaire

### `GET /api/stats.php?event_id=42`
Statistiques d'un événement (réservé au partenaire propriétaire).

**Contrôle d'accès** : vérifie que `evenements.etablissement.user_id = $_SESSION['user_id']`

**Réponse**
```json
{
  "inscriptions_par_jour": [
    { "date": "2026-05-15", "total": 12 },
    { "date": "2026-05-16", "total": 8 }
  ],
  "checkin_par_heure": [
    { "heure": 21, "total": 15 },
    { "heure": 22, "total": 32 }
  ],
  "par_ecole": [
    { "ecole": "UCA", "total": 45 },
    { "ecole": "SIGMA Clermont", "total": 18 }
  ],
  "taux_conversion": 0.68
}
```

---

## API mobile (`/api/v1/`) — textes légaux et compte

L'application mobile parle à `/api/v1/`, par **jeton** (`Authorization: Bearer …`) et non par session.
Seuls les points qui servent à récupérer ou quitter un compte sont décrits ici ; les erreurs y prennent la
forme `{ "success": false, "code": "…", "message": "…" }`.

### `GET /api/v1/legal.php` — public
Les textes légaux, pour les afficher dans l'application. Sans paramètre : la liste. Avec `?page=` : un document
(`mentions-legales`, `cgu`, `confidentialite`).

```json
{ "success": true, "page": {
    "slug": "cgu", "titre": "Conditions Générales d'Utilisation", "maj": "14/10/2026",
    "sections": [ { "titre": "Article 1 — Objet",
                    "blocs": [ { "type": "p", "texte": "…" }, { "type": "liste", "items": ["…"] } ] } ],
    "pied": "…" } }
```
Aucun HTML : seul `**gras**` est balisé. Le texte vient de `includes/textes_legaux.php`, la même source que les
pages du site. Page inconnue → 404 `introuvable`.

### `POST /api/v1/mot_de_passe_oublie.php` — public
Demande un **code à six chiffres**, envoyé par e-mail (valable 15 minutes).

```json
{ "email": "arthur@uca.fr" }
```
Réponse identique que l'adresse existe ou non : `{ "success": true, "code_dev": null }`. `code_dev` n'est rempli qu'en
local, où rien ne part. Un code déjà envoyé il y a moins d'une minute n'est pas renvoyé (cela borne la devinette).
Limite : 5 demandes par IP et par 15 minutes.

### `POST /api/v1/mot_de_passe_reinitialiser.php` — public
Choisit un nouveau mot de passe avec le code reçu.

```json
{ "email": "arthur@uca.fr", "code": "123456", "mot_de_passe": "nouveaumdp99" }
```
| Réponse | Cas |
|---|---|
| `200 { "success": true }` | Mot de passe changé ; **tous les appareils sont déconnectés** |
| `422 code_invalide` | Compte inconnu, code faux, expiré ou épuisé — même message dans tous les cas |
| `422 mot_de_passe` | Trop court (8 caractères, 12 pour un admin) ; le code n'est **pas** brûlé |
| `429 trop_de_tentatives` | 15 échecs par IP et par 15 minutes |

Cinq saisies fausses détruisent le code. Un succès remet à zéro le compteur de l'IP.

### `POST /api/v1/supprimer_compte.php` — jeton requis
Supprime son compte. Le mot de passe est redemandé.

```json
{ "mot_de_passe": "…" }
```
| Type de compte | Effet | Réponse |
|---|---|---|
| étudiant | Effacement immédiat des données personnelles ; la ligne `users` est **anonymisée** (type `supprime`, école et promotion conservées) ainsi que les présences passées, pour les statistiques des partenaires. Pass à venir annulés, places libérées. | `{ "success": true, "supprime": true }` |
| partenaire | Demande transmise à contact@linkee.fr (abonnement, soirées à venir) ; le compte reste ouvert | `{ "success": true, "supprime": false, "demande": true }` |
| admin | Refusé | `403 type_non_supprimable` |

Mot de passe faux → `403 mot_de_passe` (compte intact). Détail de ce qui est effacé : `includes/compte.php`.

---

## Codes d'erreur communs

| Code | Signification |
|------|--------------|
| `not_logged` | Session expirée ou absent |
| `not_found` | Ressource introuvable |
| `forbidden` | Accès non autorisé (mauvais rôle ou IDOR) |
| `event_full` | Quota atteint |
| `already_*` | Doublon (déjà inscrit, déjà membre…) |
| `invalid_input` | Données manquantes ou invalides |
