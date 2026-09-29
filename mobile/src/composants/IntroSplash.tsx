/**
 * L'arrivée animée après l'écran de lancement natif (expo-splash-screen,
 * app.json → fond basalte #111013 + assets/splash-icon.png, les deux
 * anneaux seuls). Les deux mondes doivent se raccorder sans à-coup : mêmes
 * anneaux, même fond, donc le passage de l'un à l'autre ne se voit pas —
 * seule cette vue anime ensuite un vrai mouvement, la glissade et le
 * rétrécissement des anneaux vers leur place dans l'en-tête (Marque, EnTete
 * paddingTop 24 + gouttière), pendant que le fond se dissout et laisse
 * apparaître l'écran réel, déjà monté dessous avec sa propre Marque à sa
 * place.
 *
 * Pas de mesure de mise en page nécessaire : la taille des anneaux est une
 * formule fixe (Anneaux : largeur = hauteur × 1.62), donc leur point de
 * départ (centre de l'écran) et d'arrivée (en-tête) se calculent directement.
 */

import React, { useEffect, useState } from 'react';
import { Animated, Easing, StyleSheet, useWindowDimensions } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import * as SecureStore from 'expo-secure-store';

import { Anneaux } from './Marque';
import { fixe, fs, gutter } from '../theme';
import { useTheme } from '../useTheme';

const CLE_INTRO_VUE = 'linkee.intro_vue';

/**
 * L'arrivée animée ne mérite d'être vue qu'une fois : rejouée à chaque
 * réouverture, l'effet de marque devient de l'attente pour qui rouvre
 * l'application dix fois par jour. Le trousseau système sert déjà au jeton
 * (session.tsx) — pas besoin d'une dépendance de plus pour un simple
 * booléen qui, comme le jeton, doit survivre aux réinstallations de l'app.
 */
export async function introDejaVue(): Promise<boolean> {
  try {
    return (await SecureStore.getItemAsync(CLE_INTRO_VUE)) === '1';
  } catch {
    // Trousseau indisponible : on préfère rejouer l'intro une fois de trop
    // plutôt que risquer de ne plus jamais la montrer.
    return false;
  }
}

export function marquerIntroVue(): void {
  SecureStore.setItemAsync(CLE_INTRO_VUE, '1').catch(() => {});
}

const HAUTEUR_ANNEAUX = fs[6];
const LARGEUR_ANNEAUX = HAUTEUR_ANNEAUX * 1.62;
// Grossis par rapport à leur taille d'en-tête : c'est ce rétrécissement qui
// rend l'arrivée cool plutôt qu'un simple fondu à plat.
const ECHELLE_DEPART = 1.9;
// Aligné entre le paddingTop de EnTete (24) et celui, un peu plus généreux,
// de l'écran de connexion (46) : l'écart disparaît de toute façon sous le
// fondu avant que l'œil ait le temps de le mesurer.
const DECALAGE_HAUT_CIBLE = 30;

export function IntroSplash({ onTermine }: { onTermine: () => void }) {
  const { c } = useTheme();
  const { width, height } = useWindowDimensions();
  const { top } = useSafeAreaInsets();
  const [progres] = useState(() => new Animated.Value(0));

  useEffect(() => {
    const anim = Animated.timing(progres, {
      toValue: 1,
      duration: 700,
      delay: 250,
      easing: Easing.out(Easing.cubic),
      useNativeDriver: false,
    });
    anim.start(({ finished }) => { if (finished) onTermine(); });
    return () => anim.stop();
  }, [progres, onTermine]);

  const centreGauche = width / 2 - LARGEUR_ANNEAUX / 2;
  const centreHaut = height / 2 - HAUTEUR_ANNEAUX / 2;
  const cibleGauche = gutter;
  const cibleHaut = top + DECALAGE_HAUT_CIBLE;

  const gauche = progres.interpolate({ inputRange: [0, 1], outputRange: [centreGauche, cibleGauche] });
  const hautPos = progres.interpolate({ inputRange: [0, 1], outputRange: [centreHaut, cibleHaut] });
  const echelle = progres.interpolate({ inputRange: [0, 1], outputRange: [ECHELLE_DEPART, 1] });
  // Le fond (et les anneaux avec lui, mêmes enfants) ne se dissout que dans
  // le dernier quart : le mouvement doit se voir avant de disparaître.
  const opacite = progres.interpolate({ inputRange: [0, 0.75, 1], outputRange: [1, 1, 0] });

  return (
    <Animated.View
      pointerEvents="none"
      style={[StyleSheet.absoluteFill, { backgroundColor: fixe.basalte, opacity: opacite }]}
    >
      <Animated.View style={{ position: 'absolute', left: gauche, top: hautPos, transform: [{ scale: echelle }] }}>
        <Anneaux hauteur={HAUTEUR_ANNEAUX} encre={fixe.craie} lave={c.rouge} />
      </Animated.View>
    </Animated.View>
  );
}
