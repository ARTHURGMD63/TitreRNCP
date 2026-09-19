<?php
/**
 * Agrégats mis en cache.
 *
 * Certaines valeurs sont lues sur chaque page par chaque visiteur, et
 * changent une fois par jour : la note moyenne d'un bar, la liste des écoles
 * représentées. Les recalculer à chaque affichage revient à faire payer à
 * mille utilisateurs le même balayage de table, mille fois par minute, pour
 * obtenir mille fois le même chiffre.
 *
 * Le contrat de ce fichier : une fonction par agrégat, la requête complète à
 * l'intérieur, le cache autour. Les pages appelantes ne savent pas s'il y a
 * eu une requête ou non, et n'ont pas à le savoir.
 *
 * Le choix des durées de vie suit une règle simple — combien de temps une
 * valeur périmée reste-t-elle acceptable à l'écran ? Une note moyenne qui
 * met cinq minutes à intégrer un nouvel avis ne gêne personne. Un compteur
 * de places restantes, lui, n'a rien à faire ici : il passe par le flux
 * temps réel (voir includes/temps_reel.php).
 */

require_once __DIR__ . '/cache.php';

/** Durée de vie des agrégats d'affichage, en secondes. */
const AGREGAT_TTL = 300;

/**
 * Note moyenne et nombre d'avis, par établissement.
 *
 * Remplace deux sous-requêtes corrélées qui vivaient dans la requête du hub :
 *
 *     (SELECT ROUND(AVG(a.note),1) FROM avis a JOIN evenements pe ...)
 *     (SELECT COUNT(*)             FROM avis a JOIN evenements pe ...)
 *
 * Chacune refaisait la jointure avis × evenements pour CHAQUE carte affichée,
 * et deux soirées du même bar la refaisaient deux fois pour le même résultat.
 * Une seule requête groupée les remplace, et son résultat sert à tout le
 * monde pendant cinq minutes.
 *
 * @return array<int,array{note:float,nb:int}>
 */
function notesEtablissements(PDO $pdo): array
{
    return cacheRemember('notes_etablissements', AGREGAT_TTL, static function () use ($pdo): array {
        $stmt = $pdo->query(
            "SELECT pe.etablissement_id AS etab,
                    ROUND(AVG(a.note), 1) AS note,
                    COUNT(*)              AS nb
               FROM avis a
               JOIN evenements pe ON pe.id = a.evenement_id
              GROUP BY pe.etablissement_id"
        );

        $notes = [];
        foreach ($stmt->fetchAll() as $ligne) {
            $notes[(int) $ligne['etab']] = [
                'note' => (float) $ligne['note'],
                'nb'   => (int) $ligne['nb'],
            ];
        }

        return $notes;
    });
}

/**
 * À appeler après l'enregistrement d'un avis.
 *
 * Sans cela, l'étudiant qui vient de noter une soirée ne verrait son étoile
 * bouger que cinq minutes plus tard — et conclurait que son avis n'a pas été
 * pris en compte.
 */
function oublierNotesEtablissements(): void
{
    cacheOublier('notes_etablissements');
}

/**
 * Les écoles représentées parmi les étudiants inscrits, triées.
 *
 * Alimente le menu déroulant de l'annuaire. C'était un `SELECT DISTINCT` sur
 * toute la table `users` à chaque affichage de l'onglet « personnes », pour
 * produire une liste d'une vingtaine d'entrées qui ne bouge qu'à
 * l'inscription d'un étudiant d'une école encore absente.
 *
 * @return list<string>
 */
function ecolesRepresentees(PDO $pdo): array
{
    return cacheRemember('ecoles_representees', AGREGAT_TTL, static function () use ($pdo): array {
        $stmt = $pdo->query(
            "SELECT DISTINCT ecole
               FROM users
              WHERE type = 'etudiant' AND ecole IS NOT NULL AND ecole <> ''
              ORDER BY ecole"
        );

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    });
}

/** À appeler après la création d'un compte étudiant. */
function oublierEcolesRepresentees(): void
{
    cacheOublier('ecoles_representees');
}
