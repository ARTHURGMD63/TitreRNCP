/**
 * Les trois voix typographiques de la charte, et leurs rôles.
 *
 *  - Display : Unbounded 800, resserré (--ls-display), pour les titres et
 *    les chiffres clés — .display, .t-card-title, .event-title…
 *  - Mono    : JetBrains Mono, toujours en capitales espacées (--ls-label),
 *    pour les étiquettes, horaires et XP — .label, .t-overline…
 *  - T       : Instrument Sans, pour tout le reste, plafonné à 700.
 *
 * React Native n'a pas de cascade : chaque texte reçoit sa couleur. Par
 * défaut c'est l'encre du thème (--noir), comme `body { color }` sur le site.
 */

import React from 'react';
import { Text, View, type StyleProp, type TextProps, type TextStyle } from 'react-native';

import { fs, lh, lsEm, mono, police, sans } from '../theme';
import { useTheme } from '../useTheme';

type Base = TextProps & { style?: StyleProp<TextStyle>; couleur?: string };

export function Display({ taille = fs[8], interligne = lh.display, couleur, style, ...reste }: Base & { taille?: number; interligne?: number }) {
  const { c } = useTheme();
  return (
    <Text
      {...reste}
      style={[
        { fontFamily: police.display, fontSize: taille, lineHeight: taille * interligne, letterSpacing: lsEm.display * taille, color: couleur ?? c.noir },
        style,
      ]}
    />
  );
}

export function Mono({ taille = fs[1], poids = 500, couleur, majuscules = true, espacement = lsEm.label, style, ...reste }:
  Base & { taille?: number; poids?: 400 | 500 | 600; majuscules?: boolean; espacement?: number }) {
  const { c } = useTheme();
  return (
    <Text
      {...reste}
      style={[
        { fontFamily: mono(poids), fontSize: taille, letterSpacing: espacement * taille, color: couleur ?? c.gris, textTransform: majuscules ? 'uppercase' : 'none' },
        style,
      ]}
    />
  );
}

export function T({ taille = fs[4], poids = 400, couleur, interligne, style, ...reste }:
  Base & { taille?: number; poids?: 400 | 500 | 600 | 700; interligne?: number }) {
  const { c } = useTheme();
  return (
    <Text
      {...reste}
      style={[
        { fontFamily: sans(poids), fontSize: taille, color: couleur ?? c.noir },
        interligne ? { lineHeight: taille * interligne } : null,
        style,
      ]}
    />
  );
}

/**
 * Le titre d'écran en deux lignes : la première à l'encre, la seconde en
 * lave (.display-italic — Unbounded n'a pas d'italique, c'est la couleur qui
 * porte l'accent). Les squads passent l'accent au dôme.
 */
export function TitreEcran({ lignes, taille = fs[8], accent, interligne = lh.tight, style }: {
  lignes: [string, string?];
  taille?: number;
  accent?: string;
  interligne?: number;
  style?: object;
}) {
  const { c } = useTheme();
  return (
    <View accessibilityRole="header" style={style}>
      <Display taille={taille} interligne={interligne}>{lignes[0]}</Display>
      {lignes[1] ? (
        <Display taille={taille} interligne={interligne} couleur={accent ?? c.surRougeClair}>{lignes[1]}</Display>
      ) : null}
    </View>
  );
}
