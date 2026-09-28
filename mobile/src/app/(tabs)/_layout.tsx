/**
 * Les quatre onglets, dans l'ordre et avec les libellés du site : Explorer,
 * Sport, Pass, Moi — dessinés par la barre flottante de la charte.
 *
 * Notifications, Classement et Abonnements vivent ici aussi, sans onglet
 * propre : sur le site ces pages gardent la barre du bas, et c'est ce qui
 * permet de la garder ici.
 */

import { Tabs } from 'expo-router';

import { BarreOnglets } from '../../composants/BarreOnglets';
import { useTheme } from '../../useTheme';

export default function Onglets() {
  const { c } = useTheme();

  return (
    <Tabs
      tabBar={(props) => <BarreOnglets {...props} />}
      // Changement d'onglet instantané : aucune animation, et les onglets sont
      // tous montés (donc chargés) dès l'ouverture plutôt qu'à la première
      // visite, qui affichait sinon un écran de chargement.
      screenOptions={{ headerShown: false, sceneStyle: { backgroundColor: c.bg }, animation: 'none', lazy: false }}
    >
      <Tabs.Screen name="index" options={{ title: 'Explorer' }} />
      <Tabs.Screen name="squads" options={{ title: 'Sport' }} />
      <Tabs.Screen name="wallet" options={{ title: 'Pass' }} />
      <Tabs.Screen name="moi" options={{ title: 'Moi' }} />
      <Tabs.Screen name="notifications" options={{ href: null }} />
      <Tabs.Screen name="classement" options={{ href: null }} />
      <Tabs.Screen name="abonnements" options={{ href: null }} />
      {/* lazy: true à part des autres — une MapView native est trop lourde
          pour se monter au lancement chez qui n'ouvre jamais la carte. */}
      <Tabs.Screen name="carte" options={{ href: null, lazy: true }} />
    </Tabs>
  );
}
