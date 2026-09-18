<?php
/**
 * Envoi d'e-mails.
 *
 * Point unique : jusqu'ici seule la réinitialisation de mot de passe envoyait
 * du courrier, avec sa propre logique. Les rappels s'ajoutant, la règle
 * « en local on n'envoie rien » doit être décidée à un seul endroit.
 *
 * Avertissement de production : mail() passe par le sendmail de la machine,
 * dont la délivrabilité est mauvaise — les messages finissent souvent en
 * indésirables. Pour un usage réel il faut un service d'envoi (SMTP
 * authentifié, avec SPF et DKIM sur le domaine). La fonction est isolée ici
 * précisément pour que ce remplacement ne touche qu'un fichier.
 */

require_once __DIR__ . '/log.php';

/** Vrai lorsque l'application tourne sur un poste de développement. */
function estEnLocal(): bool
{
    if (PHP_SAPI === 'cli') {
        return !in_array(strtolower((string) getenv('APP_ENV')), ['production', 'prod'], true);
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');
}

/**
 * Envoie un message en texte brut.
 *
 * En local rien ne part : le message est écrit dans le journal, ce qui permet
 * de vérifier son contenu sans configurer de serveur de mail.
 */
function envoyerEmail(string $destinataire, string $sujet, string $corps): bool
{
    if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
        logErreur('Adresse destinataire invalide', null, ['email' => $destinataire]);

        return false;
    }

    // Un saut de ligne dans le sujet permettrait d'injecter des en-têtes.
    $sujet = str_replace(["\r", "\n"], ' ', $sujet);

    $entetes = implode("\r\n", [
        'From: StudentLink <noreply@studentlink.app>',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ]);

    if (estEnLocal()) {
        logErreur('[mail simulé] ' . $sujet, null, [
            'a'     => $destinataire,
            'corps' => mb_substr(str_replace("\n", ' / ', $corps), 0, 220),
        ]);
        if (PHP_SAPI === 'cli') {
            printf("  [local, non envoyé] %-28s %s\n", $destinataire, $sujet);
        }

        return true;
    }

    return mail($destinataire, $sujet, $corps, $entetes);
}
