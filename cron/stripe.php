<?php
/**
 * Envoie à Stripe les suppléments payants du mois (événements au-delà du
 * quota, mises en avant) comme lignes de la prochaine facture de chaque
 * établissement abonné.
 *
 * À lancer toutes les cinq minutes, en ligne de commande :
 *     php cron/stripe.php
 *
 * Un supplément consommé juste avant le renouvellement peut partir sur la
 * facture du mois suivant : il n'est jamais perdu ni facturé deux fois (clé
 * d'idempotence `achat-<id>`, colonne achats_partenaires.stripe_ligne).
 * Sans clé Stripe configurée, le script ne fait rien.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/stripe.php';

if (!stripeActif()) {
    echo "Stripe non configuré : rien à envoyer.\n";
    exit(0);
}

$envoyes = stripeSynchroniserAchats($pdo);
printf("%s — %d supplément(s) envoyé(s) à Stripe\n", date('d/m/Y H:i'), $envoyes);

// Les événements de webhook de plus de 90 jours ne servent plus à dédoublonner
// (Stripe ne relance que trois jours).
$pdo->exec('DELETE FROM stripe_evenements WHERE recu_le < NOW() - INTERVAL 90 DAY');
