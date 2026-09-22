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

import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { api, ErreurApi, type Profil } from './api';

const CLE_JETON = 'studentlink.jeton';

type EtatSession = {
  /** null = deconnecte ; undefined = on ne sait pas encore. */
  profil: Profil | null | undefined;
  jeton: string | null;
  enCours: boolean;
  connexion: (email: string, motDePasse: string) => Promise<void>;
  deconnexion: () => Promise<void>;
};

const Contexte = createContext<EtatSession | null>(null);

/** Le nom d'appareil montre a l'utilisateur dans ses sessions actives. */
function nomAppareil(): string {
  return Platform.OS === 'ios' ? 'iPhone' : Platform.OS === 'android' ? 'Android' : 'Web';
}

async function lireJeton(): Promise<string | null> {
  try {
    return await SecureStore.getItemAsync(CLE_JETON);
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
      } catch (e) {
        if (annule) return;
        // 401 : le jeton ne vaut plus rien, on s'en debarrasse. Toute autre
        // panne — serveur injoignable — n'est PAS une raison de l'effacer :
        // l'etudiant serait deconnecte parce que son train est passe dans un
        // tunnel.
        if (e instanceof ErreurApi && e.estDeconnecte) {
          await ecrireJeton(null);
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

      async connexion(email, motDePasse) {
        setEnCours(true);
        try {
          const rep = await api.connexion(email.trim(), motDePasse, nomAppareil());
          await ecrireJeton(rep.token);
          setJeton(rep.token);
          setProfil(rep.utilisateur);
        } finally {
          setEnCours(false);
        }
      },

      async deconnexion() {
        const actuel = jeton;
        // L'etat local est vide d'abord : l'utilisateur a demande a partir, il
        // part — meme si le serveur ne repond pas. L'appel de revocation suit,
        // et son echec ne doit pas le retenir sur un ecran connecte.
        setJeton(null);
        setProfil(null);
        await ecrireJeton(null);
        if (actuel) {
          try {
            await api.deconnexion(actuel);
          } catch {
            // Le jeton expirera de lui-meme. Rien a dire a l'utilisateur.
          }
        }
      },
    }),
    [profil, jeton, enCours],
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
