# Registre des traitements de données à caractère personnel

Linkee — établi le 16/09/2026 · Version 1 (projet)
Rédigé par : Arthur Gramond
Obligation : article 30 du RGPD. Ce registre doit être tenu à jour et présenté sur demande de la CNIL.

**Statut du document.** Version de travail établie à partir du schéma de base réel de l'application. Elle doit être relue par un professionnel, et surtout corrigée sur un point : les durées de conservation ci-dessous sont des **propositions**, pas un constat. À ce jour, l'application ne purge presque rien (voir partie 4). Un registre qui annonce des durées non appliquées est pire que pas de registre.

---

## 1. Responsable du traitement

| Point | État au 16/09/2026 |
| --- | --- |
| Responsable | **Arthur Gramond, personne physique** — la société n'existe pas encore |
| À corriger | Dès l'immatriculation (SL-11), le responsable devient la société. Ce registre, les mentions légales et la politique de confidentialité doivent être repris le même jour |
| Contact données personnelles | Adresse de contact dédiée à créer sur le domaine, à publier dans la politique de confidentialité |
| Délégué à la protection des données | Non désigné. Non obligatoire ici : pas de suivi systématique à grande échelle, pas de données sensibles au sens de l'article 9 |

---

## 2. Les traitements

### T1 — Gestion des comptes étudiants

| | |
| --- | --- |
| Finalité | Créer et administrer le compte, authentifier, afficher le profil |
| Base légale | Exécution du contrat (CGU acceptées à l'inscription) |
| Personnes concernées | Étudiants inscrits |
| Données | Nom, prénom, e-mail, mot de passe haché, école, promotion, centres d'intérêt, photo de profil, horodatage d'acceptation des CGU, date de création (`users`, `user_settings`) |
| Destinataires | Les associés, dans le cadre de l'exploitation. Le prénom, le nom, l'école, la promotion et la photo sont visibles des autres étudiants via le profil public |
| Conservation proposée | Durée du compte, puis anonymisation 30 jours après suppression |

### T2 — Vérification de la majorité

| | |
| --- | --- |
| Finalité | Bloquer l'inscription des mineurs, l'application orientant vers des établissements servant de l'alcool |
| Base légale | Intérêt légitime — protection des mineurs |
| Données | Date de naissance (`users.date_naissance`) |
| Destinataires | Personne. Donnée jamais affichée, jamais transmise au partenaire |
| Conservation proposée | Durée du compte. À la suppression, ne conserver que la preuve binaire « majorité vérifiée », pas la date |
| Note | Le contrôle d'identité à l'entrée reste à la charge de l'établissement, et doit être rappelé dans le contrat partenaire |

### T3 — Réservation et contrôle d'accès aux événements

| | |
| --- | --- |
| Finalité | Réserver une place, générer le pass QR, valider la présence par scan à l'entrée |
| Base légale | Exécution du contrat |
| Données | Identifiant utilisateur, identifiant événement, jeton QR, statut (inscrit / présent / annulé), horodatage (`inscriptions`) |
| Destinataires | **L'établissement concerné, pour sa propre soirée uniquement** : identité de l'inscrit et statut de présence |
| Conservation proposée | 24 mois à compter de l'événement, puis anonymisation (rattachement à l'école et à la promotion, sans identité) |
| Point de vigilance | Ce sont les données les plus sensibles du produit : elles disent où une personne identifiée se trouvait un soir donné |

### T4 — Rappels et e-mails transactionnels

| | |
| --- | --- |
| Finalité | Rappel la veille de l'événement, réinitialisation de mot de passe |
| Base légale | Exécution du contrat |
| Données | E-mail, contenu du rappel, horodatage d'envoi (`rappels_envoyes`), jeton haché de réinitialisation (`password_resets`) |
| Destinataires | Le prestataire SMTP à venir — **sous-traitant, contrat article 28 obligatoire** |
| Conservation proposée | Jetons de réinitialisation : purge 24 h après expiration. Traces d'envoi : 12 mois |
| Note | Aucune prospection commerciale par e-mail n'est faite aujourd'hui. Si elle est mise en place un jour, elle relèvera du consentement et devra figurer ici comme un traitement distinct |

### T5 — Fonctionnalités sociales

| | |
| --- | --- |
| Finalité | Suivre d'autres étudiants, créer et rejoindre des squads, inviter à un événement, alimenter le fil |
| Base légale | Exécution du contrat |
| Données | Relations d'abonnement (`follows_users`, `follows_etablissements`), appartenance aux squads (`squads`, `squad_membres` — dont titre, lieu, date, niveau), invitations émises et reçues (`invitations`) |
| Destinataires | Les autres étudiants, selon la visibilité de chaque fonctionnalité |
| Conservation proposée | Durée du compte. Squads : 12 mois après la date de l'activité |

### T6 — Avis et notes

| | |
| --- | --- |
| Finalité | Permettre à l'étudiant de noter un événement après sa venue |
| Base légale | Exécution du contrat |
| Données | Note, commentaire libre, auteur, événement (`avis`) |
| Destinataires | Les autres étudiants et l'établissement noté |
| Conservation proposée | 24 mois, puis dissociation de l'auteur |
| Note | Le commentaire est un champ libre : il peut contenir n'importe quoi, y compris des données d'un tiers. C'est le premier motif de signalement à prévoir |

### T7 — Gamification et économies

| | |
| --- | --- |
| Finalité | Attribuer XP, niveaux et badges ; afficher à l'étudiant les économies réalisées |
| Base légale | Exécution du contrat |
| Données | Badges obtenus (`user_badges`), montants économisés par événement (`economies`) |
| Destinataires | L'étudiant. Les badges peuvent être visibles sur le profil public |
| Conservation proposée | Durée du compte |

### T8 — Statistiques partenaires

| | |
| --- | --- |
| Finalité | Fournir à l'établissement le taux de présence, la composition par école, les pics horaires de **ses propres** soirées |
| Base légale | Intérêt légitime de l'établissement à mesurer son opération |
| Données | Agrégats calculés à partir de T3 et de l'école / promotion de T1 |
| Destinataires | L'établissement concerné, pour ses seules soirées |
| Conservation proposée | Agrégats conservés sans limite, une fois détachés de toute identité |
| **Règle non négociable** | **Le partenaire consulte la liste des inscrits à sa propre soirée et des statistiques agrégées sur sa salle. Il ne reçoit jamais de fichier d'adresses, ni aucun export nominatif, même s'il le demande.** C'est une obligation, et c'est aussi ce qui fait que les étudiants s'inscrivent sans se méfier (SL-09) |

### T9 — Modération

| | |
| --- | --- |
| Finalité | Traiter les signalements, permettre le blocage entre utilisateurs |
| Base légale | Intérêt légitime — sécurité des utilisateurs et respect des CGU |
| Données | Auteur et cible du signalement, motif, détails libres, statut, modérateur ayant traité, horodatages (`user_reports`) ; relations de blocage (`user_blocks`) |
| Destinataires | Les comptes de type `admin` uniquement |
| Conservation proposée | Signalement traité : 12 mois. Signalement ayant entraîné une exclusion : 3 ans, pour pouvoir justifier la décision |
| Note | La personne signalée a un droit d'accès à son dossier ; l'identité du signalant n'a pas à lui être communiquée |

### T10 — Sécurité des accès

| | |
| --- | --- |
| Finalité | Limiter les tentatives de connexion, prévenir les attaques par force brute |
| Base légale | Intérêt légitime — sécurité du système |
| Données | **Adresse IP**, e-mail tenté, horodatage (`login_attempts`) |
| Destinataires | Personne |
| Conservation | Purge automatique déjà implémentée — `includes/security.php:73`. **Seule durée réellement appliquée aujourd'hui.** Vérifier que la fenêtre configurée ne dépasse pas 12 mois |

### T11 — Journalisation applicative

| | |
| --- | --- |
| Finalité | Diagnostiquer les erreurs de production |
| Base légale | Intérêt légitime — maintien en condition opérationnelle |
| Données | Identifiant utilisateur et URI de la page, joints au message d'erreur — `includes/log.php` |
| Destinataires | Arthur Gramond ; l'hébergeur, qui reçoit le flux `error_log` |
| Conservation proposée | 6 mois, puis suppression. **Rien n'est purgé aujourd'hui** |
| Note | Le code évite déjà de journaliser le contenu de session et les secrets. À maintenir : aucun e-mail, aucun mot de passe, aucun jeton dans les logs |

### T12 — Comptes partenaires

| | |
| --- | --- |
| Finalité | Gérer l'espace partenaire, les fiches d'établissement et les événements publiés |
| Base légale | Exécution du contrat partenaire |
| Personnes concernées | Gérants et personnels des établissements |
| Données | Nom, prénom, e-mail, mot de passe haché du compte gérant (`users` avec `type = 'partenaire'`), nom, type et adresse de l'établissement (`etablissements`), photos (`etablissement_photos`) |
| Destinataires | Public, pour la fiche établissement |
| Conservation proposée | Durée du contrat, puis 5 ans pour les seuls éléments nécessaires à la preuve de la relation commerciale |

---

## 3. Sous-traitants et hébergement

| Sous-traitant | Rôle | Contrat art. 28 | Localisation des données |
| --- | --- | --- | --- |
| Railway | Hébergement applicatif et base MySQL | À récupérer et archiver | **À vérifier impérativement** : Railway est une société américaine. Si la région d'hébergement n'est pas européenne, il y a un transfert hors UE à documenter (clauses contractuelles types, Data Privacy Framework). Sélectionner une région UE si l'option existe |
| Prestataire SMTP | Envoi des e-mails transactionnels | À signer **avant** la mise en service (SL-12, échéance 29/09) | Choisir un prestataire européen : cela supprime la question du transfert |
| Stockage objet | Photos d'établissement et avatars | À signer avant migration (SL-12, échéance 20/10) | Idem, région UE |
| Expert-comptable | Comptabilité | Hors périmètre de ce registre | — |

Aucun service de mesure d'audience publicitaire, aucun traceur tiers, aucun cookie autre que le cookie de session : c'est un choix, et il a une valeur commerciale — il rend la bannière cookies inutile et renforce l'argument de la partie T8.

---

## 4. Ce que ce registre révèle — écarts à traiter

Ces cinq points sont ressortis de la lecture du schéma. Ce sont des chantiers, pas des observations.

1. **La suppression de compte n'existe pas.** Le droit à l'effacement n'est pas optionnel. Chantier déjà identifié (SL-04, SL-12), échéance 20/10.

2. **Le `ON DELETE CASCADE` est un piège.** Toutes les tables liées à `users` sont en cascade : supprimer un compte effacerait ses inscriptions et ses check-ins, donc **les statistiques déjà vendues au partenaire**, rétroactivement. La suppression doit donc être une **anonymisation** — dissocier l'identité en conservant l'école, la promotion et le fait de la présence — et non un `DELETE` sur `users`. À concevoir avant de coder la fonctionnalité, pas après.

3. **Une seule durée de conservation est réellement appliquée** : celle de `login_attempts`. Rien ne purge les jetons de réinitialisation expirés, les traces de rappel, les invitations anciennes, les signalements traités, ni les journaux d'erreur. Toutes les durées de ce document restent théoriques tant qu'une tâche planifiée ne les applique pas — le dossier `cron/` existe déjà, c'est l'endroit.

4. **La localisation des données chez Railway n'est pas établie.** À vérifier cette semaine : c'est une question de transfert hors UE, et elle est plus lourde à corriger après le lancement qu'avant.

5. **La politique de confidentialité en ligne ne reflète pas ce registre.** Elle doit reprendre, en langage clair : les finalités ci-dessus, les durées retenues, les sous-traitants, et la procédure d'exercice des droits (accès, rectification, effacement, opposition, portabilité) avec un délai de réponse d'un mois.

---

## 5. Suivi

| Point | Responsable | Échéance |
| --- | --- | --- |
| Relecture par un professionnel du droit | Arthur | Avant publication |
| Vérification de la région d'hébergement Railway | Arthur | 22/09/2026 |
| Contrat de sous-traitance SMTP | Arthur | 29/09/2026 |
| Tâches planifiées de purge selon les durées retenues | Arthur | 20/10/2026 |
| Anonymisation et suppression de compte | Arthur | 20/10/2026 |
| Politique de confidentialité alignée sur ce registre | Arthur | 20/10/2026 |
| Reprise du responsable de traitement après immatriculation | Arthur | Jour de la création |
| Revue annuelle du registre | Arthur | 16/09/2027 |
