/**
 * Mise en forme des dates et des nombres, à l'identique du site.
 *
 * dateFr() est la transposition de la fonction PHP du même nom
 * (includes/auth_check.php) : les mêmes lettres de format que date(), puis
 * les mêmes abréviations françaises — « Jeu 12 Mars », « Sept », « Déc ».
 * Les heures s'écrivent « 20h30 » comme sur le site (format « H\hi »).
 *
 * Les dates arrivent de l'API sous la forme « 2026-09-23 20:30:00 », heure
 * de Paris. On les lit champ par champ plutôt qu'avec new Date(chaîne) :
 * l'analyse d'une date sans fuseau varie d'un moteur JavaScript à l'autre.
 */

const JOURS = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
const JOURS_LONGS = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
const MOIS = ['Janv', 'Févr', 'Mars', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];
const MOIS_LONGS = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

/** « 2026-09-23 20:30:00 » (ou « 2026-09-23T20:30 ») → Date locale. */
export function lireDate(valeur: string | null | undefined): Date | null {
  if (!valeur) return null;
  const m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/.exec(valeur);
  if (!m) return null;
  return new Date(+m[1], +m[2] - 1, +m[3], +(m[4] ?? 0), +(m[5] ?? 0), +(m[6] ?? 0));
}

const deux = (n: number) => (n < 10 ? '0' + n : String(n));

/**
 * Les lettres de date() utilisées par l'application : D j M F l Y H i G,
 * et « \x » pour un caractère littéral.
 */
export function dateFr(valeur: string | Date | null | undefined, format = 'D j M'): string {
  const d = valeur instanceof Date ? valeur : lireDate(valeur);
  if (!d) return '';
  let sortie = '';
  for (let i = 0; i < format.length; i++) {
    const car = format[i];
    if (car === '\\') { sortie += format[++i] ?? ''; continue; }
    switch (car) {
      case 'D': sortie += JOURS[d.getDay()]; break;
      case 'l': sortie += JOURS_LONGS[d.getDay()]; break;
      case 'j': sortie += d.getDate(); break;
      case 'd': sortie += deux(d.getDate()); break;
      case 'M': sortie += MOIS[d.getMonth()]; break;
      case 'F': sortie += MOIS_LONGS[d.getMonth()]; break;
      case 'm': sortie += deux(d.getMonth() + 1); break;
      case 'Y': sortie += d.getFullYear(); break;
      case 'H': sortie += deux(d.getHours()); break;
      case 'G': sortie += d.getHours(); break;
      case 'i': sortie += deux(d.getMinutes()); break;
      default: sortie += car;
    }
  }
  return sortie;
}

/** « 20h30 » */
export const heure = (valeur: string | Date | null | undefined) => dateFr(valeur, 'H\\hi');

/** Horodatage en secondes, comme strtotime(). */
export function ts(valeur: string | null | undefined): number {
  const d = lireDate(valeur);
  return d ? Math.floor(d.getTime() / 1000) : 0;
}

/**
 * number_format($n, $decimales, ',', ' ') — espace pour les milliers, virgule
 * pour les décimales. Espace simple comme le site, pas l'espace fine.
 */
export function nombre(n: number, decimales = 0, milliers = ' '): string {
  const fixe = Math.abs(n).toFixed(decimales);
  const [entier, dec] = fixe.split('.');
  const groupe = entier.replace(/\B(?=(\d{3})+(?!\d))/g, milliers);
  return (n < 0 ? '-' : '') + groupe + (dec ? ',' + dec : '');
}

/** « Arthur G. » : prénom et initiale du nom, comme les listes du site. */
export function court(prenom: string, nom?: string | null) {
  const initiale = (nom ?? '').trim().charAt(0);
  return initiale ? `${prenom} ${initiale}.` : prenom;
}

/** mb_strtoupper */
export const majuscules = (s: string) => s.toLocaleUpperCase('fr-FR');
