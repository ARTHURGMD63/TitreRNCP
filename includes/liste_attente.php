<?php
/**
 * Liste d'attente du lancement — logique partagée entre les points d'API
 * (inscription, statut) et la page d'accueil.
 *
 * « Chaque pote inscrit avec ton lien te fait gagner 25 places » : pour que
 * ce soit vrai plutôt que simulé, chaque inscription peut porter l'id de
 * qui l'a parrainée (colonne parrain_id, migration v20), et le rang affiché
 * se recalcule à chaque lecture à partir des filleuls réels.
 */

/**
 * Code de parrainage d'une inscription, dérivé de son id.
 *
 * Pas de colonne à part, pas de génération aléatoire à dédupliquer : l'id
 * est déjà unique, il suffit de l'écrire autrement. base36 tient plus court
 * qu'un id décimal à mesure que la liste grandit.
 */
function codeDepuisId(int $id): string
{
    return 'LK' . strtoupper(base_convert((string) $id, 10, 36));
}

/**
 * L'id porté par un code de parrainage, ou null s'il n'a pas cette forme.
 * Ne dit rien de l'existence de la ligne : à vérifier par l'appelant.
 */
function idDepuisCode(string $code): ?int
{
    $code = strtoupper(trim($code));
    if (!str_starts_with($code, 'LK') || strlen($code) < 3) {
        return null;
    }
    $reste = substr($code, 2);
    if (!preg_match('/^[0-9A-Z]+$/', $reste)) {
        return null;
    }

    $id = (int) base_convert($reste, 36, 10);

    return $id > 0 ? $id : null;
}

/**
 * L'état d'une inscription : son rang réel, le nombre de filleuls qui ont
 * rejoint grâce à son code, et le rang affiché une fois le bonus de
 * parrainage appliqué.
 *
 * @return array{position:int,position_affichee:int,filleuls:int,code:string}|null
 *         null si l'id ne correspond à aucune inscription.
 */
function statutListeAttente(PDO $pdo, int $id): ?array
{
    $existe = $pdo->prepare('SELECT 1 FROM liste_attente WHERE id = ?');
    $existe->execute([$id]);
    if (!$existe->fetchColumn()) {
        return null;
    }

    $rang = $pdo->prepare('SELECT COUNT(*) FROM liste_attente WHERE id <= ?');
    $rang->execute([$id]);
    $position = (int) $rang->fetchColumn();

    $filleuls = $pdo->prepare('SELECT COUNT(*) FROM liste_attente WHERE parrain_id = ?');
    $filleuls->execute([$id]);
    $nbFilleuls = (int) $filleuls->fetchColumn();

    // 25 places gagnées par filleul, sans jamais remonter avant la première place.
    $positionAffichee = max(1, $position - $nbFilleuls * 25);

    return [
        'position'          => $position,
        'position_affichee' => $positionAffichee,
        'filleuls'          => $nbFilleuls,
        'code'              => codeDepuisId($id),
    ];
}
