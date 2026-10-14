<?php
/**
 * Back-office fondateurs — finances.
 *
 * Le registre de trésorerie, son vocabulaire et les calculs de solde, de
 * point mort et d'autonomie. Extrait de crm.php, qui le recharge : les pages
 * continuent de n'inclure que crm.php.
 *
 * Les repères (capital, plancher, charges) proviennent de SL-13.
 */


/**
 * Catégories du registre, séparées par sens pour que les formulaires ne mélangent pas.
 *
 * @return array<string, array<string, string>>
 */
function financeCategories(): array
{
    return [
        'recette' => [
            'abonnement'    => 'Abonnement établissement',
            'sponsoring'    => 'Mise en avant payante',
            'autre_recette' => 'Autre recette',
        ],
        'depense' => [
            'hebergement'   => 'Hébergement, domaine, e-mails',
            'banque'        => 'Banque professionnelle',
            'comptabilite'  => 'Comptabilité',
            'assurance'     => 'Assurance RC pro',
            'marketing'     => 'Marketing et acquisition',
            'juridique'     => 'Juridique, marque, statuts',
            'materiel'      => 'Matériel',
            'autre_depense' => 'Autre dépense',
        ],
    ];
}

function financeLibelleCategorie(string $code): string
{
    foreach (financeCategories() as $groupe) {
        if (isset($groupe[$code])) return $groupe[$code];
    }
    return $code;
}

// ─── Repères issus des documents fondateurs ─────────────────────────────────

/**
 * Capital de départ (SL-13 §3). Le solde de trésorerie part de là et se
 * corrige par le registre. À ajuster une fois la société créée et le compte
 * professionnel ouvert — c'est un point de départ, pas une vérité comptable.
 */
const FINANCE_CAPITAL_INITIAL = 1000.00;

/** Plancher de trésorerie (SL-13 §3). En dessous : décision commune sous 15 jours. */
const FINANCE_PLANCHER = 1000.00;

/** Charges mensuelles de structure retenues (SL-13 §2). Sert au point mort. */
const FINANCE_CHARGES_MENSUELLES = 180.00;


// ─── Finances ───────────────────────────────────────────────────────────────

/**
 * Solde de trésorerie : capital de départ, plus les recettes réglées, moins
 * les dépenses réglées. Les lignes `prevu` n'entrent pas — une facture émise
 * n'est pas de l'argent en banque, et c'est précisément la confusion que ce
 * registre existe pour éviter.
 */
function financeSolde(PDO $pdo): float
{
    $solde = (float) $pdo->query(
        "SELECT COALESCE(SUM(CASE WHEN sens = 'recette' THEN montant_ht ELSE -montant_ht END), 0)
           FROM finance_mouvements
          WHERE statut = 'regle'"
    )->fetchColumn();

    return FINANCE_CAPITAL_INITIAL + $solde;
}

/**
 * Un mois vide, pour que la série n'ait jamais de trou.
 *
 * @return array{recettes: float, depenses: float, resultat: float, prevu_recettes: float, prevu_depenses: float}
 */
function financeMoisVide(): array
{
    return ['recettes' => 0.0, 'depenses' => 0.0, 'resultat' => 0.0,
            'prevu_recettes' => 0.0, 'prevu_depenses' => 0.0];
}

/**
 * Totaux d'un mois donné (format 'Y-m').
 *
 * `regle` et `prevu` sont séparés parce qu'ils ne répondent pas à la même
 * question : le résultat du mois ne compte que l'argent qui a bougé, le
 * prévisionnel dit ce qui reste à encaisser ou à payer.
 *
 * @return array{recettes:float, depenses:float, resultat:float, prevu_recettes:float, prevu_depenses:float}
 */
function financeMois(PDO $pdo, string $mois): array
{
    $stmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN sens='recette' AND statut='regle' THEN montant_ht END), 0) AS recettes,
            COALESCE(SUM(CASE WHEN sens='depense' AND statut='regle' THEN montant_ht END), 0) AS depenses,
            COALESCE(SUM(CASE WHEN sens='recette' AND statut='prevu' THEN montant_ht END), 0) AS prevu_recettes,
            COALESCE(SUM(CASE WHEN sens='depense' AND statut='prevu' THEN montant_ht END), 0) AS prevu_depenses
           FROM finance_mouvements
          WHERE DATE_FORMAT(date_mouvement, '%Y-%m') = ?"
    );
    $stmt->execute([$mois]);
    $l = $stmt->fetch() ?: [];

    $recettes = (float) ($l['recettes'] ?? 0);
    $depenses = (float) ($l['depenses'] ?? 0);

    return [
        'recettes'       => $recettes,
        'depenses'       => $depenses,
        'resultat'       => $recettes - $depenses,
        'prevu_recettes' => (float) ($l['prevu_recettes'] ?? 0),
        'prevu_depenses' => (float) ($l['prevu_depenses'] ?? 0),
    ];
}

/**
 * Les N derniers mois, du plus ancien au plus récent, pour le graphique.
 *
 * Une seule requête groupée, pas une par mois : la version naïve faisait
 * douze allers-retours pour dessiner douze barres, et le coût aurait grandi
 * avec la fenêtre affichée. Les mois sans mouvement sont ensuite remplis à
 * zéro — un trou dans une série temporelle se lit comme une absence de
 * données, pas comme un zéro.
 *
 * @return array<string, array{recettes: float, depenses: float, resultat: float, prevu_recettes: float, prevu_depenses: float}>
 */
function financeSerieMensuelle(PDO $pdo, int $nbMois = 12): array
{
    $nbMois = max(1, $nbMois);

    $serie = [];
    for ($i = $nbMois - 1; $i >= 0; $i--) {
        $serie[date('Y-m', strtotime("-$i month"))] = financeMoisVide();
    }

    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(date_mouvement, '%Y-%m') AS mois,
                COALESCE(SUM(CASE WHEN sens='recette' AND statut='regle' THEN montant_ht END), 0) AS recettes,
                COALESCE(SUM(CASE WHEN sens='depense' AND statut='regle' THEN montant_ht END), 0) AS depenses,
                COALESCE(SUM(CASE WHEN sens='recette' AND statut='prevu' THEN montant_ht END), 0) AS prevu_recettes,
                COALESCE(SUM(CASE WHEN sens='depense' AND statut='prevu' THEN montant_ht END), 0) AS prevu_depenses
           FROM finance_mouvements
          WHERE date_mouvement >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL ? MONTH), '%Y-%m-01')
          GROUP BY mois"
    );
    $stmt->execute([$nbMois - 1]);

    foreach ($stmt as $l) {
        if (!isset($serie[$l['mois']])) continue;
        $recettes = (float) $l['recettes'];
        $depenses = (float) $l['depenses'];
        $serie[$l['mois']] = [
            'recettes'       => $recettes,
            'depenses'       => $depenses,
            'resultat'       => $recettes - $depenses,
            'prevu_recettes' => (float) $l['prevu_recettes'],
            'prevu_depenses' => (float) $l['prevu_depenses'],
        ];
    }

    return $serie;
}

/**
 * Point mort : combien de clients au panier moyen couvrent les charges.
 *
 * Sans client payant on se rabat sur le tarif Essentiel de la grille : le
 * panier moyen vaut alors zéro, et diviser les charges par zéro ne donne
 * rien d'affichable. C'est aussi l'hypothèse de SL-03 — « 3 clients à 79 € ».
 *
 * @return array{charges:float, panier_moyen:float, clients_requis:int, atteint:bool, mrr:float}
 */
function financeCalculPointMort(float $mrr, int $payants, float $charges): array
{
    $panier = $payants > 0 ? $mrr / $payants : (float) crmOffres()['essentiel']['tarif'];

    return [
        'charges'        => $charges,
        'panier_moyen'   => $panier,
        'clients_requis' => (int) ceil($charges / max($panier, 1)),
        'atteint'        => $mrr >= $charges,
        'mrr'            => $mrr,
    ];
}

/**
 * Le point mort actuel, lu en base.
 *
 * @return array<string, mixed>
 */
function financePointMort(PDO $pdo): array
{
    return financeCalculPointMort(crmMrr($pdo), crmClientsPayants($pdo), FINANCE_CHARGES_MENSUELLES);
}

/**
 * Autonomie : combien de mois le solde tient au rythme observé.
 *
 * Null quand la consommation nette est nulle ou négative — on ne brûle plus
 * rien, la question ne se pose pas. Un solde négatif rend zéro : l'autonomie
 * est déjà épuisée, pas « négative ».
 */
function financeCalculAutonomie(float $solde, float $consommationMensuelle): ?float
{
    if ($consommationMensuelle <= 0) return null;

    return max(0.0, $solde) / $consommationMensuelle;
}

/** L'autonomie actuelle, au rythme des trois derniers mois. */
function financeAutonomieMois(PDO $pdo): ?float
{
    // Moyenne des trois derniers mois : un mois isolé sans dépense saisie ne
    // signifie pas que les charges ont disparu.
    $total = 0.0;
    foreach (financeSerieMensuelle($pdo, 3) as $m) {
        $total += max(0, $m['depenses'] - $m['recettes']);
    }

    return financeCalculAutonomie(financeSolde($pdo), $total / 3);
}
