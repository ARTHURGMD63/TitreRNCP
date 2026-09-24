# Délivrabilité des e-mails

L'application envoie deux types de message : la réinitialisation de mot de
passe (`auth/forgot.php`) et les rappels de veille de soirée
(`cron/rappels.php`). Tous deux passent par `envoyerEmail()`, dans
[`includes/mail.php`](../includes/mail.php).

Ce document explique ce qui est réglé dans le code, et ce qui ne peut l'être
que dans le DNS.

---

## 1. Le problème

`mail()` remet le message au `sendmail` de la machine, qui l'expédie sous
l'identité du serveur. Sur un hébergement mutualisé, cela donne trois
défauts, et ils se cumulent :

| Défaut | Conséquence |
| --- | --- |
| L'adresse d'enveloppe est celle du compte d'hébergement | SPF est évalué sur une machine que votre domaine n'autorise pas → échec |
| Rien n'est signé | DKIM absent → aucune preuve d'origine |
| Les rebonds ne reviennent nulle part | Une adresse morte est sollicitée indéfiniment sans qu'on l'apprenne |

Gmail et Outlook exigent depuis 2024 SPF **et** DKIM alignés pour tout
expéditeur régulier. Sans eux, le message est classé en indésirables au
mieux, refusé au pire — et un lien de réinitialisation qui n'arrive pas, ce
sont des comptes définitivement perdus.

---

## 2. Ce que le code fait désormais

Corrigé dans `includes/mail.php` :

- **Sujet encodé selon la RFC 2047.** « Réinitialisation » partait en UTF-8
  brut dans un en-tête qui ne transporte que de l'ASCII : affichage cassé
  chez plusieurs clients, et signal négatif pour les filtres.
- **Jeu d'en-têtes complet** — `Date`, `From`, `Reply-To`, `Return-Path`,
  `Message-ID`, `MIME-Version`, `Content-Type`,
  `Content-Transfer-Encoding`, `Auto-Submitted`. L'ancienne version en posait
  deux. L'absence de `Message-ID` ou de `Date` est relevée par à peu près
  tous les filtres.
- **Corps en quoted-printable**, fins de ligne normalisées en CRLF.
- **Adresse d'enveloppe explicite** (`-f`), pour que SPF et les rebonds
  s'alignent sur le domaine annoncé et non sur le compte d'hébergement.
- **Expéditeur construit sur le domaine servi** par défaut, au lieu d'un
  `noreply@linkee.app` écrit en dur qui mentait dès que le site tournait
  ailleurs.
- **Transport SMTP authentifié** en option (section 3).
- **Échecs journalisés.** `mail()` renvoyait `false` en silence.

Ces points sont vérifiés par
[`tests/Unit/MailTest.php`](../tests/Unit/MailTest.php).

**En local, rien ne part.** Le message est écrit dans le journal d'erreurs, et
le lien de réinitialisation est affiché directement par `auth/forgot.php` :
le parcours se teste sans serveur de mail.

---

## 3. Configurer l'envoi SMTP

C'est la vraie réponse en production : le message part par un serveur autorisé
à parler pour le domaine, qui le signe en DKIM.

Par variables d'environnement (Railway, Docker) :

```bash
MAIL_TRANSPORT=smtp
MAIL_FROM=noreply@votre-domaine.fr
MAIL_FROM_NOM=Linkee
MAIL_RETURN_PATH=rebonds@votre-domaine.fr
MAIL_SMTP_HOTE=smtp.votre-fournisseur.fr
MAIL_SMTP_PORT=587
MAIL_SMTP_CHIFFREMENT=tls          # tls = STARTTLS (587), ssl = TLS direct (465)
MAIL_SMTP_UTILISATEUR=...
MAIL_SMTP_MOTDEPASSE=...
```

Sur un mutualisé sans variables d'environnement, les mêmes réglages vont dans
`includes/config.local.php` (non versionné) — voir
[`config.local.example.php`](../includes/config.local.example.php).

> Le certificat du serveur SMTP **est vérifié**. Désactiver ce contrôle
> rendrait le chiffrement décoratif : un intermédiaire pourrait se présenter à
> la place du serveur et lire le mot de passe SMTP au passage. Si la connexion
> échoue sur le certificat, le problème est chez le fournisseur, pas dans ce
> réglage.

---

## 4. Les enregistrements DNS

Aucune ligne de code ne peut les poser à votre place. À publier chez le
registrar du domaine, une fois le fournisseur d'envoi choisi.

### SPF — quelles machines ont le droit d'envoyer pour ce domaine

Un seul enregistrement `TXT` à la racine. **Un seul** : deux enregistrements
SPF sur un domaine invalident les deux.

```
votre-domaine.fr.  TXT  "v=spf1 include:_spf.votre-fournisseur.fr -all"
```

`-all` refuse tout le reste. `~all` (échec doux) est un palier acceptable le
temps de vérifier qu'aucun envoi légitime n'est oublié, mais il ne protège
pas vraiment : passer à `-all` une fois sûr.

### DKIM — la signature

La clé est fournie par le service d'envoi ; il donne le sélecteur et la
valeur à publier.

```
<selecteur>._domainkey.votre-domaine.fr.  TXT  "v=DKIM1; k=rsa; p=MIGfMA0..."
```

### DMARC — que faire quand SPF ou DKIM échoue

```
_dmarc.votre-domaine.fr.  TXT  "v=DMARC1; p=none; rua=mailto:dmarc@votre-domaine.fr"
```

Commencer par `p=none` : rien n'est rejeté, mais les rapports arrivent. Une
fois les rapports propres pendant deux à trois semaines, passer à
`p=quarantine`, puis `p=reject`. Passer directement à `p=reject` fait
disparaître les messages légitimes qu'on avait oublié de déclarer, sans
prévenir.

---

## 5. Vérifier

1. **Avant d'envoyer** : [mxtoolbox.com/SuperTool.aspx](https://mxtoolbox.com/SuperTool.aspx)
   pour relire SPF, DKIM et DMARC tels que le monde les voit.
2. **Un vrai message** : envoyer à l'adresse fournie par
   [mail-tester.com](https://www.mail-tester.com). Il note le message et
   détaille chaque point manquant. Viser 9/10 ou plus.
3. **Dans l'e-mail reçu** : ouvrir la source et vérifier que les trois lignes
   `spf=pass`, `dkim=pass` et `dmarc=pass` apparaissent dans
   `Authentication-Results`.

---

## 6. Ce qui reste à faire avant une mise en service réelle

- [ ] Choisir un fournisseur d'envoi transactionnel
- [ ] Publier SPF, DKIM et DMARC
- [ ] Renseigner les variables `MAIL_*`
- [ ] Vérifier avec mail-tester
- [ ] Relever la boîte des rebonds (`MAIL_RETURN_PATH`) : une adresse de
      rebond que personne ne lit ne sert à rien
- [ ] Passer DMARC de `p=none` à `p=quarantine` puis `p=reject`
