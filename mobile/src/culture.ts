/**
 * Les règles d'affichage de l'espace Culture, sans React ni réseau :
 * testables par le lanceur intégré à Node (tests/culture.test.ts).
 *
 * Elles reprennent les définitions du serveur (includes/culture.php) :
 * un quota de 0 veut dire entrée libre, jamais complet ; un événement qui
 * dure plus d'une journée (une exposition) s'annonce par sa période plutôt
 * que par son heure de début.
 */

export type Places = { quota: number; inscrits: number; restantes: number | null; complet: boolean };

/** « Entrée libre », « Complet », « 3 places restantes », « 1 place restante ». */
export function libellePlaces(p: Places): string {
  if (p.quota <= 0) return 'Entrée libre';
  if (p.complet) return 'Complet';
  const r = p.restantes ?? Math.max(0, p.quota - p.inscrits);
  return `${r} place${r > 1 ? 's' : ''} restante${r > 1 ? 's' : ''}`;
}

/** Le taux de remplissage, ou null sans quota : une jauge n'a alors pas de sens. */
export function pourcentagePlaces(p: Places): number | null {
  if (p.quota <= 0) return null;
  return Math.min(100, Math.round((p.inscrits / p.quota) * 100));
}

/** « Gratuit », « 5 € », « 7,50 € ». */
export function libellePrix(prix: number): string {
  if (prix <= 0) return 'Gratuit';
  const arrondi = Math.round(prix * 100) / 100;
  return (Number.isInteger(arrondi) ? String(arrondi) : arrondi.toFixed(2).replace('.', ',')) + ' €';
}

/**
 * Un événement de plus de 24 heures (exposition, festival) : la carte annonce
 * « JUSQU'AU 3 DÉC » plutôt qu'une heure de début déjà passée ou sans intérêt.
 *
 * @param debut horodatage en secondes
 * @param fin   horodatage en secondes, 0 sans date de fin
 */
export function surPlusieursJours(debut: number, fin: number): boolean {
  return fin > 0 && fin - debut > 24 * 3600;
}

export type EtatInscriptionCulture = 'libre' | 'attente' | 'inscrit';

/** Le libellé du bouton d'inscription, dans l'ordre où les cas l'emportent. */
export function libelleBoutonCulture(etat: EtatInscriptionCulture, complet: boolean, termine = false): string {
  if (etat === 'inscrit') return 'Inscrit';
  if (termine) return 'Terminé';
  if (complet) return 'Complet';
  return "Je m'inscris";
}

/** « Léa, Hugo +2 y vont », comme la carte des soirées. */
export function libelleAmis(amis: { prenoms: string[]; nb: number }): string | null {
  if (amis.nb <= 0) return null;
  const noms = amis.prenoms.slice(0, 2).join(', ');
  return `${noms}${amis.nb > 2 ? ` +${amis.nb - 2}` : ''} ${amis.nb > 1 ? 'y vont' : 'y va'}`;
}
