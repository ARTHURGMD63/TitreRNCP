/**
 * Les règles des écrans critiques — connexion, inscription à une soirée, scan
 * des pass — sorties des composants pour pouvoir être testées sans téléphone
 * ni React Native (tests/regles.test.ts). Aucun import : ce fichier doit rester
 * exécutable tel quel par le lanceur de tests de Node.
 */

// ─── Connexion ──────────────────────────────────────────────────────────────

/**
 * La phrase affichée quand la connexion échoue. Mêmes textes que le site :
 * « Email ou mot de passe incorrect. » ne dit jamais lequel des deux est faux.
 *
 * @param erreur le code et le message d'une ErreurApi, ou null pour toute
 *               autre panne (réseau, exception inattendue).
 */
export function messageConnexion(erreur: { code: string; message: string } | null): string {
  if (!erreur) return 'Connexion impossible.';
  if (erreur.code === 'identifiants') return 'Email ou mot de passe incorrect.';
  if (erreur.code === 'trop_de_tentatives') return 'Trop de tentatives. Réessaye dans 15 minutes.';
  return erreur.message;
}

/** Les deux champs doivent être remplis ; l'email se juge sans ses espaces. */
export function connexionComplete(email: string, motDePasse: string): boolean {
  return email.trim() !== '' && motDePasse !== '';
}

// ─── Inscription à une soirée ───────────────────────────────────────────────

export type EtatInscription = 'libre' | 'attente' | 'vient';

/**
 * Le texte du bouton de la fiche d'une soirée. Une soirée déjà complète le
 * dit d'emblée, sans attendre le refus du serveur.
 */
export function libelleInscription(etat: EtatInscription, dejaInscrit: boolean, libelleErreur: string | null, complet = false): string {
  if (etat === 'vient') return 'Inscrit';
  if (dejaInscrit) return 'Tu es inscrit·e';
  if (complet) return 'Complet';
  return libelleErreur ?? "Rejoindre l'événement";
}

/**
 * Ce que devient le bouton après un refus. Une réponse du serveur (statut
 * non nul) donne sa phrase au bouton — « Complet », « Déjà inscrit » — ;
 * une panne réseau (statut 0) lui laisse une invitation à réessayer.
 */
export function libelleApresEchecInscription(statut: number | null, message: string): string {
  return statut !== null && statut !== 0 ? message : '→ je rejoins';
}

// ─── Scan des pass ──────────────────────────────────────────────────────────

type Presence = { id: number };

/** Ajoute une personne en tête de la liste des check-in, sans doublon. */
export function ajouterCheckin<T extends Presence>(liste: readonly T[], personne: T): T[] {
  return [personne, ...liste.filter((p) => p.id !== personne.id)];
}

/** Retire de la liste des invités à valider celui qui vient de l'être. */
export function retirerInvite<T extends { inscription_id: number }>(liste: readonly T[] | null, inscriptionId: number): T[] {
  return (liste ?? []).filter((i) => i.inscription_id !== inscriptionId);
}

/** Le message sur fond d'écran du scanner après un QR code refusé. */
export function messageEchecScan(erreur: { message: string } | null): string {
  return erreur ? erreur.message : 'Erreur de connexion';
}

/**
 * Combien de temps le résultat d'un scan reste affiché avant de réarmer la
 * caméra. Un succès reste un peu plus : l'accueil doit avoir le temps de lire
 * le nom ; sans ce délai, le même QR code, encore devant l'objectif, serait
 * scanné deux fois.
 */
export function delaiResultatScan(succes: boolean): number {
  return succes ? 2200 : 2000;
}

// ─── Carte des soirées ──────────────────────────────────────────────────────

/**
 * Le nombre d'inscrits à partir duquel un repère de la carte change de
 * couleur : calme en dessous de 10, puis ça monte, chaud, et plein à craquer.
 * Un nombre absolu, pas un taux de remplissage : une soirée sans quota a
 * quand même une affluence.
 */
export const SEUILS_AFFLUENCE = [10, 30, 60] as const;

export type PalierAffluence = 0 | 1 | 2 | 3;

/** Le palier d'affluence d'une soirée, de 0 (calme) à 3 (plein à craquer). */
export function palierAffluence(inscrits: number): PalierAffluence {
  if (inscrits >= SEUILS_AFFLUENCE[2]) return 3;
  if (inscrits >= SEUILS_AFFLUENCE[1]) return 2;
  if (inscrits >= SEUILS_AFFLUENCE[0]) return 1;
  return 0;
}

/** Les libellés de la légende, un par palier : « < 10 », « 10+ », « 30+ », « 60+ ». */
export function libellesAffluence(): string[] {
  return [`< ${SEUILS_AFFLUENCE[0]}`, ...SEUILS_AFFLUENCE.map((s) => `${s}+`)];
}

/** « Aucun inscrit », « 1 inscrit », « 42 inscrits ». */
export function libelleInscrits(inscrits: number): string {
  if (inscrits <= 0) return 'Aucun inscrit';
  return `${inscrits} inscrit${inscrits > 1 ? 's' : ''}`;
}
