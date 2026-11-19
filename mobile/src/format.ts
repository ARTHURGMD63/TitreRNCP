/**
 * Mise en forme des dates et des nombres, à l'identique du site.
 *
 * dateFr() est la transposition de la fonction PHP du même nom
 * (includes/auth_check.php) : les mêmes lettres de format que date(), puis
 * les mêmes abréviations françaises — « Jeu 12 Mars », « Sept », « Déc ».
 * Les heures s'écrivent « 20h30 » comme sur le site (format « H\hi »).
 *
 * LES DATES SONT À L'HEURE DE PARIS, QUEL QUE SOIT LE TÉLÉPHONE
 *
 * L'API renvoie « 2026-09-23 20:30:00 » sans fuseau : c'est l'heure de Paris
 * (le serveur tourne en Europe/Paris, includes/db.php). Avant, ces dates
 * étaient lues comme l'heure du téléphone : en voyage ou sur un téléphone
 * réglé sur un autre fuseau, les comptes à rebours et les « en cours » se
 * décalaient d'autant (constat BUG-08). Désormais :
 *
 *  - une date sans décalage est lue comme l'heure de Paris, et convertie en
 *    instant exact (lireDate, ts) ; une date ISO avec décalage
 *    (« …+02:00 », « …Z ») est lue telle quelle ;
 *  - l'affichage (dateFr, heure) écrit toujours l'heure de Paris : une
 *    soirée à 20h30 à Paris s'affiche « 20h30 », où que soit le téléphone.
 *
 * Les règles de l'heure d'été (dernier dimanche de mars et d'octobre, à 1 h
 * UTC) sont calculées ici plutôt que demandées à Intl : le moteur Hermes ne
 * garantit pas les fuseaux nommés partout, et ce module doit rester
 * testable sous Node sans dépendance (tests/format.test.ts).
 *
 * On lit les champs nous-mêmes plutôt qu'avec new Date(chaîne) : l'analyse
 * d'une date sans fuseau varie d'un moteur JavaScript à l'autre.
 */

const JOURS = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
const JOURS_LONGS = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
const MOIS = ['Janv', 'Févr', 'Mars', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];
const MOIS_LONGS = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

const MINUTE = 60_000;
const JOUR = 86_400_000;

/** Le dernier dimanche d'un mois, à 1 h UTC : l'heure des changements d'heure européens. */
function changementHeure(annee: number, mois0: number): number {
  const dernierJour = Date.UTC(annee, mois0 + 1, 0);
  return dernierJour - new Date(dernierJour).getUTCDay() * JOUR + 60 * MINUTE;
}

/** Le décalage de Paris sur UTC à cet instant, en minutes : 60 l'hiver, 120 l'été. */
export function decalageParis(instant: number): number {
  const annee = new Date(instant).getUTCFullYear();
  return instant >= changementHeure(annee, 2) && instant < changementHeure(annee, 9) ? 120 : 60;
}

/**
 * L'instant d'une heure de Paris donnée champ par champ. Une heure qui
 * n'existe pas (le saut de mars) avance d'une heure ; une heure qui existe
 * deux fois (le retour d'octobre) est prise à l'heure d'hiver.
 */
function instantDeParis(annee: number, mois0: number, jour: number, h: number, mi: number, s: number): number {
  const mur = Date.UTC(annee, mois0, jour, h, mi, s);
  const essai = mur - decalageParis(mur - 60 * MINUTE) * MINUTE;
  return mur - decalageParis(essai) * MINUTE;
}

type Champs = { annee: number; mois0: number; jour: number; semaine: number; h: number; mi: number };

/** Les champs de l'heure de Paris à cet instant (année, mois, jour, heure…). */
function champsParis(d: Date): Champs {
  const p = new Date(d.getTime() + decalageParis(d.getTime()) * MINUTE);
  return {
    annee: p.getUTCFullYear(), mois0: p.getUTCMonth(), jour: p.getUTCDate(),
    semaine: p.getUTCDay(), h: p.getUTCHours(), mi: p.getUTCMinutes(),
  };
}

const MOTIF_DATE = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2})(?:\.\d+)?)?\s*(Z|[+-]\d{2}:?\d{2})?)?/;

/**
 * « 2026-09-23 20:30:00 » (ou « 2026-09-23T20:30 », ou une date seule) lue
 * comme l'heure de Paris → l'instant exact. Une date ISO avec décalage
 * (« 2026-09-23T18:30:00Z », « …+02:00 ») garde le sien.
 */
export function lireDate(valeur: string | null | undefined): Date | null {
  if (!valeur) return null;
  const m = MOTIF_DATE.exec(valeur);
  if (!m) return null;
  const [annee, mois0, jour, h, mi, s] = [+m[1], +m[2] - 1, +m[3], +(m[4] ?? 0), +(m[5] ?? 0), +(m[6] ?? 0)];
  const decalage = m[7];
  if (decalage) {
    const mur = Date.UTC(annee, mois0, jour, h, mi, s);
    if (decalage === 'Z') return new Date(mur);
    const signe = decalage[0] === '-' ? -1 : 1;
    const chiffres = decalage.slice(1).replace(':', '');
    return new Date(mur - signe * (+chiffres.slice(0, 2) * 60 + +chiffres.slice(2, 4)) * MINUTE);
  }
  return new Date(instantDeParis(annee, mois0, jour, h, mi, s));
}

/**
 * Pour les formulaires seulement : la date de l'API rendue avec les mêmes
 * chiffres dans le fuseau du téléphone, comme l'affichent et la renvoient
 * les sélecteurs de date (isoDateHeure, Formulaire.tsx). Un événement saisi
 * « 20h30 » repart « 20h30 », heure de Paris pour le serveur.
 */
export function lireDateFormulaire(valeur: string | null | undefined): Date | null {
  if (!valeur) return null;
  const m = MOTIF_DATE.exec(valeur);
  if (!m) return null;
  return new Date(+m[1], +m[2] - 1, +m[3], +(m[4] ?? 0), +(m[5] ?? 0), +(m[6] ?? 0));
}

/**
 * L'heure de Paris de cet instant, rendue comme une date du téléphone aux
 * mêmes chiffres : pour les calculs « aujourd'hui / demain » qui comparent
 * des jours de Paris (regroupement.ts) avec les accesseurs habituels.
 */
export function murDeParis(instant: number): Date {
  const c = champsParis(new Date(instant));
  return new Date(c.annee, c.mois0, c.jour, c.h, c.mi);
}

const deux = (n: number) => (n < 10 ? '0' + n : String(n));

/**
 * Les lettres de date() utilisées par l'application : D j M F l Y H i G,
 * et « \x » pour un caractère littéral. Toujours écrit à l'heure de Paris.
 */
export function dateFr(valeur: string | Date | null | undefined, format = 'D j M'): string {
  const d = valeur instanceof Date ? valeur : lireDate(valeur);
  if (!d || Number.isNaN(d.getTime())) return '';
  const c = champsParis(d);
  let sortie = '';
  for (let i = 0; i < format.length; i++) {
    const car = format[i];
    if (car === '\\') { sortie += format[++i] ?? ''; continue; }
    switch (car) {
      case 'D': sortie += JOURS[c.semaine]; break;
      case 'l': sortie += JOURS_LONGS[c.semaine]; break;
      case 'j': sortie += c.jour; break;
      case 'd': sortie += deux(c.jour); break;
      case 'M': sortie += MOIS[c.mois0]; break;
      case 'F': sortie += MOIS_LONGS[c.mois0]; break;
      case 'm': sortie += deux(c.mois0 + 1); break;
      case 'Y': sortie += c.annee; break;
      case 'H': sortie += deux(c.h); break;
      case 'G': sortie += c.h; break;
      case 'i': sortie += deux(c.mi); break;
      default: sortie += car;
    }
  }
  return sortie;
}

/** « 20h30 » */
export const heure = (valeur: string | Date | null | undefined) => dateFr(valeur, 'H\\hi');

/** Horodatage en secondes, comme strtotime() sur un serveur à l'heure de Paris. */
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

/**
 * Le compte à rebours du Pass (refonte 2026-11), calculé sur l'appareil à
 * partir de la date de l'événement : « Dans 2 h », « Dans 25 min »,
 * « Maintenant » une fois commencé, et au-delà de 24 h le jour plutôt
 * qu'un nombre d'heures (« Mercredi »).
 *
 * @param debut Horodatage de l'événement, en secondes (ts()).
 * @param maintenant Horodatage courant, en millisecondes (Date.now()).
 */
export function compteARebours(debut: number, maintenant: number): string {
  const minutes = Math.floor((debut * 1000 - maintenant) / 60000);
  if (minutes <= 0) return 'Maintenant';
  if (minutes < 60) return `Dans ${minutes} min`;
  if (minutes < 24 * 60) return `Dans ${Math.floor(minutes / 60)} h`;
  return dateFr(new Date(debut * 1000), 'l');
}

/**
 * Une durée courte, des jours aux secondes : « 1j11h », « 2h05 »,
 * « 12min04s ». Au-delà de 24 h on compte en jours et en heures, jamais
 * « 35h11 » : personne ne convertit de tête.
 *
 * @param ms Durée en millisecondes (positive).
 */
export function dureeCourte(ms: number): string {
  const total = Math.max(0, Math.floor(ms / 1000));
  const j = Math.floor(total / 86400);
  const h = Math.floor((total % 86400) / 3600);
  const m = Math.floor((total % 3600) / 60);
  const s = total % 60;
  if (j > 0) return h > 0 ? `${j}j${h}h` : `${j}j`;
  if (h > 0) return `${h}h${m < 10 ? '0' : ''}${m}`;
  return `${m}min${s < 10 ? '0' : ''}${s}s`;
}
