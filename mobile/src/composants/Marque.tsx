/**
 * Le logo Outsee : deux anneaux entrelacés, l'un basalte, l'autre craie, sur
 * une tuile bleue aux coins arrondis, et le logotype « outsee » à côté. Le
 * bleu est la Pantone 2728 C (`fixe.logo`), sur la tuile comme sur le « ee ».
 *
 * Le dessin reprend Logo.png (512 px) cote pour cote. L'entrelacement est
 * l'essentiel : au croisement du haut l'anneau craie passe devant le noir, à
 * celui du bas le noir repasse devant — d'où l'arc basse du noir redessiné
 * par-dessus. La charte interdit de recomposer ce logo : il ne se dessine
 * qu'ici.
 */

import React from 'react';
import { Text, View } from 'react-native';
import Svg, { ClipPath, Circle, Defs, Rect } from 'react-native-svg';

import { fixe, fs, police } from '../theme';
import { useTheme } from '../useTheme';

function Entrelacs() {
  return (
    <>
      <Defs>
        <ClipPath id="moitieBasse">
          <Rect x={0} y={255.5} width={512} height={256.5} />
        </ClipPath>
      </Defs>
      <Circle cx={196.5} cy={255.5} r={88.5} stroke={fixe.basalte} strokeWidth={43} />
      <Circle cx={314.5} cy={255.5} r={88.5} stroke={fixe.craie} strokeWidth={43} />
      <Circle cx={196.5} cy={255.5} r={88.5} stroke={fixe.basalte} strokeWidth={43} clipPath="url(#moitieBasse)" />
    </>
  );
}

/** Le logo complet : tuile bleue carrée, anneaux au centre. */
export function Anneaux({ hauteur }: { hauteur: number }) {
  return (
    <Svg width={hauteur} height={hauteur} viewBox="0 0 512 512" fill="none">
      <Rect x={0} y={0} width={512} height={512} rx={112} fill={fixe.logo} />
      <Entrelacs />
    </Svg>
  );
}

/** Les anneaux seuls, pour les aplats colorés qui leur servent déjà de fond. */
export function AnneauxSeuls({ largeur }: { largeur: number }) {
  return (
    <Svg width={largeur} height={(largeur * 232) / 352} viewBox="80 140 352 232" fill="none">
      <Entrelacs />
    </Svg>
  );
}

type Props = {
  /** font-size de .marque — var(--fs-6) par défaut. */
  taille?: number;
  suffixe?: string;
  /**
   * Remplace le bleu du « ee » par une autre teinte — dérogation à la charte
   * réservée aux en-têtes propres à l'application mobile : le dôme en tête de
   * l'onglet Squads, par exemple. La Pantone 2728 C reste la couleur par
   * défaut partout ailleurs.
   */
  accent?: string;
};

export function Marque({ taille = fs[6], suffixe, accent }: Props) {
  const { c } = useTheme();
  const teinte = accent ?? fixe.logo;
  const mot = { fontFamily: police.display, fontSize: taille, letterSpacing: -0.045 * taille, lineHeight: taille * 1.15 };

  return (
    <View
      accessible
      accessibilityRole="image"
      accessibilityLabel={'Outsee' + (suffixe ? ' ' + suffixe : '')}
      style={{ flexDirection: 'row', alignItems: 'center', gap: 0.4 * taille }}
    >
      <Anneaux hauteur={taille * 1.6} />
      <Text style={[mot, { color: c.noir }]}>
        outs<Text style={{ color: teinte }}>ee</Text>
        {suffixe ? (
          <Text style={{ fontFamily: police.displayRegular, letterSpacing: -0.03 * taille, color: c.gris }}>
            {' ' + suffixe}
          </Text>
        ) : null}
      </Text>
    </View>
  );
}
