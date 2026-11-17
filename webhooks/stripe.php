<?php
/**
 * Point d'arrivée des webhooks Stripe.
 *
 * Stripe appelle cette adresse à chaque paiement, renouvellement, impayé ou
 * résiliation (voir includes/stripe.php pour le parcours complet). À déclarer
 * dans le tableau de bord Stripe → Développeurs → Webhooks :
 *
 *     https://<domaine>/webhooks/stripe.php
 *
 * avec les événements checkout.session.completed,
 * checkout.session.async_payment_succeeded, customer.subscription.created,
 * customer.subscription.updated, customer.subscription.deleted, invoice.paid
 * et invoice.payment_failed. Le secret de signature (whsec_…) va dans
 * STRIPE_WEBHOOK_SECRET.
 *
 * Ni session ni jeton CSRF ici : l'appelant n'est pas un navigateur. C'est la
 * signature qui prouve que la requête vient de Stripe ; sans elle, 400.
 * Réponses : 200 traité (ou déjà traité), 400 signature refusée, 500 échec
 * de traitement — Stripe relance alors pendant trois jours.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/log.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo '{"erreur":"methode"}';
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/stripe.php';

$corps  = (string) file_get_contents('php://input');
$entete = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

try {
    $evenement = stripeVerifierWebhook($corps, $entete, trim(reglage('STRIPE_WEBHOOK_SECRET', 'stripe_webhook')));
} catch (StripeErreur $e) {
    http_response_code(400);
    echo '{"erreur":"signature"}';
    exit;
}

try {
    $nouveau = stripeTraiterEvenement($pdo, $evenement);
    echo json_encode(['recu' => true, 'nouveau' => $nouveau]);
} catch (Throwable $e) {
    logErreur('Stripe : webhook non traité', $e, ['evenement' => $evenement['id'], 'type' => $evenement['type']]);
    http_response_code(500);
    echo '{"erreur":"traitement"}';
}
