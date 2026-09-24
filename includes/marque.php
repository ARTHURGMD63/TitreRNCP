<?php
/**
 * La marque Linkee : deux anneaux entrelacés et le logotype « linkee ».
 *
 * Un seul endroit pour la dessiner. Le logo était recomposé à la main dans
 * chaque en-tête (« StudentLink <em>/ Hub</em> », un <svg> différent sur le
 * hub) : la charte interdit précisément de le recomposer, de le déformer ou
 * d'en changer les couleurs, ce qu'une copie par page finit toujours par
 * faire.
 *
 * Les couleurs viennent des jetons : l'anneau de gauche et le double « ee »
 * sont en lave, l'autre anneau et « link » à l'encre du thème — craie sur
 * basalte, basalte sur craie. Aucune variante n'est à prévoir par page.
 */

/**
 * Les deux anneaux seuls (symbole, icône d'application).
 *
 * L'anneau lave passe devant l'anneau d'encre à l'intersection du bas :
 * c'est ce croisement qui les « entrelace ». aria-hidden : le symbole
 * accompagne toujours un nom lisible.
 */
function anneauxLinkee(string $classe = 'marque__anneaux'): string
{
    return '<svg class="' . htmlspecialchars($classe, ENT_QUOTES) . '" viewBox="0 0 52 32" fill="none" aria-hidden="true" focusable="false">'
         . '<circle cx="16" cy="16" r="12.5" stroke="var(--rouge)" stroke-width="5"/>'
         . '<circle cx="36" cy="16" r="12.5" stroke="var(--noir)" stroke-width="5"/>'
         // Reprise de l'arc lave au croisement du bas, par-dessus l'anneau d'encre.
         . '<path d="M 28.45 14.91 A 12.5 12.5 0 0 1 24.03 25.58" stroke="var(--rouge)" stroke-width="5"/>'
         . '</svg>';
}

/**
 * Le logo complet : anneaux + « linkee », avec un suffixe d'univers
 * facultatif (« pro », « interne ») rendu en maigre, comme sur la charte
 * (« linkee pro »).
 *
 * @param string $suffixe   Mot d'univers, échappé ici. Vide : logo seul.
 * @param string $href      Lien facultatif ; le logo devient alors un <a>.
 * @param bool   $anneaux   Faux pour le logotype seul.
 */
function marqueLinkee(string $suffixe = '', string $href = '', bool $anneaux = true): string
{
    $contenu = ($anneaux ? anneauxLinkee() : '')
             . '<span class="marque__mot" aria-hidden="true">link<span class="marque__ee">ee</span></span>'
             . ($suffixe !== '' ? '<span class="marque__suffixe" aria-hidden="true">' . htmlspecialchars($suffixe) . '</span>' : '');

    // Le nom lisible par un lecteur d'écran : « Linkee pro », pas « l-i-n-k-e-e ».
    $nom = 'Linkee' . ($suffixe !== '' ? ' ' . $suffixe : '');
    $sr  = '<span class="sr-only">' . htmlspecialchars($nom) . '</span>';

    if ($href !== '') {
        return '<a class="marque" href="' . htmlspecialchars($href, ENT_QUOTES) . '">' . $sr . $contenu . '</a>';
    }
    return '<span class="marque">' . $sr . $contenu . '</span>';
}
