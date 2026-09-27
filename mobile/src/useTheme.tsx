/**
 * Le thème courant, et le moyen d'en changer.
 *
 * Trois préférences, comme le sélecteur de Moi › Apparence : « Système »
 * (par défaut — suit clair/sombre du téléphone, y compris quand il change
 * en cours d'usage), ou un choix figé, clair ou sombre. Le réglage lui-même
 * (pas le mode résolu) est retenu d'une ouverture à l'autre, dans le
 * stockage de l'appareil (expo-secure-store, déjà présent pour le jeton).
 */

import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { useColorScheme } from 'react-native';
import * as SecureStore from 'expo-secure-store';

import { couleurs, ombre, type Couleurs, type Mode } from './theme';

const CLE = 'linkee.theme';

export type Preference = 'systeme' | Mode;

type EtatTheme = {
  mode: Mode;
  c: Couleurs;
  sombre: boolean;
  preference: Preference;
  ombre: (niveau?: 'sm' | 'base' | 'lg') => ReturnType<typeof ombre>;
  choisir: (preference: Preference) => void;
};

const Contexte = createContext<EtatTheme | null>(null);

export function FournisseurTheme({ children }: { children: React.ReactNode }) {
  const [preference, setPreference] = useState<Preference>('systeme');
  // null sur un appareil qui ne sait pas dire son réglage (rare) : on garde
  // alors le sombre historique, plutôt que de basculer au hasard.
  const schemeOS = useColorScheme();

  useEffect(() => {
    let annule = false;
    SecureStore.getItemAsync(CLE)
      .then((v) => {
        if (!annule && (v === 'clair' || v === 'sombre' || v === 'systeme')) setPreference(v);
      })
      .catch(() => {
        // Stockage indisponible (web, appareil verrouillé) : on garde le défaut.
      });
    return () => {
      annule = true;
    };
  }, []);

  const mode: Mode = preference === 'systeme' ? (schemeOS === 'light' ? 'clair' : 'sombre') : preference;

  const valeur = useMemo<EtatTheme>(
    () => ({
      mode,
      c: couleurs[mode],
      sombre: mode === 'sombre',
      preference,
      ombre: (niveau = 'base') => ombre(mode, niveau),
      choisir(p) {
        setPreference(p);
        SecureStore.setItemAsync(CLE, p).catch(() => {});
      },
    }),
    [mode, preference],
  );

  return <Contexte.Provider value={valeur}>{children}</Contexte.Provider>;
}

export function useTheme(): EtatTheme {
  const ctx = useContext(Contexte);
  if (!ctx) throw new Error('useTheme doit être utilisé dans <FournisseurTheme>');
  return ctx;
}
