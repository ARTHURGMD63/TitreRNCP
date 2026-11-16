/**
 * Le design system de Outsee (charte v1.0), transposé pour React Native.
 *
 * Chaque valeur est reprise d'assets/css/style.css, jeton pour jeton : même
 * palette, mêmes graisses, même échelle typographique, mêmes rayons. Ce n'est
 * pas une palette « inspirée de » : un étudiant qui passe du site à
 * l'application doit voir le même produit.
 *
 * Deux univers, comme le site : « la nuit » (basalte, par défaut) et « le
 * jour » (craie). Le choix se fait dans Moi › Apparence.
 *
 * Les noms de jetons sont conservés tels quels — `noir`, `blanc`, `bg` — y
 * compris là où ils mentent en sombre (`noir` y vaut la craie) : c'est ce qui
 * permet de comparer une règle CSS et son équivalent natif d'un coup d'œil.
 * Le piège est désamorcé là où il compte : les couleurs de `fixe`.
 */

export type Mode = 'clair' | 'sombre';

/** Couleurs qui ne suivent JAMAIS le thème. */
export const fixe = {
  /** Un QR code est une cible optique : modules sombres sur fond clair. */
  qrSombre: '#111116',
  qrClair: '#FFFFFF',
  basalte: '#111116',
  craie: '#F2F2F5',
  /**
   * --sur-lave : texte basalte sur les aplats vifs et clairs (volt, dôme,
   * moutarde, alerte). Malgré son nom, il ne va plus sur l'accent : voir
   * surAccent.
   */
  surLave: '#111116',
  /**
   * Texte sur l'aplat d'accent (bleu Pantone 2728 C). Le basalte n'y
   * atteindrait qu'environ 2,4:1 ; la craie y dépasse 7:1.
   */
  surAccent: '#FFFFFF',
  /** --sur-media : texte clair posé sur une photo. */
  surMedia: '#FFFFFF',
  /**
   * Le bleu du logo : Pantone 2728 C (sRGB #0047BB). Depuis le 2026-11-10,
   * c'est aussi l'accent de l'interface (le jeton `rouge`, ex-lave).
   *
   * La teinte reste exacte même sur basalte, où le « ee » n'atteint
   * qu'environ 2,4:1 : choix de marque assumé (2026-11-09), à ne pas
   * éclaircir en mode sombre.
   */
  logo: '#1D55F0',
  /** Refonte 2026-11 : la barre d'onglets flottante, sombre dans les deux thèmes. */
  barre: '#16161C',
  barreIcone: '#A7A7B3',
  /** Point « En cours », pastille de notification, « Squad commun ». */
  enCours: '#2BD67B',
  notif: '#FF3B30',
  squadCommun: '#FFE58A',
  /** Voile du panneau Filtres. */
  voile: 'rgba(17,17,22,0.45)',
} as const;

/**
 * L'accent garde son nom de jeton historique, `rouge` (« lave » dans la
 * charte), pour ne pas toucher aux règles qui l'emploient. Il vaut le bleu du
 * logo, identique dans les deux thèmes : les aplats portent du texte craie
 * (fixe.surAccent). Sur basalte, le texte d'accent passe par `surRougeClair`,
 * un bleu éclairci, car le 2728 C n'y serait pas lisible.
 */
const marque = {
  rouge: '#1D55F0', // accent : le bleu Outsee (refonte 2026-11)
  rougeDeep: '#1A47C8',
  bleu: '#5B8CFF', // dôme
  bleuDeep: '#2A56C0',
  lime: '#C8F547', // volt
  limeDeep: '#4D6B00',
  orange: '#FFC23D', // moutarde
  orangeDeep: '#8A5A00',
  alerteVif: '#FF4D6A',
  surLave: '#111116',
} as const;

const clair = {
  ...marque,
  bg: '#F2F2F5',
  blanc: '#FFFFFF',
  surface2: '#EDEDF1',
  noir: '#111116',
  grisFonce: '#3C3C46',
  gris: '#5A5A66',
  grisClair: '#E4E4EA',
  line2: '#D4D4DC',
  /** Texte posé sur un élément « encre » actif (chip, tuile, bouton). */
  surEncre: '#FFFFFF',

  succes: '#137A44',
  succesClair: '#D9F7E6',
  danger: '#C4203F',
  dangerClair: '#FFE4E9',
  alerte: '#8A5A00',
  alerteClair: '#FFF1D1',

  surRougeClair: '#1A47C8',
  surBleuClair: '#2A56C0',
  surLimeClair: '#137A44',
  surOrangeClair: '#8A5A00',

  rougeClair: '#E3EAFF',
  bleuClair: '#E3EBFF',
  limeClair: '#EEF9CF',
  orangeClair: '#FFF1D1',
};

const sombre: typeof clair = {
  ...marque,
  bg: '#0E0E12',
  blanc: '#1C1C22',
  surface2: '#2A2A32',
  noir: '#F2F2F5',
  grisFonce: '#C9C9D3',
  gris: '#A7A7B3',
  grisClair: '#2A2A32',
  line2: '#3A3A44',
  surEncre: '#111116',

  succes: '#3DDC84',
  succesClair: '#123222',
  danger: '#FF4D6A',
  dangerClair: '#3A151D',
  alerte: '#FFC23D',
  alerteClair: '#33280F',

  surRougeClair: '#8FA9FF',
  surBleuClair: '#5B8CFF',
  surLimeClair: '#3DDC84',
  surOrangeClair: '#FFC23D',

  rougeClair: '#18213F',
  bleuClair: '#18223D',
  limeClair: '#273011',
  orangeClair: '#33280F',
};

export const couleurs = { clair, sombre } as const;
export type Couleurs = typeof clair;

function luminance(hex: string): number | null {
  const m = /^#([0-9a-f]{6})$/i.exec(hex.trim());
  if (!m) return null;
  const canal = (i: number) => {
    const v = parseInt(m[1].slice(i, i + 2), 16) / 255;
    return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
  };
  return 0.2126 * canal(0) + 0.7152 * canal(2) + 0.0722 * canal(4);
}

/**
 * L'encre à poser sur un aplat dont la couleur varie (pastille, avatar,
 * badge) : craie ou basalte, celle qui contraste le plus. Une couleur qui
 * n'est pas un « #rrggbb » garde le basalte.
 */
export function encreSur(fond: string): string {
  const l = luminance(fond);
  if (l === null) return fixe.surLave;
  const lBasalte = luminance(fixe.basalte)!;
  const lCraie = luminance(fixe.craie)!;
  return (lCraie + 0.05) / (l + 0.05) > (l + 0.05) / (lBasalte + 0.05) ? fixe.surAccent : fixe.surLave;
}

/**
 * « var(--rouge) » → la couleur du thème. Les badges portent leur couleur en
 * base sous cette forme (migration v8) ; la même chaîne sert au site et ici.
 */
export function couleurCss(valeur: string | null | undefined, c: Couleurs, repli: string): string {
  if (!valeur) return repli;
  const m = /^var\(--([a-z0-9-]+)\)$/.exec(valeur.trim());
  if (!m) return valeur;
  const cle = m[1].replace(/-([a-z0-9])/g, (_, l: string) => l.toUpperCase()) as keyof Couleurs;
  return (c[cle] as string | undefined) ?? repli;
}

/** --fs-1 … --fs-10, en points. 32 : grand titre d'écran ; 26 : titre de carte ; 19 : titre de section. */
export const fs = { 1: 11, 2: 12, 3: 13, 4: 14, 5: 16, 6: 20, 7: 24, 8: 32, 9: 36, 10: 48 } as const;

/** Tailles de la refonte qui ne tombent pas sur l'échelle historique. */
export const taille = { titreEcran: 32, titreCarte: 26, titreCarteSport: 24, section: 19, surtitre: 12, secondaire: 14 } as const;

/** Interlignes, en multiples du corps (--lh-*). */
export const lh = { display: 1.2, tight: 1.2, snug: 1.3, normal: 1.5, relaxed: 1.6 } as const;

/** Interlettrages, en em (--ls-*) : à multiplier par le corps, voir ls(). */
export const lsEm = { display: -0.035, carte: -0.03, tight: -0.02, normal: 0, wide: 0.06, label: 0.06 } as const;
export const ls = (em: number, taille: number) => em * taille;

/**
 * Rayons. Refonte 2026-11 : cartes 24, personnes 22, tuiles 18, boutons et
 * chips 16, grands boutons 18, petit carré d'icône 10, barre 28.
 */
export const rayon = {
  xs: 8, sm: 12, md: 16, base: 24, lg: 24, xl: 32, pill: 999,
  carte: 24, personne: 22, tuile: 18, bouton: 16, grandBouton: 18, mini: 10, barre: 28, panneau: 28,
} as const;

/** Gouttière horizontale (18 px depuis la refonte) et cible tactile. */
export const gutter = 18;
export const toucheMin = 44;

/**
 * Les rubriques de l'application et leur couleur. Elle colore le logo, le
 * surlignage du titre, la carte principale de l'écran et les boutons
 * d'action principale.
 *
 *  - accent : l'aplat ; ce qui est posé dessus est blanc ;
 *  - surAccent2 : le texte secondaire sur l'aplat ;
 *  - doux / texteDoux : la pastille teintée (niveau, statut) et son texte.
 */
export type Rubrique = 'bleu' | 'sport' | 'culture';
export type Teintes = { accent: string; surAccent2: string; doux: string; texteDoux: string };

const TEINTES: Record<Rubrique, Record<Mode, Teintes>> = {
  bleu: {
    clair: { accent: '#1D55F0', surAccent2: '#E3EAFF', doux: '#E3EAFF', texteDoux: '#1A47C8' },
    sombre: { accent: '#1D55F0', surAccent2: '#E3EAFF', doux: '#1D2647', texteDoux: '#A9BDFF' },
  },
  sport: {
    clair: { accent: '#E02D24', surAccent2: '#FDE3E1', doux: '#FDE3E1', texteDoux: '#A3160F' },
    sombre: { accent: '#E02D24', surAccent2: '#FDE3E1', doux: '#3D1A18', texteDoux: '#FF9C96' },
  },
  culture: {
    clair: { accent: '#7B4DF2', surAccent2: '#EDE5FF', doux: '#EDE5FF', texteDoux: '#5B3BB5' },
    sombre: { accent: '#7B4DF2', surAccent2: '#EDE5FF', doux: '#2C2250', texteDoux: '#C7B3FF' },
  },
};

export function teintes(r: Rubrique, mode: Mode): Teintes {
  return TEINTES[r][mode];
}

/**
 * Le petit carré coloré des chips de catégorie (28 px, rayon 10, icône
 * blanche). La clé est le code de la catégorie côté API.
 */
export const couleursCategorie: Record<string, string> = {
  'pour-moi': '#FFB020',
  carte: '#22B573',
  bar: '#FF8A3D',
  boite: '#8B5CF6',
  resto: '#F0508A',
  padel: '#3B82F6',
  tennis: '#22B573',
  golf: '#14B8A6',
  squash: '#FF8A3D',
  expo: '#F0508A',
  concert: '#8B5CF6',
  spectacle: '#FFB020',
  patrimoine: '#14B8A6',
};

/** Les couleurs des confettis, en plus de l'accent de la rubrique. */
export const couleursConfettis = ['#FF8A3D', '#FFB020', '#22B573', '#F0508A', '#8B5CF6'] as const;

/**
 * Familles typographiques, une par graisse : React Native ne synthétise pas
 * les graisses d'une police chargée, il faut nommer le fichier.
 *
 * Refonte 2026-11 : une seule police, Onest (400 à 700), sur toute
 * l'application. Les anciens noms de rôles (display, sans, mono) restent pour
 * ne pas toucher aux écrans qui les emploient.
 */
export const police = {
  display: 'Onest_700Bold',
  displayRegular: 'Onest_400Regular',
  sans400: 'Onest_400Regular',
  sans500: 'Onest_500Medium',
  sans600: 'Onest_600SemiBold',
  sans700: 'Onest_700Bold',
  sans600Italique: 'Onest_600SemiBold',
  mono400: 'Onest_500Medium',
  mono500: 'Onest_500Medium',
  mono600: 'Onest_600SemiBold',
} as const;

export function sans(poids: 400 | 500 | 600 | 700 = 400): string {
  return { 400: police.sans400, 500: police.sans500, 600: police.sans600, 700: police.sans700 }[poids];
}
export function mono(poids: 400 | 500 | 600 = 500): string {
  return { 400: police.mono400, 500: police.mono500, 600: police.mono600 }[poids];
}

/**
 * Ombres. Refonte : elles sont rares — seulement ce qui flotte (barre
 * d'onglets, bouton « + Créer ») et la carte accent principale (halo()).
 * sm et base ne dessinent donc plus rien.
 */
export function ombre(mode: Mode, niveau: 'sm' | 'base' | 'lg') {
  if (niveau !== 'lg') return {};
  if (mode === 'sombre') {
    return { shadowColor: '#000', shadowOffset: { width: 0, height: 18 }, shadowOpacity: 0.5, shadowRadius: 24, elevation: 12 };
  }
  return { shadowColor: '#111116', shadowOffset: { width: 0, height: 16 }, shadowOpacity: 0.14, shadowRadius: 22, elevation: 10 };
}

/** L'ombre colorée de la carte accent principale. */
export function halo(couleur: string) {
  return { shadowColor: couleur, shadowOffset: { width: 0, height: 14 }, shadowOpacity: 0.3, shadowRadius: 22, elevation: 10 } as const;
}

/** --halo-lave : carte flash, pass actif — désormais l'accent bleu. */
export const haloLave = halo('#1D55F0');
