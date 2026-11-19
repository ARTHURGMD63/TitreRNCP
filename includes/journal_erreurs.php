<?php
/**
 * Journal des erreurs lisible depuis l'application admin.
 *
 * logErreur() (includes/log.php) écrit toujours dans le journal de PHP, que
 * seul l'hébergeur montre. Les administrateurs veulent voir, depuis leur
 * téléphone, ce qui casse et à quelle fréquence : chaque erreur est donc
 * aussi rangée en base, dans journal_erreurs (migration v42).
 *
 * Trois règles, parce que ce code tourne au moment où quelque chose va mal :
 *  - il ne lève jamais, et ne s'appelle jamais lui-même (une erreur pendant
 *    l'enregistrement d'une erreur ne doit pas boucler) ;
 *  - une erreur répétée tient sur une ligne : même empreinte, compteur + 1.
 *    Les nombres variables (identifiants, durées) sont neutralisés dans
 *    l'empreinte, sinon chaque occurrence ferait une ligne ;
 *  - aucune donnée personnelle : les adresses e-mail sont masquées, aucun
 *    identifiant de compte n'est gardé, et les textes sont tronqués.
 * Au plus JOURNAL_MAX_PAR_REQUETE écritures par requête : une boucle qui
 * échoue ne transforme pas une page en mille écritures.
 */

require_once __DIR__ . '/log.php';

const JOURNAL_MAX_PAR_REQUETE = 5;

/** Les adresses e-mail d'un texte, rendues méconnaissables. */
function journalMasquer(string $texte): string
{
    return (string) preg_replace_callback(
        '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/',
        static fn (array $m): string => masquerEmail($m[0]),
        $texte
    );
}

/**
 * L'empreinte d'une erreur : sa source, son message sans les nombres
 * variables, et l'endroit du code (fichier:ligne) s'il est connu.
 */
function journalEmpreinte(string $source, string $message, string $lieu = ''): string
{
    $normal = preg_replace('/\d+/', '#', mb_strtolower($message)) ?? $message;
    $normal = preg_replace('/\s+/', ' ', $normal) ?? $normal;

    return sha1($source . '|' . trim($normal) . '|' . $lieu);
}

/**
 * Range une erreur dans le journal (ou incrémente son compteur). Renvoie
 * true si c'est la première fois qu'on la voit, ce qui sert à prévenir les
 * administrateurs d'une erreur nouvelle sans les inonder pour une ancienne.
 *
 * @param array<string, mixed> $contexte données de diagnostic, sans secret
 */
function journalEnregistrer(PDO $pdo, string $source, string $message, string $detail = '', array $contexte = [], string $lieu = ''): bool
{
    static $enCours = false;
    static $ecritures = 0;
    if ($enCours || $ecritures >= JOURNAL_MAX_PAR_REQUETE || !in_array($source, ['serveur', 'app'], true)) {
        return false;
    }
    $enCours = true;
    $ecritures++;
    try {
        // Jamais d'identifiant de compte : le journal est lu par les
        // administrateurs, il n'a pas à dire qui a rencontré l'erreur.
        unset($contexte['user_id'], $contexte['email']);
        // Une adresse peut porter un jeton (réinitialisation de mot de passe) :
        // on ne garde que le chemin.
        if (isset($contexte['uri']) && is_string($contexte['uri'])) {
            $contexte['uri'] = strtok($contexte['uri'], '?') ?: $contexte['uri'];
        }
        $message  = mb_substr(journalMasquer(trim($message)), 0, 500);
        $detail   = mb_substr(journalMasquer($detail), 0, 4000);
        $ctx      = $contexte ? mb_substr(journalMasquer((string) json_encode($contexte, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 2000) : null;
        $empreinte = journalEmpreinte($source, $message, $lieu);
        $maintenant = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'INSERT INTO journal_erreurs (empreinte, source, message, detail, contexte, occurrences, premiere_le, derniere_le)
             VALUES (?, ?, ?, ?, ?, 1, ?, ?)
             ON DUPLICATE KEY UPDATE occurrences = occurrences + 1, derniere_le = VALUES(derniere_le),
                                     detail = VALUES(detail), contexte = VALUES(contexte), resolu_le = NULL'
        );
        $stmt->execute([$empreinte, $source, $message !== '' ? $message : '(sans message)', $detail !== '' ? $detail : null, $ctx, $maintenant, $maintenant]);

        // 1 : ligne créée ; 2 : ligne existante mise à jour (MySQL, MariaDB, TiDB).
        return $stmt->rowCount() === 1;
    } catch (Throwable) {
        // Table absente (migration v42 pas encore posée), base injoignable :
        // le journal de PHP a déjà l'erreur, on n'ajoute rien.
        return false;
    } finally {
        $enCours = false;
    }
}

/**
 * Prévient les administrateurs d'une erreur jamais vue, en notification
 * « compte ». Au plus 5 alertes par heure : un incident qui fait apparaître
 * vingt erreurs différentes ne doit pas envoyer vingt messages.
 */
function journalAlerterAdmins(PDO $pdo, string $source, string $message): void
{
    try {
        $recentes = (int) $pdo->query(
            "SELECT COUNT(*) FROM journal_erreurs WHERE premiere_le >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        )->fetchColumn();
        if ($recentes > 5) {
            return;
        }
        require_once __DIR__ . '/push.php';
        $admins = $pdo->query("SELECT id FROM users WHERE type = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
        $titre  = $source === 'app' ? 'Nouveau plantage de l’application' : 'Nouvelle erreur serveur';
        $corps  = mb_substr(journalMasquer($message), 0, 180);
        foreach ($admins as $id) {
            pushNotifier($pdo, (int) $id, 'compte', 'erreur:' . sha1($source . $message), $titre, $corps, ['url' => '/erreurs']);
        }
    } catch (Throwable) {
        // Une alerte qui ne part pas ne doit rien casser.
    }
}

/**
 * Point d'entrée de logErreur() : retrouve la connexion de la requête, s'il
 * y en a une, range l'erreur et prévient si elle est nouvelle.
 *
 * @param array<string, mixed> $contexte
 */
function journalDepuisLog(string $message, ?Throwable $e, array $contexte): void
{
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!$pdo instanceof PDO) {
        return;
    }
    $lieu   = $e !== null ? basename($e->getFile()) . ':' . $e->getLine() : '';
    $texte  = $message . ($e !== null ? ' — ' . get_class($e) . ' : ' . $e->getMessage() : '');
    $detail = $e !== null ? $e->getTraceAsString() : '';
    if ($lieu !== '') {
        $contexte['lieu'] = $lieu;
    }
    if (journalEnregistrer($pdo, 'serveur', $texte, $detail, $contexte, $lieu)) {
        journalAlerterAdmins($pdo, 'serveur', $texte);
    }
}
