/**
 * La barre d'onglets flottante.
 *
 * Refonte 2026-11 : une barre sombre (#16161C) dans les deux thèmes, à 16 px
 * des bords et ~20 px du bas, 62 px de haut, rayon 28. Chaque onglet a une
 * case de même largeur, icône et nom dessous ; l'actif est une case blanche,
 * les autres sont en #A7A7B3. Les cases ne bougent jamais. Mêmes libellés, même ordre, mêmes icônes que le
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

/** `court` : libellé affiché quand la case est étroite (six onglets sur un téléphone). */
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
const MARGE_BARRE = 16;

/** Marge intérieure de la barre, de part et d'autre des cases. */
const MARGE_INTERIEURE = 6;

/**
 * Taille de la barre selon l'écran : toute la largeur moins 16 px de chaque
 * côté, 440 px au plus (tablette).
 *
 * Chaque onglet a une case de même largeur, actif ou non : depuis le
 * 2026-11-16, les icônes ne bougent plus quand on change d'onglet (l'onglet
 * actif s'élargissait à la longueur de son libellé et poussait les autres).
 * `compacte` : la case est trop étroite pour le libellé complet, on prend
 * le libellé court quand l'onglet en a un.
 */
export function gabaritBarre(
  largeurEcran: number,
  nbCases: number,
): { largeur: number; marge: number; compacte: boolean; corps: number; largeurCase: number } {
  const largeur = Math.max(0, Math.min(440, largeurEcran - 2 * MARGE_BARRE));
  const n = Math.max(1, nbCases);
  const largeurCase = (largeur - 2 * MARGE_INTERIEURE) / n;
  const compacte = largeurCase < 64;
  const corps = compacte ? 10 : fs[1];
  return { largeur, marge: MARGE_INTERIEURE, compacte, corps, largeurCase };
}

/** Hauteur de la barre. */
export const HAUTEUR_PILULE = 62;

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
  const { largeur, marge, compacte, corps } = gabaritBarre(largeurEcran, onglets.length + (actionCentrale ? 1 : 0));
  const milieu = Math.ceil(onglets.length / 2);

  return (
    <View pointerEvents="box-none" style={{ position: 'absolute', left: 0, right: 0, bottom: ecartBasBarre(bas), alignItems: 'center' }}>
      <View
        accessibilityRole="tablist"
        style={[
          {
            width: largeur, paddingHorizontal: marge, height: HAUTEUR_PILULE,
            flexDirection: 'row', alignItems: 'center',
            backgroundColor: fixe.barre, borderRadius: rayon.barre,
          },
          ombre('lg'),
        ]}
      >
        {onglets.map((o, i) => {
          const estActif = o.route === actif;
          const encre = estActif ? '#111116' : fixe.barreIcone;
          return (
            <React.Fragment key={o.route}>
            {actionCentrale && i === milieu ? (
              <View style={{ flex: 1, alignItems: 'center' }}>
                <PressableAnime
                  onPress={actionCentrale.onPress}
                  accessibilityRole="button"
                  accessibilityLabel={actionCentrale.libelle}
                  style={{ width: 46, height: 46, borderRadius: rayon.md, backgroundColor: actionCentrale.couleur, alignItems: 'center', justifyContent: 'center' }}
                >
                  <Icone nom={actionCentrale.icone} taille={22} couleur={encreSur(actionCentrale.couleur)} trait={2.5} />
                </PressableAnime>
              </View>
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
              style={{ flex: 1, height: 50, marginHorizontal: 2, borderRadius: 18, alignItems: 'center', justifyContent: 'center', gap: 3, backgroundColor: estActif ? '#FFFFFF' : 'transparent' }}
            >
              <Icone nom={o.icone} taille={20} couleur={encre} />
              <Text
                numberOfLines={1}
                adjustsFontSizeToFit
                minimumFontScale={0.85}
                maxFontSizeMultiplier={1.2}
                style={{ fontFamily: sans(estActif ? 700 : 500), fontSize: corps, color: encre, paddingHorizontal: 2 }}
              >
                {compacte && o.court ? o.court : o.libelle}
              </Text>
            </PressableAnime>
            </React.Fragment>
          );
        })}
      </View>
    </View>
  );
}
