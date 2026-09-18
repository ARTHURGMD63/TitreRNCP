<?php
/**
 * Back-office fondateurs — vocabulaire et calculs.
 *
 * Tout ce qui se chiffre est ici, et pas dans les pages : le MRR affiché
 * sur le tableau de bord et celui affiché sur la page finances doivent
 * venir de la même fonction, sinon deux écrans donnent deux chiffres et
 * plus personne ne sait lequel croire.
 *
 * Les seuils et la grille proviennent des documents fondateurs
 * (docs/fondateurs/) : SL-03 pour les tarifs, SL-07 pour les indicateurs
 * et les alertes, SL-13 pour les charges et la trésorerie.
 */

// ─── Vocabulaire ────────────────────────────────────────────────────────────

/** Le pipeline commercial de SL-05, dans l'ordre. */
function crmStatuts(): array
{
    return [
        'prospect' => ['libelle' => 'Prospect',      'couleur' => 'var(--gris)',              'actif' => false],
        'contacte' => ['libelle' => 'Contacté',      'couleur' => 'var(--sur-bleu-clair)',    'actif' => false],
        'rdv'      => ['libelle' => 'Rendez-vous',   'couleur' => 'var(--sur-orange-clair)',  'actif' => false],
        'essai'    => ['libelle' => 'Essai',         'couleur' => 'var(--sur-lime-clair)',    'actif' => true],
        'actif'    => ['libelle' => 'Client actif',  'couleur' => 'var(--succes)',            'actif' => true],
        'pause'    => ['libelle' => 'En pause',      'couleur' => 'var(--alerte)',            'actif' => false],
        'perdu'    => ['libelle' => 'Perdu',         'couleur' => 'var(--danger)',            'actif' => false],
    ];
}

function crmLibelleStatut(string $code): string
{
    return crmStatuts()[$code]['libelle'] ?? $code;
}

function crmCouleurStatut(string $code): string
{
    return crmStatuts()[$code]['couleur'] ?? 'var(--gris)';
}

/**
 * La grille de SL-03. `tarif` est le prix catalogue mensuel HT ; le montant
 * réellement facturé vit dans `crm_clients.mrr`, parce que le tarif fondateur
 * est gelé et qu'une grille qui bouge ne doit pas réécrire un contrat signé.
 */
function crmOffres(): array
{
    return [
        'aucune'    => ['libelle' => 'Aucune',     'tarif' => 0,   'note' => 'Pas encore d\'abonnement'],
        'fondateur' => ['libelle' => 'Fondateur',  'tarif' => 39,  'note' => '0 € pendant 3 mois, puis 39 € gelés — 15 places'],
        'essentiel' => ['libelle' => 'Essentiel',  'tarif' => 79,  'note' => 'Bars et restaurants'],
        'premium'   => ['libelle' => 'Premium',    'tarif' => 149, 'note' => 'Discothèques, groupes, multi-établissements'],
        'bde'       => ['libelle' => 'BDE',        'tarif' => 0,   'note' => 'Gratuit, définitivement'],
    ];
}

function crmLibelleOffre(string $code): string
{
    return crmOffres()[$code]['libelle'] ?? $code;
}

function crmCategoriesClient(): array
{
    return [
        'bar'       => 'Bar',
        'boite'     => 'Boîte de nuit',
        'resto'     => 'Restaurant',
        'afterwork' => 'Afterwork',
        'bde'       => 'BDE / association',
        'autre'     => 'Autre',
    ];
}

function crmTypesInteraction(): array
{
    return [
        'appel'   => 'Appel',
        'visite'  => 'Visite',
        'email'   => 'E-mail',
        'demo'    => 'Démo',
        'relance' => 'Relance',
        'note'    => 'Note',
    ];
}

/** Catégories du registre, séparées par sens pour que les formulaires ne mélangent pas. */
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

/** Objectif de pass scannés par semaine, par jalon (SL-07 §1). */
function kpiObjectifsNorthStar(): array
{
    return [
        '2026-10-01' => 30,
        '2026-12-01' => 150,
        '2027-03-01' => 400,
        '2027-09-01' => 1000,
    ];
}

/** L'objectif en vigueur aujourd'hui : le dernier jalon franchi. */
function kpiObjectifCourant(?int $maintenant = null): int
{
    $ts = $maintenant ?? time();
    $objectif = 0;
    foreach (kpiObjectifsNorthStar() as $date => $cible) {
        if (strtotime($date) <= $ts) $objectif = $cible;
    }
    return $objectif ?: 30;
}

// ─── Calculs commerciaux ────────────────────────────────────────────────────

/**
 * Revenu mensuel récurrent : la somme des abonnements facturés.
 *
 * Seuls `actif` et `essai` comptent, et un essai en cours ne facture rien —
 * son mrr vaut 0 tant que `essai_jusqu_au` n'est pas passé. Compter les
 * essais gratuits dans le MRR gonflerait le seul chiffre qui doit rester
 * honnête.
 */
function crmMrr(PDO $pdo): float
{
    return (float) $pdo->query(
        "SELECT COALESCE(SUM(mrr), 0)
           FROM crm_clients
          WHERE statut IN ('actif','essai')
            AND (essai_jusqu_au IS NULL OR essai_jusqu_au < CURDATE())"
    )->fetchColumn();
}

/**
 * MRR engagé : ce que le parc rapportera une fois les essais terminés.
 *
 * `crmMrr()` ne compte que ce qui est facturé aujourd'hui, et c'est ce qu'il
 * doit faire — gonfler le MRR avec des essais gratuits serait se mentir. Mais
 * un établissement qui vient de souscrire ne bouge alors aucun chiffre du
 * tableau de bord, alors qu'il s'est bel et bien engagé. Les deux montants se
 * lisent donc côte à côte : l'un dit la réalité du mois, l'autre ce qui vient.
 */
function crmMrrEngage(PDO $pdo): float
{
    return (float) $pdo->query(
        "SELECT COALESCE(SUM(mrr), 0) FROM crm_clients WHERE statut IN ('actif','essai')"
    )->fetchColumn();
}

/**
 * Les dernières souscriptions, la plus récente d'abord.
 *
 * `signe_le` porte la date du premier engagement ; `updated_at` bouge à chaque
 * changement de formule. Comparer les deux permet de distinguer une nouvelle
 * signature d'une montée de gamme, qui n'a pas la même valeur commerciale.
 */
function crmSouscriptionsRecentes(PDO $pdo, int $limite = 6): array
{
    $stmt = $pdo->prepare(
        "SELECT c.id, c.nom, c.categorie, c.ville, c.offre, c.mrr,
                c.statut, c.signe_le, c.essai_jusqu_au, c.contact_nom,
                DATE(c.updated_at) AS modifie_le,
                (c.etablissement_id IS NOT NULL) AS a_un_compte
           FROM crm_clients c
          WHERE c.offre <> 'aucune' AND c.signe_le IS NOT NULL
          ORDER BY c.signe_le DESC, c.id DESC
          LIMIT ?"
    );
    $stmt->bindValue(1, $limite, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/** Souscriptions prises sur les N derniers jours. */
function crmNouvellesSouscriptions(PDO $pdo, int $jours = 7): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM crm_clients
          WHERE offre <> 'aucune'
            AND signe_le IS NOT NULL
            AND signe_le >= DATE_SUB(CURDATE(), INTERVAL ? DAY)"
    );
    $stmt->execute([$jours]);

    return (int) $stmt->fetchColumn();
}

/** Nombre de clients qui paient réellement quelque chose. */
function crmClientsPayants(PDO $pdo): int
{
    return (int) $pdo->query(
        "SELECT COUNT(*)
           FROM crm_clients
          WHERE statut IN ('actif','essai')
            AND mrr > 0
            AND (essai_jusqu_au IS NULL OR essai_jusqu_au < CURDATE())"
    )->fetchColumn();
}

/** Répartition du pipeline, tous statuts présents même à zéro. */
function crmPipeline(PDO $pdo): array
{
    $repartition = array_fill_keys(array_keys(crmStatuts()), 0);
    foreach ($pdo->query("SELECT statut, COUNT(*) n FROM crm_clients GROUP BY statut") as $ligne) {
        $repartition[$ligne['statut']] = (int) $ligne['n'];
    }
    return $repartition;
}

/**
 * Churn : clients perdus ÷ clients en début de mois (SL-07 §2).
 *
 * Null quand il n'y avait aucun client au départ — un taux calculé sur zéro
 * n'est pas « 0 % », il n'existe pas, et afficher 0 % laisserait croire
 * qu'on ne perd personne alors qu'on n'a encore personne à perdre.
 */
function crmCalculChurn(int $perdus, int $clientsDebutMois): ?float
{
    return $clientsDebutMois > 0 ? $perdus / $clientsDebutMois * 100 : null;
}

/** Le churn du mois en cours, lu en base. */
function crmChurnMensuel(PDO $pdo): ?float
{
    $perdus = (int) $pdo->query(
        "SELECT COUNT(*) FROM crm_clients
          WHERE statut = 'perdu'
            AND perdu_le >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
    )->fetchColumn();

    $debutMois = (int) $pdo->query(
        "SELECT COUNT(*) FROM crm_clients
          WHERE signe_le IS NOT NULL
            AND signe_le < DATE_FORMAT(CURDATE(), '%Y-%m-01')
            AND (perdu_le IS NULL OR perdu_le >= DATE_FORMAT(CURDATE(), '%Y-%m-01'))"
    )->fetchColumn();

    return crmCalculChurn($perdus, $debutMois);
}

// ─── Indicateurs produit (SL-07) ────────────────────────────────────────────

/**
 * Le tableau de bord hebdomadaire, sur 7 jours glissants.
 * `pass_scannes` est la North Star : c'est ce que la plaquette promet aux
 * partenaires, donc c'est ce que nous mesurons.
 */
function kpiHebdomadaires(PDO $pdo): array
{
    $q = static function (PDO $pdo, string $sql): int {
        return (int) $pdo->query($sql)->fetchColumn();
    };

    $inscrits7j = $q($pdo, "SELECT COUNT(*) FROM inscriptions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND statut != 'annule'");
    $scannes7j  = $q($pdo, "SELECT COUNT(*) FROM inscriptions WHERE statut = 'checkin' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");

    return [
        'pass_scannes'     => $scannes7j,
        'objectif_scannes' => kpiObjectifCourant(),
        'etudiants'        => $q($pdo, "SELECT COUNT(*) FROM users WHERE type = 'etudiant'"),
        'etudiants_7j'     => $q($pdo, "SELECT COUNT(*) FROM users WHERE type = 'etudiant' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
        'events_7j'        => $q($pdo, "SELECT COUNT(*) FROM evenements WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
        'reservations_7j'  => $inscrits7j,
        'invitations_7j'   => $q($pdo, "SELECT COUNT(*) FROM invitations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
        'squads_actifs'    => $q($pdo, "SELECT COUNT(*) FROM squads WHERE date_heure >= NOW()"),
        'etabs'            => $q($pdo, "SELECT COUNT(*) FROM etablissements"),
        'etabs_actifs'     => $q($pdo, "SELECT COUNT(DISTINCT etablissement_id) FROM evenements WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
        'taux_presence'    => tauxPresenceGlobal($pdo),
    ];
}

/**
 * Scannés ÷ inscrits sur les soirées passées (SL-07 : cible 70 % minimum).
 * Calculé sur les événements terminés seulement : une soirée à venir n'a
 * aucun scan et écraserait la moyenne vers zéro.
 */
function tauxPresenceGlobal(PDO $pdo, int $joursEnArriere = 30): ?float
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS inscrits,
                SUM(i.statut = 'checkin') AS presents
           FROM inscriptions i
           JOIN evenements e ON e.id = i.evenement_id
          WHERE e.date_heure < NOW()
            AND e.date_heure >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND i.statut != 'annule'"
    );
    $stmt->execute([$joursEnArriere]);
    $ligne = $stmt->fetch();

    return tauxPresence((int) ($ligne['inscrits'] ?? 0), (int) ($ligne['presents'] ?? 0));
}

/**
 * Scannés ÷ inscrits, en pourcentage. Null sans aucun inscrit : une soirée
 * sans réservation n'a pas un taux de présence de 0 %, elle n'en a pas.
 */
function tauxPresence(int $inscrits, int $presents): ?float
{
    return $inscrits > 0 ? $presents / $inscrits * 100 : null;
}

/**
 * Les signaux de SL-07 §3. Chacun déclenche une action, pas une discussion :
 * le libellé dit donc quoi faire, pas seulement ce qui va mal.
 */
function crmAlertes(PDO $pdo): array
{
    $alertes = [];

    // Établissement sans soirée publiée depuis 21 jours : premier signe de
    // churn, bien avant la résiliation.
    $stmt = $pdo->query(
        "SELECT c.id, c.nom,
                DATEDIFF(CURDATE(), COALESCE(MAX(e.created_at), c.created_at)) AS jours
           FROM crm_clients c
           JOIN etablissements et ON et.id = c.etablissement_id
           LEFT JOIN evenements e ON e.etablissement_id = et.id
          WHERE c.statut IN ('actif','essai')
          GROUP BY c.id, c.nom, c.created_at
         HAVING jours >= 21
          ORDER BY jours DESC"
    );
    foreach ($stmt as $ligne) {
        $alertes[] = [
            'niveau'  => 'alerte',
            'titre'   => $ligne['nom'] . ' — ' . (int) $ligne['jours'] . ' jours sans soirée publiée',
            'action'  => 'Appel dans la semaine : c\'est le premier signe de churn.',
            'lien'    => 'client.php?id=' . $ligne['id'],
        ];
    }

    // Fil trop statique : moins de 10 soirées publiées sur 7 jours.
    $events7j = (int) $pdo->query(
        "SELECT COUNT(*) FROM evenements WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    )->fetchColumn();
    if ($events7j < 10) {
        $alertes[] = [
            'niveau' => 'alerte',
            'titre'  => $events7j . ' soirée' . ($events7j > 1 ? 's' : '') . ' publiée' . ($events7j > 1 ? 's' : '') . ' cette semaine (seuil : 10)',
            'action' => 'Le fil devient statique et la rétention étudiante va chuter : priorité au recrutement de partenaires.',
            'lien'   => 'clients.php?statut=prospect',
        ];
    }

    // Taux de présence sous 60 % : vérifier le rappel de la veille, puis
    // appeler le partenaire avant qu'il ne tire ses propres conclusions.
    $taux = tauxPresenceGlobal($pdo);
    if ($taux !== null && $taux < 60) {
        $alertes[] = [
            'niveau' => 'danger',
            'titre'  => 'Taux de présence à ' . number_format($taux, 1, ',', ' ') . ' % (seuil : 60 %)',
            'action' => 'Vérifier que le rappel de la veille part bien, puis appeler les partenaires concernés.',
            'lien'   => 'evenements.php',
        ];
    }

    // Plancher de trésorerie.
    $solde = financeSolde($pdo);
    if ($solde < FINANCE_PLANCHER) {
        $alertes[] = [
            'niveau' => 'danger',
            'titre'  => 'Trésorerie à ' . number_format($solde, 0, ',', ' ') . ' € (plancher : ' . number_format(FINANCE_PLANCHER, 0, ',', ' ') . ' €)',
            'action' => 'Arrêt de toute dépense non indispensable, aucun nouvel engagement, décision commune sous 15 jours.',
            'lien'   => 'finances.php',
        ];
    }

    // Relances échues.
    $relances = (int) $pdo->query(
        "SELECT COUNT(*) FROM crm_interactions
          WHERE fait = 0 AND prochaine_action_le IS NOT NULL AND prochaine_action_le <= CURDATE()"
    )->fetchColumn();
    if ($relances > 0) {
        $alertes[] = [
            'niveau' => 'info',
            'titre'  => $relances . ' relance' . ($relances > 1 ? 's' : '') . ' à faire',
            'action' => 'Des actions notées en fiche client sont arrivées à échéance.',
            'lien'   => 'clients.php?relances=1',
        ];
    }

    return $alertes;
}

// ─── Souscription d'un établissement ──────────────────────────────────

/** Nombre de places fondateur, annoncé publiquement (SL-03 §4). */
const PLACES_FONDATEUR = 15;

/** Date ferme de fin de l'offre fondateur (SL-03 §4). */
const FIN_OFFRE_FONDATEUR = '2026-12-31';

/**
 * La fiche commerciale d'un établissement, ou null s'il n'en a pas encore.
 *
 * C'est elle qui porte l'abonnement : `etablissements` décrit le lieu,
 * `crm_clients` décrit le contrat. Les deux ne se confondent pas, puisqu'une
 * fiche existe déjà avant toute inscription pour les prospects.
 */
function abonnementEtablissement(PDO $pdo, int $etablissementId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM crm_clients WHERE etablissement_id = ?");
    $stmt->execute([$etablissementId]);

    return $stmt->fetch() ?: null;
}

/** Un établissement a-t-il choisi une formule ? */
function abonnementChoisi(?array $client): bool
{
    return $client !== null && ($client['offre'] ?? 'aucune') !== 'aucune';
}

/** Places fondateur encore libres. */
function placesFondateurRestantes(PDO $pdo): int
{
    $prises = (int) $pdo->query(
        "SELECT COUNT(*) FROM crm_clients WHERE offre = 'fondateur' AND statut <> 'perdu'"
    )->fetchColumn();

    return max(0, PLACES_FONDATEUR - $prises);
}

/**
 * L'offre fondateur est-elle encore ouverte ?
 *
 * Deux conditions, toutes deux annoncées publiquement : quinze places, et une
 * date ferme. La rareté n'a de valeur commerciale que si elle est vraie —
 * laisser passer la seizième signature coûterait plus que le tarif gelé.
 */
function offreFondateurOuverte(PDO $pdo, ?int $maintenant = null): bool
{
    $ts = $maintenant ?? time();

    return placesFondateurRestantes($pdo) > 0
        && $ts <= strtotime(FIN_OFFRE_FONDATEUR . ' 23:59:59');
}

/**
 * Les formules qu'un établissement peut choisir lui-même.
 *
 * `bde` n'y figure pas : la gratuité BDE est inscrite au contrat partenaire
 * (SL-03), elle passe donc par un fondateur. La laisser en libre-service
 * reviendrait à offrir l'abonnement à qui coche la bonne case.
 */
function formulesSouscriptibles(PDO $pdo): array
{
    $offres = crmOffres();
    unset($offres['aucune'], $offres['bde']);

    if (!offreFondateurOuverte($pdo)) {
        unset($offres['fondateur']);
    }

    return $offres;
}

/**
 * Fin de la période d'essai pour une formule donnée.
 *
 * SL-03 accorde trois mois offerts aux fondateurs, et pour les autres « une
 * soirée test complète, publiée et scannée, avant toute facturation ». Cette
 * condition ne se calcule pas à la signature : on pose un mois, affiché à
 * l'établissement, que les fondateurs ajustent depuis la fiche client une
 * fois la soirée test passée.
 */
function finEssaiPourFormule(string $offre, ?int $maintenant = null): string
{
    $depart = $maintenant ?? time();
    $mois   = $offre === 'fondateur' ? 3 : 1;

    return date('Y-m-d', strtotime("+$mois month", $depart));
}

/**
 * Renvoie vers le choix de formule tant qu'aucune n'a été prise.
 *
 * À appeler dans chaque page partenaire, juste après la lecture de
 * l'établissement. Un compte qui publie des soirées sans abonnement consomme
 * l'audience sans qu'aucune décision commerciale n'ait été prise, et
 * n'apparaît dans le CRM qu'en « essai » par défaut.
 */
function exigerAbonnement(PDO $pdo, ?array $etablissement): void
{
    // Pas d'établissement : rien à facturer, la page concernée s'en occupe.
    if (!$etablissement) return;

    if (abonnementChoisi(abonnementEtablissement($pdo, (int) $etablissement['id']))) return;

    header('Location: ' . baseUrl('/partenaire/abonnement.php'));
    exit;
}

/** Signalements en attente de traitement (pastille de la barre laterale). */
function crmSignalementsEnAttente(PDO $pdo): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM user_reports WHERE statut = 'nouveau'")->fetchColumn();
}

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

/** Un mois vide, pour que la série n'ait jamais de trou. */
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

/** Le point mort actuel, lu en base. */
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
