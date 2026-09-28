<?php
/**
 * GET /api/v1/admin_finances.php — état des finances, lecture seule.
 *
 * Reprend admin/finances.php : trésorerie (capital + recettes réglées -
 * dépenses réglées), plancher SL-13, MRR facturé, résultat du mois courant,
 * et les vingt derniers mouvements. Aucune écriture ici — créer, régler ou
 * supprimer un mouvement reste réservé au site, cet écran sert à consulter
 * sans changer d'application.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/crm.php';

apiExigerMethode('GET');

$moi = apiAdmin($pdo);

$solde      = financeSolde($pdo);
$mrr        = crmMrr($pdo);
$mrrEngage  = crmMrrEngage($pdo);
$payants    = crmClientsPayants($pdo);
$moisCourant = financeMois($pdo, date('Y-m'));
$autonomie  = financeAutonomieMois($pdo);

$stmt = $pdo->prepare(
    "SELECT m.id, m.sens, m.categorie, m.libelle, m.montant_ht, m.statut, m.date_mouvement, c.nom AS client_nom
       FROM finance_mouvements m
       LEFT JOIN crm_clients c ON c.id = m.client_id
      ORDER BY m.date_mouvement DESC, m.id DESC
      LIMIT 20"
);
$stmt->execute();
$mouvements = $stmt->fetchAll();

apiReponse([
    'success' => true,
    'tresorerie' => [
        'solde'    => round($solde, 2),
        'plancher' => FINANCE_PLANCHER,
        'sous_le_plancher' => $solde < FINANCE_PLANCHER,
        'autonomie_mois' => $autonomie,
    ],
    'mrr' => [
        'facture' => round($mrr, 2),
        'engage'  => round($mrrEngage, 2),
        'clients_payants' => $payants,
    ],
    'mois_courant' => [
        'recettes' => round($moisCourant['recettes'], 2),
        'depenses' => round($moisCourant['depenses'], 2),
        'resultat' => round($moisCourant['resultat'], 2),
        'prevu_recettes' => round($moisCourant['prevu_recettes'], 2),
        'prevu_depenses' => round($moisCourant['prevu_depenses'], 2),
    ],
    'mouvements' => array_map(
        static fn (array $m): array => [
            'id' => (int) $m['id'],
            'sens' => (string) $m['sens'],
            'categorie' => (string) $m['categorie'],
            'libelle' => (string) $m['libelle'],
            'montant_ht' => (float) $m['montant_ht'],
            'statut' => (string) $m['statut'],
            'date' => (string) $m['date_mouvement'],
            'client' => $m['client_nom'] !== null ? (string) $m['client_nom'] : null,
        ],
        $mouvements
    ),
]);
