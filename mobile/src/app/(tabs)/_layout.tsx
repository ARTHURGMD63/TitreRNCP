/**
 * Les onglets, dans l'ordre et avec les libellés du site : Explorer,
 * Personnes, Sport, Pass, Moi — dessinés par la barre flottante de la
 * charte. Personnes n'a pas d'onglet propre sur le site (explore.php le
 * propose en sélecteur en haut d'Explorer) ; ici il en a un, plus facile à
 * retrouver qu'une bascule cachée dans un autre écran.
 *
 * Notifications, Classement et Abonnements vivent ici aussi, sans onglet
 * propre : sur le site ces pages gardent la barre du bas, et c'est ce qui
 * permet de la garder ici.
 */

import { Animated, Easing } from 'react-native';
import { Tabs } from 'expo-router';

import { useMouvementReduit } from '../../composants/Animations';
import { BarreOnglets } from '../../composants/BarreOnglets';
import { useTheme } from '../../useTheme';

/**
 * Refonte 2026-11 : le nouvel onglet apparaît en fondu avec un léger
 * glissement de 40 px, en 450 ms. Seulement une transition : le système de
 * navigation ne change pas.
 */
const glissement = ({ current }: { current: { progress: Animated.AnimatedInterpolation<number> } }) => ({
  sceneStyle: {
    opacity: current.progress.interpolate({ inputRange: [-1, 0, 1], outputRange: [0, 1, 0] }),
    transform: [{ translateX: current.progress.interpolate({ inputRange: [-1, 0, 1], outputRange: [-40, 0, 40] }) }],
  },
});

export default function Onglets() {
  const { c } = useTheme();
  const reduit = useMouvementReduit();

  return (
    <Tabs
      tabBar={(props) => <BarreOnglets {...props} />}
      // Les onglets sont tous montés (donc chargés) dès l'ouverture plutôt
      // qu'à la première visite, qui affichait sinon un écran de chargement.
      // Animation coupée si le téléphone demande moins de mouvement.
      screenOptions={{
        headerShown: false, sceneStyle: { backgroundColor: c.bg }, lazy: false,
        ...(reduit
          ? { animation: 'none' as const }
          : {
              animation: 'shift' as const,
              sceneStyleInterpolator: glissement,
              transitionSpec: { animation: 'timing' as const, config: { duration: 450, easing: Easing.out(Easing.cubic) } },
            }),
      }}
    >
      <Tabs.Screen name="index" options={{ title: 'Explorer' }} />
      <Tabs.Screen name="personnes" options={{ title: 'Personnes' }} />
      <Tabs.Screen name="squads" options={{ title: 'Sport' }} />
      <Tabs.Screen name="wallet" options={{ title: 'Pass' }} />
      <Tabs.Screen name="moi" options={{ title: 'Moi' }} />
      <Tabs.Screen name="notifications" options={{ href: null }} />
      <Tabs.Screen name="classement" options={{ href: null }} />
      <Tabs.Screen name="abonnements" options={{ href: null }} />
      {/* lazy: true à part des autres — une MapView native est trop lourde
          pour se monter au lancement chez qui n'ouvre jamais la carte. */}
      <Tabs.Screen name="carte" options={{ href: null, lazy: true }} />
      {/* Onglet Culture : indépendant d'Explorer et de Sport. */}
      <Tabs.Screen name="culture" options={{ title: 'Culture' }} />
    </Tabs>
  );
}
