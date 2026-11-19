/**
 * Numéro de téléphone (v43) : mêmes règles que includes/telephone.php.
 *
 * Seuls les mobiles français (06, 07) sont acceptés. Le serveur reste juge :
 * ce contrôle sert à prévenir avant d'envoyer un SMS pour rien.
 */

/** « 06 12 34 56 78 », « +33 6… », « 0033 6… » deviennent « +33612345678 » ; null sinon. */
export function normaliserTelephone(saisie: string): string | null {
  const s = saisie.trim().replace(/[\s.\-()]/g, '');
  let national: string;
  if (s.startsWith('+33')) national = s.slice(3);
  else if (s.startsWith('0033')) national = s.slice(4);
  else if (s.startsWith('0')) national = s.slice(1);
  else return null;
  // « +33 (0)6… » : le zéro entre parenthèses a perdu ses parenthèses.
  if (national.length === 10 && national.startsWith('0')) national = national.slice(1);
  return /^[67]\d{8}$/.test(national) ? `+33${national}` : null;
}

/** « +33612345678 » devient « 06 12 34 56 78 ». */
export function afficherTelephone(international: string): string {
  const m = /^\+33(\d{9})$/.exec(international);
  if (!m) return international;
  return `0${m[1]}`.replace(/(\d{2})(?=\d)/g, '$1 ');
}

/** Lien qui ouvre l'appel. */
export function lienAppel(international: string): string {
  return `tel:${international}`;
}
