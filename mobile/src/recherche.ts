/**
 * Recherche dans une liste de choix (le sélecteur d'école, entre autres).
 *
 * On tape comme on parle : sans accents, sans majuscules, un bout de mot
 * suffit, et chaque mot tapé doit se retrouver dans le libellé ou dans ses
 * mots-clés (« ecole commerce » trouve Clermont School of Business).
 *
 * Les accents sont retirés à la main plutôt qu'avec String.normalize, dont
 * la prise en charge par le moteur JavaScript de l'app n'est pas garantie.
 */

const ACCENTS: Record<string, string> = {
  à: 'a', â: 'a', ä: 'a', á: 'a', ã: 'a',
  ç: 'c',
  é: 'e', è: 'e', ê: 'e', ë: 'e',
  î: 'i', ï: 'i', í: 'i',
  ô: 'o', ö: 'o', ó: 'o', õ: 'o',
  ù: 'u', û: 'u', ü: 'u', ú: 'u',
  ÿ: 'y', œ: 'oe', æ: 'ae',
};

/** Minuscules, sans accents, apostrophes et tirets changés en espaces. */
export function simplifier(texte: string): string {
  return texte
    .toLowerCase()
    .replace(/[àâäáãçéèêëîïíôöóõùûüúÿœæ]/g, (l) => ACCENTS[l] ?? l)
    .replace(/[’'\-–—()]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

/**
 * Les options dont le libellé (ou ses mots-clés) contient tous les mots
 * tapés, dans leur ordre d'origine. Une recherche vide rend toute la liste.
 */
export function filtrerOptions<O extends { valeur: string; libelle: string }>(
  options: O[],
  requete: string,
  motsCles: Record<string, string> = {},
): O[] {
  const mots = simplifier(requete).split(' ').filter(Boolean);
  if (mots.length === 0) return options;
  return options.filter((o) => {
    const texte = simplifier(`${o.libelle} ${motsCles[o.valeur] ?? ''}`);
    return mots.every((m) => texte.includes(m));
  });
}
