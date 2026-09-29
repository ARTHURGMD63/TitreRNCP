/**
 * L'arrivée animée après l'écran de lancement natif (expo-splash-screen,
 * app.json → fond basalte #111013 + assets/splash-icon.png, les deux
 * anneaux seuls).
 *
 * Volontairement simple après plusieurs essais ratés (mauvais endroit à
 * l'arrivée, écran qui reste noir) : pas de déplacement, pas de
 * rétrécissement, pas d'interpolation de couleur — seulement un fondu
 * d'opacité sur les anneaux, immobiles au centre, sur un fond basalte fixe.
 * Une seule chose anime (un nombre, l'opacité), ce qui est la garantie la
 * plus simple qu'Animated puisse tenir. Moins spectaculaire, mais fiable.
 */

import React, { useEffect, useState } from 'react';
import { Animated, StyleSheet, View } from 'react-native';

import { Anneaux } from './Marque';
import { fixe, fs } from '../theme';
import { useTheme } from '../useTheme';

const HAUTEUR_ANNEAUX = fs[9];

export function IntroSplash({ onTermine }: { onTermine: () => void }) {
  const { c } = useTheme();
  const [opacite] = useState(() => new Animated.Value(1));

  useEffect(() => {
    const anim = Animated.timing(opacite, {
      toValue: 0,
      duration: 400,
      delay: 500,
      useNativeDriver: true,
    });
    anim.start(({ finished }) => { if (finished) onTermine(); });
    return () => anim.stop();
  }, [opacite, onTermine]);

  return (
    <Animated.View pointerEvents="none" style={[StyleSheet.absoluteFill, { backgroundColor: fixe.basalte, opacity: opacite }]}>
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center' }}>
        <Anneaux hauteur={HAUTEUR_ANNEAUX} encre={fixe.craie} lave={c.rouge} />
      </View>
    </Animated.View>
  );
}
