/**
 * Les règles des notifications push qui ne dépendent d'aucun module natif :
 * elles se testent avec Node (voir tests/pushRegles.test.ts).
 */

/**
 * Les pages qu'une notification a le droit d'ouvrir.
 *
 * Le serveur envoie `data.url` ; l'application ne navigue JAMAIS vers une
 * adresse qu'elle n'a pas prévue. Une notification forgée (ou un jour mal
 * écrite côté serveur) ne doit pas pouvoir ouvrir un écran arbitraire, ni
 * une adresse externe.
 */
const ROUTES_AUTORISEES: RegExp[] = [
  /^\/notifications$/,
  /^\/abonnement$/,
  /^\/wallet$/,
  /^\/(evenement|culture|creneau|etudiant)\/\d{1,9}$/,
];

/** L'adresse à ouvrir, ou null si elle n'est pas dans la liste. */
export function routeSure(url: unknown): string | null {
  if (typeof url !== 'string') return null;
  return ROUTES_AUTORISEES.some((r) => r.test(url)) ? url : null;
}

export type EtatPermission = 'accordee' | 'a_demander' | 'refusee' | 'indisponible';
