<?php
/**
 * Modèle de configuration pour un hébergement sans variables
 * d'environnement — mutualisés type InfinityFree, o2switch, OVH.
 *
 * Copier ce fichier en « config.local.php » dans le même dossier, puis
 * reporter les identifiants affichés par le panneau de l'hébergeur.
 *
 * config.local.php n'est pas versionné : le mot de passe de la base ne
 * part jamais sur GitHub. C'est la seule raison d'être de ce modèle.
 *
 * Ordre de priorité appliqué par db.php :
 *   1. variables d'environnement (Railway, Docker) ;
 *   2. ce fichier ;
 *   3. valeurs WAMP par défaut.
 */

return [
    // InfinityFree donne un hôte de la forme « sqlXXX.infinityfree.com ».
    // Ce n'est jamais « localhost » : la base est sur une autre machine.
    'host' => 'sqlXXX.infinityfree.com',

    // Nom imposé par l'hébergeur, préfixé par l'identifiant du compte.
    'name' => 'if0_00000000_linkee',

    'user' => 'if0_00000000',
    'pass' => 'a-remplacer',
    'port' => '3306',

    // Connexions persistantes a MySQL : evite une poignee de main par
    // requete, ce qui compte quand la base est sur une autre machine.
    //
    // A n'activer que si l'hebergeur autorise assez de connexions
    // simultanees. Sur une offre limitee a 30, laisser false : une
    // connexion reste alors ouverte par processus PHP, et le quota
    // s'epuise avant que le trafic n'augmente vraiment.
    'persistant' => false,

    // ─── Envoi des e-mails ──────────────────────────────────────────────
    //
    // Deux messages en dependent : la reinitialisation de mot de passe et
    // les rappels de veille de soiree. Sans ces reglages, l'application
    // utilise mail(), qui expedie sous l'identite du serveur mutualise :
    // le SPF du domaine ne couvre pas cette machine, rien n'est signe en
    // DKIM, et Gmail classe en indesirables. Un lien de reinitialisation
    // qui n'arrive pas, ce sont des comptes perdus.
    //
    // Lire docs/EMAIL.md avant de remplir : le code ne peut pas publier
    // les enregistrements DNS a votre place, et sans eux le reste ne sert
    // a rien.

    // Adresse annoncee dans « De : ». Doit appartenir au domaine sur lequel
    // SPF et DKIM sont publies, sinon DMARC echoue malgre tout.
    'mail_from'     => 'noreply@votre-domaine.fr',
    'mail_from_nom' => 'Linkee',

    // Adresse qui recoit les rebonds. Une boite que personne ne releve ne
    // sert a rien : c'est la qu'on apprend qu'une adresse est morte.
    'mail_return_path' => 'rebonds@votre-domaine.fr',

    // 'mail' = la fonction PHP (defaut, deconseille en production).
    // 'smtp' = remise a un serveur authentifie, qui signe en DKIM.
    'mail_transport' => 'mail',

    // Renseigner uniquement si mail_transport vaut 'smtp'.
    'mail_smtp_hote'        => 'smtp.votre-fournisseur.fr',
    'mail_smtp_port'        => '587',
    // 'tls' = STARTTLS sur 587 ; 'ssl' = TLS direct sur 465.
    'mail_smtp_chiffrement' => 'tls',
    'mail_smtp_utilisateur' => '',
    'mail_smtp_motdepasse'  => '',
];
