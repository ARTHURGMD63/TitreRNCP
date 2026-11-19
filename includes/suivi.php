<?php
/**
 * Le suivi de l'application pour les administrateurs (api/v1/admin_suivi.php).
 *
 * Tout ce qu'il faut voir d'un coup d'œil depuis le téléphone : combien de
 * monde, quand, ce qui se publie, ce qui se vend, et ce qui casse. Lecture
 * seule : rien ici n'écrit.
 *
 * Chaque volet est calculé à part et protégé : une table absente (base pas
 * encore migrée) ou une requête qui échoue rend ce volet nul, journalise
 * l'erreur, et laisse les autres s'afficher. Un tableau de bord qui tombe en
 * entier au premier souci ne sert à rien le jour où il y a un souci.
 *
 * Les bornes de temps sont calculées en PHP, à l'heure de Paris, et passées
 * aux requêtes : NOW() dépend du fuseau de la session SQL (voir PAY-19).
 */

require_once __DIR__ . '/crm.php';
require_once __DIR__ . '/finances.php';
require_once __DIR__ . '/kpi.php';
require_once __DIR__ . '/log.php';

/** Jours couverts par les séries quotidiennes. */
const SUIVI_JOURS_SERIE = 30;
/** Jours sur lesquels se calculent les heures de pointe. */
const SUIVI_JOURS_HEURES = 14;

/**
 * Une série quotidienne complète : chaque jour de la fenêtre, à zéro s'il
 * n'a rien. Un trou dans une série se lit comme une absence de données.
 *
 * @param array<string, int> $parJour 'Y-m-d' => valeur
 * @return list<array{jour: string, n: int}>
 */
function suiviSerieQuotidienne(array $parJour, int $jours, ?int $maintenant = null): array
{
    $maintenant ??= time();
    $serie = [];
    for ($i = $jours - 1; $i >= 0; $i--) {
        $jour    = date('Y-m-d', strtotime("-$i day", $maintenant));
        $serie[] = ['jour' => $jour, 'n' => (int) ($parJour[$jour] ?? 0)];
    }

    return $serie;
}

/**
 * Moyenne d'actifs par heure de la journée (0 à 23), sur une fenêtre de
 * plusieurs jours : c'est ce qui montre les heures de pointe.
 *
 * @param array<int, int> $totalParHeure heure (0-23) => nombre de comptes actifs cumulé sur la fenêtre
 * @return list<array{heure: int, moyenne: float}>
 */
function suiviHeuresDePointe(array $totalParHeure, int $jours): array
{
    $jours  = max(1, $jours);
    $heures = [];
    for ($h = 0; $h < 24; $h++) {
        $heures[] = ['heure' => $h, 'moyenne' => round(((int) ($totalParHeure[$h] ?? 0)) / $jours, 1)];
    }

    return $heures;
}

/** Évolution en pourcentage d'une période sur la précédente, null sans base. */
function suiviEvolution(float $actuel, float $precedent): ?int
{
    if ($precedent <= 0) {
        return null;
    }

    return (int) round(($actuel - $precedent) / $precedent * 100);
}

/**
 * Lance un volet ; en cas d'échec, le journalise et rend null.
 *
 * @template T
 * @param callable(): T $calcul
 * @return T|null
 */
function suiviVolet(string $nom, callable $calcul): mixed
{
    try {
        return $calcul();
    } catch (Throwable $e) {
        logErreur('Suivi admin : volet « ' . $nom . ' » indisponible', $e);

        return null;
    }
}

/**
 * @param list<mixed> $params
 * @return array<string, int> 'Y-m-d' => nombre
 */
function suiviCompterParJour(PDO $pdo, string $sql, array $params): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $parJour = [];
    foreach ($stmt->fetchAll(PDO::FETCH_NUM) as [$jour, $n]) {
        $parJour[(string) $jour] = (int) $n;
    }

    return $parJour;
}

/** @return array<string, mixed> */
function suiviUtilisateurs(PDO $pdo, int $maintenant): array
{
    $jour  = date('Y-m-d 00:00:00', $maintenant);
    $j7    = date('Y-m-d H:i:s', $maintenant - 7 * 86400);
    $j30   = date('Y-m-d H:i:s', $maintenant - 30 * 86400);
    $j60   = date('Y-m-d H:i:s', $maintenant - 60 * 86400);
    $debut = date('Y-m-d 00:00:00', strtotime('-' . (SUIVI_JOURS_SERIE - 1) . ' day', $maintenant));

    $comptes = $pdo->query(
        "SELECT type, COUNT(*) AS n FROM users WHERE type IN ('etudiant','partenaire','admin') GROUP BY type"
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    $nouveaux = $pdo->prepare(
        "SELECT SUM(created_at >= ?) AS jour, SUM(created_at >= ?) AS semaine, SUM(created_at >= ?) AS mois,
                SUM(created_at >= ? AND created_at < ?) AS mois_precedent
           FROM users WHERE type IN ('etudiant','partenaire') AND created_at >= ?"
    );
    $nouveaux->execute([$jour, $j7, $j30, $j60, $j30, $j60]);
    $n = $nouveaux->fetch(PDO::FETCH_ASSOC) ?: [];

    $parJour = suiviCompterParJour(
        $pdo,
        "SELECT DATE(created_at) AS jour, COUNT(*) FROM users
          WHERE type IN ('etudiant','partenaire') AND created_at >= ? GROUP BY DATE(created_at)",
        [$debut]
    );
    $record = $pdo->query(
        "SELECT DATE(created_at) AS jour, COUNT(*) AS n FROM users
          WHERE type IN ('etudiant','partenaire') GROUP BY DATE(created_at) ORDER BY n DESC, jour DESC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);

    // Actifs : comptes qui ont utilisé l'application (activite_heures, v42).
    $actifs = $pdo->prepare(
        'SELECT COUNT(DISTINCT CASE WHEN heure >= ? THEN user_id END) AS jour,
                COUNT(DISTINCT CASE WHEN heure >= ? THEN user_id END) AS semaine,
                COUNT(DISTINCT user_id) AS mois
           FROM activite_heures WHERE heure >= ?'
    );
    $actifs->execute([$jour, $j7, $j30]);
    $a = $actifs->fetch(PDO::FETCH_ASSOC) ?: [];

    $debutHeures = date('Y-m-d H:00:00', $maintenant - SUIVI_JOURS_HEURES * 86400);
    $stmt = $pdo->prepare('SELECT heure, COUNT(*) AS n FROM activite_heures WHERE heure >= ? GROUP BY heure');
    $stmt->execute([$debutHeures]);
    $parHeureDuJour = [];
    $picHoraire     = null;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $h = (int) date('G', (int) strtotime((string) $l['heure']));
        $parHeureDuJour[$h] = ($parHeureDuJour[$h] ?? 0) + (int) $l['n'];
        if ($picHoraire === null || (int) $l['n'] > $picHoraire['n']) {
            $picHoraire = ['heure' => (string) $l['heure'], 'n' => (int) $l['n']];
        }
    }

    return [
        'etudiants'      => (int) ($comptes['etudiant'] ?? 0),
        'etablissements' => (int) ($comptes['partenaire'] ?? 0),
        'admins'         => (int) ($comptes['admin'] ?? 0),
        'nouveaux'       => [
            'jour'      => (int) ($n['jour'] ?? 0),
            'semaine'   => (int) ($n['semaine'] ?? 0),
            'mois'      => (int) ($n['mois'] ?? 0),
            'evolution' => suiviEvolution((float) ($n['mois'] ?? 0), (float) ($n['mois_precedent'] ?? 0)),
        ],
        'actifs'         => [
            'jour'    => (int) ($a['jour'] ?? 0),
            'semaine' => (int) ($a['semaine'] ?? 0),
            'mois'    => (int) ($a['mois'] ?? 0),
        ],
        'serie_inscriptions' => suiviSerieQuotidienne($parJour, SUIVI_JOURS_SERIE, $maintenant),
        'record_inscriptions' => $record ? ['jour' => (string) $record['jour'], 'n' => (int) $record['n']] : null,
        'heures_de_pointe'    => suiviHeuresDePointe($parHeureDuJour, SUIVI_JOURS_HEURES),
        'pic_horaire'         => $picHoraire,
    ];
}

/** @return array<string, mixed> */
function suiviEvenements(PDO $pdo, int $maintenant): array
{
    $maintenantSql = date('Y-m-d H:i:s', $maintenant);
    $mois          = date('Y-m-01 00:00:00', $maintenant);
    $debut         = date('Y-m-d 00:00:00', strtotime('-' . (SUIVI_JOURS_SERIE - 1) . ' day', $maintenant));

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total,
                SUM(created_at >= ?) AS publies_mois,
                SUM(annule_le IS NULL AND COALESCE(fin_effective, date_heure) >= ?) AS a_venir,
                SUM(annule_le >= ?) AS annules_mois
           FROM evenements"
    );
    $stmt->execute([$mois, $maintenantSql, $mois]);
    $e = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare(
        "SELECT SUM(statut <> 'annule' AND created_at >= ?) AS pass_mois,
                SUM(statut = 'checkin' AND checkin_le >= ?) AS entrees_mois
           FROM inscriptions WHERE created_at >= ? OR checkin_le >= ?"
    );
    $stmt->execute([$mois, $mois, $mois, $mois]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $parJour = suiviCompterParJour(
        $pdo,
        "SELECT DATE(created_at) AS jour, COUNT(*) FROM inscriptions
          WHERE statut <> 'annule' AND created_at >= ? GROUP BY DATE(created_at)",
        [$debut]
    );

    return [
        'total'        => (int) ($e['total'] ?? 0),
        'publies_mois' => (int) ($e['publies_mois'] ?? 0),
        'a_venir'      => (int) ($e['a_venir'] ?? 0),
        'annules_mois' => (int) ($e['annules_mois'] ?? 0),
        'pass_mois'    => (int) ($p['pass_mois'] ?? 0),
        'entrees_mois' => (int) ($p['entrees_mois'] ?? 0),
        'taux_presence_30j' => tauxPresenceGlobal($pdo),
        'serie_pass'   => suiviSerieQuotidienne($parJour, SUIVI_JOURS_SERIE, $maintenant),
    ];
}

/** @return array<string, mixed> */
function suiviAbonnements(PDO $pdo, int $maintenant): array
{
    $mois = date('Y-m-01', $maintenant);
    $parOffre = $pdo->query(
        "SELECT offre, COUNT(*) AS n, SUM(statut = 'essai') AS essais, COALESCE(SUM(mrr), 0) AS mrr
           FROM crm_clients
          WHERE statut IN ('actif','essai') AND offre <> 'aucune'
          GROUP BY offre"
    )->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT SUM(signe_le >= ?) AS nouveaux_mois, SUM(perdu_le >= ?) AS perdus_mois FROM crm_clients"
    );
    $stmt->execute([$mois, $mois]);
    $m = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $offres = [];
    foreach ($parOffre as $l) {
        $offres[] = [
            'offre'   => (string) $l['offre'],
            'libelle' => crmLibelleOffre((string) $l['offre']),
            'nombre'  => (int) $l['n'],
            'essais'  => (int) $l['essais'],
            'mrr'     => round((float) $l['mrr'], 2),
        ];
    }
    usort($offres, static fn (array $a, array $b): int => $b['nombre'] <=> $a['nombre']);

    return [
        'actifs'          => array_sum(array_column($offres, 'nombre')),
        'par_offre'       => $offres,
        'nouveaux_mois'   => (int) ($m['nouveaux_mois'] ?? 0),
        'perdus_mois'     => (int) ($m['perdus_mois'] ?? 0),
        'mrr'             => round(crmMrr($pdo), 2),
        'mrr_engage'      => round(crmMrrEngage($pdo), 2),
        'clients_payants' => crmClientsPayants($pdo),
    ];
}

/** Libellés des options payantes (achats_partenaires.nature). */
function suiviLibelleOption(string $nature): string
{
    return match ($nature) {
        'evenement'  => 'Événements en supplément',
        'flash'      => 'Offres flash',
        'sponsoring' => 'Mises en avant',
        default      => $nature,
    };
}

/** @return array<string, mixed> */
function suiviOptions(PDO $pdo, int $maintenant): array
{
    $mois      = date('Y-m', $maintenant);
    $precedent = date('Y-m', strtotime('first day of last month', $maintenant));
    $stmt = $pdo->prepare(
        'SELECT mois, nature, COUNT(*) AS n, COALESCE(SUM(montant), 0) AS montant
           FROM achats_partenaires WHERE mois IN (?, ?) GROUP BY mois, nature'
    );
    $stmt->execute([$mois, $precedent]);
    $lignes = [];
    $totaux = [$mois => 0.0, $precedent => 0.0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $totaux[(string) $l['mois']] += (float) $l['montant'];
        if ((string) $l['mois'] !== $mois) {
            continue;
        }
        $lignes[] = [
            'nature'  => (string) $l['nature'],
            'libelle' => suiviLibelleOption((string) $l['nature']),
            'nombre'  => (int) $l['n'],
            'montant' => round((float) $l['montant'], 2),
        ];
    }

    return [
        'mois'          => $mois,
        'detail'        => $lignes,
        'total_mois'    => round($totaux[$mois], 2),
        'total_precedent' => round($totaux[$precedent], 2),
    ];
}

/**
 * @param array<string, mixed>|null $abonnements
 * @param array<string, mixed>|null $options
 * @return array<string, mixed>
 */
function suiviRevenus(PDO $pdo, int $maintenant, ?array $abonnements, ?array $options): array
{
    $serie   = financeSerieMensuelle($pdo, 6);
    $stmt    = $pdo->prepare('SELECT mois, COALESCE(SUM(montant), 0) FROM achats_partenaires WHERE mois >= ? GROUP BY mois');
    $stmt->execute([array_key_first($serie)]);
    $optionsParMois = array_map('floatval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));

    $mois = [];
    foreach ($serie as $cle => $m) {
        $mois[] = [
            'mois'     => $cle,
            'recettes' => round($m['recettes'], 2),
            'options'  => round($optionsParMois[$cle] ?? 0, 2),
        ];
    }
    $courant = financeMois($pdo, date('Y-m', $maintenant));

    return [
        'mrr'              => $abonnements['mrr'] ?? round(crmMrr($pdo), 2),
        'options_mois'     => $options['total_mois'] ?? 0,
        'recettes_mois'    => round($courant['recettes'], 2),
        'depenses_mois'    => round($courant['depenses'], 2),
        'resultat_mois'    => round($courant['resultat'], 2),
        'tresorerie'       => round(financeSolde($pdo), 2),
        'serie_6_mois'     => $mois,
    ];
}

/** @return array<string, mixed> */
function suiviSante(PDO $pdo, int $maintenant): array
{
    $j1 = date('Y-m-d H:i:s', $maintenant - 86400);

    $stmt = $pdo->prepare(
        "SELECT SUM(resolu_le IS NULL) AS ouvertes,
                SUM(resolu_le IS NULL AND source = 'serveur') AS serveur,
                SUM(resolu_le IS NULL AND source = 'app') AS app,
                SUM(derniere_le >= ?) AS vues_24h,
                SUM(premiere_le >= ?) AS nouvelles_24h
           FROM journal_erreurs"
    );
    $stmt->execute([$j1, $j1]);
    $e = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $compter = static function (string $sql, array $params = []) use ($pdo): ?int {
        try {
            $s = $pdo->prepare($sql);
            $s->execute($params);

            return (int) $s->fetchColumn();
        } catch (Throwable) {
            return null;
        }
    };

    return [
        'erreurs_ouvertes'      => (int) ($e['ouvertes'] ?? 0),
        'erreurs_serveur'       => (int) ($e['serveur'] ?? 0),
        'plantages_app'         => (int) ($e['app'] ?? 0),
        'erreurs_vues_24h'      => (int) ($e['vues_24h'] ?? 0),
        'erreurs_nouvelles_24h' => (int) ($e['nouvelles_24h'] ?? 0),
        'push_en_echec_24h'     => $compter("SELECT COUNT(*) FROM push_envois WHERE statut = 'echec' AND created_at >= ?", [$j1]),
        // envoyer_apres est en UTC (includes/push.php) : on compare en UTC.
        'push_en_attente'       => $compter("SELECT COUNT(*) FROM push_envois WHERE statut = 'en_attente' AND envoyer_apres < ?", [gmdate('Y-m-d H:i:s', $maintenant - 900)]),
        'paiements_en_attente'  => $compter("SELECT COUNT(*) FROM paiements_evenements WHERE statut = 'en_attente' AND created_at >= ?", [$j1]),
        'signalements'          => $compter("SELECT COUNT(*) FROM user_reports WHERE statut = 'nouveau'"),
        'bde_en_attente'        => $compter("SELECT COUNT(*) FROM etablissements WHERE verification = 'en_attente'"),
    ];
}

/**
 * Le tableau de bord complet.
 *
 * @return array<string, mixed>
 */
function suiviTableauDeBord(PDO $pdo, ?int $maintenant = null): array
{
    $maintenant ??= time();
    $abonnements = suiviVolet('abonnements', static fn () => suiviAbonnements($pdo, $maintenant));
    $options     = suiviVolet('options', static fn () => suiviOptions($pdo, $maintenant));

    return [
        'genere_le'    => date('Y-m-d H:i:s', $maintenant),
        'utilisateurs' => suiviVolet('utilisateurs', static fn () => suiviUtilisateurs($pdo, $maintenant)),
        'evenements'   => suiviVolet('événements', static fn () => suiviEvenements($pdo, $maintenant)),
        'abonnements'  => $abonnements,
        'options'      => $options,
        'revenus'      => suiviVolet('revenus', static fn () => suiviRevenus($pdo, $maintenant, $abonnements, $options)),
        'sante'        => suiviVolet('santé', static fn () => suiviSante($pdo, $maintenant)),
    ];
}

/**
 * Les erreurs du journal, les plus récentes d'abord.
 *
 * @return list<array<string, mixed>>
 */
function suiviErreurs(PDO $pdo, string $filtre = 'ouvertes', int $limite = 100): array
{
    $ou = match ($filtre) {
        'resolues' => 'resolu_le IS NOT NULL',
        'app'      => "resolu_le IS NULL AND source = 'app'",
        'serveur'  => "resolu_le IS NULL AND source = 'serveur'",
        default    => 'resolu_le IS NULL',
    };
    $stmt = $pdo->prepare(
        "SELECT id, source, message, detail, contexte, occurrences, premiere_le, derniere_le, resolu_le
           FROM journal_erreurs WHERE $ou ORDER BY derniere_le DESC, id DESC LIMIT ?"
    );
    $stmt->bindValue(1, max(1, min(200, $limite)), PDO::PARAM_INT);
    $stmt->execute();

    return array_map(static function (array $l): array {
        $contexte = json_decode((string) ($l['contexte'] ?? ''), true);

        return [
            'id'          => (int) $l['id'],
            'source'      => (string) $l['source'],
            'message'     => (string) $l['message'],
            'detail'      => $l['detail'] !== null ? (string) $l['detail'] : null,
            'contexte'    => is_array($contexte) ? $contexte : null,
            'occurrences' => (int) $l['occurrences'],
            'premiere_le' => (string) $l['premiere_le'],
            'derniere_le' => (string) $l['derniere_le'],
            'resolu_le'   => $l['resolu_le'] !== null ? (string) $l['resolu_le'] : null,
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}
