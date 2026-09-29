/**
 * L'arrivée animée après l'écran de lancement natif (expo-splash-screen,
 * app.json → fond basalte #111013 + assets/splash-icon.png, les deux
 * anneaux seuls). Les anneaux, grossis au centre de l'écran, rétrécissent et
 * glissent jusqu'à leur place exacte dans l'en-tête de l'écran qui va
 * s'afficher — pas une approximation : `cible` dit à ce composant EXACTEMENT
 * où atterrir, calculé une fois pour toutes ci-dessous à partir du code réel
 * de chaque en-tête, pas deviné.
 *
 * Pourquoi une cible, et pas une seule position par défaut : le point
 * d'arrivée n'est pas le même partout. `login.tsx` pose sa Marque à
 * `insets.top + 46` (padding du ScrollView) et 24 px du bord ; les onglets
 * étudiant (`index.tsx`, `squads.tsx`, `moi.tsx`, `personnes.tsx`) la posent
 * à `insets.top + 24` (EnTete) et `gutter` (20) du bord. L'accueil
 * partenaire, admin et l'onboarding n'ont pas de Marque du tout dans leur
 * en-tête — y faire atterrir des anneaux inventerait un endroit qui n'existe
 * pas, donc `cible` vaut alors `null` et l'animation reste un simple fondu
 * sur place, sans déplacement.
 *
 * Deux règles qui ont chacune coûté un essai raté avant celui-ci :
 *  - Ne JAMAIS animer une COULEUR (`interpolate` entre deux couleurs) : ça a
 *    déjà rendu l'écran entièrement noir. Le fond se gère avec deux couches
 *    opaques (`c.bg` fixe en dessous, un voile basalte au-dessus) dont seule
 *    l'OPACITÉ — un nombre — descend à 0 sur la toute fin.
 *  - Le voile et les anneaux se dissipent ENSEMBLE, à la même toute fin de
 *    course : si la cible calculée est décalée de quelques pixels sur un
 *    appareil imprévu, personne ne le voit, puisque tout s'efface au moment
 *    où l'écart pourrait se voir.
 */

import React, { useEffect, useState } from 'react';
import { Animated, Easing, StyleSheet, useWindowDimensions, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Anneaux } from './Marque';
import { fixe, fs, gutter } from '../theme';
import { useTheme } from '../useTheme';

export type CibleIntro = 'login' | 'tabs' | null;

const HAUTEUR_ANNEAUX = fs[6];
const LARGEUR_ANNEAUX = HAUTEUR_ANNEAUX * 1.62;
// Grossis par rapport à leur taille d'en-tête : c'est ce rétrécissement qui
// rend l'arrivée cool plutôt qu'un simple fondu à plat.
const ECHELLE_DEPART = 2.1;

export function IntroSplash({ onTermine, cible }: { onTermine: () => void; cible: CibleIntro }) {
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

  // Position et taille de la Marque réelle sur l'écran qui va s'afficher —
  // lues dans le code de chaque en-tête, pas approximées.
  const posCible = cible === 'login'
    ? { gauche: 24, haut: top + 46 }
    : cible === 'tabs'
      ? { gauche: gutter, haut: top + 24 }
      : null;

  const centreGauche = width / 2 - LARGEUR_ANNEAUX / 2;
  const centreHaut = height / 2 - HAUTEUR_ANNEAUX / 2;

  // Sans cible connue (onboarding, partenaire, admin) : les anneaux ne
  // bougent pas, ils restent au centre et s'effacent sur place.
  const gauche = posCible
    ? progres.interpolate({ inputRange: [0, 1], outputRange: [centreGauche, posCible.gauche] })
    : centreGauche;
  const hautPos = posCible
    ? progres.interpolate({ inputRange: [0, 1], outputRange: [centreHaut, posCible.haut] })
    : centreHaut;
  const echelle = posCible
    ? progres.interpolate({ inputRange: [0, 1], outputRange: [ECHELLE_DEPART, 1] })
    : 1;

  // Uniquement des nombres (opacité), jamais une couleur interpolée — voir
  // le commentaire d'en-tête. Le voile basalte reste opaque presque toute la
  // durée, puis s'efface vite à la fin pour révéler la couche `c.bg` fixe
  // en dessous.
  const opaciteVoile = progres.interpolate({ inputRange: [0, 0.85, 1], outputRange: [1, 1, 0] });
  // Les anneaux s'effacent avec le voile, pour qu'un écart de quelques
  // pixels avec la vraie Marque ne se voie jamais comme un saut.
  const opaciteAnneaux = progres.interpolate({ inputRange: [0, 0.65, 0.85, 1], outputRange: [1, 1, 0, 0] });

  return (
    <View pointerEvents="none" style={StyleSheet.absoluteFill}>
      {/* La couleur d'arrivée, fixe, dès le premier rendu. */}
      <View style={[StyleSheet.absoluteFill, { backgroundColor: c.bg }]} />
      <Animated.View style={[StyleSheet.absoluteFill, { backgroundColor: fixe.basalte, opacity: opaciteVoile }]}>
        <Animated.View style={{ position: 'absolute', left: gauche, top: hautPos, opacity: opaciteAnneaux, transform: [{ scale: echelle }] }}>
          <Anneaux hauteur={HAUTEUR_ANNEAUX} encre={fixe.craie} lave={c.rouge} />
        </Animated.View>
      </Animated.View>
    </View>
  );
}
