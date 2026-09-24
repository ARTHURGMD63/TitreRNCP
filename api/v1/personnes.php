<?php
/**
 * GET /api/v1/personnes.php — l'annuaire étudiant (Explore › Personnes).
 *
 * Paramètres : q, ecole, interest (« __moi__ » pour « Comme moi »), p.
 *
 * hubAnnuaire() est la fonction qui remplit l'onglet Personnes du site :
 * suggestions, liste classée par affinité, écoles et intérêts des filtres.
 * Rien n'est recalculé ici, les deux écrans ne peuvent donc pas diverger.
 * Les intérêts communs, le squad partagé et l'état de suivi voyagent avec
 * chaque profil, comme ils s'affichent sur chaque rangée.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/hub.php';

apiExigerMethode('GET');

$u   = apiEtudiant($pdo);
$uid = (int) $u['id'];

$criteres = hubCriteres(['view' => 'people'] + $_GET);
$annuaire = hubAnnuaire($pdo, ['id' => $uid, 'ecole' => (string) ($u['ecole'] ?? '')], $criteres);

$profil = static fn (array $p): array => apiPersonne($p) + [
    'etat_suivi'       => in_array($p['follow_statut'] ?? null, ['pending', 'accepted'], true) ? (string) $p['follow_statut'] : 'none',
    'interets_communs' => array_values(array_map('strval', $p['common_interests'] ?? [])),
    'squads_communs'   => (int) ($p['shared_squads'] ?? 0),
    'score'            => (int) ($p['score'] ?? 0),
];

apiReponse([
    'success'     => true,
    'profils'     => array_map($profil, $annuaire['profils']),
    'suggestions' => array_map($profil, $annuaire['suggestions']),
    'filtres'     => [
        'mes_interets'       => array_values($annuaire['mes_interets']),
        'ecoles'             => array_values($annuaire['ecoles']),
        'catalogue'          => array_values($annuaire['catalogue']),
        'interets_manquants' => (bool) $annuaire['interets_manquants'],
        'comme_moi'          => (bool) $annuaire['comme_moi'],
        'interet'            => (string) $annuaire['interet'],
        'ecole'              => $criteres['ecole'],
        'q'                  => $criteres['q'],
        'valeur_comme_moi'   => FILTRE_MES_INTERETS,
    ],
    'pagination'  => [
        'page'       => $criteres['page_profils'],
        'a_suivre'   => (bool) $annuaire['reste'],
        'cumulative' => true,
    ],
]);
