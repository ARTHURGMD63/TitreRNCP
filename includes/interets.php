<?php
require_once __DIR__ . '/log.php';
/**
 * Catalogue des centres d'intérêt.
 *
 * La liste vivait en double dans profil.php : une copie pour l'affichage du
 * formulaire, une seconde pour la validation du POST. Deux listes qui doivent
 * rester identiques finissent toujours par diverger, et celle qui gagne est
 * celle de la validation — l'étiquette absente de la seconde disparaissait
 * silencieusement à l'enregistrement. Une seule source ici, lue partout.
 *
 * « Techno » et « Bars » figurent au catalogue parce que des comptes les
 * portent déjà : les retirer aurait effacé ces valeurs au premier
 * enregistrement de profil.
 */

/** @return string[] Le catalogue, dans son ordre d'affichage. */
function interetsDisponibles(): array
{
    return [
        'Sorties', 'Soirées', 'Bars', 'Boîtes', 'Techno', 'Musique', 'Mixologie',
        'Running', 'Muscu', 'Vélo', 'Foot', 'Tennis', 'Yoga',
        'Cuisine', 'Voyage', 'Cinéma', 'Lecture', 'Art', 'Photo',
        'Gaming', 'Code', 'Échecs', 'Animaux', 'Bénévolat',
    ];
}

/**
 * Ne garde que les intérêts du catalogue, dans l'ordre du catalogue.
 * Tout ce qui vient d'un formulaire passe par ici.
 *
 * @param  mixed    $choisis Liste brute (typiquement $_POST['interests']).
 * @return string[]
 */
function filtrerInterets($choisis): array
{
    if (!is_array($choisis)) return [];

    $propres = array_filter(array_map(
        static fn($v) => is_string($v) ? trim($v) : '',
        $choisis
    ));

    return array_values(array_intersect(interetsDisponibles(), $propres));
}

/**
 * Décompose la colonne `users.interests`.
 * Les espaces autour des virgules sont tolérés : d'anciennes lignes en ont.
 *
 * @return string[]
 */
function interetsDepuisTexte(?string $texte): array
{
    if ($texte === null || trim($texte) === '') return [];

    return array_values(array_filter(array_map('trim', explode(',', $texte))));
}

/** Recompose la colonne `users.interests`. */
function interetsVersTexte(array $interets): string
{
    return implode(',', $interets);
}

/**
 * Reporte les intérêts d'un compte dans la table indexée `user_interets`.
 *
 * `users.interests` reste la source de vérité — c'est elle que lisent les
 * gabarits pour afficher les étiquettes. Mais une chaîne « techno,rock » ne
 * s'indexe pas : classer l'annuaire par goûts communs imposait un
 * FIND_IN_SET par intérêt et par profil, sur toute la table. La table dérivée
 * range la même information sous une forme que la base sait parcourir par
 * index (voir db_migrations_v15.sql).
 *
 * À appeler juste après chaque écriture de la colonne. Les deux seuls
 * endroits concernés sont l'inscription et la page « Moi » ; s'il s'en ajoute
 * un troisième, c'est cette fonction qu'il doit appeler.
 *
 * Tolérante aux pannes : si la table n'existe pas encore — migration v15 pas
 * passée — l'enregistrement du profil ne doit pas échouer pour autant. Le
 * classement retombe simplement sur un score nul, et la migration, qui sait
 * reconstruire la table depuis la colonne texte, rattrapera le retard.
 *
 * @param string[] $interets
 */
function synchroniserInterets(PDO $pdo, int $userId, array $interets): void
{
    $interets = array_values(array_unique(array_filter(array_map('trim', $interets))));

    try {
        // Effacer puis réécrire, plutôt que calculer la différence : une
        // liste de vingt-quatre entrées au maximum ne justifie pas la
        // complexité, et la transaction garantit qu'aucun visiteur ne voit
        // l'état intermédiaire — un profil momentanément sans aucun goût.
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM user_interets WHERE user_id = ?')->execute([$userId]);

        if ($interets) {
            $trous = implode(',', array_fill(0, count($interets), '(?, ?)'));
            $valeurs = [];
            foreach ($interets as $interet) {
                $valeurs[] = $userId;
                $valeurs[] = $interet;
            }
            $pdo->prepare("INSERT INTO user_interets (user_id, interet) VALUES $trous")
                ->execute($valeurs);
        }

        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logErreur('Synchronisation des intérêts impossible', $e, ['user' => $userId]);
    }
}

/**
 * Les cochés d'abord, le reste ensuite — l'ordre de rendu du sélecteur.
 *
 * @param  string[] $choisis
 * @return string[]
 */
function interetsTriesParSelection(array $choisis): array
{
    $catalogue = interetsDisponibles();

    return array_merge(
        array_values(array_intersect($catalogue, $choisis)),
        array_values(array_diff($catalogue, $choisis))
    );
}

/**
 * Le sélecteur d'étiquettes, partagé par l'inscription et le profil.
 *
 * Le comportement (cochés en tête, repli « +N » au-delà de quatre) est câblé
 * dans app.js sur #interets-liste. L'état visuel vient de la case elle-même
 * via `input:checked + .interest-tag` : aucune classe posée à côté, donc
 * rien qui puisse diverger de la valeur envoyée.
 *
 * @param string[] $choisis Les intérêts déjà retenus.
 */
function selecteurInteretsHtml(array $choisis, string $labelId = 'label-interets', int $maxVisible = 4): string
{
    $html = '<div class="interets-liste" id="interets-liste"'
          . ' data-max-visible="' . (int) $maxVisible . '"'
          . ' role="group" aria-labelledby="' . htmlspecialchars($labelId) . '">';

    foreach (interetsTriesParSelection($choisis) as $interet) {
        $coche = in_array($interet, $choisis, true);
        $html .= '<label class="interest-chip">'
               . '<input type="checkbox" name="interests[]" value="' . htmlspecialchars($interet) . '"'
               . ($coche ? ' checked' : '') . '>'
               . '<span class="interest-tag">' . htmlspecialchars($interet) . '</span>'
               . '</label>';
    }

    // Le repli. Masqué tant qu'il n'y a rien à replier ; les étiquettes
    // repliées restent des cases cochées, donc toujours envoyées.
    $html .= '<button type="button" class="interest-tag interest-plus" id="interets-plus" hidden aria-expanded="false"></button>';

    return $html . '</div>';
}
