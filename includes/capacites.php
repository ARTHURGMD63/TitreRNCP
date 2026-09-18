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

/**
 * Grille des capacités, par code de formule.
 *
 * `flash_par_mois` à null vaut « illimité ». `mises_en_avant_offertes` est le
 * nombre de sponsorisations à 0 € comprises dans l'abonnement, par mois.
 *
 * @return array<string, array{flash_par_mois:?int, mises_en_avant_offertes:int}>
 */
function grilleCapacites(): array
{
    return [
        'aucune'    => ['flash_par_mois' => 0,    'mises_en_avant_offertes' => 0],
        'fondateur' => ['flash_par_mois' => 4,    'mises_en_avant_offertes' => 0],
        'essentiel' => ['flash_par_mois' => 4,    'mises_en_avant_offertes' => 0],
        'bde'       => ['flash_par_mois' => 4,    'mises_en_avant_offertes' => 0],
        'premium'   => ['flash_par_mois' => null, 'mises_en_avant_offertes' => 1],
    ];
}

/**
 * Les capacités d'un établissement, d'après la formule de sa fiche client.
 *
 * Une formule inconnue — une ligne ancienne, un code retiré de la grille —
 * retombe sur « aucune » plutôt que de tout autoriser : en cas de doute, on
 * ne distribue pas des droits qui n'ont pas été payés.
 *
 * @return array{offre:string, flash_par_mois:?int, mises_en_avant_offertes:int}
 */
function capacitesEtablissement(PDO $pdo, int $etablissementId): array
{
    $client = abonnementEtablissement($pdo, $etablissementId);
    $offre  = (string) ($client['offre'] ?? 'aucune');
    $grille = grilleCapacites();

    return ['offre' => $offre] + ($grille[$offre] ?? $grille['aucune']);
}

/**
 * Reste-t-il une offre flash à publier ce mois-ci ?
 *
 * Le mois est celui du calendrier, pas une fenêtre glissante : un quota
 * mensuel se lit sur un calendrier, et c'est la date de publication qui
 * compte, pas celle de la soirée. L'événement en cours de modification ne se
 * compte pas lui-même, sinon rééditer une offre flash existante déclencherait
 * son propre quota.
 */
function flashDisponible(PDO $pdo, int $etablissementId, array $capacites, ?int $evenementExclu = null): bool
{
    if ($capacites['flash_par_mois'] === null) {
        return true;
    }
    if ((int) $capacites['flash_par_mois'] === 0) {
        return false;
    }

    $sql = "SELECT COUNT(*) FROM evenements
            WHERE etablissement_id = ? AND is_flash = 1
              AND YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())";
    $par = [$etablissementId];
    if ($evenementExclu !== null) {
        $sql .= " AND id <> ?";
        $par[] = $evenementExclu;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($par);

    return (int) $stmt->fetchColumn() < (int) $capacites['flash_par_mois'];
}

/**
 * Mises en avant offertes non encore consommées ce mois-ci.
 *
 * Une mise en avant comprise dans l'abonnement s'écrit comme une ligne de
 * sponsoring à 0 €, et non comme une absence de sponsoring : sans trace, on
 * ne saurait pas combien en ont déjà été consommées.
 */
function misesEnAvantOffertesRestantes(PDO $pdo, int $etablissementId, array $capacites): int
{
    $droit = (int) $capacites['mises_en_avant_offertes'];
    if ($droit <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM evenements
         WHERE etablissement_id = ? AND is_sponsorise = 1 AND sponsor_tarif = 0
           AND YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())"
    );
    $stmt->execute([$etablissementId]);

    return max(0, $droit - (int) $stmt->fetchColumn());
}
