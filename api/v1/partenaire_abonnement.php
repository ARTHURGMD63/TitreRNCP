<?php
/**
 * GET  /api/v1/partenaire_abonnement.php — la formule en cours (s'il y en a
 *      une), les formules souscriptibles et les places fondateur restantes.
 *      Reprend partenaire/abonnement.php, sans le HTML.
 * POST /api/v1/partenaire_abonnement.php {offre} — souscrire ou changer de
 *      formule. Même logique que le site : offre revérifiée contre la liste
 *      ouverte, place fondateur reverrouillée dans la transaction, ligne
 *      « prévu » posée au registre financier si le tarif n'est pas nul.
 *
 * Écran manquant côté application avant cet ajout : un établissement sans
 * formule restait bloqué au tableau de bord (`abonnement_requis`, voir
 * partenaire_dashboard.php) sans aucun moyen d'en choisir une depuis
 * l'application.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/crm.php';
require_once __DIR__ . '/../../includes/capacites.php';
require_once __DIR__ . '/../../includes/sponsoring.php';

$u   = apiPartenaire($pdo);
$uid = (int) $u['id'];

$etab = apiEtablissementDe($pdo, $uid);
if (!$etab) {
    apiErreur('Aucun établissement associé à ce compte.', 404, 'etablissement_absent');
}

/**
 * @return list<array<string, mixed>>
 */
function partenaireAbonnementFormules(PDO $pdo, string $typeEtablissement, ?array $clientActuel): array
{
    $restantes = placesFondateurRestantes($pdo);
    $formules  = [];
    $grille    = grilleCapacites();
    foreach (formulesSouscriptibles($pdo, $typeEtablissement, $clientActuel) as $code => $f) {
        $cap = $grille[$code] ?? $grille['aucune'];
        $formules[] = [
            'evenements_par_mois' => (int) $cap['evenements_par_mois'],
            'evenements_debut'    => $cap['evenements_debut'],
            'flash_par_mois'      => $cap['flash_par_mois'],
            'mises_en_avant'      => (int) $cap['mises_en_avant_offertes'],
            'code'                => $code,
            'libelle'             => $f['libelle'],
            'tarif'               => (float) $f['tarif'],
            'note'                => $f['note'],
            'places_restantes'    => $code === 'fondateur' ? $restantes : null,
            'mois_essai'          => $code === 'fondateur' ? 3 : 1,
        ];
    }
    return $formules;
}

/**
 * @param array<string, mixed>|null $client
 * @return array<string, mixed>|null
 */
function partenaireAbonnementClient(?array $client): ?array
{
    if ($client === null) {
        return null;
    }
    return [
        'offre'          => (string) $client['offre'],
        'offre_libelle'  => crmLibelleOffre((string) $client['offre']),
        'mrr'            => (float) $client['mrr'],
        'statut'         => (string) $client['statut'],
        'essai_jusquau'  => $client['essai_jusqu_au'] !== null ? (string) $client['essai_jusqu_au'] : null,
    ];
}

$methode = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($methode === 'POST') {
    $corps = apiCorps();
    $offre = (string) ($corps['offre'] ?? '');

    $stmtEmail = $pdo->prepare('SELECT email FROM users WHERE id = ?');
    $stmtEmail->execute([$uid]);

    // Toutes les règles (type du compte, une formule par mois, période offerte
    // une seule fois, fondateur réservé au lancement) vivent dans
    // souscrireFormule(), partagée avec le site.
    $res = souscrireFormule($pdo, $etab, $u, $offre, $stmtEmail->fetchColumn() ?: null);
    if (!$res['ok']) {
        $statut = match ($res['code']) {
            'offre_invalide' => 422,
            'fondateur_complet', 'changement_limite' => 409,
            default => 500,
        };
        apiErreur($res['message'], $statut, $res['code']);
    }

    apiReponse([
        'success' => true,
        'message' => $res['message'],
        'client'  => partenaireAbonnementClient(abonnementEtablissement($pdo, (int) $etab['id'])),
    ]);
}

apiExigerMethode('GET');

$client = abonnementEtablissement($pdo, (int) $etab['id']);

$capacites = capacitesEtablissement($pdo, (int) $etab['id']);

apiReponse([
    'success'  => true,
    'client'   => partenaireAbonnementClient($client),
    'formules' => compteValide($etab) ? partenaireAbonnementFormules($pdo, (string) $etab['type'], $client) : [],
    'est_club' => ($etab['type'] ?? '') === 'club',
    'type_compte' => (string) ($etab['type'] ?? ''),
    // Un BDE n'a ni formule ni publication tant que l'administrateur ne l'a pas validé.
    'validation'  => [
        'requise' => verificationRequise((string) ($etab['type'] ?? '')),
        'statut'  => (string) ($etab['verification'] ?? 'non_requise'),
        'valide'  => compteValide($etab),
        'note'    => $etab['verification_note'] !== null ? (string) $etab['verification_note'] : null,
    ],
    // Une formule se change au plus une fois par mois.
    'changement' => (function () use ($client): array {
        $dejaChange = abonnementChoisi($client) && substr((string) ($client['offre_changee_le'] ?? ''), 0, 7) === date('Y-m');
        return [
            'possible' => !$dejaChange,
            'prochain' => $dejaChange ? date('Y-m-d', (int) strtotime('first day of next month')) : null,
        ];
    })(),
    // Ce que la formule en cours comprend, et ce qui en a été consommé ce mois.
    'inclus'   => [
        'evenements_par_mois'     => (int) $capacites['evenements_quota_actuel'],
        'en_periode_offerte'      => (bool) $capacites['en_periode_offerte'],
        'evenements_apres_offre'  => (int) $capacites['evenements_par_mois'],
        'flash_par_mois'          => $capacites['flash_par_mois'],
        'mises_en_avant_offertes' => (int) $capacites['mises_en_avant_offertes'],
    ],
    'usage'    => usagePartenaireMois($pdo, (int) $etab['id'], $capacites),
    // Les achats possibles en plus de l'abonnement, tarifs HT.
    'achats'   => [
        'evenement_supplementaire' => (float) PRIX_EVENEMENT_SUPPLEMENTAIRE,
        'sponsoring' => array_map(
            static fn (string $code, array $f): array => [
                'code' => $code, 'nom' => $f['nom'], 'tarif' => (float) $f['tarif'],
                'heures' => (int) $f['heures'], 'notifications' => (int) $f['notifications'], 'resume' => $f['resume'],
            ],
            array_keys(formulesSponsoring()),
            array_values(formulesSponsoring())
        ),
    ],
]);
