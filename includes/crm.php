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
 *
 * Ce fichier garde le vocabulaire commercial, le MRR, le churn et la
 * souscription des établissements. Les finances (finances.php) et les
 * indicateurs produit avec leurs alertes (kpi.php) en ont été extraits ; ils
 * sont rechargés ici, si bien que les pages n'incluent toujours que crm.php.
 */

require_once __DIR__ . '/finances.php';
require_once __DIR__ . '/kpi.php';

// ─── Vocabulaire ────────────────────────────────────────────────────────────

/**
 * Le pipeline commercial de SL-05, dans l'ordre.
 *
 * @return array<string, array{libelle: string, couleur: string, actif: bool}>
 */
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
 *
 * @return array<string, array{libelle: string, tarif: int, note: string}>
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

/**
 * @return array<string, string>
 */
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

/**
 * @return array<string, string>
 */
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
 *
 * @return list<array<string, mixed>>
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

/**
 * Répartition du pipeline, tous statuts présents même à zéro.
 *
 * @return array<string, int>
 */
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
 *
 * @return array<string, mixed>|null
 */
function abonnementEtablissement(PDO $pdo, int $etablissementId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM crm_clients WHERE etablissement_id = ?");
    $stmt->execute([$etablissementId]);

    return $stmt->fetch() ?: null;
}

/**
 * Un établissement a-t-il choisi une formule ?
 *
 * @param array<string, mixed>|null $client
 */
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
 *
 * @return array<string, array{libelle: string, tarif: int, note: string}>
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
 *
 * @param array<string, mixed>|null $etablissement
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

