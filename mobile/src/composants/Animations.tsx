/**
 * La couche d'animation de la refonte (2026-11), posée par-dessus la logique
 * existante : rien ici ne déclenche d'action, tout réagit à un état déjà
 * établi (un écran qui s'affiche, une inscription qui a réussi).
 *
 * Uniquement l'API Animated de React Native : aucune dépendance de plus.
 * Le réglage d'accessibilité « Réduire les animations » est respecté
 * partout : les mouvements et les confettis disparaissent, seuls restent
 * les changements d'état instantanés.
 *
 *  - Apparition : l'arrivée en cascade des blocs d'un écran (opacité 0 → 1,
 *    montée de 14 px, 380 ms, décalage de 45 ms par bloc plafonné au 6ᵉ),
 *    rejouée à chaque affichage de l'écran, jamais à un simple re-render ;
 *  - PressableAnime : l'effet d'appui (0,97 en 90 ms, retour en ressort) ;
 *  - Confettis : l'explosion qui suit une inscription réussie ;
 *  - Compteur : un chiffre qui défile de 0 à sa valeur, au premier affichage ;
 *  - Pulsation : un battement unique à l'apparition.
 */

import React, { useCallback, useEffect, useRef, useState } from 'react';
import { AccessibilityInfo, Animated, Easing, Pressable, Text, View, type PressableProps, type StyleProp, type TextStyle, type ViewStyle } from 'react-native';
import { useFocusEffect } from 'expo-router';

import { couleursConfettis, duree } from '../theme';

const sortieCubique = Easing.out(Easing.cubic);

// ─── Mouvement réduit ───────────────────────────────────────────────────────

let reduitConnu = false;
const abonnes = new Set<(v: boolean) => void>();
let ecouteInstallee = false;

function installerEcoute() {
  if (ecouteInstallee) return;
  ecouteInstallee = true;
  AccessibilityInfo.isReduceMotionEnabled()
    .then((v) => { reduitConnu = v; abonnes.forEach((f) => f(v)); })
    .catch(() => {});
  AccessibilityInfo.addEventListener('reduceMotionChanged', (v) => {
    reduitConnu = v;
    abonnes.forEach((f) => f(v));
  });
}

/** Le réglage « Réduire les animations » du téléphone, suivi en direct. */
export function useMouvementReduit(): boolean {
  const [reduit, setReduit] = useState(reduitConnu);
  useEffect(() => {
    installerEcoute();
    abonnes.add(setReduit);
    return () => { abonnes.delete(setReduit); };
  }, []);
  return reduit;
}

// ─── Apparition en cascade ──────────────────────────────────────────────────

/**
 * Enveloppe un bloc d'écran. `index` fixe son rang dans la cascade (45 ms de
 * décalage par rang). L'animation se rejoue quand l'écran reprend le focus
 * — les onglets restent montés, c'est le seul « affichage » qui compte.
 *
 * Affinée le 2026-11-18 : la montée de 28 px en 550 ms faisait attendre la
 * dixième carte d'une liste plus d'une seconde. Le décalage s'arrête au
 * 6ᵉ rang : ce qui est plus bas arrive avec lui, hors de l'écran de toute
 * façon.
 */
export function Apparition({ index = 0, children, style }: { index?: number; children: React.ReactNode; style?: StyleProp<ViewStyle> }) {
  const reduit = useMouvementReduit();
  const [progres] = useState(() => new Animated.Value(reduitConnu ? 1 : 0));
  const reduitRef = useRef(reduit);
  useEffect(() => { reduitRef.current = reduit; }, [reduit]);

  useFocusEffect(
    useCallback(() => {
      if (reduitRef.current) {
        progres.setValue(1);
        return;
      }
      progres.setValue(0);
      const rang = Math.min(Math.max(index, 0), duree.cascadeMax);
      const anim = Animated.timing(progres, { toValue: 1, duration: duree.apparition, delay: rang * duree.cascade, easing: sortieCubique, useNativeDriver: true });
      anim.start();
      return () => anim.stop();
    }, [index, progres]),
  );

  return (
    <Animated.View
      style={[
        style,
        {
          opacity: progres,
          transform: [{ translateY: progres.interpolate({ inputRange: [0, 1], outputRange: [14, 0] }) }],
        },
      ]}
    >
      {children}
    </Animated.View>
  );
}

// ─── Effet d'appui ──────────────────────────────────────────────────────────

const PressableAnimable = Animated.createAnimatedComponent(Pressable);

/**
 * Un Pressable qui se réduit à 0,97 quand on l'appuie, puis revient en
 * ressort léger. Le style n'accepte pas de fonction ({ pressed }) : c'est
 * l'échelle qui dit l'appui. Un bouton désactivé ne bouge pas.
 *
 * 0,95 (avant le 2026-11-18) écrasait visiblement les boutons pleine
 * largeur ; 0,97 se lit encore sur une chip de 40 px.
 */
export function PressableAnime({ style, onPressIn, onPressOut, disabled, children, ...reste }:
  Omit<PressableProps, 'style' | 'children'> & { style?: StyleProp<ViewStyle>; children?: React.ReactNode }) {
  const reduit = useMouvementReduit();
  const [echelle] = useState(() => new Animated.Value(1));
  const enfoncer = () => {
    if (reduit) return;
    Animated.timing(echelle, { toValue: 0.97, duration: duree.appui, easing: Easing.out(Easing.quad), useNativeDriver: true }).start();
  };
  const relacher = () => {
    if (reduit) { echelle.setValue(1); return; }
    Animated.spring(echelle, { toValue: 1, speed: 28, bounciness: 6, useNativeDriver: true }).start();
  };
  return (
    <PressableAnimable
      {...reste}
      disabled={disabled}
      onPressIn={(e) => { if (!disabled) enfoncer(); onPressIn?.(e); }}
      onPressOut={(e) => { relacher(); onPressOut?.(e); }}
      style={[style, { transform: [{ scale: echelle }] }]}
    >
      {children}
    </PressableAnimable>
  );
}

// ─── Confettis ──────────────────────────────────────────────────────────────

const NB_CONFETTIS = 28;

type Morceau = { angle: number; distance: number; rotation: number; couleur: string };

function tirer(couleurs: readonly string[]): Morceau[] {
  return Array.from({ length: NB_CONFETTIS }, (_, i) => ({
    angle: (i / NB_CONFETTIS) * Math.PI * 2 + (Math.random() - 0.5) * 0.4,
    distance: 70 + Math.random() * 70,
    rotation: (Math.random() - 0.5) * 720,
    couleur: couleurs[i % couleurs.length],
  }));
}

/**
 * L'explosion de confettis : environ 28 rectangles de 8 × 12 px, projetés
 * en cercle sur 70 à 140 px, avec rotation et fondu, en 1,1 s.
 *
 * Se pose en absolu au-dessus de l'élément d'origine (son parent doit
 * laisser déborder). Chaque nouvelle valeur de `declencheur` (> 0) relance
 * une explosion ; rien ne se passe en mouvement réduit.
 *
 * @param couleurs La couleur de la rubrique en tête, puis la palette fête.
 */
export function Confettis({ declencheur, couleurs }: { declencheur: number; couleurs: readonly string[] }) {
  const reduit = useMouvementReduit();
  const [progres] = useState(() => new Animated.Value(0));
  const [morceaux, setMorceaux] = useState<Morceau[] | null>(null);

  useEffect(() => {
    if (!declencheur || reduit) return;
    // Le tirage au sort suit le déclencheur, jamais le rendu.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setMorceaux(tirer(couleurs));
    progres.setValue(0);
    const anim = Animated.timing(progres, { toValue: 1, duration: 1100, easing: Easing.out(Easing.quad), useNativeDriver: true });
    anim.start(({ finished }) => { if (finished) setMorceaux(null); });
    return () => anim.stop();
    // Les couleurs sont une constante de l'écran : seul le déclencheur relance.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [declencheur, reduit, progres]);

  if (!morceaux) return null;
  return (
    <View pointerEvents="none" accessible={false} style={{ position: 'absolute', left: '50%', top: '50%', width: 0, height: 0, zIndex: 20, elevation: 20 }}>
      {morceaux.map((m, i) => (
        <Animated.View
          key={i}
          style={{
            position: 'absolute', left: -4, top: -6, width: 8, height: 12, borderRadius: 2, backgroundColor: m.couleur,
            opacity: progres.interpolate({ inputRange: [0, 0.6, 1], outputRange: [1, 1, 0] }),
            transform: [
              { translateX: progres.interpolate({ inputRange: [0, 1], outputRange: [0, Math.cos(m.angle) * m.distance] }) },
              { translateY: progres.interpolate({ inputRange: [0, 1], outputRange: [0, Math.sin(m.angle) * m.distance] }) },
              { rotate: progres.interpolate({ inputRange: [0, 1], outputRange: ['0deg', `${m.rotation}deg`] }) },
            ],
          }}
        />
      ))}
    </View>
  );
}

/** Les couleurs d'une explosion pour une rubrique donnée. */
export function paletteConfettis(accent: string): string[] {
  return [accent, ...couleursConfettis];
}

// ─── Compteur ───────────────────────────────────────────────────────────────

/**
 * Un nombre qui défile de 0 jusqu'à sa vraie valeur en ~1,1 s (ease-out),
 * au premier affichage seulement. `format` met en forme la valeur courante
 * (arrondi, « € »…). Un changement de valeur ensuite s'affiche aussitôt.
 */
export function Compteur({ valeur, format = (n) => String(Math.round(n)), delai = 0, style }: {
  valeur: number;
  format?: (n: number) => string;
  delai?: number;
  style?: StyleProp<TextStyle>;
}) {
  const reduit = useMouvementReduit();
  const [affiche, setAffiche] = useState(() => (reduitConnu ? valeur : 0));
  const joue = useRef(false);
  const [progres] = useState(() => new Animated.Value(0));

  useEffect(() => {
    if (joue.current || reduit) {
      setAffiche(valeur);
      return;
    }
    joue.current = true;
    const id = progres.addListener(({ value }) => setAffiche(value * valeur));
    const anim = Animated.timing(progres, { toValue: 1, duration: 1100, delay: delai, easing: sortieCubique, useNativeDriver: false });
    anim.start(() => setAffiche(valeur));
    return () => { progres.removeListener(id); };
  }, [valeur, reduit, delai, progres]);

  return <Text style={style} accessibilityLabel={format(valeur)}>{format(affiche)}</Text>;
}

// ─── Pulsation ──────────────────────────────────────────────────────────────

/** Un battement unique (échelle `ampleur`) à l'apparition. */
export function Pulsation({ children, ampleur = 1.14, delai = 300, style }: { children: React.ReactNode; ampleur?: number; delai?: number; style?: StyleProp<ViewStyle> }) {
  const reduit = useMouvementReduit();
  const [echelle] = useState(() => new Animated.Value(1));
  useEffect(() => {
    if (reduit) return;
    const anim = Animated.sequence([
      Animated.delay(delai),
      Animated.timing(echelle, { toValue: ampleur, duration: 180, easing: Easing.out(Easing.quad), useNativeDriver: true }),
      Animated.timing(echelle, { toValue: 1, duration: 260, easing: Easing.inOut(Easing.quad), useNativeDriver: true }),
    ]);
    anim.start();
    return () => anim.stop();
  }, [reduit, ampleur, delai, echelle]);
  return <Animated.View style={[style, { transform: [{ scale: echelle }] }]}>{children}</Animated.View>;
}
