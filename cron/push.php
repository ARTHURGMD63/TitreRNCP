<?php
/**
 * Notifications push : prépare ce qui est programmé, puis vide la file.
 *
 * À lancer chaque minute, en ligne de commande :
 *     php cron/push.php
 *
 * Trois choses, dans cet ordre :
 *
 *   1. les notifications que les établissements mis en avant ont programmées
 *      (« envoyer le vendredi à 17 h ») partent vers les abonnés du lieu à
 *      l'heure choisie — jamais plus de trois heures après : un message
 *      resté en retard n'a plus de sens ;
 *   2. le rappel de la veille, le soir (de 18 h à 22 h) pour les événements
 *      de demain, aux étudiants inscrits ;
 *   3. l'envoi de tout ce qui est dû (includes/push.php : préférences,
 *      plafonds, nuit).
 *
 * Le script est idempotent : la clé de chaque message (« rappel:42 »,
 * « sponso:7:19 ») interdit d'en écrire deux pour la même personne, si bien
 * qu'on peut le relancer ou le chevaucher sans risque.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('Europe/Paris');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/push.php';

$maintenant = new DateTimeImmutable('now', new DateTimeZone('Europe/Paris'));
$ecrits     = 0;

// ── 1. Notifications programmées par les lieux mis en avant ────────────────
$stmt = $pdo->prepare(
    "SELECT sn.id AS sn_id, sn.envoyer_le, e.id AS evenement_id, e.titre,
            et.nom AS lieu, fe.user_id
       FROM sponsor_notifications sn
       JOIN evenements e ON e.id = sn.evenement_id
       JOIN etablissements et ON et.id = e.etablissement_id
       JOIN follows_etablissements fe ON fe.etablissement_id = et.id
      WHERE e.is_sponsorise = 1
        AND e.date_heure >= ?
        AND sn.envoyer_le <= ?
        AND sn.envoyer_le >= ?"
);
$stmt->execute([
    $maintenant->format('Y-m-d H:i:s'),
    $maintenant->format('Y-m-d H:i:s'),
    $maintenant->modify('-3 hours')->format('Y-m-d H:i:s'),
]);
foreach ($stmt->fetchAll() as $l) {
    [$titre, $corps] = pushTexteLieu((string) $l['lieu'], (string) $l['titre']);
    $voulu = new DateTimeImmutable((string) $l['envoyer_le'], new DateTimeZone('Europe/Paris'));
    try {
        $ecrits += pushMettreEnFile(
            $pdo, (int) $l['user_id'], 'lieux', 'sponso:' . $l['sn_id'] . ':' . $l['user_id'],
            $titre, $corps, ['url' => '/evenement/' . $l['evenement_id']], $voulu
        ) !== null ? 1 : 0;
    } catch (Throwable $e) {
        logErreur('Notification de lieu non écrite', $e, ['sponsor_notification' => $l['sn_id']]);
    }
}

// ── 2. Rappel de la veille, le soir ────────────────────────────────────────
$heure = (int) $maintenant->format('G');
if ($heure >= 18 && $heure < PUSH_NUIT_DEBUT) {
    $stmt = $pdo->prepare(
        "SELECT i.id AS inscription_id, i.user_id, e.id AS evenement_id, e.titre, e.date_heure, et.nom AS lieu
           FROM inscriptions i
           JOIN evenements e ON e.id = i.evenement_id
           JOIN etablissements et ON et.id = e.etablissement_id
          WHERE i.statut = 'inscrit' AND e.date_heure >= ? AND e.date_heure < ?"
    );
    // Bornes plutôt que DATE(...) : l'index sur date_heure sert.
    $demain = $maintenant->modify('+1 day')->setTime(0, 0);
    $stmt->execute([$demain->format('Y-m-d H:i:s'), $demain->modify('+1 day')->format('Y-m-d H:i:s')]);
    foreach ($stmt->fetchAll() as $r) {
        [$titre, $corps] = pushTexteRappel((string) $r['titre'], date('H\hi', strtotime((string) $r['date_heure'])), (string) $r['lieu']);
        try {
            $ecrits += pushMettreEnFile(
                $pdo, (int) $r['user_id'], 'rappels', 'rappel:' . $r['inscription_id'],
                $titre, $corps, ['url' => '/evenement/' . $r['evenement_id']]
            ) !== null ? 1 : 0;
        } catch (Throwable $e) {
            logErreur('Rappel push non écrit', $e, ['inscription' => $r['inscription_id']]);
        }
    }
}

// ── 3. Envoi de ce qui est dû ──────────────────────────────────────────────
$bilan = pushTraiter($pdo, 500);

// Le ménage de l'historique d'envoi se fait dans cron/entretien.php.

if ($ecrits > 0 || $bilan['envoyes'] > 0 || $bilan['reportes'] > 0) {
    printf(
        "%s : %d préparé(s), %d envoyé(s), %d ignoré(s), %d reporté(s).\n",
        $maintenant->format('Y-m-d H:i'), $ecrits, $bilan['envoyes'], $bilan['ignores'], $bilan['reportes']
    );
}
