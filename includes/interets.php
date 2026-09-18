<?php
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
