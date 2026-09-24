<?php
/**
 * GET  /api/v1/notifications.php          — la cloche et la page Notifications.
 * POST /api/v1/notifications.php {lire:1} — ouvrir la page vaut lecture.
 *
 * Les éléments viennent de notificationsEtudiant(), la fonction qui remplit la
 * cloche du hub et notifications.php sur le site : mêmes sources, même ordre,
 * même limite. Les libellés de date (« il y a 3 h », « 12 Sept ») sont
 * calculés ici par les fonctions du site, pour que l'application écrive
 * exactement la même chose.
 *
 * `nouvelle` suit la dernière lecture (migration v17), comme la pastille lave
 * de la page Notifications.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/notifications.php';

$u   = apiEtudiant($pdo);
$uid = (int) $u['id'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    apiReponse(['success' => marquerNotificationsLues($pdo, $uid)]);
}

apiExigerMethode('GET');

$notifs = notificationsEtudiant($pdo, $uid);
$luesLe = notificationsLuesLe($pdo, $uid);
$luesTs = $luesLe !== null ? strtotime($luesLe) : null;

$minuit  = strtotime('today');
$semaine = strtotime('-7 days', $minuit);

$items = [];
foreach ($notifs['items'] as $n) {
    $ts  = (string) $n['ts'];
    $sec = strtotime($ts) ?: 0;

    $item = [
        'type'     => (string) $n['type'],
        'ts'       => $ts,
        'depuis'   => depuisQuand($ts),
        'traiter'  => (bool) $n['traiter'],
        'nouvelle' => $luesTs === null || $sec > $luesTs,
        'section'  => $sec >= $minuit ? "Aujourd'hui" : ($sec >= $semaine ? 'Cette semaine' : 'Plus tôt'),
    ];

    if ($n['type'] === 'demande') {
        $item['acteur'] = apiPersonne($n['acteur']);
    } elseif ($n['type'] === 'invitation') {
        $inv = $n['invit'];
        $item['invitation'] = [
            'id'         => (int) $inv['id'],
            'cible_type' => (string) $inv['cible_type'],
            'cible_nom'  => $inv['cible_nom'] !== null ? (string) $inv['cible_nom'] : null,
            'de'         => apiPersonne([
                'id' => $inv['from_id'], 'prenom' => $inv['from_prenom'],
                'nom' => $inv['from_nom'], 'photo' => $inv['from_photo'],
            ]),
        ];
    } elseif ($n['type'] === 'ami') {
        $a = $n['activite'];
        $item['activite'] = [
            'cible_type' => (string) $a['cible_type'],
            'cible_id'   => (int) $a['cible_id'],
            'cible_nom'  => (string) $a['cible_nom'],
            'lieu'       => (string) $a['lieu'],
            'acteur'     => apiPersonne([
                'id' => $a['acteur_id'], 'prenom' => $a['prenom'],
                'nom' => $a['nom'], 'photo' => $a['photo'],
            ]),
        ];
    } else {
        $e = $n['event'];
        $item['evenement'] = [
            'id'    => (int) $e['id'],
            'titre' => (string) $e['titre'],
            'lieu'  => (string) $e['lieu'],
            'date'  => dateFr((string) $e['date_heure'], 'j M'),
        ];
    }

    $items[] = $item;
}

apiReponse([
    'success'   => true,
    'a_traiter' => (int) $notifs['aTraiter'],
    'items'     => $items,
]);
