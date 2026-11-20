<?php
/**
 * Notifications push : appareils, préférences, file d'envoi, envoi par Expo.
 *
 * Tout message passe par la file `push_envois` (migration v35) :
 *
 *   - pushNotifier() / pushMettreEnFile() l'y écrivent. La clé (« rappel:42 »)
 *     est unique par personne : le même événement ne produit jamais deux
 *     messages, même si le code qui l'écrit est rejoué ;
 *   - cron/push.php appelle pushTraiter() chaque minute, qui applique les
 *     préférences, les plafonds du jour et la nuit, puis envoie à Expo.
 *
 * Une écriture dans la file n'échoue jamais bruyamment : une notification
 * ratée ne doit pas faire échouer l'action qui l'a provoquée (suivre
 * quelqu'un, valider un BDE).
 *
 * Les heures de la file sont en UTC (colonne envoyer_apres) : elles ne
 * dépendent ni du fuseau du serveur ni de celui de la base.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/log.php';

/** Heure (Paris) à partir de laquelle on n'envoie plus rien. */
const PUSH_NUIT_DEBUT = 22;
/** Heure (Paris) à laquelle les messages de la nuit partent. */
const PUSH_NUIT_FIN = 8;
/** Au-delà, un message en échec réseau est abandonné. */
const PUSH_TENTATIVES_MAX = 5;
/** Expo accepte au plus 100 messages par requête. */
const PUSH_LOT_EXPO = 100;
const PUSH_URL_EXPO = 'https://exp.host/--/api/v2/push/send';

/**
 * Les catégories connues. `modifiable` : la personne peut la couper.
 * `plafond` : messages envoyés au plus par jour et par personne.
 *
 * @return array<string, array{libelle:string, detail:string, modifiable:bool, plafond:int}>
 */
function pushCategories(): array
{
    return [
        'social' => [
            'libelle'    => 'Abonnements et invitations',
            'detail'     => 'Demandes d’abonnement, demandes acceptées, invitations.',
            'modifiable' => true,
            'plafond'    => 10,
        ],
        'rappels' => [
            'libelle'    => 'Rappels de soirée',
            'detail'     => 'La veille d’un événement auquel tu es inscrit.',
            'modifiable' => true,
            'plafond'    => 5,
        ],
        'lieux' => [
            'libelle'    => 'Offres des lieux suivis',
            'detail'     => 'Les nouvelles soirées et les annonces des établissements que tu suis.',
            'modifiable' => true,
            'plafond'    => 2,
        ],
        'compte' => [
            'libelle'    => 'Mon compte',
            'detail'     => 'Les décisions qui concernent ton compte.',
            'modifiable' => false,
            'plafond'    => 10,
        ],
    ];
}

/**
 * Les catégories qu'un type de compte peut régler. Un établissement ne
 * reçoit que des messages « compte », qui ne se coupent pas.
 *
 * @return list<string>
 */
function pushCategoriesPour(string $type): array
{
    return $type === 'etudiant' ? ['social', 'rappels', 'lieux'] : [];
}

/**
 * Les choix de la personne. Absence de ligne = catégorie active.
 *
 * @return array<string, bool>
 */
function pushPreferences(PDO $pdo, int $uid): array
{
    $stmt = $pdo->prepare('SELECT categorie, actif FROM preferences_push WHERE user_id = ?');
    $stmt->execute([$uid]);
    $prefs = [];
    foreach ($stmt->fetchAll() as $l) {
        $prefs[(string) $l['categorie']] = (bool) $l['actif'];
    }

    return $prefs;
}

/** Coupe ou rétablit une catégorie. Faux si elle n'existe pas ou ne se règle pas. */
function pushDefinirPreference(PDO $pdo, int $uid, string $categorie, bool $actif): bool
{
    $defs = pushCategories();
    if (!isset($defs[$categorie]) || !$defs[$categorie]['modifiable']) {
        return false;
    }
    $pdo->prepare(
        'INSERT INTO preferences_push (user_id, categorie, actif) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE actif = VALUES(actif)'
    )->execute([$uid, $categorie, $actif ? 1 : 0]);

    return true;
}

/** Un jeton Expo bien formé : « ExponentPushToken[...] » ou « ExpoPushToken[...] ». */
function pushJetonValide(string $jeton): bool
{
    return strlen($jeton) <= 255 && preg_match('/^Expo(nent)?PushToken\[[A-Za-z0-9_\-]{10,200}\]$/', $jeton) === 1;
}

/**
 * Rattache un téléphone au compte. Un jeton appartient à UN compte : s'il
 * était à quelqu'un d'autre (téléphone prêté, changement de compte), il
 * change de propriétaire, et l'ancien ne reçoit plus rien dessus.
 */
function pushEnregistrerAppareil(PDO $pdo, int $uid, string $jeton, string $plateforme, ?string $appareil = null): bool
{
    $jeton = trim($jeton);
    if (!pushJetonValide($jeton) || !in_array($plateforme, ['ios', 'android'], true)) {
        return false;
    }
    $appareil = $appareil !== null ? mb_substr(trim($appareil), 0, 120) : null;
    $pdo->prepare(
        'INSERT INTO appareils_push (user_id, jeton, plateforme, appareil, actif, derniere_vue)
         VALUES (?, ?, ?, ?, 1, ?)
         ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), plateforme = VALUES(plateforme),
                                 appareil = VALUES(appareil), actif = 1, derniere_vue = VALUES(derniere_vue)'
    )->execute([$uid, $jeton, $plateforme, $appareil === '' ? null : $appareil, gmdate('Y-m-d H:i:s')]);

    return true;
}

/** Déconnexion : le téléphone ne reçoit plus rien pour ce compte. */
function pushRetirerAppareil(PDO $pdo, int $uid, string $jeton): void
{
    $pdo->prepare('UPDATE appareils_push SET actif = 0 WHERE jeton = ? AND user_id = ?')
        ->execute([trim($jeton), $uid]);
}

/** Le prénom affiché dans un message, ou « Quelqu’un ». */
function pushPrenomDe(PDO $pdo, int $uid): string
{
    $stmt = $pdo->prepare('SELECT prenom FROM users WHERE id = ?');
    $stmt->execute([$uid]);
    $prenom = trim((string) $stmt->fetchColumn());

    return $prenom !== '' ? $prenom : 'Quelqu’un';
}

// ── Textes ─────────────────────────────────────────────────────────────────

/** @return array{0:string, 1:string} */
function pushTexteDemandeSuivi(string $prenom): array
{
    return ['Nouvelle demande', $prenom . ' veut te suivre.'];
}

/** @return array{0:string, 1:string} */
function pushTexteDemandeAcceptee(string $prenom): array
{
    return ['Demande acceptée', $prenom . ' a accepté ta demande.'];
}

/** @return array{0:string, 1:string} */
function pushTexteBdeValide(): array
{
    return ['Association validée', 'Ton association est validée : tu peux publier tes événements.'];
}

/** @return array{0:string, 1:string} */
function pushTexteBdeRefuse(): array
{
    return ['Association non validée', 'Ta demande n’a pas été validée. Ouvre l’application pour en savoir plus.'];
}

/** @return array{0:string, 1:string} */
function pushTexteLieu(string $lieu, string $titreEvenement): array
{
    return [$lieu, $titreEvenement];
}

/**
 * Un lieu suivi vient de publier une soirée.
 *
 * @return array{0:string, 1:string}
 */
function pushTexteNouvelEvenement(string $lieu, string $titreEvenement): array
{
    return [$lieu, 'Nouvelle soirée : ' . $titreEvenement];
}

/** @return array{0:string, 1:string} */
function pushTexteEvenementAnnule(string $titreEvenement): array
{
    return ['Soirée annulée', $titreEvenement . ' n’aura pas lieu : l’établissement a fermé son compte. Ton pass est retiré.'];
}

/**
 * Événement annulé par l'établissement ou par l'équipe Outsee, avec le
 * motif s'il a été donné.
 *
 * @return array{0:string, 1:string}
 */
function pushTexteEvenementAnnuleParOrganisateur(string $titreEvenement, ?string $motif = null): array
{
    $corps = $titreEvenement . ' n’aura pas lieu. Ton pass est annulé.';
    if ($motif !== null && trim($motif) !== '') {
        $corps .= ' Motif : ' . trim($motif);
    }

    return ['Événement annulé', $corps];
}

/** @return array{0:string, 1:string} */
function pushTexteRappel(string $titreEvenement, string $heure, string $lieu): array
{
    return ['C’est demain', $titreEvenement . ' à ' . $heure . ', ' . $lieu . '.'];
}

// ── File ───────────────────────────────────────────────────────────────────

/**
 * L'heure d'envoi effective (UTC) : celle voulue, repoussée au matin si elle
 * tombe la nuit à Paris.
 */
function pushHeureEnvoi(?DateTimeImmutable $voulu = null): DateTimeImmutable
{
    $paris = new DateTimeZone('Europe/Paris');
    $t     = ($voulu ?? new DateTimeImmutable('now', $paris))->setTimezone($paris);
    $h     = (int) $t->format('G');
    if ($h >= PUSH_NUIT_DEBUT) {
        $t = $t->modify('+1 day')->setTime(PUSH_NUIT_FIN, 0);
    } elseif ($h < PUSH_NUIT_FIN) {
        $t = $t->setTime(PUSH_NUIT_FIN, 0);
    }

    return $t->setTimezone(new DateTimeZone('UTC'));
}

/**
 * Écrit un message dans la file. Renvoie son id, ou null si la même clé
 * existe déjà pour cette personne (message déjà prévu ou parti).
 *
 * @param array<string, mixed> $donnees
 */
function pushMettreEnFile(
    PDO $pdo,
    int $uid,
    string $categorie,
    string $cle,
    string $titre,
    string $corps,
    array $donnees = [],
    ?DateTimeImmutable $voulu = null
): ?int {
    if (!isset(pushCategories()[$categorie]) || $uid <= 0) {
        return null;
    }
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO push_envois (user_id, categorie, cle, titre, corps, donnees, envoyer_apres)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $uid,
        $categorie,
        mb_substr($cle, 0, 120),
        mb_substr($titre, 0, 120),
        mb_substr($corps, 0, 255),
        $donnees ? json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        pushHeureEnvoi($voulu)->format('Y-m-d H:i:s'),
    ]);

    return $stmt->rowCount() === 1 ? (int) $pdo->lastInsertId() : null;
}

/**
 * Comme pushMettreEnFile(), sans jamais lever : appelé au milieu d'une action
 * de l'utilisateur, qui doit réussir même si la file est indisponible.
 *
 * @param array<string, mixed> $donnees
 */
function pushNotifier(PDO $pdo, int $uid, string $categorie, string $cle, string $titre, string $corps, array $donnees = []): void
{
    try {
        pushMettreEnFile($pdo, $uid, $categorie, $cle, $titre, $corps, $donnees);
    } catch (Throwable $e) {
        logErreur('Notification push non écrite', $e, ['destinataire' => $uid, 'categorie' => $categorie]);
    }
}

// ── Envoi ──────────────────────────────────────────────────────────────────

/**
 * Envoie un lot à Expo. Renvoie un ticket par message (même ordre), ou null
 * si Expo est injoignable.
 *
 * @param list<array<string, mixed>> $messages
 * @return list<array<string, mixed>>|null
 */
function pushEnvoyerExpo(array $messages): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $entetes = ['Content-Type: application/json', 'Accept: application/json'];
    $acces   = reglage('EXPO_ACCESS_TOKEN', 'expo_access_token');
    if ($acces !== '') {
        $entetes[] = 'Authorization: Bearer ' . $acces;
    }
    $ch = curl_init(PUSH_URL_EXPO);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $entetes,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if (PHP_OS_FAMILY === 'Windows' && (string) ini_get('curl.cainfo') === '' && defined('CURLSSLOPT_NATIVE_CA')) {
        curl_setopt($ch, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
    }
    $reponse = curl_exec($ch);
    $code    = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if (!is_string($reponse) || $code >= 500) {
        return null;
    }
    $json = json_decode($reponse, true);
    if (!is_array($json) || !isset($json['data']) || !is_array($json['data'])) {
        logErreur('Réponse Expo inattendue', null, ['http' => $code]);

        return null;
    }

    return array_values($json['data']);
}

/**
 * Traite ce qui est dû : préférences, plafonds, appareils, puis envoi.
 *
 * Un verrou nommé empêche deux passages simultanés (cron qui déborde) :
 * le second s'arrête aussitôt.
 *
 * @param callable|null $envoyeur remplace pushEnvoyerExpo() (tests)
 * @return array{envoyes:int, ignores:int, reportes:int}
 */
function pushTraiter(PDO $pdo, int $limite = 500, ?callable $envoyeur = null): array
{
    $bilan = ['envoyes' => 0, 'ignores' => 0, 'reportes' => 0];
    $envoyeur ??= 'pushEnvoyerExpo';

    $verrou = $pdo->query("SELECT GET_LOCK('outsee_push', 0)");
    if ($verrou === false || (int) $verrou->fetchColumn() !== 1) {
        return $bilan;
    }

    try {
        $maintenant = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            "SELECT id, user_id, categorie, titre, corps, donnees, tentatives
               FROM push_envois
              WHERE statut = 'en_attente' AND envoyer_apres <= ?
              ORDER BY envoyer_apres, id
              LIMIT " . max(1, min(2000, $limite))
        );
        $stmt->execute([$maintenant]);
        $dus = $stmt->fetchAll();
        if (!$dus) {
            return $bilan;
        }

        $defs    = pushCategories();
        $marquer = $pdo->prepare('UPDATE push_envois SET statut = ?, envoye_le = ? WHERE id = ?');
        $ignorer = static function (int $id) use ($marquer, &$bilan): void {
            $marquer->execute(['ignore', null, $id]);
            $bilan['ignores']++;
        };

        // Compteurs du jour (Paris), par personne et catégorie.
        $debutJour = (new DateTimeImmutable('today', new DateTimeZone('Europe/Paris')))
            ->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $compter = $pdo->prepare(
            "SELECT COUNT(*) FROM push_envois
              WHERE user_id = ? AND categorie = ? AND statut = 'envoye' AND envoye_le >= ?"
        );
        $appareils = $pdo->prepare('SELECT jeton FROM appareils_push WHERE user_id = ? AND actif = 1');

        $prefsCache = [];
        $envoisDuJour = [];
        /** @var list<array{id:int, tentatives:int, message:array<string,mixed>, jeton:string}> $aEnvoyer */
        $aEnvoyer = [];

        foreach ($dus as $l) {
            $id  = (int) $l['id'];
            $uid = (int) $l['user_id'];
            $cat = (string) $l['categorie'];
            if (!isset($defs[$cat])) {
                $ignorer($id);
                continue;
            }
            if ($defs[$cat]['modifiable']) {
                $prefsCache[$uid] ??= pushPreferences($pdo, $uid);
                if (($prefsCache[$uid][$cat] ?? true) === false) {
                    $ignorer($id);
                    continue;
                }
            }
            $cleCompte = $uid . ':' . $cat;
            if (!isset($envoisDuJour[$cleCompte])) {
                $compter->execute([$uid, $cat, $debutJour]);
                $envoisDuJour[$cleCompte] = (int) $compter->fetchColumn();
            }
            if ($envoisDuJour[$cleCompte] >= $defs[$cat]['plafond']) {
                $ignorer($id);
                continue;
            }
            $appareils->execute([$uid]);
            $jetons = $appareils->fetchAll(PDO::FETCH_COLUMN);
            if (!$jetons) {
                $ignorer($id);
                continue;
            }
            $envoisDuJour[$cleCompte]++;
            $donnees = is_string($l['donnees']) ? (json_decode($l['donnees'], true) ?: []) : [];
            foreach ($jetons as $jeton) {
                $aEnvoyer[] = [
                    'id'         => $id,
                    'tentatives' => (int) $l['tentatives'],
                    'jeton'      => (string) $jeton,
                    'message'    => [
                        'to'        => (string) $jeton,
                        'title'     => (string) $l['titre'],
                        'body'      => (string) $l['corps'],
                        'data'      => (object) $donnees,
                        'sound'     => 'default',
                        'channelId' => $cat,
                    ],
                ];
            }
        }

        $partis    = [];
        $reportes  = [];
        $desactiver = $pdo->prepare('UPDATE appareils_push SET actif = 0 WHERE jeton = ?');
        foreach (array_chunk($aEnvoyer, PUSH_LOT_EXPO) as $lot) {
            $tickets = $envoyeur(array_column($lot, 'message'));
            foreach ($lot as $i => $item) {
                $ticket = $tickets[$i] ?? null;
                if ($tickets === null || !is_array($ticket)) {
                    $reportes[$item['id']] = $item['tentatives'];
                    continue;
                }
                if (($ticket['status'] ?? '') === 'ok') {
                    $partis[$item['id']] = true;
                } elseif (($ticket['details']['error'] ?? '') === 'DeviceNotRegistered') {
                    // Application désinstallée : le jeton ne servira plus.
                    $desactiver->execute([$item['jeton']]);
                }
            }
        }

        $ids = array_unique(array_column($aEnvoyer, 'id'));
        $reessayer = $pdo->prepare(
            "UPDATE push_envois SET tentatives = tentatives + 1,
                    statut = IF(tentatives + 1 >= ?, 'echec', 'en_attente'),
                    envoyer_apres = ?
              WHERE id = ?"
        );
        foreach ($ids as $id) {
            if (isset($partis[$id])) {
                $marquer->execute(['envoye', $maintenant, $id]);
                $bilan['envoyes']++;
            } elseif (isset($reportes[$id])) {
                // Expo injoignable : on réessaie dans quelques minutes.
                $reessayer->execute([PUSH_TENTATIVES_MAX, gmdate('Y-m-d H:i:s', time() + 300), $id]);
                $bilan['reportes']++;
            } else {
                // Refusé par Expo pour chaque appareil : rien à réessayer.
                $ignorer($id);
            }
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('outsee_push')");
    }

    return $bilan;
}
