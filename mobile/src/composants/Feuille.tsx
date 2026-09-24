/**
 * La feuille modale du site (.modal-overlay + .modal-sheet).
 *
 * Un voile basalte à 60 % légèrement flouté qui apparaît en fondu (250 ms),
 * et la feuille qui monte du bas avec la même courbe que le site
 * (cubic-bezier(0.32, 0.72, 0, 1), 340 ms) : fond de page, rayon de 32 en
 * haut, poignée, 90 % de la hauteur au plus. Un toucher sur le voile la
 * ferme, comme un clic à côté sur le site. Elle passe au-dessus de tout,
 * barre d'onglets comprise.
 */

import React, { useEffect, useState } from 'react';
import { Animated, Easing, KeyboardAvoidingView, Modal, Platform, Pressable, ScrollView, View, useWindowDimensions } from 'react-native';
import { BlurView } from 'expo-blur';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { rayon } from '../theme';
import { useTheme } from '../useTheme';
import { VueToast } from './Toast';

type Props = {
  visible: boolean;
  onClose: () => void;
  children: React.ReactNode;
};

const courbe = Easing.bezier(0.32, 0.72, 0, 1);

export function Feuille({ visible, onClose, children }: Props) {
  const { c } = useTheme();
  const { height } = useWindowDimensions();
  const bas = useSafeAreaInsets().bottom;

  // La Modal reste montée le temps de l'animation de fermeture.
  const [monte, setMonte] = useState(visible);
  const [precedent, setPrecedent] = useState(visible);
  const [voile] = useState(() => new Animated.Value(0));
  const [glisse] = useState(() => new Animated.Value(height));

  // Ouverture : la Modal se monte avant que l'animation ne commence.
  if (visible !== precedent) {
    setPrecedent(visible);
    if (visible) setMonte(true);
  }

  useEffect(() => {
    if (visible) {
      voile.setValue(0);
      glisse.setValue(height);
      Animated.parallel([
        Animated.timing(voile, { toValue: 1, duration: 250, useNativeDriver: true }),
        Animated.timing(glisse, { toValue: 0, duration: 340, easing: courbe, useNativeDriver: true }),
      ]).start();
    } else {
      Animated.parallel([
        Animated.timing(voile, { toValue: 0, duration: 250, useNativeDriver: true }),
        Animated.timing(glisse, { toValue: height, duration: 280, easing: courbe, useNativeDriver: true }),
      ]).start(({ finished }) => {
        if (finished) setMonte(false);
      });
    }
  }, [visible, height, voile, glisse]);

  return (
    <Modal visible={monte} transparent animationType="none" onRequestClose={onClose} statusBarTranslucent>
      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <View style={{ flex: 1, justifyContent: 'flex-end' }}>
          <Animated.View style={{ position: 'absolute', top: 0, right: 0, bottom: 0, left: 0, opacity: voile }}>
            <Pressable onPress={onClose} accessibilityLabel="Fermer" style={{ flex: 1 }}>
              <BlurView intensity={8} tint="dark" style={{ flex: 1 }}>
                <View style={{ flex: 1, backgroundColor: 'rgba(17,16,19,0.6)' }} />
              </BlurView>
            </Pressable>
          </Animated.View>
          <Animated.View
            accessibilityViewIsModal
            style={{
              maxHeight: height * 0.9, backgroundColor: c.bg,
              borderTopLeftRadius: rayon.xl, borderTopRightRadius: rayon.xl,
              borderWidth: 1, borderBottomWidth: 0, borderColor: c.grisClair,
              transform: [{ translateY: glisse }],
            }}
          >
            <ScrollView
              keyboardShouldPersistTaps="handled"
              contentContainerStyle={{ paddingTop: 12, paddingHorizontal: 22, paddingBottom: 36 + bas }}
            >
              <View style={{ width: 40, height: 5, backgroundColor: c.line2, borderRadius: 99, alignSelf: 'center', marginBottom: 18 }} />
              {children}
            </ScrollView>
          </Animated.View>
        </View>
      </KeyboardAvoidingView>
      <VueToast />
    </Modal>
  );
}
