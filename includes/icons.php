<?php
/**
 * Jeu d'icônes nommées.
 *
 * Les emoji ont trois défauts qui les rendent inutilisables dans une interface
 * soignée : ils sont dessinés différemment par chaque système d'exploitation,
 * ils ignorent la couleur qu'on leur demande, et ils ne s'alignent pas sur la
 * grille typographique. On les remplace par des tracés sur la grille 24×24 de
 * l'application, qui héritent de la couleur courante et suivent --icon-stroke.
 *
 * Usage :  <?= icon('trophee') ?>            taille moyenne (20px)
 *          <?= icon('flamme', 'icon-sm') ?>  petite (16px)
 */

/** Tracés, sur la grille 24×24 commune à toute l'application. */
const ICONES = [
    'trophee'   => '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M17 5h3a3 3 0 0 1-3 3M7 5H4a3 3 0 0 0 3 3"/>',
    'flamme'    => '<path d="M12 2c1 4-2 5-2 8a4 4 0 0 0 8 0c0-1-.4-2-1-3 2 1.5 3 3.6 3 6a8 8 0 0 1-16 0c0-4.5 3-6.5 5-9 1-1.2 2.4-1.6 3-2z"/>',
    'etoile'    => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
    'epingle'   => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
    'personnes' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'personne'  => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    // Un drapeau plutot qu'une poignee de main : a 24px la poignee devenait
    // illisible et se lisait comme un crayon.
    'drapeau'   => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>',
    'papillon'  => '<path d="M12 6v12"/><path d="M12 8C9 3 2 4 2 10c0 5 6 8 10 8"/><path d="M12 8c3-5 10-4 10 2 0 5-6 8-10 8"/>',
    'lune'      => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
    'oiseau'    => '<path d="M16 7h.01"/><path d="M3.4 18H12a8 8 0 0 0 8-8V7a4 4 0 0 0-7.28-2.3L2 20"/><path d="M20 7 9 20l-2-4"/>',
    'piece'     => '<circle cx="12" cy="12" r="9"/><path d="M14.5 9.5a2.5 2.5 0 0 0-5 .5c0 3 5 1.5 5 4.5a2.5 2.5 0 0 1-5 .5"/><path d="M12 6.5v11"/>',
    'billet'    => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
    'appareil'  => '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
    'valide'    => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
    'echec'     => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
    'outil'     => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
    'loupe'     => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
    'vide'      => '<circle cx="12" cy="12" r="10"/><line x1="8" y1="15" x2="16" y2="15"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/>',
    'fleche-g'  => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
    'fleche-d'  => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
    'fleche-b'  => '<line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>',
    'check'     => '<polyline points="20 6 9 17 4 12"/>',
    'croix'     => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
    'musique'   => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
    'calendrier'=> '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
];

/**
 * Rend une icône du jeu.
 *
 * @param string $nom     Clé du tracé. Une clé inconnue ne casse rien : elle
 *                        ne rend simplement rien, plutôt que d'afficher un
 *                        carré vide au milieu de l'interface.
 * @param string $classes Classes additionnelles (icon-sm, icon-lg…).
 * @param string $titre   Si renseigné, l'icône devient porteuse de sens et
 *                        reçoit ce libellé ; sinon elle est décorative.
 */
function icon(string $nom, string $classes = '', string $titre = ''): string
{
    if (!isset(ICONES[$nom])) {
        return '';
    }

    $acc = $titre !== ''
        ? 'role="img" aria-label="' . htmlspecialchars($titre, ENT_QUOTES) . '"'
        : 'aria-hidden="true"';

    return sprintf(
        '<svg class="icon%s" viewBox="0 0 24 24" %s>%s</svg>',
        $classes !== '' ? ' ' . $classes : '',
        $acc,
        ICONES[$nom]
    );
}
