/**
 * Le toast du site (.toast) : une pilule à l'encre du thème, posée au-dessus
 * de la barre d'onglets, qui monte de 14 px en apparaissant et s'efface au
 * bout de 2,8 s. Validé en volt, erreur en alerte vive, texte basalte.
 *
 * Un seul pour toute l'application, appelé par toast('…', 'success').
 *
 * Sur le site le toast passe au-dessus des feuilles modales (z-index 300
 * contre 200). Une Modal native, elle, recouvre tout ce qui est dessiné hors
 * d'elle : la feuille affiche donc sa propre copie du même toast (<VueToast>),
 * sans quoi « Invitation envoyée ! » s'afficherait sous la feuille, invisible.
 */

import React, { createContext, useCallback, useContext, useMemo, useRef, useState } from 'react';
import { Animated, Easing, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { fixe, fs, rayon, sans } from '../theme';
import { ecartBasBarre, HAUTEUR_PILULE } from './BarreOnglets';
import { useTheme } from '../useTheme';

type Genre = '' | 'success' | 'error';
type Afficher = (message: string, genre?: Genre) => void;

type Etat = {
  afficher: Afficher;
  message: { texte: string; genre: Genre } | null;
  apparition: Animated.Value;
};

const Contexte = createContext<Etat | null>(null);

const courbe = Easing.bezier(0.2, 0.9, 0.3, 1);

export function FournisseurToast({ children }: { children: React.ReactNode }) {
  const [message, setMessage] = useState<Etat['message']>(null);
  const [apparition] = useState(() => new Animated.Value(0));
  const minuteur = useRef<ReturnType<typeof setTimeout> | null>(null);

  const afficher = useCallback<Afficher>((texte, genre = '') => {
    setMessage({ texte, genre });
    if (minuteur.current) clearTimeout(minuteur.current);
    Animated.timing(apparition, { toValue: 1, duration: 280, easing: courbe, useNativeDriver: true }).start();
    minuteur.current = setTimeout(() => {
      Animated.timing(apparition, { toValue: 0, duration: 280, easing: courbe, useNativeDriver: true }).start();
    }, 2800);
  }, [apparition]);

  const valeur = useMemo(() => ({ afficher, message, apparition }), [afficher, message, apparition]);

  return (
    <Contexte.Provider value={valeur}>
      {children}
      <VueToast />
    </Contexte.Provider>
  );
}

/** Le dessin du toast ; posé à la racine, et dans chaque feuille ouverte. */
export function VueToast() {
  const ctx = useContext(Contexte);
  const { c, ombre } = useTheme();
  const bas = useSafeAreaInsets().bottom;
  if (!ctx?.message) return null;

  const { genre, texte } = ctx.message;
  const fond = genre === 'success' ? c.lime : genre === 'error' ? c.alerteVif : c.noir;
  const encre = genre ? fixe.surLave : c.bg;

  return (
    <View pointerEvents="none" style={{ position: 'absolute', left: 0, right: 0, bottom: ecartBasBarre(bas) + HAUTEUR_PILULE + 18, alignItems: 'center' }}>
      <Animated.View
        accessibilityLiveRegion="polite"
        style={[
          { backgroundColor: fond, paddingVertical: 12, paddingHorizontal: 20, borderRadius: rayon.pill, maxWidth: '92%' },
          ombre('lg'),
          { opacity: ctx.apparition, transform: [{ translateY: ctx.apparition.interpolate({ inputRange: [0, 1], outputRange: [14, 0] }) }] },
        ]}
      >
        <Text numberOfLines={2} style={{ fontFamily: sans(600), fontSize: fs[3], color: encre, textAlign: 'center' }}>{texte}</Text>
      </Animated.View>
    </View>
  );
}

export function useToast(): Afficher {
  const ctx = useContext(Contexte);
  return ctx ? ctx.afficher : () => {};
}
