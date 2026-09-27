/**
 * Les deux onglets de l'espace partenaire : Tableau de bord (avec le scan
 * des pass), Événements. Même barre flottante que l'espace étudiant, avec
 * sa propre liste d'onglets — voir BarreOnglets.
 */

import { Tabs } from 'expo-router';

import { BarreOnglets, type OngletBarre } from '../../composants/BarreOnglets';
import { useTheme } from '../../useTheme';

const ONGLETS: OngletBarre[] = [
  { route: 'index', libelle: 'Tableau de bord', icone: 'appareil' },
  { route: 'evenements', libelle: 'Événements', icone: 'calendrier' },
];

const PARENT: Record<string, string> = {
  scan: 'index',
  'evenement-form': 'evenements',
};

export default function OngletsPartenaire() {
  const { c } = useTheme();

  return (
    <Tabs
      tabBar={(props) => <BarreOnglets {...props} onglets={ONGLETS} parent={PARENT} />}
      screenOptions={{ headerShown: false, sceneStyle: { backgroundColor: c.bg }, animation: 'none', lazy: false }}
    >
      <Tabs.Screen name="index" options={{ title: 'Tableau de bord' }} />
      <Tabs.Screen name="evenements" options={{ title: 'Événements' }} />
      <Tabs.Screen name="scan" options={{ href: null }} />
      <Tabs.Screen name="evenement-form" options={{ href: null }} />
    </Tabs>
  );
}
