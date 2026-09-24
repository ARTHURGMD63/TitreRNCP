/**
 * Le thème courant, et le moyen d'en changer.
 *
 * Comme le site : sombre par défaut (« la nuit pour les étudiants »), clair
 * sur demande depuis Moi › Apparence, et le choix est retenu d'une ouverture à
 * l'autre. Le site le range dans localStorage ; ici il va dans le stockage
 * de l'appareil (expo-secure-store, déjà présent pour le jeton).
 */

import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';
import * as SecureStore from 'expo-secure-store';

import { couleurs, ombre, type Couleurs, type Mode } from './theme';

const CLE = 'linkee.theme';

type EtatTheme = {
  mode: Mode;
  c: Couleurs;
  sombre: boolean;
  ombre: (niveau?: 'sm' | 'base' | 'lg') => ReturnType<typeof ombre>;
  choisir: (mode: Mode) => void;
};

const Contexte = createContext<EtatTheme | null>(null);

export function FournisseurTheme({ children }: { children: React.ReactNode }) {
  const [mode, setMode] = useState<Mode>('sombre');

  useEffect(() => {
    let annule = false;
    SecureStore.getItemAsync(CLE)
      .then((v) => {
        if (!annule && (v === 'clair' || v === 'sombre')) setMode(v);
      })
      .catch(() => {
        // Stockage indisponible (web, appareil verrouillé) : on garde le défaut.
      });
    return () => {
      annule = true;
    };
  }, []);

  const valeur = useMemo<EtatTheme>(
    () => ({
      mode,
      c: couleurs[mode],
      sombre: mode === 'sombre',
      ombre: (niveau = 'base') => ombre(mode, niveau),
      choisir(m) {
        setMode(m);
        SecureStore.setItemAsync(CLE, m).catch(() => {});
      },
    }),
    [mode],
  );

  return <Contexte.Provider value={valeur}>{children}</Contexte.Provider>;
}

export function useTheme(): EtatTheme {
  const ctx = useContext(Contexte);
  if (!ctx) throw new Error('useTheme doit être utilisé dans <FournisseurTheme>');
  return ctx;
}
