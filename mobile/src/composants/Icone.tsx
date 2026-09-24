/**
 * Le jeu d'icônes du site, tracé pour tracé.
 *
 * Les chaînes ci-dessous sont copiées telles quelles d'includes/icons.php et
 * des SVG écrits dans les gabarits (cloche, barre du bas, partage…) : même
 * grille 24×24, même trait de 2 px à bouts arrondis. Aucun emoji, aucune
 * police d'icônes tierce — une icône qui ne vient pas d'ici n'a pas sa place
 * dans l'application.
 *
 * Les tracés restent écrits en SVG plutôt que convertis en objets : c'est ce
 * qui permet de vérifier d'un coup d'œil qu'ils n'ont pas bougé depuis le
 * site. Un petit lecteur les transforme en éléments react-native-svg.
 */

import React from 'react';
import Svg, { Circle, Line, Path, Polygon, Polyline, Rect } from 'react-native-svg';

const TRACES = {
  // ── includes/icons.php ─────────────────────────────────────────────
  trophee: '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M17 5h3a3 3 0 0 1-3 3M7 5H4a3 3 0 0 0 3 3"/>',
  flamme: '<path d="M12 2c1 4-2 5-2 8a4 4 0 0 0 8 0c0-1-.4-2-1-3 2 1.5 3 3.6 3 6a8 8 0 0 1-16 0c0-4.5 3-6.5 5-9 1-1.2 2.4-1.6 3-2z"/>',
  etoile: '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
  epingle: '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
  personnes: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
  personne: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
  drapeau: '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>',
  papillon: '<path d="M12 6v12"/><path d="M12 8C9 3 2 4 2 10c0 5 6 8 10 8"/><path d="M12 8c3-5 10-4 10 2 0 5-6 8-10 8"/>',
  lune: '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
  oiseau: '<path d="M16 7h.01"/><path d="M3.4 18H12a8 8 0 0 0 8-8V7a4 4 0 0 0-7.28-2.3L2 20"/><path d="M20 7 9 20l-2-4"/>',
  piece: '<circle cx="12" cy="12" r="9"/><path d="M14.5 9.5a2.5 2.5 0 0 0-5 .5c0 3 5 1.5 5 4.5a2.5 2.5 0 0 1-5 .5"/><path d="M12 6.5v11"/>',
  billet: '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
  appareil: '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
  valide: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
  echec: '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
  outil: '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
  loupe: '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
  vide: '<circle cx="12" cy="12" r="10"/><line x1="8" y1="15" x2="16" y2="15"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/>',
  'fleche-g': '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
  'fleche-d': '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
  'fleche-b': '<line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>',
  check: '<polyline points="20 6 9 17 4 12"/>',
  croix: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
  musique: '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
  calendrier: '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',

  // ── SVG écrits dans les gabarits ───────────────────────────────────
  cloche: '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
  carte: '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
  'etoile-fine': '<path d="M12 3l1.912 5.813h6.111l-4.943 3.591 1.887 5.804-4.967-3.607-4.967 3.607 1.887-5.804-4.943-3.591h6.111z"/>',
  horloge: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
  eclair: '<path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>',
  'marque-page': '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>',
  engrenage: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
  'ajout-personne': '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>',
  ticket: '<path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v2z"/><line x1="13" y1="5" x2="13" y2="19"/>',
  interdit: '<circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>',
  activite: '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
  'chevron-b': '<polyline points="6 9 12 15 18 9"/>',
  'chevron-g': '<polyline points="15 18 9 12 15 6"/>',
  partage: '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>',
  info: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
  image: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
  cadenas: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
  envoi: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
  soleil: '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>',
  'soleil-petit': '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
  deconnexion: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
  qr: '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="3" height="3"/>',
} as const;

export type NomIcone = keyof typeof TRACES;

type Element = { balise: string; attrs: Record<string, string> };

/** Lecture des tracés, une fois par icône, mise en cache. */
const cache = new Map<string, Element[]>();
function lire(source: string): Element[] {
  const connu = cache.get(source);
  if (connu) return connu;
  const elements: Element[] = [];
  const reBalise = /<(\w+)\s([^>]*?)\/>/g;
  const reAttr = /([\w-]+)="([^"]*)"/g;
  let m: RegExpExecArray | null;
  while ((m = reBalise.exec(source))) {
    const attrs: Record<string, string> = {};
    let a: RegExpExecArray | null;
    while ((a = reAttr.exec(m[2]))) attrs[a[1]] = a[2];
    elements.push({ balise: m[1], attrs });
  }
  cache.set(source, elements);
  return elements;
}

const n = (v: string | undefined) => (v === undefined ? undefined : Number(v));

type Props = {
  nom: NomIcone;
  taille?: number;
  couleur: string;
  /** --icon-stroke : 2 partout ; quelques gabarits l'épaississent (2.5, 3). */
  trait?: number;
  /** Remplissage (les étoiles pleines de l'écran d'avis). */
  plein?: string;
};

export function Icone({ nom, taille = 20, couleur, trait = 2, plein = 'none' }: Props) {
  const commun = { stroke: couleur, strokeWidth: trait, strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const, fill: plein };
  return (
    <Svg width={taille} height={taille} viewBox="0 0 24 24">
      {lire(TRACES[nom]).map(({ balise, attrs }, i) => {
        switch (balise) {
          case 'path':
            return <Path key={i} d={attrs.d} {...commun} />;
          case 'circle':
            return <Circle key={i} cx={n(attrs.cx)} cy={n(attrs.cy)} r={n(attrs.r)} {...commun} />;
          case 'line':
            return <Line key={i} x1={n(attrs.x1)} y1={n(attrs.y1)} x2={n(attrs.x2)} y2={n(attrs.y2)} {...commun} />;
          case 'polyline':
            return <Polyline key={i} points={attrs.points} {...commun} fill="none" />;
          case 'polygon':
            return <Polygon key={i} points={attrs.points} {...commun} />;
          case 'rect':
            return (
              <Rect key={i} x={n(attrs.x)} y={n(attrs.y)} width={n(attrs.width)} height={n(attrs.height)}
                rx={n(attrs.rx)} ry={n(attrs.ry)} {...commun} />
            );
          default:
            return null;
        }
      })}
    </Svg>
  );
}

/** Nom d'icône venu de la base (badges) : un nom inconnu ne rend rien, comme icon(). */
export function estIcone(nom: string): nom is NomIcone {
  return Object.prototype.hasOwnProperty.call(TRACES, nom);
}
