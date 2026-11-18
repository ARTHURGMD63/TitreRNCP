/**
 * Une rangée qui défile à l'horizontale (chips de filtres, tuiles de
 * rubrique) et qui revient au début quand on quitte l'écran.
 *
 * Sans cela, une rangée poussée vers la droite le restait au retour sur
 * l'onglet : l'écran rejouait son apparition, mais la rangée repartait
 * coupée au milieu, « Tout » et « Filtres » hors de vue.
 */

import React, { useCallback, useRef } from 'react';
import { ScrollView, type StyleProp, type ViewStyle } from 'react-native';
import { useFocusEffect } from 'expo-router';

export function RangeeDefilante({ children, style, contentContainerStyle }: {
  children: React.ReactNode;
  style?: StyleProp<ViewStyle>;
  contentContainerStyle?: StyleProp<ViewStyle>;
}) {
  const rangee = useRef<ScrollView>(null);

  // Le nettoyage d'un effet de focus tourne à la sortie de l'écran.
  useFocusEffect(useCallback(() => () => rangee.current?.scrollTo({ x: 0, animated: false }), []));

  return (
    <ScrollView
      ref={rangee}
      horizontal
      showsHorizontalScrollIndicator={false}
      keyboardShouldPersistTaps="handled"
      style={[{ flexGrow: 0 }, style]}
      contentContainerStyle={contentContainerStyle}
    >
      {children}
    </ScrollView>
  );
}
