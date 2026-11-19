/**
 * Regrouper des pass par jour (pass actifs) ou par mois (historique).
 *
 * Avec plusieurs pass, une carte par pass faisait un écran Pass interminable.
 * Les pass du même jour tiennent désormais dans un seul bloc, titré
 * « Aujourd'hui », « Demain » ou « Samedi 22 novembre » ; l'historique, lui,
 * se range par mois.
 *
 * Module sans dépendance (testé par tests/regroupement.test.ts) : les dates
 * du serveur sont lues comme des heures locales, « 2026-11-22 23:30:00 »,
 * comme le fait lireDate() de format.ts.
 */

export type Groupe<T> = { cle: string; libelle: string; elements: T[] };

type AvecDate = { date_heure: string | null };

const JOURS = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
const MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

/** Le groupe des éléments sans date lisible : toujours rangé en dernier. */
export const SANS_DATE = 'sans-date';

const deux = (n: number) => (n < 10 ? '0' + n : String(n));

/** « 2026-11-22 23:30:00 » → « 2026-11-22 », ou SANS_DATE. */
export function cleJour(dateHeure: string | null | undefined): string {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(dateHeure ?? '');
  return m ? `${m[1]}-${m[2]}-${m[3]}` : SANS_DATE;
}

/** « 2026-11-22 23:30:00 » → « 2026-11 », ou SANS_DATE. */
export function cleMois(dateHeure: string | null | undefined): string {
  const jour = cleJour(dateHeure);
  return jour === SANS_DATE ? SANS_DATE : jour.slice(0, 7);
}

/** La clé du jour d'une date locale. */
export function cleDuJour(d: Date): string {
  return `${d.getFullYear()}-${deux(d.getMonth() + 1)}-${deux(d.getDate())}`;
}

/**
 * « Aujourd'hui », « Demain », « Hier », sinon « Samedi 22 novembre »,
 * avec l'année quand ce n'est pas celle de `maintenant`.
 */
export function libelleJour(cle: string, maintenant: Date): string {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(cle);
  if (!m) return 'Date à confirmer';
  const jour = new Date(+m[1], +m[2] - 1, +m[3]);
  const demain = new Date(maintenant.getFullYear(), maintenant.getMonth(), maintenant.getDate() + 1);
  const hier = new Date(maintenant.getFullYear(), maintenant.getMonth(), maintenant.getDate() - 1);
  if (cle === cleDuJour(maintenant)) return 'Aujourd’hui';
  if (cle === cleDuJour(demain)) return 'Demain';
  if (cle === cleDuJour(hier)) return 'Hier';
  const annee = jour.getFullYear() !== maintenant.getFullYear() ? ` ${jour.getFullYear()}` : '';
  return `${JOURS[jour.getDay()]} ${jour.getDate()} ${MOIS[jour.getMonth()]}${annee}`;
}

/** « 2026-11 » → « Novembre 2026 ». */
export function libelleMois(cle: string): string {
  const m = /^(\d{4})-(\d{2})$/.exec(cle);
  if (!m) return 'Date inconnue';
  const nom = MOIS[+m[2] - 1] ?? '';
  return `${nom.charAt(0).toUpperCase()}${nom.slice(1)} ${m[1]}`;
}

/** Regroupe en gardant, dans chaque groupe, l'ordre reçu. */
function regrouper<T>(liste: T[], cle: (e: T) => string, libelle: (cle: string) => string): Groupe<T>[] {
  const groupes = new Map<string, Groupe<T>>();
  for (const e of liste) {
    const k = cle(e);
    let g = groupes.get(k);
    if (!g) {
      g = { cle: k, libelle: libelle(k), elements: [] };
      groupes.set(k, g);
    }
    g.elements.push(e);
  }
  return [...groupes.values()];
}

/** L'ordre des groupes : chronologique, ou antichronologique ; « sans date » en dernier. */
function trier<T>(groupes: Groupe<T>[], sens: 1 | -1): Groupe<T>[] {
  return groupes.sort((a, b) => {
    if (a.cle === SANS_DATE) return 1;
    if (b.cle === SANS_DATE) return -1;
    return a.cle < b.cle ? -sens : a.cle > b.cle ? sens : 0;
  });
}

/**
 * Les pass actifs par jour, du plus proche au plus lointain ; dans un jour,
 * par heure.
 */
export function parJour<T extends AvecDate>(liste: T[], maintenant: Date): Groupe<T>[] {
  const tries = [...liste].sort((a, b) => (a.date_heure ?? '').localeCompare(b.date_heure ?? ''));
  return trier(regrouper(tries, (p) => cleJour(p.date_heure), (k) => libelleJour(k, maintenant)), 1);
}

/** L'historique par mois, du plus récent au plus ancien ; dans un mois, du plus récent au plus ancien. */
export function parMois<T extends AvecDate>(liste: T[]): Groupe<T>[] {
  const tries = [...liste].sort((a, b) => (b.date_heure ?? '').localeCompare(a.date_heure ?? ''));
  return trier(regrouper(tries, (p) => cleMois(p.date_heure), libelleMois), -1);
}
