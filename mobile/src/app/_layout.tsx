/**
 * Racine de l'application : polices, thème, session, toast, et la garde
 * d'authentification.
 *
 * C'est l'équivalent d'auth_check.php — le point unique par lequel tout
 * passe. Une application ne « redirige » pas : elle n'affiche simplement pas
 * les écrans auxquels on n'a pas droit. `Stack.Protected` est déclaratif :
 * une route dont la garde est fausse n'est jamais montée, donc jamais rendue
 * sans ses données.
 *
 *  - déconnecté : connexion, inscription, mot de passe oublié ;
 *  - étudiant qui vient de s'inscrire : l'accueil (onboarding.php) ;
 *  - étudiant : les onglets et les fiches ;
 *  - partenaire : l'espace pro vit sur le site, l'application le dit.
 */

import React from 'react';
import { ActivityIndicator, View } from 'react-native';
import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { useFonts } from 'expo-font';
import { Unbounded_400Regular, Unbounded_800ExtraBold } from '@expo-google-fonts/unbounded';
import {
  InstrumentSans_400Regular, InstrumentSans_500Medium, InstrumentSans_600SemiBold,
  InstrumentSans_600SemiBold_Italic, InstrumentSans_700Bold,
} from '@expo-google-fonts/instrument-sans';
import { JetBrainsMono_400Regular, JetBrainsMono_500Medium, JetBrainsMono_600SemiBold } from '@expo-google-fonts/jetbrains-mono';

import { FournisseurSession, useSession } from '../session';
import { FournisseurTheme, useTheme } from '../useTheme';
import { FournisseurToast } from '../composants/Toast';

function Navigation() {
  const { profil, nouveau } = useSession();
  const { c, sombre } = useTheme();

  if (profil === undefined) {
    return (
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: c.bg }}>
        <ActivityIndicator color={c.rouge} />
      </View>
    );
  }

  const etudiant = profil?.type === 'etudiant';

  return (
    <>
      <StatusBar style={sombre ? 'light' : 'dark'} />
      <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: c.bg }, animation: 'fade' }}>
        <Stack.Protected guard={profil === null}>
          <Stack.Screen name="login" />
          <Stack.Screen name="inscription" options={{ animation: 'slide_from_right' }} />
          <Stack.Screen name="mot-de-passe" options={{ animation: 'slide_from_right' }} />
        </Stack.Protected>

        <Stack.Protected guard={etudiant && nouveau}>
          <Stack.Screen name="onboarding" />
        </Stack.Protected>

        <Stack.Protected guard={etudiant && !nouveau}>
          <Stack.Screen name="(tabs)" />
          <Stack.Screen name="evenement/[id]" options={{ animation: 'slide_from_right' }} />
          <Stack.Screen name="etudiant/[id]" options={{ animation: 'slide_from_right' }} />
          <Stack.Screen name="avis/[id]" options={{ animation: 'slide_from_right' }} />
        </Stack.Protected>

        <Stack.Protected guard={profil !== null && !etudiant}>
          <Stack.Screen name="partenaire" />
        </Stack.Protected>
      </Stack>
    </>
  );
}

function Polices({ children }: { children: React.ReactNode }) {
  const [pretes] = useFonts({
    Unbounded_400Regular, Unbounded_800ExtraBold,
    InstrumentSans_400Regular, InstrumentSans_500Medium, InstrumentSans_600SemiBold,
    InstrumentSans_600SemiBold_Italic, InstrumentSans_700Bold,
    JetBrainsMono_400Regular, JetBrainsMono_500Medium, JetBrainsMono_600SemiBold,
  });
  // Sans ses polices, l'écran s'afficherait une fraction de seconde en
  // police système puis sauterait : on attend, sur le fond basalte.
  if (!pretes) return <View style={{ flex: 1, backgroundColor: '#111013' }} />;
  return <>{children}</>;
}

export default function Racine() {
  return (
    <SafeAreaProvider>
      <Polices>
        <FournisseurTheme>
          <FournisseurSession>
            <FournisseurToast>
              <Navigation />
            </FournisseurToast>
          </FournisseurSession>
        </FournisseurTheme>
      </Polices>
    </SafeAreaProvider>
  );
}
