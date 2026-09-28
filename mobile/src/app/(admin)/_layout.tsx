/**
 * Les onglets de l'espace admin : Modération, Étudiants, Soirées — les
 * pages du back-office qualifiées de « raisonnables sur mobile » (listes,
 * pas de gros formulaire ni de graphique). Le CRM et le tableau de bord
 * fondateurs restent sur le site, ouverts depuis l'onglet Plus ; les
 * finances, elles, ont leur écran natif de consultation (finances.tsx,
 * ouvert depuis Plus, sans onglet propre — comme notifications sur Explorer).
 */

import { Tabs } from 'expo-router';

import { BarreOnglets, type OngletBarre } from '../../composants/BarreOnglets';
import { useTheme } from '../../useTheme';

const ONGLETS: OngletBarre[] = [
  { route: 'index', libelle: 'Modération', icone: 'drapeau' },
  { route: 'etudiants', libelle: 'Étudiants', icone: 'personnes' },
  { route: 'evenements', libelle: 'Soirées', icone: 'calendrier' },
  { route: 'plus', libelle: 'Plus', icone: 'engrenage' },
];

export default function OngletsAdmin() {
  const { c } = useTheme();

  return (
    <Tabs
      tabBar={(props) => <BarreOnglets {...props} onglets={ONGLETS} />}
      screenOptions={{ headerShown: false, sceneStyle: { backgroundColor: c.bg }, animation: 'none', lazy: false }}
    >
      <Tabs.Screen name="index" options={{ title: 'Modération' }} />
      <Tabs.Screen name="etudiants" options={{ title: 'Étudiants' }} />
      <Tabs.Screen name="evenements" options={{ title: 'Soirées' }} />
      <Tabs.Screen name="plus" options={{ title: 'Plus' }} />
      <Tabs.Screen name="finances" options={{ href: null }} />
    </Tabs>
  );
}
