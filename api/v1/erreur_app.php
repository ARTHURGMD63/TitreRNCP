<?php
/**
 * POST /api/v1/erreur_app.php — l'application signale qu'elle a planté.
 *
 * Sans cela, un plantage sur le téléphone d'un étudiant ne laisse aucune trace
 * côté serveur : on l'apprend par un avis à une étoile. L'application envoie
 * ici, quand une erreur JavaScript n'est pas rattrapée, le message, le début
 * de la pile d'appels, la plateforme et la version. Le tout part dans le
 * journal d'erreurs du serveur (includes/log.php), à côté des erreurs PHP.
 *
 * Volontairement anonyme : aucun jeton, aucun identifiant de compte, aucune
 * donnée personnelle n'est demandée ni conservée. Les textes sont tronqués,
 * et le nombre de signalements par adresse IP est borné : ce point d'entrée
 * est ouvert, il ne doit pas servir à remplir le disque du serveur.
 *
 * Réponse : toujours { success: true } (l'application n'a rien à en faire).
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/security.php';

apiExigerMethode('POST');

$ip = ipClient();
// Vingt signalements par quart d'heure et par adresse : largement assez pour
// un vrai plantage, pas pour un script qui s'amuse.
if (isRateLimited($pdo, $ip, 20, 900, 'erreur-app')) {
    apiReponse(['success' => true]);
}
recordLoginAttempt($pdo, $ip, 'erreur-app');

$corps = apiCorps();
$texte = static fn (string $cle, int $max): string => mb_substr(
    preg_replace('/[\r\n]+/', ' / ', trim(is_scalar($corps[$cle] ?? null) ? (string) $corps[$cle] : '')) ?? '',
    0,
    $max
);

logErreur('[app mobile] ' . $texte('message', 300), null, [
    'pile'       => $texte('pile', 1500),
    'fatale'     => !empty($corps['fatale']),
    'plateforme' => $texte('plateforme', 20),
    'version'    => $texte('version', 20),
]);

apiReponse(['success' => true]);
