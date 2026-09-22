/**
 * Le design system de StudentLink, transpose pour React Native.
 *
 * Les valeurs sont reprises une a une d'assets/css/style.css, y compris les
 * paires de contraste verifiees qui s'y trouvent. Ce n'est pas une nouvelle
 * palette « inspiree de » : c'est la meme, sans quoi l'application et le site
 * divergeraient des la premiere retouche, et un etudiant qui passe de l'un a
 * l'autre verrait deux produits.
 *
 * CE QUI A CHANGE, ET POURQUOI
 *
 * Le web pose des variables CSS et laisse la cascade choisir. React Native n'a
 * ni cascade ni variables : chaque composant recoit ses couleurs a la main,
 * depuis le theme rendu par useTheme(). D'ou un objet par mode plutot qu'un
 * jeu de variables redefinies.
 *
 * Les noms de jetons sont conserves tels quels — `noir`, `blanc`, `bg` — y
 * compris la ou ils mentent en mode sombre (`noir` vaut alors un creme clair).
 * Les renommer aurait ete plus honnete, mais aurait surtout empeche de
 * comparer une regle CSS et son equivalent natif d'un coup d'oeil. Le piege
 * est reel : c'est exactement lui qui avait rendu le QR code illisible en mode
 * sombre. Il est desamorce au seul endroit qui compte, `fixe` ci-dessous.
 */

export type Mode = 'clair' | 'sombre';

/**
 * Couleurs qui ne suivent JAMAIS le theme.
 *
 * Un QR code n'est pas un element d'interface, c'est une cible optique : les
 * lecteurs attendent des modules sombres sur fond clair, et la norme exige une
 * marge claire autour. Prendre ces deux valeurs dans le theme — ce que faisait
 * le web — donne un code creme sur blanc en mode sombre, invisible a l'oeil et
 * refuse par les scanners.
 */
export const fixe = {
  qrSombre: '#1C1916',
  qrClair: '#FFFFFF',
  /** Texte clair pose sur une photo ou un aplat de marque. */
  surMedia: '#FFFFFF',
  /** Texte sombre pose sur un aplat clair de marque (l'or). */
  surMediaEncre: '#1C1916',
} as const;

const marque = {
  rouge: '#E0492B',
  rougeDeep: '#BB3619',
  bleu: '#4A40C2',
  bleuDeep: '#332A92',
  lime: '#EFB23A',
  limeDeep: '#8E6414',
  orange: '#EC8233',
  orangeDeep: '#A75517',
} as const;

const clair = {
  ...marque,
  bg: '#F3EEE3',
  blanc: '#FFFFFF',
  surface2: '#FBF8F1',
  noir: '#1C1916',
  grisFonce: '#5B554C',
  gris: '#6E6860',
  grisClair: '#EAE3D6',
  line2: '#DED6C6',

  rougeClair: '#FBE7E1',
  bleuClair: '#ECE9FA',
  limeClair: '#FBF0D6',
  orangeClair: '#FCEBDB',

  succes: '#1F6B34',
  succesClair: '#E3F2E6',
  danger: '#B3261E',
  dangerClair: '#FBE9E7',
  alerte: '#8A5A00',
  alerteClair: '#FFF3DC',

  // Texte pose sur une teinte claire. Sans ces jetons, les badges empruntent
  // la couleur pure de la marque et tombent entre 2.2 et 3.4:1.
  surRougeClair: '#BB3619',
  surBleuClair: '#332A92',
  surLimeClair: '#8E6414',
  surOrangeClair: '#A75517',
} as const;

const sombre = {
  ...marque,
  bg: '#16130F',
  blanc: '#211D18',
  surface2: '#1B1813',
  noir: '#F3EEE3',
  grisFonce: '#C9C1B2',
  gris: '#8E8576',
  grisClair: '#322C24',
  line2: '#3D362C',

  rougeClair: '#3A201A',
  bleuClair: '#1E1B36',
  limeClair: '#322A18',
  orangeClair: '#33220F',

  // Sur fond sombre la teinte s'assombrit : le texte doit s'eclaircir, sinon
  // les badges retombent a 2.2:1.
  succes: '#7FC99A',
  succesClair: '#14261A',
  danger: '#F29384',
  dangerClair: '#2E1512',
  alerte: '#E0B25C',
  alerteClair: '#2B2008',

  surRougeClair: '#E5674F',
  surBleuClair: '#827BD4',
  surLimeClair: '#BC8825',
  surOrangeClair: '#D98A45',
} as const;

export const couleurs = { clair, sombre } as const;

/**
 * Les noms de jetons du theme, dont les valeurs sont des couleurs.
 *
 * `typeof clair` ne conviendrait pas : `as const` y fige chaque valeur en
 * litteral, si bien que `bg` vaut le type `"#F3EEE3"` et non `string`. Le
 * theme sombre, dont `bg` vaut « #16130F », n'etait alors pas assignable au
 * meme type — deux themes qui ne partagent aucune valeur ne partageraient
 * jamais de type. On garde donc les CLES exactes, en elargissant les valeurs.
 */
export type Couleurs = { readonly [K in keyof typeof clair]: string };

/** Rayons, repris de --radius & co. */
export const rayon = {
  sm: 10,
  base: 12,
  bouton: 10,
  lg: 16,
  xl: 22,
  pill: 999,
} as const;

/**
 * Echelle typographique, en points.
 *
 * Le web part de rem ; ici les valeurs sont en points, que React Native met
 * lui-meme a l'echelle des reglages d'accessibilite du systeme. Un etudiant
 * qui a grossi le texte de son iPhone doit voir l'application grossir avec.
 */
export const taille = {
  xs: 11,
  sm: 12,
  base: 13,
  texte: 14,
  corps: 16,
  titre: 20,
  grand: 26,
  hero: 34,
} as const;

/**
 * Familles typographiques.
 *
 * Playfair Display pour les titres, DM Sans pour le reste — comme le web.
 * Tant que les polices ne sont pas embarquees (expo-font), on retombe sur les
 * familles systeme : `undefined` laisse React Native choisir San Francisco sur
 * iOS, ce qui reste correct. Mettre un nom de police absente donnerait un
 * rendu par defaut silencieux et different selon la plateforme.
 */
export const police = {
  titre: undefined as string | undefined,
  texte: undefined as string | undefined,
} as const;

/** Espacements, multiples de 4. */
export const espace = {
  xs: 4,
  sm: 8,
  base: 12,
  md: 16,
  lg: 20,
  xl: 28,
  xxl: 40,
} as const;

/**
 * Ombres.
 *
 * iOS et Android ne les expriment pas pareil : `shadow*` d'un cote,
 * `elevation` de l'autre. Les deux sont poses, chaque plateforme ignore ce qui
 * ne la concerne pas.
 */
export function ombre(mode: Mode, niveau: 'sm' | 'base' | 'lg' = 'base') {
  const opacite = mode === 'sombre' ? { sm: 0.4, base: 0.4, lg: 0.5 } : { sm: 0.06, base: 0.08, lg: 0.12 };
  const rayonOmbre = { sm: 3, base: 10, lg: 22 };
  const decalage = { sm: 1, base: 2, lg: 6 };

  return {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: decalage[niveau] },
    shadowOpacity: opacite[niveau],
    shadowRadius: rayonOmbre[niveau],
    elevation: decalage[niveau] * 2,
  };
}
