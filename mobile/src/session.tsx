/**
 * La session : qui est connecte, et le jeton qui le prouve.
 *
 * OU EST RANGE LE JETON, ET POURQUOI LA
 *
 * Dans expo-secure-store, c'est-a-dire le trousseau du systeme — Keychain sur
 * iOS, Keystore sur Android. Pas dans AsyncStorage, qui n'est qu'un fichier
 * JSON en clair dans le bac a sable de l'application : lisible tel quel sur un
 * appareil debride, et emporte par une sauvegarde non chiffree.
 *
 * Le jeton vaut un mot de passe — il ouvre le compte sans en demander — donc
 * il se range comme un mot de passe.
 */

import React, { createContext, useContext, useEffect, useMemo, useRef, useState } from 'react';
import * as SecureStore from 'expo-secure-store';
import { AppState, Platform } from 'react-native';

import { api, ErreurApi, surDeconnexion, type Profil } from './api';
import { effacerCopies } from './horsLigne';
import { retirerAppareil, synchroniserAppareil } from './notifications';

const CLE_JETON = 'outsee.jeton';
/** Clés d'avant les changements de nom : relues une fois, puis effacées. */
const ANCIENNES_CLES_JETON = ['linkee.jeton', 'studentlink.jeton'];

type EtatSession = {
  /** null = deconnecte ; undefined = on ne sait pas encore. */
  profil: Profil | null | undefined;
  jeton: string | null;
  enCours: boolean;
  /** Vrai juste après une inscription : l'accueil (onboarding) s'affiche. */
  nouveau: boolean;
  connexion: (email: string, motDePasse: string, codeTotp?: string) => Promise<void>;
  inscription: (champs: Record<string, unknown>) => Promise<void>;
  terminerAccueil: () => void;
  /** Après « Enregistrer les modifications » : photo, école, promo. */
  mettreAJour: (p: Profil) => void;
  deconnexion: () => Promise<void>;
};

const Contexte = createContext<EtatSession | null>(null);

/** Le nom d'appareil montre a l'utilisateur dans ses sessions actives. */
function nomAppareil(): string {
  return Platform.OS === 'ios' ? 'iPhone' : Platform.OS === 'android' ? 'Android' : 'Web';
}

async function lireJeton(): Promise<string | null> {
  try {
    const jeton = await SecureStore.getItemAsync(CLE_JETON);
    if (jeton) return jeton;
    // Une installation d'avant Outsee garde son jeton sous l'ancien nom :
    // on le reprend plutôt que de déconnecter tout le monde à la mise à jour.
    for (const cle of ANCIENNES_CLES_JETON) {
      const ancien = await SecureStore.getItemAsync(cle);
      if (!ancien) continue;
      await SecureStore.setItemAsync(CLE_JETON, ancien);
      await SecureStore.deleteItemAsync(cle);
      return ancien;
    }
    return null;
  } catch {
    // Le trousseau peut etre indisponible — appareil verrouille juste apres
    // le demarrage, ou execution sur le web ou SecureStore n'existe pas. On
    // repart comme si personne n'etait connecte plutot que de planter au
    // lancement.
    return null;
  }
}

async function ecrireJeton(jeton: string | null): Promise<void> {
  try {
    if (jeton === null) await SecureStore.deleteItemAsync(CLE_JETON);
    else await SecureStore.setItemAsync(CLE_JETON, jeton);
  } catch (e) {
    console.warn('Trousseau indisponible', e);
  }
}

export function FournisseurSession({ children }: { children: React.ReactNode }) {
  const [profil, setProfil] = useState<Profil | null | undefined>(undefined);
  const [jeton, setJeton] = useState<string | null>(null);
  const [enCours, setEnCours] = useState(false);
  const [nouveau, setNouveau] = useState(false);
  // Le jeton courant, lisible depuis les rappels sans les recréer.
  const jetonCourant = useRef<string | null>(null);
  useEffect(() => { jetonCourant.current = jeton; }, [jeton]);

  // Un jeton refusé en cours de route (expiré, révoqué depuis un autre
  // appareil) : déconnexion locale, sans appel au serveur — il ne reconnaît
  // déjà plus ce jeton. Seulement si c'est toujours le nôtre : la réponse
  // tardive d'une ancienne session ne déconnecte pas la nouvelle.
  useEffect(() => surDeconnexion((refuse) => {
    if (refuse !== jetonCourant.current) return;
    jetonCourant.current = null;
    setJeton(null);
    setProfil(null);
    setNouveau(false);
    effacerCopies();
    void ecrireJeton(null);
  }), []);

  // Le jeton push peut changer : il est rattaché à nouveau à chaque retour
  // au premier plan (seulement si la permission est déjà donnée).
  useEffect(() => {
    if (!jeton) return;
    const abonnement = AppState.addEventListener('change', (etat) => {
      if (etat === 'active') void synchroniserAppareil(jeton);
    });
    return () => abonnement.remove();
  }, [jeton]);

  // Au lancement : un jeton range ne prouve pas que la session vit encore. Il
  // a pu expirer, ou etre revoque depuis un autre appareil. On le verifie
  // aupres du serveur avant d'afficher quoi que ce soit de connecte.
  useEffect(() => {
    let annule = false;

    (async () => {
      const range = await lireJeton();
      if (!range) {
        if (!annule) setProfil(null);
        return;
      }

      try {
        const { utilisateur } = await api.moi(range);
        if (annule) return;
        setJeton(range);
        setProfil(utilisateur);
        void synchroniserAppareil(range);
      } catch (e) {
        if (annule) return;
        // 401 : le jeton ne vaut plus rien, on s'en debarrasse. Toute autre
        // panne — serveur injoignable — n'est PAS une raison de l'effacer :
        // l'etudiant serait deconnecte parce que son train est passe dans un
        // tunnel.
        // Hors connexion, api.moi() relit le profil mis de côté (horsLigne.ts) :
        // on n'arrive ici que sans copie, au tout premier lancement.
        if (e instanceof ErreurApi && e.estDeconnecte) {
          await ecrireJeton(null);
          effacerCopies();
        }
        setProfil(null);
      }
    })();

    return () => {
      annule = true;
    };
  }, []);

  const valeur = useMemo<EtatSession>(
    () => ({
      profil,
      jeton,
      enCours,
      nouveau,

      async connexion(email, motDePasse, codeTotp) {
        setEnCours(true);
        try {
          const rep = await api.connexion(email.trim(), motDePasse, nomAppareil(), codeTotp);
          // Un autre compte a pu se servir de l'appareil : ses copies partent.
          effacerCopies();
          await ecrireJeton(rep.token);
          setJeton(rep.token);
          setProfil(rep.utilisateur);
          void synchroniserAppareil(rep.token);
        } finally {
          setEnCours(false);
        }
      },

      async inscription(champs) {
        setEnCours(true);
        try {
          const rep = await api.inscription({ ...champs, appareil: nomAppareil() });
          effacerCopies();
          await ecrireJeton(rep.token);
          setNouveau(rep.utilisateur.type === 'etudiant');
          setJeton(rep.token);
          setProfil(rep.utilisateur);
          void synchroniserAppareil(rep.token);
        } finally {
          setEnCours(false);
        }
      },

      terminerAccueil() {
        setNouveau(false);
      },

      mettreAJour(p) {
        setProfil(p);
      },

      async deconnexion() {
        const actuel = jeton;
        // L'etat local est vide d'abord : l'utilisateur a demande a partir, il
        // part — meme si le serveur ne repond pas. L'appel de revocation suit,
        // et son echec ne doit pas le retenir sur un ecran connecte.
        setJeton(null);
        setProfil(null);
        await ecrireJeton(null);
        effacerCopies();
        if (actuel) {
          // Le téléphone est retiré du compte AVANT la révocation : après,
          // le serveur ne reconnaîtrait plus le jeton qui le prouve, et le
          // prochain compte connecté ici recevrait les messages de celui-ci.
          await retirerAppareil(actuel);
          try {
            await api.deconnexion(actuel);
          } catch {
            // Le jeton expirera de lui-meme. Rien a dire a l'utilisateur.
          }
        }
      },
    }),
    [profil, jeton, enCours, nouveau],
  );

  return <Contexte.Provider value={valeur}>{children}</Contexte.Provider>;
}

export function useSession(): EtatSession {
  const ctx = useContext(Contexte);
  if (!ctx) throw new Error('useSession doit être utilisé dans <FournisseurSession>');
  return ctx;
}

/**
 * Le jeton, en exigeant qu'il existe.
 *
 * Les ecrans derriere la garde d'authentification savent qu'ils en ont un ;
 * cette fonction evite d'ecrire `jeton!` dans chacun d'eux.
 */
export function useJeton(): string {
  const { jeton } = useSession();
  if (!jeton) throw new Error('Écran authentifié rendu sans jeton');
  return jeton;
}
