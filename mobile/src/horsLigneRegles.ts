/**
 * Les règles de la copie hors connexion qui ne dépendent d'aucun module
 * natif : elles se testent avec Node (voir tests/horsLigneRegles.test.ts).
 *
 * Ce qui est mis de côté, sous quel nom, combien de temps, et combien de
 * fichiers au plus. Le stockage lui-même vit dans horsLigne.ts.
 */

/**
 * La version du format des copies. À augmenter quand la forme d'une réponse
 * change au point qu'un écran planterait en relisant une copie d'avant la
 * mise à jour : les anciennes copies ne sont alors plus jamais relues.
 */
export const VERSION_COPIES = 1;

/** Au-delà, une copie est trop vieille pour être présentée (7 jours). */
export const DUREE_COPIE_MS = 7 * 24 * 3600 * 1000;

/** Nombre maximal de fichiers gardés ; les plus anciens partent d'abord. */
export const PLAFOND_COPIES = 50;

/**
 * Les seules lectures mises de côté : ce qui sert vraiment sans réseau, à
 * l'entrée d'une soirée ou dans le métro.
 *
 *  - wallet.php            : les pass et leur QR code ;
 *  - me.php                : le profil, pour ouvrir l'application sans réseau ;
 *  - moi.php               : la page Moi ;
 *  - partenaire_profil.php : le type d'établissement, qui décide des onglets ;
 *  - evenement.php, culture_evenement.php, sport_creneau.php : la fiche
 *    qu'ouvre un pass (soirée, Culture, partie).
 *
 * JAMAIS : les listes de participants (e-mails), l'administration, les
 * finances, le suivi d'un événement côté établissement, l'annuaire. Une
 * lecture absente de cette liste n'est ni écrite ni relue (RGPD :
 * minimisation, constat MOB-01 de l'audit).
 */
const LECTURES_GARDEES = new Set([
  'wallet.php',
  'me.php',
  'moi.php',
  'partenaire_profil.php',
  'evenement.php',
  'culture_evenement.php',
  'sport_creneau.php',
]);

/**
 * Vrai si la lecture de ce chemin (relatif à api/v1/, paramètres compris)
 * peut être mise de côté.
 */
export function copieAutorisee(chemin: string): boolean {
  const fichier = chemin.replace(/^\/+/, '').split(/[?#]/)[0] ?? '';
  return LECTURES_GARDEES.has(fichier);
}

/**
 * La clé d'une lecture : la version du format, puis l'adresse sans le
 * serveur. Le jeton n'y entre pas (il change à chaque connexion) ; c'est
 * l'effacement à la déconnexion qui empêche un compte de relire la copie
 * d'un autre.
 */
export function cleLecture(url: string): string {
  return `v${VERSION_COPIES}:${url.replace(/^https?:\/\/[^/]+/, '')}`;
}

/** Un nom de fichier stable pour une clé (FNV-1a 32 bits, en base 36). */
export function nomCopie(cle: string): string {
  let h = 0x811c9dc5;
  for (let i = 0; i < cle.length; i++) {
    h ^= cle.charCodeAt(i);
    h = Math.imul(h, 0x01000193) >>> 0;
  }
  return `${h.toString(36)}-${cle.length}.json`;
}

export type Copie<T> = { le: number; donnees: T };

/**
 * Le texte d'un fichier de copie, relu : la copie si elle est intacte et
 * récente, null sinon. Une copie corrompue (fichier tronqué, JSON illisible,
 * forme inattendue) est ignorée, jamais une cause de plantage.
 */
export function lireCopie<T>(texte: string, maintenant: number): Copie<T> | null {
  let copie: unknown;
  try {
    copie = JSON.parse(texte);
  } catch {
    return null;
  }
  if (typeof copie !== 'object' || copie === null) return null;
  const { le, donnees } = copie as { le?: unknown; donnees?: unknown };
  if (typeof le !== 'number' || !Number.isFinite(le)) return null;
  if (donnees === undefined || donnees === null || typeof donnees !== 'object') return null;
  if (maintenant - le > DUREE_COPIE_MS || le > maintenant + 60000) return null;
  return { le, donnees: donnees as T };
}

/**
 * Les fichiers à supprimer pour revenir sous le plafond : les plus anciens.
 * `fichiers` : nom et date de dernière écriture (ms, 0 si inconnue).
 */
export function copiesASupprimer(fichiers: { nom: string; modifie: number }[], maintenant: number, plafond = PLAFOND_COPIES): string[] {
  const perimes = fichiers.filter((f) => f.modifie > 0 && maintenant - f.modifie > DUREE_COPIE_MS);
  const restants = fichiers
    .filter((f) => !perimes.includes(f))
    .sort((a, b) => b.modifie - a.modifie);
  return [...perimes, ...restants.slice(plafond)].map((f) => f.nom);
}
