<?php
/**
 * Réglages de production sur le VPS — modèle.
 *
 * deploy/installer_serveur.sh le copie dans
 * /var/www/outsee/shared/config.local.php (mode 640, lisible par PHP
 * seulement) en remplissant le domaine et le mot de passe de la base. Le
 * déploiement relie ce fichier à includes/config.local.php de chaque
 * version. Il n'est jamais versionné ni envoyé par GitHub.
 *
 * Clés lues par includes/config.php (reglage()) : une variable
 * d'environnement du même nom passerait devant.
 */

return [
    // Production : aucune trace d'erreur affichée, aucun code de test renvoyé.
    'app_env'      => 'production',
    // Adresse publique, sans barre finale : liens des e-mails, retours Stripe.
    'url_publique' => 'https://__DOMAINE__',

    // ─── Adresse IP du visiteur (limiteurs de connexion) ────────────────
    // En-tête cru pour l'adresse du visiteur : 'aucun' (seule REMOTE_ADDR),
    // 'cloudflare' (CF-Connecting-IP) ou 'x-forwarded-for'. Un en-tête n'est
    // lu que si la connexion vient d'un proxy listé dans proxy_confiance
    // (plages CIDR, et les mots-clés « prive » et « cloudflare »).
    // Sur le VPS, nginx reçoit le visiteur directement : 'aucun'. Si
    // Cloudflare est placé devant, préférer real_ip dans deploy/nginx
    // (outsee.conf) et laisser 'aucun' ici.
    // Sur Render (variables d'environnement) : IP_ENTETE=x-forwarded-for et
    // PROXY_CONFIANCE=prive,cloudflare.
    'ip_entete'       => 'aucun',
    'proxy_confiance' => '',

    // ─── Base MariaDB locale (socket) ───────────────────────────────────
    'host' => 'localhost',
    'port' => '3306',
    'name' => 'outsee',
    'user' => 'outsee',
    'pass' => '__MOT_DE_PASSE_BASE__',
    'persistant' => false,

    // ─── Stripe (tableau de bord Stripe → Développeurs) ─────────────────
    // Clé secrète : sk_live_… en production (sk_test_… pour essayer).
    'stripe_secret'   => '',
    // Secret de signature du webhook https://__DOMAINE__/webhooks/stripe.php
    'stripe_webhook'  => '',
    // Taux de TVA 20 % créé dans Stripe (Produits → Taux de taxe) : txr_…
    'stripe_taux_tva' => '',

    // ─── SMS de vérification du numéro (OVHcloud → Télécom → SMS) ───────
    // Clés créées sur https://eu.api.ovh.com/createToken/ avec le seul droit
    // POST /sms/*. Vides : le numéro est enregistré sans code SMS.
    'ovh_sms_app_key'      => '',
    'ovh_sms_app_secret'   => '',
    'ovh_sms_consumer_key' => '',
    'ovh_sms_service'      => '',        // « sms-ab12345-1 »
    'ovh_sms_expediteur'   => 'Outsee',  // expéditeur à faire valider par OVH, sinon ''

    // ─── E-mails (voir docs/EMAIL.md : SPF, DKIM, DMARC) ────────────────
    'mail_from'             => 'noreply@__DOMAINE__',
    'mail_from_nom'         => 'Outsee',
    'mail_return_path'      => 'rebonds@__DOMAINE__',
    'mail_transport'        => 'smtp',
    'mail_smtp_hote'        => 'ssl0.ovh.net',
    'mail_smtp_port'        => '465',
    'mail_smtp_chiffrement' => 'ssl',
    'mail_smtp_utilisateur' => 'noreply@__DOMAINE__',
    'mail_smtp_motdepasse'  => '',

    // ─── Surveillance (cron/sante.php) ──────────────────────────────────
    // Destinataire des alertes de panne et de sauvegarde manquée : une boîte
    // lue chaque jour (vide ou absente : contact@outsee.fr).
    'alerte_email' => 'contact@__DOMAINE__',

    // ─── Liens des stores (vitrine et page « c'est dans l'appli ») ──────
    'app_store_url'  => '',
    'play_store_url' => '',
];
