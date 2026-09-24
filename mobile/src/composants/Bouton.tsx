/**
 * Les boutons de la charte (.btn et ses variantes).
 *
 *  - primaire  : pilule lave, texte basalte — une seule par écran ;
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
import { ActivityIndicator, Pressable, Text, View, type StyleProp, type ViewStyle, type TextStyle } from 'react-native';

import { fixe, fs, rayon, sans, toucheMin } from '../theme';
import { useTheme } from '../useTheme';
import { Icone, type NomIcone } from './Icone';

export type Variante = 'primaire' | 'contour' | 'basalte' | 'texte' | 'bleu' | 'alerte';

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
};

export function Bouton({
  libelle, onPress, variante = 'primaire', plein, desactive, chargement, icone, iconeADroite,
  style, styleTexte, taillePolice = fs[5], accessibilityLabel,
}: Props) {
  const { c } = useTheme();
  const inactif = desactive || chargement;

  let fond = 'transparent';
  let encre = c.noir;
  let filet = 'transparent';

  switch (variante) {
    case 'primaire': fond = c.rouge; encre = fixe.surLave; break;
    case 'bleu': fond = c.bleu; encre = fixe.surLave; break;
    case 'alerte': fond = c.alerteVif; encre = fixe.surLave; break;
    case 'contour': fond = 'transparent'; encre = c.noir; filet = c.noir; break;
    case 'basalte': fond = fixe.basalte; encre = fixe.craie; filet = fixe.basalte; break;
    case 'texte': fond = 'transparent'; encre = c.surRougeClair; break;
  }

  if (desactive && !chargement) {
    if (variante === 'primaire' || variante === 'bleu' || variante === 'alerte') {
      fond = c.surface2; encre = c.grisFonce; filet = c.grisClair;
    } else if (variante === 'basalte') {
      fond = 'rgba(17,16,19,0.14)'; encre = fixe.surLave; filet = 'transparent';
    }
  }

  const opaciteDesactive = desactive && (variante === 'contour' || variante === 'texte') ? 0.5 : 1;

  return (
    <Pressable
      onPress={onPress}
      disabled={inactif}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? libelle}
      accessibilityState={{ disabled: !!inactif, busy: !!chargement }}
      style={({ pressed }) => [
        {
          flexDirection: iconeADroite ? 'row-reverse' : 'row',
          alignItems: 'center', justifyContent: 'center', gap: 8,
          paddingVertical: 13, paddingHorizontal: variante === 'texte' ? 4 : 24,
          minHeight: plein ? 52 : toucheMin,
          borderRadius: rayon.pill, borderWidth: 1.5, borderColor: filet, backgroundColor: fond,
          opacity: opaciteDesactive,
          transform: pressed && !inactif ? [{ translateY: 1 }, { scale: 0.99 }] : [],
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
    </Pressable>
  );
}

/** Rangée de deux boutons ou plus, espacés comme .mt-8 / .mt-12. */
export function Pile({ children, ecart = 8 }: { children: React.ReactNode; ecart?: number }) {
  return <View style={{ gap: ecart }}>{children}</View>;
}
