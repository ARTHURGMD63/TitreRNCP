# Référence API — StudentLink

Tous les endpoints JSON se trouvent dans `/api/`. Ils nécessitent une **session PHP active** (utilisateur connecté).

---

## Authentification

Tous les endpoints vérifient `$_SESSION['user_id']`. Si absent → `{"error": "Non connecté"}` (HTTP 401).

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

## Codes d'erreur communs

| Code | Signification |
|------|--------------|
| `not_logged` | Session expirée ou absent |
| `not_found` | Ressource introuvable |
| `forbidden` | Accès non autorisé (mauvais rôle ou IDOR) |
| `event_full` | Quota atteint |
| `already_*` | Doublon (déjà inscrit, déjà membre…) |
| `invalid_input` | Données manquantes ou invalides |
