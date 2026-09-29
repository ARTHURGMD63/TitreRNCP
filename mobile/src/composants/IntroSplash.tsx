/**
 * L'arrivée animée après l'écran de lancement natif (expo-splash-screen,
 * app.json → fond basalte #111013 + assets/splash-icon.png, les deux
 * anneaux seuls). Les deux mondes doivent se raccorder sans à-coup : mêmes
 * anneaux, même fond, donc le passage de l'un à l'autre ne se voit pas —
 * seule cette vue anime ensuite un vrai mouvement, la glissade et le
 * rétrécissement des anneaux vers leur place dans l'en-tête (Marque, EnTete
 * paddingTop 24 + gouttière).
 *
 * Pas de mesure de mise en page nécessaire : la taille des anneaux est une
 * formule fixe (Anneaux : largeur = hauteur × 1.62), donc leur point de
 * départ (centre de l'écran) et d'arrivée (en-tête) se calculent directement.
 *
 * Deux pièges corrigés après les premiers essais :
 *  - Le fond ne se DISSOUT pas par transparence (opacité → 0) : sur un écran
 *    clair, un voile basalte qui s'éclaircit en fondu se voit comme une
 *    lueur sombre trouble avant l'écran réel. Il ne change pas non plus de
 *    COULEUR en l'animant directement (interpolation basalte → c.bg) : ça
 *    revient au même défaut par un autre calcul, et surtout Animated ne
 *    garantit une interpolation de couleur fiable que via des bibliothèques
 *    dédiées — ça s'est vu rendre l'écran entièrement noir. La bonne
 *    solution est plus bête : une couche `c.bg` FIXE en dessous depuis le
 *    départ, et un voile basalte AU-DESSUS dont seule l'OPACITÉ (un nombre,
 *    jamais une couleur) descend à 0 sur la toute fin — le même résultat
 *    visuel qu'un fondu de couleur, sans jamais interpoler de couleur.
 *  - Les anneaux, eux, s'effacent (opacité, même technique) avant la toute
 *    fin de leur course : leur position finale n'est qu'une approximation de
 *    celle du vrai logo de l'écran (impossible à mesurer d'ici, il
 *    appartient à un autre composant). Sans ce fondu, l'écart, même petit,
 *    se voyait comme un saut au moment où le voile se dissipe.
 */

import React, { useEffect, useState } from 'react';
import { Animated, Easing, StyleSheet, useWindowDimensions, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Anneaux } from './Marque';
import { fixe, fs, gutter } from '../theme';
import { useTheme } from '../useTheme';

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
  // Uniquement des nombres (opacité), jamais une couleur interpolée — voir
  // le commentaire d'en-tête. Le voile basalte reste opaque presque toute la
  // durée, puis s'efface vite à la fin pour révéler la couche `c.bg` fixe
  // en dessous : même effet qu'un fondu de couleur, sans en interpoler une.
  const opaciteVoile = progres.interpolate({ inputRange: [0, 0.88, 1], outputRange: [1, 1, 0] });
  // Les anneaux s'effacent juste avant, pour qu'un léger écart avec la
  // position réelle du logo ne se voie pas comme un saut.
  const opaciteAnneaux = progres.interpolate({ inputRange: [0, 0.7, 0.88, 1], outputRange: [1, 1, 0, 0] });

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
