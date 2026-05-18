# Modèle Conceptuel de Données — StudentLink

## Diagramme Entité-Relation (Mermaid)

```mermaid
erDiagram
    users {
        int id PK
        varchar nom
        varchar prenom
        varchar email UK
        varchar password
        varchar ecole
        varchar promo
        enum type
        timestamp created_at
    }

    etablissements {
        int id PK
        int user_id FK
        varchar nom
        enum type
        varchar adresse
        varchar ville
    }

    evenements {
        int id PK
        int etablissement_id FK
        varchar titre
        text description
        enum type
        datetime date_heure
        int quota
        int reduction
        decimal prix_normal
        tinyint is_flash
        datetime flash_expiry
        tinyint is_gratuit
        varchar lieu
        timestamp created_at
    }

    inscriptions {
        int id PK
        int user_id FK
        int evenement_id FK
        varchar qr_code
        enum statut
        timestamp created_at
    }

    squads {
        int id PK
        int createur_id FK
        enum type
        varchar titre
        text description
        enum niveau
        datetime date_heure
        varchar lieu
        int quota
        timestamp created_at
    }

    squad_membres {
        int id PK
        int squad_id FK
        int user_id FK
        timestamp joined_at
    }

    economies {
        int id PK
        int user_id FK
        int evenement_id FK
        decimal montant
        date date_economie
    }

    follows_users {
        int follower_id FK
        int followed_id FK
        timestamp created_at
    }

    follows_etablissements {
        int user_id FK
        int etablissement_id FK
        timestamp created_at
    }

    avis {
        int id PK
        int user_id FK
        int evenement_id FK
        tinyint note
        text commentaire
        timestamp created_at
    }

    badges {
        varchar code PK
        varchar nom
        varchar description
        varchar icon
        varchar couleur
    }

    user_badges {
        int user_id FK
        varchar badge_code FK
        timestamp unlocked_at
    }

    user_settings {
        int user_id FK
        enum theme
    }

    login_attempts {
        int id PK
        varchar ip
        varchar email
        timestamp attempted_at
    }

    password_resets {
        int id PK
        varchar email
        varchar token_hash UK
        datetime expires_at
        tinyint used
        timestamp created_at
    }

    users ||--o{ etablissements : "possède"
    users ||--o{ inscriptions : "s'inscrit"
    users ||--o{ squad_membres : "rejoint"
    users ||--o{ squads : "crée"
    users ||--o{ economies : "économise"
    users ||--o{ follows_users : "suit (follower)"
    users ||--o{ follows_users : "est suivi (followed)"
    users ||--o{ follows_etablissements : "suit"
    users ||--o{ avis : "rédige"
    users ||--o{ user_badges : "débloque"
    users ||--|| user_settings : "configure"

    etablissements ||--o{ evenements : "organise"
    etablissements ||--o{ follows_etablissements : "est suivi par"

    evenements ||--o{ inscriptions : "reçoit"
    evenements ||--o{ economies : "génère"
    evenements ||--o{ avis : "reçoit"

    squads ||--o{ squad_membres : "contient"

    badges ||--o{ user_badges : "attribué à"
```

---

## Description des entités

### `users`
Utilisateurs de la plateforme. Deux rôles : `etudiant` et `partenaire`.  
Un partenaire possède un ou plusieurs `etablissements`.

### `etablissements`
Lieux partenaires (bar, boîte, resto, afterwork). Rattaché à un compte `partenaire`.

### `evenements`
Événements créés par les établissements. Peuvent être flash (expiration automatique), gratuits ou payants avec réduction.

### `inscriptions`
Lien étudiant ↔ événement. Statut : `inscrit` → `checkin` → `annule`. Contient le QR code unique.

### `squads`
Groupes sportifs créés par des étudiants. 4 types : running, vélo, muscu, autre.

### `squad_membres`
Table de jonction étudiants ↔ squads. Contrainte UNIQUE par couple (squad, user).

### `economies`
Montants économisés par chaque étudiant lors de ses inscriptions avec réduction.

### `follows_users`
Auto-référence sur `users`. Un étudiant peut en suivre un autre.

### `follows_etablissements`
Un étudiant peut suivre un établissement pour recevoir ses futures offres.

### `avis`
Note (1-5) + commentaire optionnel, laissé après un check-in validé. Unique par (user, événement).

### `badges`
9 badges définis (first_event, five_events, ten_events, first_squad, five_squads, first_follow, reviewer, early_bird, saver_50).

### `user_badges`
Badges débloqués par chaque utilisateur, avec la date de déverrouillage.

### `login_attempts`
Enregistre les tentatives de connexion par IP pour le rate limiting (anti-bruteforce).

### `password_resets`
Tokens de réinitialisation de mot de passe : hashé SHA-256, expiration 1h, usage unique.

---

## Contraintes principales

| Contrainte | Table | Colonnes |
|-----------|-------|---------|
| UNIQUE | `users` | `email` |
| UNIQUE | `inscriptions` | `(user_id, evenement_id)` |
| UNIQUE | `squad_membres` | `(squad_id, user_id)` |
| UNIQUE | `avis` | `(user_id, evenement_id)` |
| UNIQUE | `follows_users` | `(follower_id, followed_id)` |
| UNIQUE | `follows_etablissements` | `(user_id, etablissement_id)` |
| PK composite | `user_badges` | `(user_id, badge_code)` |
| FK CASCADE | toutes | `ON DELETE CASCADE` |
