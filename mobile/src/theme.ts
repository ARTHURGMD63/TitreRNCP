/**
 * Le design system de Linkee (charte v1.0), transposé pour React Native.
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
  qrSombre: '#111013',
  qrClair: '#FFFFFF',
  basalte: '#111013',
  craie: '#F5F1E8',
  /** --sur-lave : sur un aplat vif, le texte est toujours en basalte. */
  surLave: '#111013',
  /** --sur-media : texte clair posé sur une photo. */
  surMedia: '#F5F1E8',
} as const;

const marque = {
  rouge: '#FF5424', // lave
  rougeDeep: '#B8350D',
  bleu: '#5B8CFF', // dôme
  bleuDeep: '#2A56C0',
  lime: '#C8F547', // volt
  limeDeep: '#4D6B00',
  orange: '#FFC23D', // moutarde
  orangeDeep: '#8A5A00',
  alerteVif: '#FF4D6A',
  surLave: '#111013',
} as const;

const clair = {
  ...marque,
  bg: '#F5F1E8',
  blanc: '#FFFFFF',
  surface2: '#EFEAE0',
  noir: '#111013',
  grisFonce: '#4B4751',
  gris: '#67626D',
  grisClair: '#E8E2D6',
  line2: '#DCD5C7',

  succes: '#2D6A14',
  succesClair: '#E6F4D6',
  danger: '#C4203F',
  dangerClair: '#FFE4E9',
  alerte: '#8A5A00',
  alerteClair: '#FFF1D1',

  surRougeClair: '#B8350D',
  surBleuClair: '#2A56C0',
  surLimeClair: '#4D6B00',
  surOrangeClair: '#8A5A00',

  rougeClair: '#FFE3D9',
  bleuClair: '#E3EBFF',
  limeClair: '#EEF9CF',
  orangeClair: '#FFF1D1',
};

const sombre: typeof clair = {
  ...marque,
  bg: '#111013',
  blanc: '#1C1A1F',
  surface2: '#242127',
  noir: '#F5F1E8',
  grisFonce: '#CFC9D3',
  gris: '#8A858F',
  grisClair: '#2A272E',
  line2: '#36323B',

  succes: '#C8F547',
  succesClair: '#273011',
  danger: '#FF4D6A',
  dangerClair: '#3A151D',
  alerte: '#FFC23D',
  alerteClair: '#33280F',

  surRougeClair: '#FF5424',
  surBleuClair: '#5B8CFF',
  surLimeClair: '#C8F547',
  surOrangeClair: '#FFC23D',

  rougeClair: '#3A1B12',
  bleuClair: '#18223D',
  limeClair: '#273011',
  orangeClair: '#33280F',
};

export const couleurs = { clair, sombre } as const;
export type Couleurs = typeof clair;

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

/** --fs-1 … --fs-10, en points. */
export const fs = { 1: 11, 2: 12, 3: 13, 4: 14, 5: 16, 6: 20, 7: 24, 8: 32, 9: 36, 10: 48 } as const;

/** Interlignes, en multiples du corps (--lh-*). */
export const lh = { display: 1.04, tight: 1.12, snug: 1.3, normal: 1.5, relaxed: 1.6 } as const;

/** Interlettrages, en em (--ls-*) : à multiplier par le corps, voir ls(). */
export const lsEm = { display: -0.035, tight: -0.02, normal: 0, wide: 0.06, label: 0.1 } as const;
export const ls = (em: number, taille: number) => em * taille;

/** Rayons (--radius-*). */
export const rayon = { xs: 8, sm: 12, md: 16, base: 24, lg: 24, xl: 32, pill: 999 } as const;

/** Gouttière horizontale (--gutter) et cible tactile (--touch-min). */
export const gutter = 20;
export const toucheMin = 44;

/**
 * Familles typographiques, une par graisse : React Native ne synthétise pas
 * les graisses d'une police chargée, il faut nommer le fichier.
 *
 *  - display : Unbounded 800 (titres, chiffres clés), 400 (suffixe du logo)
 *  - sans    : Instrument Sans 400 à 700 (texte, interfaces, boutons)
 *  - mono    : JetBrains Mono 400 à 600 (étiquettes, horaires, XP)
 */
export const police = {
  display: 'Unbounded_800ExtraBold',
  displayRegular: 'Unbounded_400Regular',
  sans400: 'InstrumentSans_400Regular',
  sans500: 'InstrumentSans_500Medium',
  sans600: 'InstrumentSans_600SemiBold',
  sans700: 'InstrumentSans_700Bold',
  sans600Italique: 'InstrumentSans_600SemiBold_Italic',
  mono400: 'JetBrainsMono_400Regular',
  mono500: 'JetBrainsMono_500Medium',
  mono600: 'JetBrainsMono_600SemiBold',
} as const;

export function sans(poids: 400 | 500 | 600 | 700 = 400): string {
  return { 400: police.sans400, 500: police.sans500, 600: police.sans600, 700: police.sans700 }[poids];
}
export function mono(poids: 400 | 500 | 600 = 500): string {
  return { 400: police.mono400, 500: police.mono500, 600: police.mono600 }[poids];
}

/**
 * Ombres (--shadow-*). Sur basalte, pas d'ombre portée : on superpose des
 * surfaces plus claires — sauf --shadow-lg, gardé pour ce qui flotte.
 */
export function ombre(mode: Mode, niveau: 'sm' | 'base' | 'lg') {
  if (mode === 'sombre') {
    return niveau === 'lg'
      ? { shadowColor: '#000', shadowOffset: { width: 0, height: 18 }, shadowOpacity: 0.5, shadowRadius: 24, elevation: 12 }
      : {};
  }
  const t = {
    sm: { h: 1, r: 2, o: 0.04, e: 1 },
    base: { h: 6, r: 12, o: 0.06, e: 2 },
    lg: { h: 16, r: 22, o: 0.12, e: 10 },
  }[niveau];
  return { shadowColor: '#111013', shadowOffset: { width: 0, height: t.h }, shadowOpacity: t.o, shadowRadius: t.r, elevation: t.e };
}

/** --halo-lave : seuls la carte flash et le pass actif le portent. */
export const haloLave = {
  shadowColor: '#FF5424',
  shadowOffset: { width: 0, height: 18 },
  shadowOpacity: 0.45,
  shadowRadius: 26,
  elevation: 14,
} as const;
