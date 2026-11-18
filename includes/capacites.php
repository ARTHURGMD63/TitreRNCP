<?php
/**
 * Ce que chaque formule donne le droit de faire.
 *
 * `includes/sponsoring.php` dit ce qu'une mise en avant coûte ; ce fichier dit
 * ce qui est compris dans l'abonnement. Les deux pages de publication
 * (création et modification d'un événement) s'y réfèrent, et le quota est
 * revérifié à l'enregistrement : la case « offre flash » est dans le
 * formulaire, elle se recoche à la console.
 *
 * La grille ci-dessous est la traduction du contrat partenaire (SL-03). Elle
 * vit ici et nulle part ailleurs : un quota recopié dans une page finit par
 * diverger de celui qui est facturé.
 */

require_once __DIR__ . '/crm.php';
require_once __DIR__ . '/comptes.php';
require_once __DIR__ . '/sponsoring.php';

/**
 * Grille des capacités, par code de formule — la grille tarifaire Outsee.
 *
 * `evenements_par_mois` : événements publiés compris dans l'abonnement ;
 * au-delà, chacun coûte PRIX_EVENEMENT_SUPPLEMENTAIRE. `evenements_debut`
 * est le quota des trois premiers mois (fondateur : 8, puis 6), null sinon.
 * `flash_par_mois` à null vaut « illimité ». `mises_en_avant_offertes` est le
 * nombre de sponsorisations à 0 € comprises dans l'abonnement, par mois.
 *
 * @return array<string, array{evenements_par_mois:int, evenements_debut:?int, flash_par_mois:?int, mises_en_avant_offertes:int}>
 */
function grilleCapacites(): array
{
    return [
        'aucune'    => ['evenements_par_mois' => 0,  'evenements_debut' => null, 'flash_par_mois' => 0, 'mises_en_avant_offertes' => 0],
        'bde'       => ['evenements_par_mois' => 4,  'evenements_debut' => null, 'flash_par_mois' => 0, 'mises_en_avant_offertes' => 0],
        'fondateur' => ['evenements_par_mois' => 6,  'evenements_debut' => 8,    'flash_par_mois' => 1, 'mises_en_avant_offertes' => 0],
        'club'      => ['evenements_par_mois' => 12, 'evenements_debut' => null, 'flash_par_mois' => 0, 'mises_en_avant_offertes' => 0],
        'essentiel' => ['evenements_par_mois' => 6,  'evenements_debut' => null, 'flash_par_mois' => 0, 'mises_en_avant_offertes' => 0],
        'premium'   => ['evenements_par_mois' => 15, 'evenements_debut' => null, 'flash_par_mois' => 1, 'mises_en_avant_offertes' => 1],
    ];
}

/**
 * Les capacités d'un établissement, d'après la formule de sa fiche client.
 *
 * Une formule inconnue — une ligne ancienne, un code retiré de la grille —
 * ou qui ne va pas avec le type du compte (la formule Club sur un bar, la
 * formule BDE sur un restaurant) retombe sur « aucune » plutôt que de tout
 * autoriser : en cas de doute, on ne distribue pas des droits qui n'ont pas
 * été payés.
 *
 * Le quota d'événements renvoyé est celui d'aujourd'hui : un fondateur dans
 * ses trois mois offerts (`essai_jusqu_au` pas encore passée) en a 8, ensuite 6.
 *
 * @return array{offre:string, evenements_par_mois:int, evenements_debut:?int, flash_par_mois:?int, mises_en_avant_offertes:int, evenements_quota_actuel:int, en_periode_offerte:bool}
 */
function capacitesEtablissement(PDO $pdo, int $etablissementId, ?int $maintenant = null): array
{
    $client = abonnementEtablissement($pdo, $etablissementId);
    $offre  = (string) ($client['offre'] ?? 'aucune');
    $grille = grilleCapacites();

    $stmt = $pdo->prepare('SELECT * FROM etablissements WHERE id = ?');
    $stmt->execute([$etablissementId]);
    $etab = $stmt->fetch();
    if ($etab && (!formuleCompatible((string) $etab['type'], $offre) || !compteValide($etab))) {
        $offre = 'aucune';
    }

    $cap = $grille[$offre] ?? $grille['aucune'];

    $ts = $maintenant ?? time();
    $essai = $client['essai_jusqu_au'] ?? null;
    $enPeriodeOfferte = $offre !== 'aucune' && $essai !== null && $ts <= strtotime($essai . ' 23:59:59');

    $quota = $cap['evenements_par_mois'];
    if ($cap['evenements_debut'] !== null && $enPeriodeOfferte) {
        $quota = $cap['evenements_debut'];
    }

    return ['offre' => $offre] + $cap + [
        'evenements_quota_actuel' => $quota,
        'en_periode_offerte'      => $enPeriodeOfferte,
    ];
}

// ─── Le registre des achats ───────────────────────────────────────────────
//
// Tout ce qui se consomme (un événement publié, une offre flash, une mise en
// avant) s'écrit dans `achats_partenaires` à l'instant où c'est consommé, et
// n'en sort jamais. Les quotas se lisent là, pas dans `evenements` : supprimer
// un événement ne rend donc rien, et ne fait pas disparaître ce qu'il coûtait.

/** Le mois courant, à l'heure de Paris : 'AAAA-MM'. */
function moisCourant(): string
{
    return date('Y-m');
}

/**
 * Verrouille l'établissement le temps d'une transaction.
 *
 * À appeler après beginTransaction(), avant de lire un quota : deux requêtes
 * simultanées ne lisent plus le même « il reste une place » pour la
 * consommer toutes les deux.
 */
function verrouillerEtablissement(PDO $pdo, int $etablissementId): void
{
    $pdo->prepare('SELECT id FROM etablissements WHERE id = ? FOR UPDATE')->execute([$etablissementId]);
}

/** @param ''|'formule' $formule */
function enregistrerAchat(PDO $pdo, int $etablissementId, ?int $evenementId, string $nature, float $montant, ?string $formule = null, ?string $libelle = null): void
{
    $pdo->prepare(
        'INSERT INTO achats_partenaires (etablissement_id, evenement_id, nature, mois, montant, formule, libelle)
         VALUES (?,?,?,?,?,?,?)'
    )->execute([$etablissementId, $evenementId, $nature, moisCourant(), round($montant, 2), $formule, $libelle]);
}

/**
 * Inscrit au registre ce qu'un événement vient de consommer.
 *
 * Une seule fonction pour l'application et pour le site : une publication qui
 * ne passerait pas par ici échapperait aux quotas.
 *
 * `$avant` est la ligne de l'événement avant modification (null à la
 * création), `$apres` la ligne enregistrée. Ne s'écrit que ce qui change :
 *   - création : l'événement (avec son supplément éventuel), son offre flash,
 *     sa mise en avant ;
 *   - modification : une offre flash nouvellement cochée (une seule fois par
 *     événement, la décocher puis la recocher ne la consomme pas deux fois),
 *     une mise en avant nouvelle ou dont la formule change.
 *
 * @param array<string, mixed>|null $avant
 * @param array<string, mixed>      $apres
 */
function enregistrerAchatsEvenement(PDO $pdo, int $etablissementId, int $evenementId, ?array $avant, array $apres): void
{
    $titre = (string) ($apres['titre'] ?? '');

    if ($avant === null) {
        enregistrerAchat($pdo, $etablissementId, $evenementId, 'evenement', (float) ($apres['supplement_tarif'] ?? 0), null, $titre);
    }

    if (!empty($apres['is_flash']) && empty($avant['is_flash'])) {
        $deja = $pdo->prepare("SELECT COUNT(*) FROM achats_partenaires WHERE evenement_id = ? AND nature = 'flash'");
        $deja->execute([$evenementId]);
        if (!(int) $deja->fetchColumn()) {
            enregistrerAchat($pdo, $etablissementId, $evenementId, 'flash', 0, null, $titre);
        }
    }

    // `sponsor_nouvelle` vient de resoudreSponsoring() : un nouvel achat, même de
    // la même formule qu'avant (une mise en avant terminée qu'on rachète).
    $sponsoNouveau = array_key_exists('sponsor_nouvelle', $apres)
        ? (bool) $apres['sponsor_nouvelle'] && !empty($apres['is_sponsorise'])
        : !empty($apres['is_sponsorise'])
            && (empty($avant['is_sponsorise']) || ($avant['sponsor_formule'] ?? null) !== ($apres['sponsor_formule'] ?? null));
    if ($sponsoNouveau) {
        enregistrerAchat(
            $pdo, $etablissementId, $evenementId, 'sponsoring',
            (float) ($apres['sponsor_tarif'] ?? 0), (string) ($apres['sponsor_formule'] ?? ''), $titre
        );
    }
}

/**
 * Combien d'événements publiés ce mois-ci, et combien de plus que le quota ?
 *
 * @param array<string, mixed> $capacites
 * @return array{publies:int, quota:int, restants:int, supplementaires:int, a_payer:float}
 */
function usageEvenementsMois(PDO $pdo, int $etablissementId, array $capacites): array
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*), COALESCE(SUM(montant), 0) FROM achats_partenaires
          WHERE etablissement_id = ? AND nature = 'evenement' AND mois = ?"
    );
    $stmt->execute([$etablissementId, moisCourant()]);
    [$publies, $aPayer] = array_map('floatval', $stmt->fetch(PDO::FETCH_NUM));

    $quota = (int) $capacites['evenements_quota_actuel'];

    return [
        'publies'         => (int) $publies,
        'quota'           => $quota,
        'restants'        => max(0, $quota - (int) $publies),
        'supplementaires' => max(0, (int) $publies - $quota),
        'a_payer'         => round($aPayer, 2),
    ];
}

/**
 * Le prochain événement publié sera-t-il un supplément payant ?
 *
 * @param array<string, mixed> $capacites
 */
function evenementSeraSupplementaire(PDO $pdo, int $etablissementId, array $capacites): bool
{
    return usageEvenementsMois($pdo, $etablissementId, $capacites)['restants'] <= 0;
}

/**
 * Reste-t-il une offre flash à publier ce mois-ci ?
 *
 * Le mois est celui du calendrier. Un événement qui a déjà consommé son offre
 * flash peut la garder en étant modifié : c'est `$evenementExclu` qui le dit.
 *
 * @param array<string, mixed> $capacites
 */
function flashDisponible(PDO $pdo, int $etablissementId, array $capacites, ?int $evenementExclu = null): bool
{
    if ($capacites['flash_par_mois'] === null) {
        return true;
    }
    if ((int) $capacites['flash_par_mois'] === 0) {
        return false;
    }

    if ($evenementExclu !== null) {
        $deja = $pdo->prepare("SELECT COUNT(*) FROM achats_partenaires WHERE evenement_id = ? AND nature = 'flash'");
        $deja->execute([$evenementExclu]);
        if ((int) $deja->fetchColumn()) {
            return true;
        }
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM achats_partenaires WHERE etablissement_id = ? AND nature = 'flash' AND mois = ?"
    );
    $stmt->execute([$etablissementId, moisCourant()]);

    return (int) $stmt->fetchColumn() < (int) $capacites['flash_par_mois'];
}

/**
 * Mises en avant offertes non encore consommées ce mois-ci.
 *
 * Une mise en avant comprise dans l'abonnement s'écrit au registre comme un
 * sponsoring à 0 €, et non comme une absence : sans trace, on ne saurait pas
 * combien en ont déjà été consommées.
 *
 * @param array<string, mixed> $capacites
 */
function misesEnAvantOffertesRestantes(PDO $pdo, int $etablissementId, array $capacites): int
{
    $droit = (int) $capacites['mises_en_avant_offertes'];
    if ($droit <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM achats_partenaires
          WHERE etablissement_id = ? AND nature = 'sponsoring' AND montant = 0 AND mois = ?"
    );
    $stmt->execute([$etablissementId, moisCourant()]);

    return max(0, $droit - (int) $stmt->fetchColumn());
}

/**
 * Tout ce que l'établissement a consommé et payé en plus ce mois-ci, dans la
 * forme que lit l'application : événements, offres flash, mises en avant, et
 * le total des achats hors abonnement.
 *
 * @param array<string, mixed> $capacites
 * @return array<string, mixed>
 */
function usagePartenaireMois(PDO $pdo, int $etablissementId, array $capacites): array
{
    $evenements = usageEvenementsMois($pdo, $etablissementId, $capacites);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM achats_partenaires WHERE etablissement_id = ? AND nature = 'flash' AND mois = ?"
    );
    $stmt->execute([$etablissementId, moisCourant()]);
    $flashUtilises = (int) $stmt->fetchColumn();
    $flashQuota    = $capacites['flash_par_mois'] === null ? null : (int) $capacites['flash_par_mois'];

    $stmt = $pdo->prepare(
        "SELECT COUNT(*), COALESCE(SUM(montant), 0), COALESCE(SUM(montant = 0), 0)
           FROM achats_partenaires WHERE etablissement_id = ? AND nature = 'sponsoring' AND mois = ?"
    );
    $stmt->execute([$etablissementId, moisCourant()]);
    [$nbSponso, $coutSponso, $nbOfferts] = array_map('floatval', $stmt->fetch(PDO::FETCH_NUM));

    $offertesDroit = (int) $capacites['mises_en_avant_offertes'];

    return [
        'mois'       => moisCourant(),
        'evenements' => $evenements + ['prix_supplement' => (float) PRIX_EVENEMENT_SUPPLEMENTAIRE],
        'flash'      => [
            'utilises' => $flashUtilises,
            'quota'    => $flashQuota,
            'restants' => $flashQuota === null ? null : max(0, $flashQuota - $flashUtilises),
        ],
        'mises_en_avant' => [
            'offertes_droit'     => $offertesDroit,
            'offertes_restantes' => max(0, $offertesDroit - (int) $nbOfferts),
            'achetees'           => (int) $nbSponso - (int) $nbOfferts,
            'cout'               => round($coutSponso, 2),
        ],
        'achats_total' => round($evenements['a_payer'] + $coutSponso, 2),
    ];
}

/**
 * Ce qu'une demande de mise en avant devient pour un événement.
 *
 * Une seule fonction pour l'application et pour le site, pour que la règle ne
 * dépende pas de l'écran :
 *
 *   - une mise en avant EN COURS est payée jusqu'à sa fin : elle ne se change
 *     pas et ne se retire pas. Sans cela, retirer puis recocher la même
 *     formule la facturerait deux fois pour la même période, et passer du Top
 *     du fil au Coup de projecteur en rognerait six jours sans rien rendre ;
 *   - une mise en avant TERMINÉE reste dans l'historique de l'événement, sans
 *     rien coûter de plus : modifier le titre d'une soirée dont la mise en
 *     avant est finie ne la rachète pas. Pour en acheter une nouvelle, il faut
 *     la demander explicitement ;
 *   - un nouvel achat court à partir de maintenant, jusqu'à la fin de
 *     l'événement au plus ;
 *   - la mise en avant offerte de l'abonnement ne couvre qu'un Coup de
 *     projecteur.
 *
 * @param array<string, mixed> $capacites
 * @param array<string, mixed>|null $avant la ligne de l'événement avant modification
 * @return array{erreur:?string, nouvelle:bool, is_sponsorise:int, formule:?string, tarif:float, fin:?string}
 */
function resoudreSponsoring(PDO $pdo, int $etablissementId, array $capacites, ?array $avant, bool $demande, ?string $formule, string $dateHeure, ?string $dateFin): array
{
    $etaitSponso = $avant !== null && !empty($avant['is_sponsorise']);
    $conserver   = static fn (): array => [
        'erreur' => null, 'nouvelle' => false,
        'is_sponsorise' => $etaitSponso ? 1 : 0,
        'formule' => $etaitSponso ? ($avant['sponsor_formule'] ?? null) : null,
        'tarif'   => $etaitSponso ? (float) ($avant['sponsor_tarif'] ?? 0) : 0.0,
        'fin'     => $etaitSponso ? ($avant['sponsor_jusqu_au'] ?? null) : null,
    ];

    if ($avant !== null && sponsoringActif($avant)) {
        if ($demande && $formule === ($avant['sponsor_formule'] ?? null)) {
            return $conserver();
        }
        $jusquau = date('d/m à H\hi', (int) strtotime((string) ($avant['sponsor_jusqu_au'] ?? 'now')));
        return ['erreur' => "La mise en avant de cet événement est en cours jusqu'au $jusquau : elle est payée jusque-là et ne se change ni ne se retire."] + $conserver();
    }

    if (!$demande) {
        return $conserver();
    }

    if (!formuleSponsoringValide($formule)) {
        return ['erreur' => 'Choisis une formule de sponsoring.'] + $conserver();
    }

    $fin = finSponsoring((string) $formule, $dateHeure, null, $dateFin);
    if ($fin === null) {
        return ['erreur' => 'Cet événement est déjà terminé : il ne peut plus être mis en avant.'] + $conserver();
    }

    $tarif = tarifSponsoring((string) $formule);
    if (in_array($formule, formulesSponsoringOffrables(), true)
        && misesEnAvantOffertesRestantes($pdo, $etablissementId, $capacites) > 0) {
        $tarif = 0.0;
    }

    return ['erreur' => null, 'nouvelle' => true, 'is_sponsorise' => 1, 'formule' => (string) $formule, 'tarif' => $tarif, 'fin' => $fin];
}
