<?php
/**
 * Back-office fondateurs — indicateurs produit et alertes.
 *
 * Objectifs de la North Star, tableau de bord hebdomadaire, taux de présence
 * et signaux d'alerte de SL-07. Extrait de crm.php, qui le recharge : les
 * pages continuent de n'inclure que crm.php.
 *
 * crmAlertes() lit la trésorerie (financeSolde, FINANCE_PLANCHER) : les deux
 * fichiers se chargent ensemble, par crm.php.
 */

/**
 * Objectif de pass scannés par semaine, par jalon (SL-07 §1).
 *
 * @return array<string, int>
 */
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

// ─── Indicateurs produit (SL-07) ────────────────────────────────────────────

/**
 * Le tableau de bord hebdomadaire, sur 7 jours glissants.
 * `pass_scannes` est la North Star : c'est ce que la plaquette promet aux
 * partenaires, donc c'est ce que nous mesurons.
 *
 * @return array<string, mixed>
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
 *
 * @return list<array<string, mixed>>
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
