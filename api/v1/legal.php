<?php
/**
 * GET /api/v1/legal.php — les textes légaux, pour l'afficher dans l'application.
 *
 *   sans paramètre   la liste des documents : { slug, titre }
 *   ?page=<slug>     un document : titre, date de révision, sections, pied
 *
 * Public : on lit les conditions avant de créer un compte, donc sans jeton.
 * Les boutiques d'applications exigent que ces textes soient consultables
 * depuis l'application, pas seulement depuis un site.
 *
 * Le texte vient de includes/textes_legaux.php, la même source que les pages
 * du site : les deux ne peuvent pas diverger. Aucun HTML n'en sort — seul
 * **gras** est balisé, que l'application sait afficher.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/textes_legaux.php';

apiExigerMethode('GET');

$textes = textesLegaux();
$slug   = is_string($_GET['page'] ?? null) ? trim($_GET['page']) : '';

if ($slug === '') {
    $liste = [];
    foreach ($textes as $s => $doc) {
        $liste[] = ['slug' => $s, 'titre' => $doc['titre']];
    }
    apiReponse(['success' => true, 'pages' => $liste]);
}

if (!isset($textes[$slug])) {
    apiErreur('Page introuvable.', 404, 'introuvable');
}

$doc = $textes[$slug];

apiReponse([
    'success' => true,
    'page'    => [
        'slug'     => $slug,
        'titre'    => $doc['titre'],
        'maj'      => legalDateFr((string) $doc['maj']),
        'sections' => $doc['sections'],
        'pied'     => $doc['pied'] ?? null,
    ],
]);
