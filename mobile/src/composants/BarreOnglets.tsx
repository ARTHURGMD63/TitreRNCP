/**
 * La barre d'onglets flottante.
 *
 * Refonte 2026-11 : une barre sombre (#16161C) dans les deux thèmes, à 18 px
 * des bords et ~20 px du bas, 68 px de haut, rayon 28. L'onglet actif est une
 * pastille blanche qui porte son icône et son nom ; les autres n'ont que
 * l'icône, en #A7A7B3, avec leur nom en libellé accessible. Mêmes libellés, même ordre, mêmes icônes que le
 * site : Explorer, Squads, Pass, Moi — Personnes et Culture en plus, propres à
 * l'application (explore.php le proposait en sélecteur en haut d'Explorer,
 * pas en onglet).
 *
 * Les écrans secondaires qui gardent la barre sur le site (Notifications,
 * Classement, Abonnements) allument l'onglet dont ils dépendent, comme les
 * gabarits PHP : Explorer pour les notifications, Moi pour le reste.
 *
 * L'onglet « Squads » du site s'appelle « Sport » ici (icône trophée) :
 * même route et même contenu, seul le libellé visible change.
 */

import React from 'react';
import { Text, View, useWindowDimensions } from 'react-native';
import type { Tabs } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { encreSur, fixe, fs, rayon, sans } from '../theme';
import { useTheme } from '../useTheme';
import { PressableAnime } from './Animations';
import { Icone, type NomIcone } from './Icone';

/** `court` : libellé affiché quand la barre est compacte (petit écran, six onglets). */
export type OngletBarre = { route: string; libelle: string; icone: NomIcone; court?: string };

/** Le bouton central surélevé (espace partenaire : « + Créer une soirée »). */
export type ActionCentraleBarre = { libelle: string; icone: NomIcone; couleur: string; onPress: () => void };

const ONGLETS: OngletBarre[] = [
  { route: 'index', libelle: 'Explorer', icone: 'loupe' },
  { route: 'personnes', libelle: 'Personnes', icone: 'personnes', court: 'Réseau' },
  { route: 'squads', libelle: 'Sport', icone: 'trophee' },
  { route: 'culture', libelle: 'Culture', icone: 'musee' },
  { route: 'wallet', libelle: 'Pass', icone: 'carte' },
  { route: 'moi', libelle: 'Moi', icone: 'personne' },
];

/** L'onglet allumé par chaque écran secondaire. */
const PARENT: Record<string, string> = {
  notifications: 'index',
  classement: 'moi',
  abonnements: 'moi',
  carte: 'index',
};

/** Les propriétés que Tabs passe à une barre personnalisée. */
type BottomTabBarProps = Parameters<NonNullable<React.ComponentProps<typeof Tabs>['tabBar']>>[0];

/**
 * Distance entre la barre et le bas de l'écran : ~20 px au-dessus du bord,
 * ou juste au-dessus de l'indicateur d'accueil quand il y en a un.
 */
export function ecartBasBarre(zoneBasse: number): number {
  return zoneBasse > 0 ? Math.max(12, zoneBasse - 12) : 20;
}

/** Marges latérales de la barre. */
const MARGE_BARRE = 18;

/**
 * Taille de la barre selon l'écran : toute la largeur moins 18 px de chaque
 * côté, 440 px au plus (tablette). `compacte` réduit le libellé de l'onglet
 * actif sur un très petit écran, pour que les icônes gardent 44 px.
 */
export function gabaritBarre(
  largeurEcran: number,
  nbOnglets: number,
  plusLongLibelle = 9,
): { largeur: number; marge: number; compacte: boolean; corps: number } {
  const largeur = Math.max(0, Math.min(440, largeurEcran - 2 * MARGE_BARRE));
  const n = Math.max(1, nbOnglets);
  // Les onglets inactifs gardent 40 px au moins ; l'actif prend le reste.
  const resteActif = largeur - 16 - (n - 1) * 40;
  const compacte = resteActif < 40 + plusLongLibelle * 9;
  const corps = compacte ? fs[4] : fs[5] - 1;
  return { largeur, marge: 8, compacte, corps };
}

/** Hauteur de la barre. */
export const HAUTEUR_PILULE = 68;

/** Ce que le contenu doit laisser libre en bas : la barre et 16 px d'air. */
export function reserveBarre(zoneBasse: number): number {
  return ecartBasBarre(zoneBasse) + HAUTEUR_PILULE + 16;
}

/**
 * @param onglets Par défaut les six onglets étudiant. L'espace partenaire
 *        passe sa propre liste, plus courte — même barre, mêmes règles.
 * @param parent Onglet allumé par un écran secondaire sans onglet propre.
 * @param actionCentrale Bouton d'action au centre de la barre, pour une
 *        action plutôt qu'une navigation (créer une soirée, côté partenaire).
 *        Depuis la refonte, il est placé dans la rangée, au milieu des
 *        onglets : posé par-dessus, il recouvrait l'onglet actif élargi.
 */
export function BarreOnglets({ state, navigation, onglets = ONGLETS, parent = PARENT, actionCentrale }: BottomTabBarProps & { onglets?: OngletBarre[]; parent?: Record<string, string>; actionCentrale?: ActionCentraleBarre }) {
  const { ombre } = useTheme();
  const bas = useSafeAreaInsets().bottom;
  const largeurEcran = useWindowDimensions().width;
  const courant = state.routes[state.index]?.name ?? onglets[0]?.route;
  const actif = parent[courant] ?? courant;
  const libelleActif = onglets.find((o) => o.route === actif)?.libelle ?? '';
  const { largeur, marge, corps } = gabaritBarre(largeurEcran, onglets.length + (actionCentrale ? 1 : 0), libelleActif.length);
  const milieu = Math.ceil(onglets.length / 2);

  return (
    <View pointerEvents="box-none" style={{ position: 'absolute', left: 0, right: 0, bottom: ecartBasBarre(bas), alignItems: 'center' }}>
      <View
        accessibilityRole="tablist"
        style={[
          {
            width: largeur, paddingHorizontal: marge, height: HAUTEUR_PILULE,
            flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between',
            backgroundColor: fixe.barre, borderRadius: rayon.barre,
          },
          ombre('lg'),
        ]}
      >
        {onglets.map((o, i) => {
          const estActif = o.route === actif;
          return (
            <React.Fragment key={o.route}>
            {actionCentrale && i === milieu ? (
              <PressableAnime
                onPress={actionCentrale.onPress}
                accessibilityRole="button"
                accessibilityLabel={actionCentrale.libelle}
                style={{ width: 48, height: 48, marginHorizontal: 2, borderRadius: rayon.bouton, backgroundColor: actionCentrale.couleur, alignItems: 'center', justifyContent: 'center' }}
              >
                <Icone nom={actionCentrale.icone} taille={24} couleur={encreSur(actionCentrale.couleur)} trait={2.5} />
              </PressableAnime>
            ) : null}
            <PressableAnime
              accessibilityRole="tab"
              accessibilityState={{ selected: estActif }}
              accessibilityLabel={o.libelle}
              onPress={() => {
                const route = state.routes.find((r) => r.name === o.route);
                const evenement = route ? navigation.emit({ type: 'tabPress', target: route.key, canPreventDefault: true }) : null;
                if (courant !== o.route || parent[courant]) {
                  if (!evenement?.defaultPrevented) navigation.navigate(o.route);
                }
              }}
              style={
                estActif
                  ? { flexDirection: 'row', alignItems: 'center', gap: 8, height: 52, paddingHorizontal: 14, borderRadius: 20, backgroundColor: '#FFFFFF', flexShrink: 1 }
                  : { flex: 1, minWidth: 40, height: 52, alignItems: 'center', justifyContent: 'center' }
              }
            >
              <Icone nom={o.icone} taille={22} couleur={estActif ? '#111116' : fixe.barreIcone} />
              {estActif ? (
                <Text numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.8} style={{ fontFamily: sans(600), fontSize: corps, color: '#111116', flexShrink: 1 }}>
                  {o.libelle}
                </Text>
              ) : null}
            </PressableAnime>
            </React.Fragment>
          );
        })}
      </View>
    </View>
  );
}
