/**
 * Racine de l'application : session, theme, et la garde d'authentification.
 *
 * C'est l'equivalent d'auth_check.php cote web — le point unique par lequel
 * tout passe. La difference tient a ce qu'une application ne « redirige » pas :
 * elle n'affiche simplement pas les ecrans auxquels on n'a pas droit.
 *
 * POURQUOI Stack.Protected ET NON UNE REDIRECTION DANS UN EFFET
 *
 * La premiere version rendait la pile complete et corrigeait ensuite, dans un
 * useEffect, en appelant router.replace('/login'). Un effet s'execute APRES le
 * rendu : l'ecran Explore etait donc monte une fois sans jeton, et le garde-fou
 * de useJeton() levait « Ecran authentifie rendu sans jeton » a chaque
 * lancement a froid. Trouve en lancant l'application, pas en la relisant.
 *
 * `guard` est declaratif : une route dont la garde est fausse n'est pas dans
 * la pile, donc jamais montee, donc il n'y a pas d'instant ou elle existe sans
 * ses donnees. Le probleme ne se corrige pas apres coup — il ne se pose plus.
 */

import { ActivityIndicator, View } from 'react-native';
import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { FournisseurSession, useSession } from '../session';
import { useTheme } from '../useTheme';

function Navigation() {
  const { profil } = useSession();
  const { c, sombre } = useTheme();

  // Tant que la session n'est pas tranchee, on n'affiche ni la connexion ni
  // l'application : basculer sur l'une puis l'autre ferait clignoter l'ecran a
  // chaque lancement, alors que le jeton etait valable depuis le debut.
  if (profil === undefined) {
    return (
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: c.bg }}>
        <ActivityIndicator color={c.rouge} />
      </View>
    );
  }

  return (
    <>
      {/* La barre d'etat suit le theme, comme la meta theme-color du site.
          Pas de backgroundColor : expo-status-bar ne l'expose pas — sur iOS la
          barre est transparente et prend le fond de l'ecran. */}
      <StatusBar style={sombre ? 'light' : 'dark'} />

      <Stack
        screenOptions={{
          headerShown: false,
          contentStyle: { backgroundColor: c.bg },
          animation: 'fade',
        }}
      >
        <Stack.Protected guard={profil !== null}>
          <Stack.Screen name="(tabs)" />
        </Stack.Protected>

        <Stack.Protected guard={profil === null}>
          <Stack.Screen name="login" />
        </Stack.Protected>
      </Stack>
    </>
  );
}

export default function Racine() {
  return (
    <SafeAreaProvider>
      <FournisseurSession>
        <Navigation />
      </FournisseurSession>
    </SafeAreaProvider>
  );
}
