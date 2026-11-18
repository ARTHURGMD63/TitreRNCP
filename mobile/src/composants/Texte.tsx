/**
 * Les trois voix typographiques, et leurs rôles. Depuis la refonte 2026-11,
 * toutes en Onest :
 *
 *  - Display : 700, resserré (-0.035em), pour les titres et les chiffres clés ;
 *  - Mono    : le surtitre — 12 px, 600, capitales espacées de 0.06em ;
 *  - T       : tout le reste, de 400 à 700.
 *
 * React Native n'a pas de cascade : chaque texte reçoit sa couleur. Par
 * défaut c'est l'encre du thème (--noir), comme `body { color }` sur le site.
 */

import React from 'react';
import { Text, View, type StyleProp, type TextProps, type TextStyle } from 'react-native';

import { fixe, fs, lh, lsEm, mono, police, rayon, sans, taille as tailles, teintes, type Rubrique } from '../theme';
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

export function Mono({ taille = fs[2], poids = 600, couleur, majuscules = true, espacement = lsEm.label, style, ...reste }:
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
 * Le grand titre d'écran : 27 px, 700, interligne 1.2, -0.035em. Le ou les
 * mots clés sont surlignés dans une pastille de la couleur de la rubrique
 * (texte blanc, padding 0 10 3, rayon 10) : « Les bons plans [du moment.] ».
 *
 * Deux écritures :
 *  - `avant` + `surligne` : le texte coule et passe à la ligne mot par mot,
 *    la pastille ne se coupe jamais ;
 *  - `lignes` (historique) : la première ligne à l'encre, la seconde
 *    surlignée — ou simplement colorée si `accent` est donné (nom et prénom
 *    d'un profil, où rien n'est à surligner).
 */
export function TitreEcran({ lignes, avant, surligne, rubrique = 'bleu', taille = tailles.titreEcran, accent, interligne = lh.tight, style }: {
  lignes?: [string, string?];
  avant?: string;
  surligne?: string;
  rubrique?: Rubrique;
  taille?: number;
  accent?: string;
  interligne?: number;
  style?: object;
}) {
  const { mode } = useTheme();
  if (lignes && accent) {
    return (
      <View accessibilityRole="header" style={style}>
        <Display taille={taille} interligne={interligne}>{lignes[0]}</Display>
        {lignes[1] ? <Display taille={taille} interligne={interligne} couleur={accent}>{lignes[1]}</Display> : null}
      </View>
    );
  }
  const debut = avant ?? lignes?.[0] ?? '';
  const pastille = surligne ?? lignes?.[1];
  const fond = teintes(rubrique, mode).accent;
  const mots = debut.split(/\s+/).filter(Boolean);
  const hauteur = taille * interligne;
  return (
    <View
      accessible
      accessibilityRole="header"
      accessibilityLabel={[debut, pastille].filter(Boolean).join(' ')}
      style={[{ flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', columnGap: taille * 0.26, rowGap: 2 }, style]}
    >
      {mots.map((m, i) => (
        <Display key={i} taille={taille} interligne={interligne}>{m}</Display>
      ))}
      {pastille ? (
        <View style={{ backgroundColor: fond, borderRadius: rayon.mini, paddingHorizontal: 10, paddingBottom: 3, height: hauteur + 3, justifyContent: 'center' }}>
          <Display taille={taille} interligne={interligne} couleur={fixe.surAccent} numberOfLines={1}>{pastille}</Display>
        </View>
      ) : null}
    </View>
  );
}

/** Le titre de section (« À suivre », « 1 pass actif ») : 17 px, 700. */
export function TitreSection({ children, style }: { children: React.ReactNode; style?: StyleProp<TextStyle> }) {
  return (
    <T taille={tailles.section} poids={700} accessibilityRole="header" style={[{ letterSpacing: -0.02 * tailles.section }, style]}>{children}</T>
  );
}
