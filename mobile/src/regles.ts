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
