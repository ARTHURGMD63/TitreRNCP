/**
 * Les quatre onglets, dans l'ordre du web : Explore, Squads, Wallet, Moi.
 *
 * Meme ordre et memes libelles que la barre du bas du site : un etudiant qui
 * passe du navigateur a l'application doit retrouver son pouce au meme
 * endroit. Les icones sont de la famille Feather, celle dont le trait — 2 px,
 * bouts arrondis — correspond aux SVG dessines a la main dans les gabarits.
 */

import Feather from '@expo/vector-icons/Feather';
import { Tabs } from 'expo-router';

import { useTheme } from '../../useTheme';
import { taille } from '../../theme';

export default function Onglets() {
  const { c } = useTheme();

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: c.noir,
        tabBarInactiveTintColor: c.gris,
        tabBarStyle: {
          backgroundColor: c.blanc,
          borderTopColor: c.grisClair,
        },
        tabBarLabelStyle: {
          fontSize: taille.xs,
          fontWeight: '700',
          letterSpacing: 0.5,
        },
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: 'EXPLORE',
          tabBarIcon: ({ color, size }) => <Feather name="search" size={size} color={color} />,
        }}
      />
      <Tabs.Screen
        name="squads"
        options={{
          title: 'SQUADS',
          tabBarIcon: ({ color, size }) => <Feather name="users" size={size} color={color} />,
        }}
      />
      <Tabs.Screen
        name="wallet"
        options={{
          title: 'WALLET',
          tabBarIcon: ({ color, size }) => <Feather name="credit-card" size={size} color={color} />,
        }}
      />
      <Tabs.Screen
        name="moi"
        options={{
          title: 'MOI',
          tabBarIcon: ({ color, size }) => <Feather name="user" size={size} color={color} />,
        }}
      />
    </Tabs>
  );
}
