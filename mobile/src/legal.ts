/**
 * Les textes légaux, tels que l'API les sert (api/v1/legal.php).
 *
 * Aucun HTML : un texte porte seulement des passages **en gras**, que
 * fragments() découpe pour que l'écran les affiche avec la bonne graisse.
 * Le texte lui-même vit sur le serveur (includes/textes_legaux.php), une
 * seule source pour l'application et le site.
 */

export type BlocLegal =
  | { type: 'p'; texte: string }
  | { type: 'liste'; items: string[] };

export type SectionLegale = { titre?: string; blocs: BlocLegal[] };

export type PageLegale = {
  slug: string;
  titre: string;
  /** « 14/10/2026 », déjà mise en forme par le serveur. */
  maj: string;
  sections: SectionLegale[];
  pied: string | null;
};

export type FragmentTexte = { texte: string; gras: boolean };

/**
 * « Identité : **nom**, email » → [« Identité : », gras « nom », « , email »].
 *
 * Les astérisques doublés alternent texte courant et gras. Un balisage resté
 * ouvert (nombre impair) laisse la fin en gras plutôt que d'afficher les
 * astérisques tels quels.
 */
export function fragments(texte: string): FragmentTexte[] {
  return texte
    .split('**')
    .map((morceau, i) => ({ texte: morceau, gras: i % 2 === 1 }))
    .filter((f) => f.texte !== '');
}

/** Les pages proposées dans les menus, dans l'ordre. */
export const PAGES_LEGALES: { slug: string; libelle: string }[] = [
  { slug: 'mentions-legales', libelle: 'Mentions légales' },
  { slug: 'cgu', libelle: 'CGU' },
  { slug: 'confidentialite', libelle: 'Politique de confidentialité' },
];
