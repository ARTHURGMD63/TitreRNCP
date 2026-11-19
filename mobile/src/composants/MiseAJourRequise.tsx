/**
 * L'écran bloquant « mise à jour requise ».
 *
 * Le serveur refuse une version trop ancienne de l'application (426, code
 * `mise_a_jour_requise`, voir api/v1/_socle.php) : une version cassée, ou qui
 * ne respecte plus les règles des stores. Plutôt que de laisser chaque écran
 * échouer l'un après l'autre, la racine (_layout.tsx) remplace toute
 * l'application par celui-ci (constat ARC-02). Il n'y a rien d'autre à
 * faire que mettre à jour : pas de retour, pas de navigation.
 *
 * Le bouton n'apparaît que si l'adresse de la fiche du store est fixée au
 * build (EXPO_PUBLIC_URL_STORE_IOS, EXPO_PUBLIC_URL_STORE_ANDROID) : sans
 * elle, un texte dit où aller, plutôt qu'un bouton qui n'ouvrirait rien.
 */

import React, { useEffect } from 'react';
import { Linking, Platform, View } from 'react-native';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { fs, gutter, lh } from '../theme';
import { useTheme } from '../useTheme';
import { Bouton } from './Bouton';
import { CarreIcone } from './Design';
import { Display, T } from './Texte';

/** L'adresse de la fiche du store pour ce téléphone, ou null si elle n'est pas configurée. */
function adresseStore(): string | null {
  // Écrites en toutes lettres : Expo ne remplace que les accès directs à
  // process.env.EXPO_PUBLIC_… au moment du build.
  const adresse = Platform.OS === 'ios'
    ? process.env.EXPO_PUBLIC_URL_STORE_IOS
    : Platform.OS === 'android' ? process.env.EXPO_PUBLIC_URL_STORE_ANDROID : undefined;
  return adresse && /^(https|itms-apps|market):\/\//.test(adresse) ? adresse : null;
}

export function MiseAJourRequise({ message }: { message: string }) {
  const { c, sombre } = useTheme();
  const { top, bottom } = useSafeAreaInsets();
  const store = adresseStore();
  const nomStore = Platform.OS === 'ios' ? "l'App Store" : 'le Play Store';

  // Le refus peut arriver dès le lancement, avant que l'écran de lancement
  // natif ne soit retiré par la navigation (qui n'est plus montée).
  useEffect(() => {
    SplashScreen.hideAsync().catch(() => {});
  }, []);

  return (
    <View
      accessibilityViewIsModal
      style={{ flex: 1, backgroundColor: c.bg, alignItems: 'center', justifyContent: 'center', paddingHorizontal: gutter + 8, paddingTop: top, paddingBottom: bottom }}
    >
      <StatusBar style={sombre ? 'light' : 'dark'} />
      <CarreIcone icone="fleche-b" fond={c.surface2} encre={c.noir} taille={64} />
      <Display taille={fs[7]} style={{ textAlign: 'center', marginTop: 20, marginBottom: 12 }}>
        Mise à jour requise
      </Display>
      <T taille={fs[4]} couleur={c.grisFonce} interligne={lh.normal} style={{ textAlign: 'center', marginBottom: 28 }}>
        {message}
      </T>
      {store ? (
        <Bouton
          libelle="Mettre à jour"
          icone="fleche-b"
          plein
          onPress={() => void Linking.openURL(store).catch(() => {})}
        />
      ) : (
        <T taille={fs[4]} couleur={c.gris} interligne={lh.normal} style={{ textAlign: 'center' }}>
          {`Ouvre ${nomStore} et mets Outsee à jour, puis relance l'application.`}
        </T>
      )}
    </View>
  );
}
