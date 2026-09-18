<?php
/**
 * Journalisation des erreurs.
 *
 * Sans cela, une erreur en production disparaissait sans laisser de trace :
 * on apprenait les pannes par les utilisateurs. Tout part dans le journal
 * d'erreurs de PHP (error_log), donc vers la destination déjà configurée par
 * l'hébergeur — aucune dépendance supplémentaire.
 *
 * Rien de ce qui est journalisé ne doit remonter au navigateur : les messages
 * destinés à l'utilisateur restent neutres.
 */

/**
 * Consigne une erreur avec son contexte.
 *
 * @param string          $message   Ce qu'on tentait de faire, en clair.
 * @param Throwable|null  $e         L'exception, si elle existe.
 * @param array<string,mixed> $contexte Données utiles au diagnostic (identifiants, pas de secrets).
 */
function logErreur(string $message, ?Throwable $e = null, array $contexte = []): void
{
    $parties = ['[StudentLink] ' . $message];

    if ($e !== null) {
        $parties[] = sprintf(
            '%s: %s (%s:%d)',
            get_class($e),
            $e->getMessage(),
            basename($e->getFile()),
            $e->getLine()
        );
    }

    // L'identifiant de session aide à relier plusieurs erreurs d'un même
    // parcours ; on ne journalise jamais le contenu de la session.
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
        $contexte['user_id'] = $_SESSION['user_id'];
    }
    if (!empty($_SERVER['REQUEST_URI'])) {
        $contexte['uri'] = $_SERVER['REQUEST_URI'];
    }

    if ($contexte) {
        $parties[] = json_encode($contexte, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    error_log(implode(' | ', $parties));
}

/**
 * Installe les gardes globaux : plus rien ne se perd, et rien ne fuit.
 *
 * En production les messages techniques ne sont jamais affichés ; en
 * développement ils le restent, pour ne pas travailler à l'aveugle.
 */
function installerGardesErreurs(): void
{
    $prod = in_array(strtolower((string) getenv('APP_ENV')), ['production', 'prod'], true);

    ini_set('log_errors', '1');
    ini_set('display_errors', $prod ? '0' : '1');

    set_exception_handler(static function (Throwable $e) use ($prod): void {
        logErreur('Exception non interceptée', $e);
        if (headers_sent()) {
            return;
        }
        http_response_code(500);
        // Une requête d'API attend du JSON, une page attend du HTML.
        if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
        } else {
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!doctype html><meta charset="utf-8"><title>Erreur</title>'
               . '<p style="font-family:system-ui;padding:40px">Une erreur est survenue. '
               . 'Réessaie dans un instant.</p>';
            if (!$prod) {
                echo '<pre style="font-family:ui-monospace;padding:0 40px;color:#B3261E">'
                   . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString())
                   . '</pre>';
            }
        }
    });

    register_shutdown_function(static function (): void {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            logErreur('Erreur fatale', null, [
                'message' => $err['message'],
                'fichier' => basename($err['file']),
                'ligne'   => $err['line'],
            ]);
        }
    });
}
