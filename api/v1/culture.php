<?php
/**
 * GET /api/v1/culture.php — le fil de l'espace Culture.
 *
 *   ?categorie=expo|concert|…   voir CULTURE_CATEGORIES (includes/culture.php)
 *   ?pe=2                       page, CUMULATIVE comme evenements.php : la
 *                               page 2 rend les pages 1 et 2
 *
 * Le catalogue des catégories part avec la réponse : l'application n'a pas de
 * liste à recopier, une catégorie ajoutée côté serveur y apparaît seule.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/culture.php';

apiExigerMethode('GET');

$u   = apiEtudiant($pdo);
$uid = (int) $u['id'];

$criteres = cultureCriteres($_GET);
$resultat = cultureEvenements($pdo, $uid, $criteres);
$amis     = cultureAmisParEvenement($pdo, $uid);

apiReponse([
    'success'    => true,
    'evenements' => array_map(
        static fn (array $c): array => cultureFormater($c, $amis[(int) $c['id']] ?? null),
        $resultat['evenements']
    ),
    'pagination' => [
        'page'       => $criteres['page'],
        'a_suivre'   => $resultat['reste'],
        'cumulative' => true,
    ],
    'filtres' => [
        'categorie'  => $criteres['categorie'],
        'categories' => array_map(
            static fn (string $code, string $libelle): array => ['code' => $code, 'libelle' => $libelle],
            array_keys(CULTURE_CATEGORIES),
            array_values(CULTURE_CATEGORIES)
        ),
    ],
]);
