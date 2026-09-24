/**
 * Le logo Linkee : deux anneaux entrelacés et le logotype « linkee ».
 *
 * Le dessin de marqueLinkee() (includes/marque.php), point pour point :
 * l'anneau de gauche et le double « ee » en lave, l'autre anneau et « link »
 * à l'encre du thème, l'arc lave repris par-dessus au croisement du bas.
 * La charte interdit de le recomposer : il ne se dessine qu'ici.
 */

import React from 'react';
import { Text, View } from 'react-native';
import Svg, { Circle, Path } from 'react-native-svg';

import { fs, police } from '../theme';
import { useTheme } from '../useTheme';

export function Anneaux({ hauteur, encre, lave = '#FF5424' }: { hauteur: number; encre: string; lave?: string }) {
  return (
    <Svg width={hauteur * 1.62} height={hauteur} viewBox="0 0 52 32" fill="none">
      <Circle cx={16} cy={16} r={12.5} stroke={lave} strokeWidth={5} />
      <Circle cx={36} cy={16} r={12.5} stroke={encre} strokeWidth={5} />
      <Path d="M 28.45 14.91 A 12.5 12.5 0 0 1 24.03 25.58" stroke={lave} strokeWidth={5} />
    </Svg>
  );
}

type Props = {
  /** font-size de .marque — var(--fs-6) par défaut. */
  taille?: number;
  suffixe?: string;
};

export function Marque({ taille = fs[6], suffixe }: Props) {
  const { c } = useTheme();
  const mot = { fontFamily: police.display, fontSize: taille, letterSpacing: -0.045 * taille, lineHeight: taille * 1.15 };

  return (
    <View
      accessible
      accessibilityRole="image"
      accessibilityLabel={'Linkee' + (suffixe ? ' ' + suffixe : '')}
      style={{ flexDirection: 'row', alignItems: 'center', gap: 0.42 * taille }}
    >
      <Anneaux hauteur={taille} encre={c.noir} lave={c.rouge} />
      <Text style={[mot, { color: c.noir }]}>
        link<Text style={{ color: c.rouge }}>ee</Text>
        {suffixe ? (
          <Text style={{ fontFamily: police.displayRegular, letterSpacing: -0.03 * taille, color: c.gris }}>
            {' ' + suffixe}
          </Text>
        ) : null}
      </Text>
    </View>
  );
}
