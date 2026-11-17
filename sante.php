<?php
/**
 * GET /sante.php — la sonde de disponibilité.
 *
 * Répond 200 quand le serveur et la base fonctionnent, 503 sinon. À donner à
 * un service de surveillance (UptimeRobot, Better Stack, Uptime Kuma…) qui
 * prévient par e-mail ou SMS à la première panne ; cron/sante.php l'appelle
 * aussi toutes les cinq minutes.
 *
 * Aucun détail technique dans la réponse : seulement ce qui va ou ne va pas,
 * jamais pourquoi. Pas de session, pas de cookie, pas de cache.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/sante.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$etat = [
    'base'   => santeBase($pdo),
    'disque' => santeDisque(),
];
$ok = !in_array(false, $etat, true);

http_response_code($ok ? 200 : 503);
echo json_encode(['ok' => $ok] + $etat);
