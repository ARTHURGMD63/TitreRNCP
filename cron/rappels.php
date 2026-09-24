<?php
/**
 * Rappel de la veille.
 *
 * Le no-show est le premier risque de ce produit : une inscription gratuite
 * et sans friction produit beaucoup d'absents, et c'est la raison numéro un
 * pour un établissement de cesser de publier. Un rappel la veille est le
 * levier le moins cher pour y remédier.
 *
 * À lancer une fois par jour, en ligne de commande :
 *     php cron/rappels.php
 *
 * Options :
 *     --simulation   affiche ce qui serait envoyé, sans rien envoyer ni écrire
 *
 * Le script est idempotent : un rappel déjà envoyé n'est jamais renvoyé, la
 * clé primaire de rappels_envoyes l'interdit au niveau de la base.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mail.php';

$simulation = in_array('--simulation', $argv ?? [], true);

/*
 * « Demain » au sens du calendrier, pas « dans 24 heures » : un événement à
 * 23 h et un événement à 1 h du matin doivent recevoir leur rappel le même
 * jour, sinon le second part deux jours avant.
 */
$sql = "
    SELECT i.id AS inscription_id, u.email, u.prenom,
           e.titre, e.date_heure, e.reduction,
           et.nom AS etablissement_nom, et.ville
    FROM inscriptions i
    JOIN users u        ON u.id = i.user_id
    JOIN evenements e   ON e.id = i.evenement_id
    JOIN etablissements et ON et.id = e.etablissement_id
    LEFT JOIN rappels_envoyes r
           ON r.inscription_id = i.id AND r.type = 'veille'
    WHERE i.statut = 'inscrit'
      AND DATE(e.date_heure) = DATE(NOW() + INTERVAL 1 DAY)
      AND r.inscription_id IS NULL
    ORDER BY e.date_heure ASC
";

$aRappeler = $pdo->query($sql)->fetchAll();

if (!$aRappeler) {
    echo "Aucun rappel à envoyer.\n";
    exit(0);
}

printf("%d rappel(s) à envoyer%s.\n", count($aRappeler), $simulation ? ' (simulation)' : '');

$envoyes = 0;
$echecs  = 0;

foreach ($aRappeler as $r) {
    $heure = date('H\hi', strtotime($r['date_heure']));
    $sujet = sprintf('Demain %s — %s', $heure, $r['titre']);

    $corps = sprintf(
        "Salut %s,\n\n"
        . "Petit rappel : tu es inscrit·e à %s, demain à %s.\n\n"
        . "  Lieu   : %s, %s\n"
        . "%s"
        . "\nTon pass est dans l'application, onglet Wallet. Présente-le à l'entrée.\n\n"
        . "Tu ne peux plus venir ? Annule ton pass depuis le Wallet : ta place sera "
        . "rendue à quelqu'un d'autre.\n\n"
        . "À demain,\nL'équipe Linkee",
        $r['prenom'],
        $r['titre'],
        $heure,
        $r['etablissement_nom'],
        $r['ville'],
        $r['reduction'] > 0 ? sprintf("  Ta remise : -%d%%\n", (int) $r['reduction']) : ''
    );

    if ($simulation) {
        printf("  [simulation] %-28s %s\n", $r['email'], $sujet);
        $envoyes++;
        continue;
    }

    if (!envoyerEmail($r['email'], $sujet, $corps)) {
        logErreur('Rappel de la veille non envoyé', null, [
            'inscription' => $r['inscription_id'],
            'email'       => $r['email'],
        ]);
        $echecs++;
        continue;
    }

    /*
     * La trace est écrite après l'envoi : en cas de panne du serveur de mail
     * le rappel sera retenté au prochain passage, plutôt que d'être perdu.
     */
    $pdo->prepare(
        "INSERT IGNORE INTO rappels_envoyes (inscription_id, type) VALUES (?, 'veille')"
    )->execute([$r['inscription_id']]);

    $envoyes++;
}

printf("Terminé : %d envoyé(s), %d échec(s).\n", $envoyes, $echecs);
exit($echecs > 0 ? 1 : 0);
