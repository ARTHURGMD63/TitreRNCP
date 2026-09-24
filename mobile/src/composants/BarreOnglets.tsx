/**
 * La barre d'onglets flottante du site (.bottom-nav).
 *
 * Une pilule de surface posée à 16 px du bas, 64 px de haut, 408 px de large
 * au plus ; quatre onglets, l'icône au-dessus du libellé. L'onglet actif est
 * en gras, son icône en lave. Mêmes libellés, même ordre, mêmes icônes que le
 * site : Explorer, Squads, Pass, Moi.
 *
 * Les écrans secondaires qui gardent la barre sur le site (Notifications,
 * Classement, Abonnements) allument l'onglet dont ils dépendent, comme les
 * gabarits PHP : Explorer pour les notifications, Moi pour le reste.
 */

import React from 'react';
import { Pressable, Text, View, useWindowDimensions } from 'react-native';
import type { Tabs } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { fs, rayon, sans } from '../theme';
import { useTheme } from '../useTheme';
import { Icone, type NomIcone } from './Icone';

const ONGLETS: { route: string; libelle: string; icone: NomIcone }[] = [
  { route: 'index', libelle: 'Explorer', icone: 'loupe' },
  { route: 'squads', libelle: 'Squads', icone: 'personnes' },
  { route: 'wallet', libelle: 'Pass', icone: 'carte' },
  { route: 'moi', libelle: 'Moi', icone: 'personne' },
];

/** L'onglet allumé par chaque écran secondaire. */
const PARENT: Record<string, string> = {
  notifications: 'index',
  classement: 'moi',
  abonnements: 'moi',
};

/** Les propriétés que Tabs passe à une barre personnalisée. */
type BottomTabBarProps = Parameters<NonNullable<React.ComponentProps<typeof Tabs>['tabBar']>>[0];

/**
 * Distance entre la barre et le bas de l'écran.
 *
 * Sur le site, 16 px sous la barre, mais le navigateur ajoute déjà sa propre
 * bordure. Dans l'application, 16 px au-dessus de la zone du geste d'accueil
 * la faisaient flotter trop haut : la barre descend dans cette zone et ne
 * garde qu'un petit écart au-dessus de l'indicateur.
 */
export function ecartBasBarre(zoneBasse: number): number {
  return zoneBasse > 0 ? Math.max(8, zoneBasse - 20) : 12;
}

/** Hauteur de la pilule. */
export const HAUTEUR_PILULE = 64;

/** Ce que le contenu doit laisser libre en bas : la barre et 16 px d'air. */
export function reserveBarre(zoneBasse: number): number {
  return ecartBasBarre(zoneBasse) + HAUTEUR_PILULE + 16;
}

export function BarreOnglets({ state, navigation }: BottomTabBarProps) {
  const { c, ombre } = useTheme();
  const bas = useSafeAreaInsets().bottom;
  // width: calc(100% - 32px) ; max-width: 408px
  const largeur = Math.min(useWindowDimensions().width - 32, 408);
  const courant = state.routes[state.index]?.name ?? 'index';
  const actif = PARENT[courant] ?? courant;

  return (
    <View pointerEvents="box-none" style={{ position: 'absolute', left: 0, right: 0, bottom: ecartBasBarre(bas), alignItems: 'center' }}>
      <View
        accessibilityRole="tablist"
        style={[
          {
            width: largeur, paddingHorizontal: 10, height: HAUTEUR_PILULE,
            flexDirection: 'row', alignItems: 'center', justifyContent: 'space-around',
            backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.pill,
          },
          ombre('lg'),
        ]}
      >
        {ONGLETS.map((o) => {
          const estActif = o.route === actif;
          const couleur = estActif ? c.noir : c.gris;
          return (
            <Pressable
              key={o.route}
              accessibilityRole="tab"
              accessibilityState={{ selected: estActif }}
              accessibilityLabel={o.libelle}
              onPress={() => {
                const route = state.routes.find((r) => r.name === o.route);
                const evenement = route ? navigation.emit({ type: 'tabPress', target: route.key, canPreventDefault: true }) : null;
                if (courant !== o.route || PARENT[courant]) {
                  if (!evenement?.defaultPrevented) navigation.navigate(o.route);
                }
              }}
              style={{ flex: 1, alignItems: 'center', justifyContent: 'center', gap: 3, paddingVertical: 8, minHeight: 44 }}
            >
              <Icone nom={o.icone} taille={21} couleur={estActif ? c.rouge : couleur} />
              <Text style={{ fontFamily: sans(estActif ? 700 : 500), fontSize: fs[3], color: couleur }}>{o.libelle}</Text>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}
