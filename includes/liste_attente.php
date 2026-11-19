<?php
/**
 * Liste d'attente du lancement — logique partagée entre les points d'API
 * (inscription, statut) et la page d'accueil.
 *
 * « Chaque pote inscrit avec ton lien te fait gagner 25 places » : pour que
 * ce soit vrai plutôt que simulé, chaque inscription peut porter l'id de
 * qui l'a parrainée (colonne parrain_id, migration v20), et le rang affiché
 * se recalcule à chaque lecture à partir des filleuls réels.
 *
 * Le code de parrainage était l'id écrit en base 36 (« LK » + base36(id)) :
 * séquentiel, donc devinable, et chacun pouvait s'attribuer les inscriptions
 * des autres ou lire leur rang (ETU-12). Depuis la migration v41, chaque
 * nouvelle inscription reçoit un code aléatoire (colonne `code`). Les
 * inscriptions plus anciennes, qui ont déjà partagé leur lien, gardent leur
 * ancien code tant qu'elles n'en ont pas d'autre.
 */

/** Places gagnées par filleul. */
const LISTE_ATTENTE_PLACES_PAR_FILLEUL = 25;

/** Filleuls qui comptent au plus : au-delà, le rang ne remonte plus. */
const LISTE_ATTENTE_FILLEULS_MAX = 10;

/** Caractères d'un code aléatoire : sans 0/O ni 1/I, qui se confondent. */
const LISTE_ATTENTE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

/** Longueur de la partie aléatoire (32^10, environ 10^15 codes). */
const LISTE_ATTENTE_CODE_LONGUEUR = 10;

/**
 * Ancien code de parrainage, dérivé de l'id. Ne sert plus qu'aux
 * inscriptions antérieures à la migration v41 (colonne `code` vide).
 */
function codeDepuisId(int $id): string
{
    return 'LK' . strtoupper(base_convert((string) $id, 10, 36));
}

/**
 * L'id porté par un ancien code de parrainage, ou null s'il n'a pas cette
 * forme. Ne dit rien de l'existence de la ligne : à vérifier par l'appelant.
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

/** Un nouveau code de parrainage, imprévisible. */
function codeParrainageAleatoire(): string
{
    $code = 'LK';
    $max  = strlen(LISTE_ATTENTE_ALPHABET) - 1;
    for ($i = 0; $i < LISTE_ATTENTE_CODE_LONGUEUR; $i++) {
        $code .= LISTE_ATTENTE_ALPHABET[random_int(0, $max)];
    }

    return $code;
}

/**
 * L'inscription que désigne un code de parrainage, ou null.
 *
 * D'abord un code aléatoire (colonne `code`). À défaut, un ancien code
 * « LK » + base36(id), accepté seulement pour une inscription qui n'a pas de
 * code aléatoire : celles d'après v41 ne se devinent donc plus par leur id.
 */
function idListeAttenteDepuisCode(PDO $pdo, string $code): ?int
{
    $code = strtoupper(trim($code));
    if ($code === '' || strlen($code) > 16) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id FROM liste_attente WHERE code = ?');
    $stmt->execute([$code]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }

    $ancien = idDepuisCode($code);
    if ($ancien === null) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id FROM liste_attente WHERE id = ? AND code IS NULL');
    $stmt->execute([$ancien]);

    return $stmt->fetchColumn() !== false ? $ancien : null;
}

/**
 * Le rang affiché : 25 places par filleul, au plus LISTE_ATTENTE_FILLEULS_MAX
 * filleuls comptés, sans jamais remonter avant la première place. Sans
 * plafond, quelques dizaines d'adresses jetables menaient n'importe qui en
 * tête de la liste (ETU-12).
 */
function positionAffichee(int $position, int $filleuls): int
{
    $comptes = max(0, min(LISTE_ATTENTE_FILLEULS_MAX, $filleuls));

    return max(1, $position - $comptes * LISTE_ATTENTE_PLACES_PAR_FILLEUL);
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
    $existe = $pdo->prepare('SELECT code FROM liste_attente WHERE id = ?');
    $existe->execute([$id]);
    $ligne = $existe->fetch(PDO::FETCH_ASSOC);
    if (!$ligne) {
        return null;
    }

    $rang = $pdo->prepare('SELECT COUNT(*) FROM liste_attente WHERE id <= ?');
    $rang->execute([$id]);
    $position = (int) $rang->fetchColumn();

    $filleuls = $pdo->prepare('SELECT COUNT(*) FROM liste_attente WHERE parrain_id = ?');
    $filleuls->execute([$id]);
    $nbFilleuls = (int) $filleuls->fetchColumn();

    return [
        'position'          => $position,
        'position_affichee' => positionAffichee($position, $nbFilleuls),
        'filleuls'          => $nbFilleuls,
        'code'              => !empty($ligne['code']) ? (string) $ligne['code'] : codeDepuisId($id),
    ];
}
