/**
 * La coquille d'un écran : .app-shell, .page-header et .page-content.
 *
 * Fond de page, marge haute sous la barre d'état, gouttière de 20 px, et la
 * réserve basse qui empêche la barre d'onglets flottante de recouvrir la fin
 * du contenu (--nav-h + 16 px). Le tirer-pour-rafraîchir remplace le
 * rechargement de page du site.
 *
 * Chargement et erreur : le site rend ses pages côté serveur et n'a jamais
 * d'écran d'attente ; une application, si. Ils restent sobres — le rond lave,
 * ou la phrase d'erreur et « Réessayer ».
 */

import React from 'react';
import { ActivityIndicator, RefreshControl, ScrollView, View, type StyleProp, type ViewStyle } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { fs, gutter } from '../theme';
import { useTheme } from '../useTheme';
import { HAUTEUR_BARRE } from './BarreOnglets';
import { Bouton } from './Bouton';
import { T } from './Texte';

type Props = {
  children: React.ReactNode;
  /** La barre d'onglets est-elle affichée sous cet écran ? */
  avecBarre?: boolean;
  rafraichit?: boolean;
  onRafraichir?: () => void;
  /** Contenu hors défilement (feuilles modales). */
  horsDefilement?: React.ReactNode;
  style?: StyleProp<ViewStyle>;
  /** Pas de marge haute : l'écran pose lui-même son en-tête (fiche événement). */
  plein?: boolean;
  /** Pour faire défiler l'écran jusqu'à un bloc (« Modifier » → le formulaire). */
  defilementRef?: React.RefObject<ScrollView | null>;
};

export function Ecran({ children, avecBarre = true, rafraichit = false, onRafraichir, horsDefilement, style, plein, defilementRef }: Props) {
  const { c } = useTheme();
  const { top, bottom } = useSafeAreaInsets();
  return (
    <View style={{ flex: 1, backgroundColor: c.bg }}>
      <ScrollView
        ref={defilementRef}
        keyboardShouldPersistTaps="handled"
        contentContainerStyle={[
          { paddingTop: plein ? 0 : top, paddingBottom: (avecBarre ? HAUTEUR_BARRE : 24) + bottom, width: '100%', maxWidth: 440, alignSelf: 'center' },
          style,
        ]}
        refreshControl={onRafraichir ? <RefreshControl refreshing={rafraichit} onRefresh={onRafraichir} tintColor={c.rouge} colors={[c.rouge]} progressBackgroundColor={c.blanc} /> : undefined}
      >
        {children}
      </ScrollView>
      {horsDefilement}
    </View>
  );
}

/** .page-header : 24 px en haut, 12 px en bas, la gouttière sur les côtés. */
export function EnTete({ children, style }: { children: React.ReactNode; style?: StyleProp<ViewStyle> }) {
  return <View style={[{ paddingTop: 24, paddingHorizontal: gutter, paddingBottom: 12 }, style]}>{children}</View>;
}

/** .page-content : la gouttière, 24 px en bas. */
export function Contenu({ children, style }: { children: React.ReactNode; style?: StyleProp<ViewStyle> }) {
  return <View style={[{ paddingHorizontal: gutter, paddingBottom: 24 }, style]}>{children}</View>;
}

export function Chargement() {
  const { c } = useTheme();
  return (
    <View style={{ paddingVertical: 60, alignItems: 'center' }}>
      <ActivityIndicator color={c.rouge} />
    </View>
  );
}

export function Erreur({ message, onReessayer }: { message: string; onReessayer: () => void }) {
  const { c } = useTheme();
  return (
    <View style={{ paddingVertical: 48, paddingHorizontal: gutter, alignItems: 'center', gap: 16 }}>
      <T taille={fs[5]} couleur={c.grisFonce} style={{ textAlign: 'center' }}>{message}</T>
      <Bouton libelle="Réessayer" variante="contour" onPress={onReessayer} />
    </View>
  );
}
