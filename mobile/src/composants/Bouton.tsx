/**
 * Les boutons de la charte (docs/CHARTE_GRAPHIQUE_OUTSEE.md, § 6.1). Tous
 * ont un rayon de 16 (rayon.bouton), pas de forme en pilule.
 *
 *  - primaire  : aplat de la couleur de la rubrique, texte blanc — une seule
 *                action principale par écran ;
 *  - contour   : filet d'encre, fond transparent (secondaire) ;
 *  - basalte   : aplat basalte posé sur un aplat vif (« Je fonce ») ;
 *  - texte     : un lien d'action à la couleur de l'accent (tertiaire) ;
 *  - sport     : l'action principale de l'univers sport (squads, terrains), rouge Sport ;
 *  - alerte    : « Supprimer définitivement », en alerte vive.
 *
 * Les états, les mêmes pour tous les boutons de l'application (Bouton,
 * BoutonAction, BoutonContour, depuis le 2026-11-18) :
 *
 *  - appui      : réduction à 0,97 (PressableAnime) ;
 *  - chargement : les couleurs restent, l'icône cède la place à une roue et
 *                 le libellé reste — le bouton ne change pas de largeur ;
 *  - fait       : l'action est acquise (« Inscrit ») : il garde sa couleur,
 *                 porte la coche et ne répond plus ;
 *  - désactivé  : il sort de la couleur (surface 2, encre gris foncé) quelle
 *                 que soit la variante. Plus d'opacité réduite : un texte à
 *                 demi effacé tombait sous 4,5:1.
 */

import React from 'react';
import { ActivityIndicator, Text, View, type StyleProp, type ViewStyle, type TextStyle } from 'react-native';

import { fixe, fs, hauteurBouton, rayon, sans } from '../theme';
import { useTheme } from '../useTheme';
import { PressableAnime } from './Animations';
import { Icone, type NomIcone } from './Icone';

export type Variante = 'primaire' | 'contour' | 'basalte' | 'texte' | 'sport' | 'alerte' | 'encre';

type Props = {
  libelle: string;
  onPress?: () => void;
  variante?: Variante;
  plein?: boolean;
  desactive?: boolean;
  chargement?: boolean;
  /** L'action est acquise (« Inscrit ») : couleur gardée, coche, sans réponse à l'appui. */
  fait?: boolean;
  icone?: NomIcone;
  /** Côté de l'icône ; « Suivant → » la porte à droite. */
  iconeADroite?: boolean;
  style?: StyleProp<ViewStyle>;
  styleTexte?: StyleProp<TextStyle>;
  taillePolice?: number;
  accessibilityLabel?: string;
  /** Couleur de l'aplat « primaire » : l'accent de la rubrique (bleu par défaut). */
  accent?: string;
  /** Repère des tests de bout en bout (Maestro) ; sans effet à l'écran. */
  testID?: string;
};

export function Bouton({
  libelle, onPress, variante = 'primaire', plein, desactive, chargement, fait, icone, iconeADroite,
  style, styleTexte, taillePolice = fs[4] + 1, accessibilityLabel, accent, testID,
}: Props) {
  const { c } = useTheme();
  const inactif = desactive || chargement || fait;

  let fond = 'transparent';
  let encre = c.noir;
  let filet = 'transparent';

  switch (variante) {
    case 'primaire': fond = accent ?? c.rouge; encre = fixe.surAccent; filet = fond; break;
    // « sport » : l'action principale de l'univers sport, au rouge de la rubrique.
    case 'sport': fond = '#E02D24'; encre = fixe.surAccent; filet = fond; break;
    case 'encre': fond = c.noir; encre = c.surEncre; filet = c.noir; break;
    case 'alerte': fond = c.alerteVif; encre = fixe.surLave; break;
    case 'contour': fond = 'transparent'; encre = c.noir; filet = c.line2; break;
    case 'basalte': fond = fixe.basalte; encre = fixe.craie; filet = fixe.basalte; break;
    case 'texte': fond = 'transparent'; encre = c.surRougeClair; break;
  }

  if (desactive && !chargement && !fait) {
    if (variante === 'basalte') {
      // Posé sur un aplat vif : un voile plutôt que la surface claire.
      fond = 'rgba(17,17,22,0.14)'; encre = fixe.surAccent; filet = 'transparent';
    } else if (variante === 'texte') {
      encre = c.gris;
    } else {
      fond = c.surface2; encre = c.grisFonce; filet = c.surface2;
    }
  }

  const iconeAffichee = fait ? 'check' : icone;

  return (
    <PressableAnime
      onPress={onPress}
      disabled={inactif}
      testID={testID}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? libelle}
      accessibilityState={{ disabled: !!inactif, busy: !!chargement, checked: fait || undefined }}
      style={[
        {
          flexDirection: iconeADroite ? 'row-reverse' : 'row',
          alignItems: 'center', justifyContent: 'center', gap: 8,
          paddingVertical: 10, paddingHorizontal: variante === 'texte' ? 4 : 18,
          minHeight: plein ? hauteurBouton.plein : hauteurBouton.normal,
          borderRadius: rayon.bouton, borderWidth: 1.5, borderColor: filet, backgroundColor: fond,
        },
        plein ? { alignSelf: 'stretch' } : { alignSelf: 'flex-start' },
        style,
      ]}
    >
      {chargement ? (
        <ActivityIndicator size="small" color={encre} style={{ width: 16, height: 16 }} />
      ) : iconeAffichee ? (
        <Icone nom={iconeAffichee} taille={16} couleur={encre} trait={fait ? 2.5 : undefined} />
      ) : null}
      <Text numberOfLines={1} style={[{ fontFamily: sans(700), fontSize: taillePolice, color: encre }, styleTexte]}>
        {libelle}
      </Text>
    </PressableAnime>
  );
}

/** Rangée de deux boutons ou plus, espacés comme .mt-8 / .mt-12. */
export function Pile({ children, ecart = 8 }: { children: React.ReactNode; ecart?: number }) {
  return <View style={{ gap: ecart }}>{children}</View>;
}
