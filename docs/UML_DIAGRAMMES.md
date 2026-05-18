# Diagrammes UML — StudentLink

---

## 1. Diagramme de cas d'utilisation

```mermaid
graph TD
    Etudiant([👤 Étudiant])
    Partenaire([🏢 Partenaire])
    Visiteur([👁 Visiteur])

    subgraph Auth
        UC1[S'inscrire]
        UC2[Se connecter]
        UC3[Mot de passe oublié]
        UC4[Modifier son profil]
    end

    subgraph Événements
        UC5[Explorer les events]
        UC6[S'inscrire à un event]
        UC7[Annuler son inscription]
        UC8[Scanner son QR code]
        UC9[Laisser un avis]
    end

    subgraph Social
        UC10[Suivre un étudiant]
        UC11[Voir un profil]
        UC12[Créer une squad]
        UC13[Rejoindre une squad]
        UC14[Inviter à un event/squad]
    end

    subgraph Gamification
        UC15[Voir ses badges]
        UC16[Consulter son niveau XP]
        UC17[Activer le dark mode]
    end

    subgraph Wallet
        UC18[Voir ses pass]
        UC19[Voir ses économies]
    end

    subgraph Partenaire_UC
        UC20[Créer un événement]
        UC21[Gérer ses événements]
        UC22[Valider un check-in]
        UC23[Voir les analytics]
        UC24[Supprimer un événement]
    end

    Visiteur --> UC1
    Visiteur --> UC2
    Visiteur --> UC3

    Etudiant --> UC4
    Etudiant --> UC5
    Etudiant --> UC6
    Etudiant --> UC7
    Etudiant --> UC8
    Etudiant --> UC9
    Etudiant --> UC10
    Etudiant --> UC11
    Etudiant --> UC12
    Etudiant --> UC13
    Etudiant --> UC14
    Etudiant --> UC15
    Etudiant --> UC16
    Etudiant --> UC17
    Etudiant --> UC18
    Etudiant --> UC19

    Partenaire --> UC20
    Partenaire --> UC21
    Partenaire --> UC22
    Partenaire --> UC23
    Partenaire --> UC24
```

---

## 2. Diagramme de séquence — Inscription à un événement

```mermaid
sequenceDiagram
    actor Étudiant
    participant Browser
    participant explore.php
    participant api/inscrire.php
    participant DB

    Étudiant->>Browser: Clique "S'inscrire"
    Browser->>api/inscrire.php: POST /api/inscrire.php {event_id}
    api/inscrire.php->>api/inscrire.php: Vérifie session (requireStudent)
    api/inscrire.php->>DB: SELECT quota, nb_inscrits FROM evenements
    DB-->>api/inscrire.php: quota=100, inscrits=42
    api/inscrire.php->>api/inscrire.php: Vérifie places disponibles
    api/inscrire.php->>DB: INSERT INTO inscriptions (qr_code=SHA256, statut='inscrit')
    DB-->>api/inscrire.php: OK (id=123)
    api/inscrire.php->>DB: INSERT INTO economies (montant)
    api/inscrire.php-->>Browser: {"success":true, "qr_code":"abc..."}
    Browser-->>Étudiant: Animation confetti + mise à jour UI
```

---

## 3. Diagramme de séquence — Connexion avec rate limiting

```mermaid
sequenceDiagram
    actor Utilisateur
    participant Browser
    participant auth/login.php
    participant includes/security.php
    participant DB

    Utilisateur->>Browser: Soumet email + mot de passe
    Browser->>auth/login.php: POST {email, password, csrf_token}
    auth/login.php->>includes/security.php: csrfVerify()
    includes/security.php->>includes/security.php: hash_equals(session_token, post_token)

    alt CSRF invalide
        includes/security.php-->>Browser: HTTP 403 "CSRF token invalide"
    end

    auth/login.php->>includes/security.php: isRateLimited($ip)
    includes/security.php->>DB: COUNT login_attempts WHERE ip=? AND attempted_at > -15min
    DB-->>includes/security.php: count=2

    alt count >= 5
        includes/security.php-->>Browser: "Trop de tentatives. Réessaye dans 15 min."
    end

    auth/login.php->>DB: SELECT * FROM users WHERE email=?
    DB-->>auth/login.php: user row

    alt password_verify() OK
        auth/login.php->>includes/security.php: clearLoginAttempts($ip)
        auth/login.php->>auth/login.php: session_regenerate_id(true)
        auth/login.php->>auth/login.php: $_SESSION[user_id, type, prenom...]
        auth/login.php-->>Browser: Redirect /explore.php
    else Échec
        auth/login.php->>includes/security.php: recordLoginAttempt($ip, $email)
        auth/login.php-->>Browser: "Email ou mot de passe incorrect"
    end
```

---

## 4. Diagramme de séquence — Réinitialisation du mot de passe

```mermaid
sequenceDiagram
    actor Utilisateur
    participant forgot.php
    participant security.php
    participant DB
    participant Email

    Utilisateur->>forgot.php: POST {email}
    forgot.php->>security.php: createPasswordResetToken($email)
    security.php->>DB: SELECT id FROM users WHERE email=?

    alt Email inconnu
        security.php-->>forgot.php: null (anti-énumération)
    end

    security.php->>security.php: token = bin2hex(random_bytes(32))
    security.php->>security.php: hash = SHA256(token)
    security.php->>DB: DELETE old tokens WHERE email=?
    security.php->>DB: INSERT password_resets (hash, expires_at=+1h)
    security.php-->>forgot.php: token brut

    forgot.php->>Email: sendResetEmail(email, token)
    forgot.php-->>Utilisateur: "Lien envoyé (si email connu)"

    Utilisateur->>reset.php: GET /reset.php?token=xxx
    reset.php->>security.php: validateResetToken(token)
    security.php->>security.php: hash = SHA256(token)
    security.php->>DB: SELECT email WHERE hash=? AND expires_at>NOW() AND used=0
    DB-->>security.php: email trouvé

    Utilisateur->>reset.php: POST {password, password_confirm, csrf_token}
    reset.php->>security.php: consumeResetToken(token, newPassword)
    security.php->>DB: UPDATE password_resets SET used=1
    security.php->>DB: UPDATE users SET password=bcrypt(newPassword)
    reset.php-->>Utilisateur: "Mot de passe mis à jour ✅"
```

---

## 5. Diagramme de séquence — Déblocage d'un badge

```mermaid
sequenceDiagram
    actor Étudiant
    participant avis.php
    participant gamification.php
    participant DB

    Étudiant->>avis.php: POST {note=5, commentaire="Super soirée !"}
    avis.php->>DB: INSERT INTO avis ... ON DUPLICATE KEY UPDATE
    avis.php->>gamification.php: checkBadges($pdo, $uid)
    gamification.php->>gamification.php: getUserStats($uid)
    gamification.php->>DB: COUNT inscriptions, squads, follows, avis
    DB-->>gamification.php: {events:3, squads:1, follows:2, avis:1}
    gamification.php->>gamification.php: Évalue conditions badges
    gamification.php->>DB: INSERT IGNORE INTO user_badges ('reviewer')
    DB-->>gamification.php: 1 row inserted (nouveau badge !)
    gamification.php-->>avis.php: ['reviewer']
    avis.php-->>Étudiant: "Merci pour ton avis !"
```

---

## 6. Diagramme de classes simplifié

```mermaid
classDiagram
    class User {
        +int id
        +string nom
        +string prenom
        +string email
        +string password
        +string ecole
        +string promo
        +string type
        +getXp() int
        +getLevel() int
        +getBadges() array
    }

    class Etablissement {
        +int id
        +int user_id
        +string nom
        +string type
        +string ville
        +getEvents() array
        +getAverageNote() float
    }

    class Evenement {
        +int id
        +int etablissement_id
        +string titre
        +datetime date_heure
        +int quota
        +int reduction
        +bool is_flash
        +bool is_gratuit
        +isAvailable() bool
        +isFull() bool
        +isPast() bool
    }

    class Inscription {
        +int id
        +int user_id
        +int evenement_id
        +string qr_code
        +string statut
        +generateQrCode() string
    }

    class Squad {
        +int id
        +int createur_id
        +string titre
        +string type
        +string niveau
        +int quota
        +isFull() bool
    }

    class Badge {
        +string code
        +string nom
        +string icon
        +string couleur
    }

    User "1" --> "0..*" Inscription
    User "1" --> "0..*" Squad : crée
    User "0..*" --> "0..*" User : suit
    User "0..*" --> "0..*" Badge : débloque
    Etablissement "1" --> "0..*" Evenement
    Evenement "1" --> "0..*" Inscription
    User "0..*" --> "0..*" Squad : rejoint
```
