/**
 * L'accès hors connexion.
 *
 * Quelques lectures de l'API (liste blanche dans horsLigneRegles.ts : les
 * pass, le profil, la fiche qu'ouvre un pass) sont mises de côté sur
 * l'appareil. Quand le réseau manque — métro, salle bondée, mode avion —,
 * l'API relit cette copie au lieu d'afficher une erreur : l'étudiant retrouve
 * ses pass et leur QR code, son profil. Les écritures (s'inscrire, rejoindre
 * une partie…) ne sont jamais rejouées : elles échouent avec le message
 * habituel. Rien d'autre n'est écrit : ni participants, ni administration,
 * ni finances (constat MOB-01 de l'audit).
 *
 * OÙ, ET POURQUOI LÀ
 *
 * Dans le dossier « cache » de l'application (expo-file-system), exclu des
 * sauvegardes iCloud et Google : une copie de données personnelles n'a pas à
 * quitter le téléphone. Le système peut le vider s'il manque de place ; on
 * perd alors la copie, pas le compte. Le jeton, lui, reste dans le trousseau
 * (session.tsx) ; rien ici ne permet d'ouvrir le compte. Au plus
 * PLAFOND_COPIES fichiers, de moins de 7 jours, tous effacés à la
 * déconnexion.
 *
 * L'état « hors connexion » est tenu par lecture : le bandeau des écrans
 * l'écoute (useHorsLigne) et dit de quand datent les données affichées. Une
 * autre lecture qui réussit ne l'efface pas : seule une nouvelle réponse du
 * serveur pour la même adresse le fait.
 *
 * LE RETOUR DU RÉSEAU
 *
 * Rien ne relançait les lectures quand le réseau revenait : l'étudiant
 * gardait la copie et son bandeau jusqu'à tirer l'écran vers le bas
 * (constat BUG-09). L'application n'embarque pas NetInfo ; le moment le plus
 * sûr où le réseau a pu revenir est le retour au premier plan (on sort du
 * métro, on rouvre l'application). useRechargementAuRetour() relance alors
 * le chargement des écrans qui affichent une copie — et seulement eux, et
 * seulement tant qu'une copie est affichée.
 */

import { useEffect, useRef, useSyncExternalStore } from 'react';
import { AppState } from 'react-native';

import { copiesASupprimer, lireCopie, nomCopie, type Copie } from './horsLigneRegles';

export { cleLecture, copieAutorisee } from './horsLigneRegles';

/** Le module natif, chargé à la demande : absent sur le web et sous Node. */
function systemeFichiers(): typeof import('expo-file-system') | null {
  try {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    return require('expo-file-system') as typeof import('expo-file-system');
  } catch {
    return null;
  }
}

const DOSSIER = 'outsee-hors-ligne';

let ancienDossierRetire = false;

/**
 * Les versions précédentes écrivaient dans « documents », sauvegardé dans
 * iCloud, et sans liste blanche : ce dossier part une fois pour toutes.
 */
function retirerAncienDossier(fs: typeof import('expo-file-system')) {
  if (ancienDossierRetire) return;
  ancienDossierRetire = true;
  try {
    const ancien = new fs.Directory(fs.Paths.document, DOSSIER);
    if (ancien.exists) ancien.delete();
  } catch {
    // Rien à retirer, ou déjà retiré.
  }
}

function dossier() {
  const fs = systemeFichiers();
  if (!fs) return null;
  try {
    retirerAncienDossier(fs);
    const d = new fs.Directory(fs.Paths.cache, DOSSIER);
    if (!d.exists) d.create({ intermediates: true, idempotent: true });
    return { fs, d };
  } catch {
    return null;
  }
}

/** Retire les copies périmées et les plus anciennes au-delà du plafond. */
function elaguer(lieu: NonNullable<ReturnType<typeof dossier>>) {
  try {
    const fichiers = lieu.d.list()
      .filter((f): f is InstanceType<typeof lieu.fs.File> => f instanceof lieu.fs.File)
      .map((f) => ({ f, nom: f.name, modifie: f.modificationTime ?? 0 }));
    const aSupprimer = new Set(copiesASupprimer(fichiers, Date.now()));
    for (const { f, nom } of fichiers) {
      if (aSupprimer.has(nom)) {
        try { f.delete(); } catch { /* déjà parti */ }
      }
    }
  } catch {
    // Un élagage manqué n'empêche rien : il se refera à la prochaine écriture.
  }
}

/** Met de côté la réponse d'une lecture. Un échec d'écriture n'est jamais bloquant. */
export function memoriser(cle: string, donnees: unknown): void {
  const lieu = dossier();
  if (!lieu) return;
  try {
    const f = new lieu.fs.File(lieu.d, nomCopie(cle));
    f.write(JSON.stringify({ le: Date.now(), donnees } satisfies Copie<unknown>));
  } catch {
    // Disque plein, module absent : on vit sans copie.
  }
  elaguer(lieu);
}

/** La dernière copie d'une lecture, ou null s'il n'y en a pas (ou plus d'utilisable). */
export async function relire<T>(cle: string): Promise<Copie<T> | null> {
  const lieu = dossier();
  if (!lieu) return null;
  try {
    const f = new lieu.fs.File(lieu.d, nomCopie(cle));
    if (!f.exists) return null;
    const copie = lireCopie<T>(await f.text(), Date.now());
    if (!copie) {
      // Corrompue ou périmée : elle ne servira plus.
      try { f.delete(); } catch { /* tant pis */ }
    }
    return copie;
  } catch {
    return null;
  }
}

/** À la déconnexion : plus rien du compte ne reste sur l'appareil. */
export function effacerCopies(): void {
  const lieu = dossier();
  if (lieu) {
    try { lieu.d.delete(); } catch { /* déjà absent */ }
  }
  if (affichees.size > 0) {
    affichees.clear();
    publier();
  }
}

// ─── L'état partagé : quelles lectures sont affichées depuis une copie ───────

/** Par clé de lecture : la date (ms) de la copie affichée faute de réseau. */
const affichees = new Map<string, number>();
/** La plus ancienne de ces dates, ou null : tout vient du serveur. */
let depuis: number | null = null;
const abonnes = new Set<() => void>();

function publier() {
  const suivant = affichees.size === 0 ? null : Math.min(...affichees.values());
  if (suivant === depuis) return;
  depuis = suivant;
  abonnes.forEach((f) => f());
}

/**
 * Appelé par l'API pour une lecture : `null` quand le serveur a répondu pour
 * cette adresse, la date de la copie relue quand il n'a pas répondu. Le
 * bandeau garde la copie la plus ancienne encore affichée — c'est elle qui
 * dit jusqu'où les données peuvent être dépassées.
 */
export function signaler(cle: string, copieDu: number | null): void {
  if (copieDu === null) {
    if (!affichees.delete(cle)) return;
  } else {
    affichees.set(cle, copieDu);
  }
  publier();
}

function abonner(f: () => void) {
  abonnes.add(f);
  return () => { abonnes.delete(f); };
}

/** La date des données affichées hors connexion (ms), ou null en ligne. */
export function useHorsLigne(): number | null {
  return useSyncExternalStore(abonner, () => depuis, () => depuis);
}

/**
 * Tant que des données hors connexion sont affichées, relance `recharger`
 * à chaque retour de l'application au premier plan. Une lecture qui
 * aboutit efface la copie affichée (signaler) : le bandeau disparaît, et
 * l'écoute avec lui. Branché sur l'écran entier (Ecran.tsx), avec son
 * « tirer pour rafraîchir ».
 */
export function useRechargementAuRetour(recharger: (() => void) | undefined): void {
  const horsLigne = useHorsLigne() !== null;
  // Le dernier `recharger` reçu, sans réabonner l'écoute à chaque rendu.
  const rappel = useRef(recharger);
  useEffect(() => { rappel.current = recharger; }, [recharger]);

  useEffect(() => {
    if (!horsLigne) return;
    let etatPrecedent = AppState.currentState;
    const abonnement = AppState.addEventListener('change', (etat) => {
      // Seulement un vrai retour : arrière-plan (ou inactif) → actif.
      if (etat === 'active' && etatPrecedent !== 'active') rappel.current?.();
      etatPrecedent = etat;
    });
    return () => abonnement.remove();
  }, [horsLigne]);
}
