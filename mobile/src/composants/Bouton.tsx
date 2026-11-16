/**
 * Les boutons de la charte (.btn et ses variantes).
 *
 *  - primaire  : pilule d’accent, texte craie — une seule par écran ;
 *  - contour   : filet d'encre, fond transparent (secondaire) ;
 *  - basalte   : la pilule basalte posée sur un aplat lave (« Je fonce ») ;
 *  - texte     : un lien d'action en lave (tertiaire) ;
 *  - bleu      : l'action principale de l'univers sport (squads) ;
 *  - alerte    : « Supprimer définitivement », en alerte vive.
 *
 * Désactivé, un bouton plein sort de la couleur (« Inscrit », « Complet ») :
 * la lave à demi effacée donnait un brun sans contraste — même règle que le
 * site.
 */

import React from 'react';
import { ActivityIndicator, Text, View, type StyleProp, type ViewStyle, type TextStyle } from 'react-native';

import { fixe, fs, rayon, sans, toucheMin } from '../theme';
import { useTheme } from '../useTheme';
import { PressableAnime } from './Animations';
import { Icone, type NomIcone } from './Icone';

export type Variante = 'primaire' | 'contour' | 'basalte' | 'texte' | 'bleu' | 'alerte' | 'encre';

type Props = {
  libelle: string;
  onPress?: () => void;
  variante?: Variante;
  plein?: boolean;
  desactive?: boolean;
  chargement?: boolean;
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
  libelle, onPress, variante = 'primaire', plein, desactive, chargement, icone, iconeADroite,
  style, styleTexte, taillePolice = fs[5], accessibilityLabel, accent, testID,
}: Props) {
  const { c } = useTheme();
  const inactif = desactive || chargement;

  let fond = 'transparent';
  let encre = c.noir;
  let filet = 'transparent';

  switch (variante) {
    case 'primaire': fond = accent ?? c.rouge; encre = fixe.surAccent; filet = fond; break;
    // « bleu » : l'action principale de l'univers sport, rouge depuis la refonte.
    case 'bleu': fond = '#E02D24'; encre = fixe.surAccent; filet = fond; break;
    case 'encre': fond = c.noir; encre = c.surEncre; filet = c.noir; break;
    case 'alerte': fond = c.alerteVif; encre = fixe.surLave; break;
    case 'contour': fond = 'transparent'; encre = c.noir; filet = c.line2; break;
    case 'basalte': fond = fixe.basalte; encre = fixe.craie; filet = fixe.basalte; break;
    case 'texte': fond = 'transparent'; encre = c.surRougeClair; break;
  }

  if (desactive && !chargement) {
    if (variante === 'primaire' || variante === 'bleu' || variante === 'alerte' || variante === 'encre') {
      fond = c.surface2; encre = c.grisFonce; filet = c.grisClair;
    } else if (variante === 'basalte') {
      fond = 'rgba(17,16,19,0.14)'; encre = fixe.surAccent; filet = 'transparent';
    }
  }

  const opaciteDesactive = desactive && (variante === 'contour' || variante === 'texte') ? 0.5 : 1;

  return (
    <PressableAnime
      onPress={onPress}
      disabled={inactif}
      testID={testID}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? libelle}
      accessibilityState={{ disabled: !!inactif, busy: !!chargement }}
      style={[
        {
          flexDirection: iconeADroite ? 'row-reverse' : 'row',
          alignItems: 'center', justifyContent: 'center', gap: 8,
          paddingVertical: 13, paddingHorizontal: variante === 'texte' ? 4 : 22,
          minHeight: plein ? 54 : toucheMin,
          borderRadius: plein ? rayon.grandBouton : rayon.bouton, borderWidth: 1.5, borderColor: filet, backgroundColor: fond,
          opacity: opaciteDesactive,
        },
        plein ? { alignSelf: 'stretch' } : { alignSelf: 'flex-start' },
        style,
      ]}
    >
      {chargement ? (
        <ActivityIndicator size="small" color={encre} />
      ) : (
        <>
          {icone ? <Icone nom={icone} taille={17} couleur={encre} /> : null}
          <Text numberOfLines={1} style={[{ fontFamily: sans(700), fontSize: taillePolice, color: encre }, styleTexte]}>
            {libelle}
          </Text>
        </>
      )}
    </PressableAnime>
  );
}

/** Rangée de deux boutons ou plus, espacés comme .mt-8 / .mt-12. */
export function Pile({ children, ecart = 8 }: { children: React.ReactNode; ecart?: number }) {
  return <View style={{ gap: ecart }}>{children}</View>;
}
