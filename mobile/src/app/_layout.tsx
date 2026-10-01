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
 *  - partenaire : son propre groupe d'onglets, natif — voir (partenaire)/ ;
 *  - admin : idem, plus léger — voir (admin)/. Les pages les plus lourdes
 *    (CRM, finances, tableau de bord fondateurs) restent sur le site.
 */

import React, { useEffect, useState } from 'react';
import { View } from 'react-native';
import { Stack } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
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
import { IntroSplash } from '../composants/IntroSplash';

// L'écran de lancement (fond basalte, les deux anneaux) reste affiché tant
// que l'application n'est pas prête : polices chargées ET session tranchée.
// Sans cela on voyait, entre les deux, un écran vide puis un rond de
// chargement.
SplashScreen.preventAutoHideAsync().catch(() => {});
SplashScreen.setOptions({ fade: true, duration: 300 });

function Navigation() {
  const { profil, nouveau } = useSession();
  const { c, sombre } = useTheme();
  // Une fois la session tranchée, l'écran natif cède la place à cette
  // arrivée animée (voir IntroSplash) plutôt que de sauter cash sur l'écran
  // réel — déjà monté dessous, prêt à apparaître sous le fondu. Rejouée à
  // chaque lancement complet : `Navigation` ne remonte qu'à un vrai démarrage
  // de l'application (fermée puis rouverte), jamais à un simple retour au
  // premier plan.
  const [introVisible, setIntroVisible] = useState(true);

  useEffect(() => {
    if (profil !== undefined) SplashScreen.hideAsync().catch(() => {});
  }, [profil]);

  // Session pas encore vérifiée : l'écran de lancement natif couvre encore
  // l'attente.
  if (profil === undefined) {
    return <View style={{ flex: 1, backgroundColor: c.bg }} />;
  }

  const etudiant   = profil?.type === 'etudiant';
  const partenaire = profil?.type === 'partenaire';
  const admin      = profil?.type === 'admin';

  return (
    <>
      <StatusBar style={sombre ? 'light' : 'dark'} />
      <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: c.bg }, animation: 'none' }}>
        <Stack.Protected guard={profil === null}>
          <Stack.Screen name="login" />
          <Stack.Screen name="inscription" />
          <Stack.Screen name="mot-de-passe" />
        </Stack.Protected>

        <Stack.Protected guard={etudiant && nouveau}>
          <Stack.Screen name="onboarding" />
        </Stack.Protected>

        <Stack.Protected guard={etudiant && !nouveau}>
          <Stack.Screen name="(tabs)" />
          <Stack.Screen name="evenement/[id]" />
          <Stack.Screen name="etudiant/[id]" />
          <Stack.Screen name="avis/[id]" />
        </Stack.Protected>

        <Stack.Protected guard={partenaire}>
          <Stack.Screen name="(partenaire)" />
        </Stack.Protected>

        <Stack.Protected guard={admin}>
          <Stack.Screen name="(admin)" />
        </Stack.Protected>
      </Stack>
      {introVisible ? <IntroSplash onTermine={() => setIntroVisible(false)} /> : null}
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
  // police système puis sauterait : l'écran de lancement reste affiché.
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
