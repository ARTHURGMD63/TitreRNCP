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

    // ─── Liens des stores (vitrine et page « c'est dans l'appli ») ──────
    'app_store_url'  => '',
    'play_store_url' => '',
];
