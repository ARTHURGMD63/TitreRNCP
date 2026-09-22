<?php
/**
 * GET /api/v1/evenements.php — le fil des soirees a venir.
 *
 * Parametres, tous optionnels et repris tels quels du hub web :
 *
 *   ?type=all|pour-moi|bar|boite|resto   « pour-moi » = les lieux suivis et
 *                                        les soirees ou des amis vont
 *   ?musique=techno|house|…              style exact, voir musique.php
 *   ?pe=2                                page (voir ci-dessous)
 *
 * Il n'y a PAS de recherche textuelle sur ce point : `?q=` ne filtre que
 * l'annuaire des personnes, et le passer ici ne change rien. Mieux vaut
 * l'ecrire que laisser l'application croire a un filtre qui ne filtre pas.
 *
 * LA PAGINATION EST CUMULATIVE, ce n'est pas une etourderie : le hub web
 * fonctionne en « voir plus », donc la page 2 renvoie les pages 1 ET 2. Cela
 * tombe bien pour un defilement infini — l'application remplace sa liste par
 * la reponse complete au lieu d'y concatener une tranche, et n'a donc jamais
 * de doublon ni de trou si une soiree est creee entre deux appels. En
 * contrepartie, la reponse grossit page apres page.
 *
 * Aucune requete n'est ecrite ici : la couche donnees vit dans
 * includes/hub.php depuis le decoupage d'explore.php, et c'est exactement ce
 * qui permet aujourd'hui de servir le meme fil en JSON sans dupliquer dix
 * requetes — ni risquer que la version mobile et la version web divergent.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/hub.php';

apiExigerMethode('GET');

$u   = apiEtudiant($pdo);
$uid = (int) $u['id'];

$criteres     = hubCriteres($_GET);
$etabsSuivis  = hubEtablissementsSuivis($pdo, $uid);
$amisParEvent = hubAmisParEvenement($pdo, $uid);

$resultat = hubEvenements($pdo, $uid, $criteres, $etabsSuivis, $amisParEvent);

/**
 * Met un evenement en forme pour l'application.
 *
 * Le gabarit web recevait la ligne SQL brute et se debrouillait. Une API ne
 * peut pas : les types y sont significatifs — `18` et `"18"` ne se comparent
 * pas pareil en JavaScript, et `0` n'est pas `false` pour un decodeur strict
 * comme celui de Swift. Tout est donc converti explicitement.
 *
 * @param array<string,mixed> $e
 * @param array<int,array{prenoms:list<string>,nb:int}> $amis tel que le rend
 *        hubAmisParEvenement() : les prenoms deja agreges, et leur nombre.
 * @return array<string,mixed>
 */
function apiEvenement(array $e, array $amis): array
{
    $id      = (int) $e['id'];
    $quota   = (int) ($e['quota'] ?? 0);
    $inscrits = (int) ($e['nb_inscrits'] ?? 0);

    return [
        'id'          => $id,
        'titre'       => (string) $e['titre'],
        'description' => (string) ($e['description'] ?? ''),
        'type'        => (string) ($e['type'] ?? ''),
        'style_musique' => $e['style_musique'] !== null ? (string) $e['style_musique'] : null,
        'date_heure'  => (string) $e['date_heure'],
        'lieu'        => (string) ($e['lieu'] ?? ''),

        'etablissement' => [
            'id'      => (int) ($e['etab_id'] ?? $e['etablissement_id']),
            'nom'     => (string) ($e['etablissement_nom'] ?? ''),
            'type'    => (string) ($e['etab_type'] ?? ''),
            'ville'   => (string) ($e['ville'] ?? ''),
            'note'    => $e['etab_note'] !== null ? round((float) $e['etab_note'], 1) : null,
            'nb_avis' => (int) ($e['etab_nb_avis'] ?? 0),
        ],

        'places' => [
            'quota'     => $quota,
            'inscrits'  => $inscrits,
            // Calcule ici plutot que dans l'application : la regle « complet »
            // doit etre la meme partout, et une soiree sans quota n'est pas
            // une soiree a zero place.
            'restantes' => $quota > 0 ? max(0, $quota - $inscrits) : null,
            'complet'   => $quota > 0 && $inscrits >= $quota,
        ],

        'reduction'    => $e['reduction'] !== null ? (int) $e['reduction'] : null,
        'prix_normal'  => $e['prix_normal'] !== null ? (float) $e['prix_normal'] : null,
        'is_gratuit'   => (bool) ($e['is_gratuit'] ?? false),
        'is_flash'     => (bool) ($e['is_flash'] ?? false),
        'flash_expiry' => $e['flash_expiry'] !== null ? (string) $e['flash_expiry'] : null,
        'sponsorise'   => (bool) ($e['sponso_actif'] ?? false),
        'deja_inscrit' => (bool) ($e['deja_inscrit'] ?? false),

        // « Hugo et Maxime y vont » : l'argument le plus fort de la carte.
        // hubAmisParEvenement() agrege les prenoms en base (GROUP_CONCAT) et
        // rend ['prenoms' => [...], 'nb' => n] — le compte separement, parce
        // que la carte en affiche trois et annonce « et 4 autres ».
        'amis' => [
            'prenoms' => array_values($amis[$id]['prenoms'] ?? []),
            'nb'      => (int) ($amis[$id]['nb'] ?? 0),
        ],
    ];
}

apiReponse([
    'success'    => true,
    'evenements' => array_map(
        static fn (array $e): array => apiEvenement($e, $amisParEvent),
        $resultat['evenements']
    ),
    'pagination' => [
        'page'      => $criteres['page_evenements'],
        // `reste` dit s'il existe une page suivante sans avoir eu a compter
        // toute la table : hubEvenements demande une ligne de plus que ce
        // qu'il rend, et regarde si elle est venue.
        'a_suivre'  => (bool) $resultat['reste'],
        // Annonce explicitement a l'application que la page suivante contient
        // aussi celle-ci : sans ce drapeau, elle concatenerait et afficherait
        // chaque soiree deux fois.
        'cumulative' => true,
    ],
    'filtres' => [
        'type'    => $criteres['type'],
        'musique' => $criteres['musique'],
    ],
]);
