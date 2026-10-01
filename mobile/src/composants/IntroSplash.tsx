/**
 * L'arrivée animée après l'écran de lancement natif (expo-splash-screen,
 * app.json → fond basalte #111013 + assets/splash-icon.png, les deux
 * anneaux seuls).
 *
 * Troisième version, après deux ratées : les deux premières tentaient de
 * faire atterrir le logo exactement à sa place dans l'en-tête de l'écran
 * suivant (glissade + rétrécissement) — un calcul de position fragile, et le
 * résultat ne convainquait de toute façon pas. Celle-ci ne vise plus aucun
 * endroit précis : les anneaux et le mot « linkee » se composent au centre
 * de l'écran, chacun avec sa propre entrée, puis tout l'écran s'efface pour
 * révéler l'application, déjà montée dessous. Plus simple à régler, et plus
 * « motion design » qu'un simple fondu plat : un ressort sur les anneaux
 * (léger rebond), puis le mot qui glisse juste après, un temps de pause pour
 * laisser lire la marque, puis la sortie.
 *
 * Toujours la même règle, qui a déjà coûté un essai raté : jamais de couleur
 * animée (`interpolate` entre deux couleurs → écran rendu noir sur certains
 * appareils). Uniquement de l'opacité et des transforms (scale, rotate,
 * translateX), le seul terrain qu'Animated garantit avec useNativeDriver.
 */

import React, { useEffect, useState } from 'react';
import { Animated, Easing, StyleSheet, Text, View } from 'react-native';

import { Anneaux } from './Marque';
import { fixe, fs, police } from '../theme';
import { useTheme } from '../useTheme';

const TAILLE = fs[9];
const MOT = { fontFamily: police.display, fontSize: TAILLE, letterSpacing: -0.045 * TAILLE, lineHeight: TAILLE * 1.15 };

export function IntroSplash({ onTermine }: { onTermine: () => void }) {
  const { c } = useTheme();
  const [vAnneaux] = useState(() => new Animated.Value(0));
  const [vTexte] = useState(() => new Animated.Value(0));
  const [vFondu] = useState(() => new Animated.Value(1));

  useEffect(() => {
    const suite = Animated.sequence([
      // Les anneaux arrivent en ressort : un léger rebond au-delà de leur
      // taille finale, pas un simple fondu — c'est lui qui porte l'effet.
      Animated.spring(vAnneaux, { toValue: 1, friction: 5, tension: 65, useNativeDriver: true }),
      Animated.timing(vTexte, { toValue: 1, duration: 280, easing: Easing.out(Easing.cubic), useNativeDriver: true }),
      Animated.delay(350),
      Animated.timing(vFondu, { toValue: 0, duration: 380, easing: Easing.in(Easing.cubic), useNativeDriver: true }),
    ]);
    suite.start(({ finished }) => { if (finished) onTermine(); });
    return () => suite.stop();
  }, [vAnneaux, vTexte, vFondu, onTermine]);

  const anneauxOpacite = vAnneaux.interpolate({ inputRange: [0, 1], outputRange: [0, 1], extrapolate: 'clamp' });
  // Pas de clamp ici : le petit dépassement du ressort au-delà de 1 doit se
  // lire comme un rebond, en taille et en rotation.
  const anneauxEchelle = vAnneaux.interpolate({ inputRange: [0, 1], outputRange: [0.45, 1] });
  const anneauxRotation = vAnneaux.interpolate({ inputRange: [0, 1], outputRange: ['-22deg', '0deg'] });

  const texteOpacite = vTexte.interpolate({ inputRange: [0, 1], outputRange: [0, 1], extrapolate: 'clamp' });
  const texteDecalage = vTexte.interpolate({ inputRange: [0, 1], outputRange: [-14, 0], extrapolate: 'clamp' });

  return (
    <Animated.View pointerEvents="none" style={[StyleSheet.absoluteFill, { backgroundColor: fixe.basalte, opacity: vFondu }]}>
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', flexDirection: 'row', gap: 0.42 * TAILLE }}>
        <Animated.View style={{ opacity: anneauxOpacite, transform: [{ scale: anneauxEchelle }, { rotate: anneauxRotation }] }}>
          <Anneaux hauteur={TAILLE} encre={fixe.craie} lave={c.rouge} />
        </Animated.View>
        <Animated.View style={{ opacity: texteOpacite, transform: [{ translateX: texteDecalage }] }}>
          <Text style={[MOT, { color: fixe.craie }]}>
            link<Text style={{ color: c.rouge }}>ee</Text>
          </Text>
        </Animated.View>
      </View>
    </Animated.View>
  );
}
