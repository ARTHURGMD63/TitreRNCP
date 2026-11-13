/**
 * Les onglets de l'espace partenaire : Tableau de bord (avec le scan des
 * pass), Événements, Terrains pour un club sportif, Moi. Même barre
 * flottante que l'espace étudiant, avec sa propre liste d'onglets — voir
 * BarreOnglets.
 *
 * Le bouton surélevé au centre ouvre directement la création d'un événement
 * (evenement-form, sans id), sans passer par l'onglet Événements.
 *
 * L'onglet Terrains n'apparaît que pour un club (etablissements.type =
 * 'club', v28) : le type est lu une fois, au montage, depuis le profil de
 * l'établissement. La route existe pour tous ; un bar qui l'atteindrait par
 * un lien recevrait le refus « pas_un_club » du serveur.
 */

import { useEffect, useState } from 'react';
import { router, Tabs } from 'expo-router';

import { api } from '../../api';
import { BarreOnglets, type OngletBarre } from '../../composants/BarreOnglets';
import { useJeton } from '../../session';
import { useTheme } from '../../useTheme';

const ONGLETS: OngletBarre[] = [
  { route: 'index', libelle: 'Tableau de bord', icone: 'appareil' },
  { route: 'evenements', libelle: 'Événements', icone: 'calendrier' },
  { route: 'moi', libelle: 'Moi', icone: 'personne' },
];

const ONGLETS_CLUB: OngletBarre[] = [
  { route: 'index', libelle: 'Tableau', icone: 'appareil' },
  { route: 'evenements', libelle: 'Événements', icone: 'calendrier' },
  { route: 'terrains', libelle: 'Terrains', icone: 'trophee' },
  { route: 'moi', libelle: 'Moi', icone: 'personne' },
];

const PARENT: Record<string, string> = {
  scan: 'index',
  'evenement-form': 'evenements',
  abonnement: 'moi',
  photos: 'moi',
};

export default function OngletsPartenaire() {
  const { c } = useTheme();
  const jeton = useJeton();
  const [estClub, setEstClub] = useState(false);

  useEffect(() => {
    let actif = true;
    api.partenaireProfil(jeton)
      .then((r) => { if (actif) setEstClub(r.etablissement.type === 'club'); })
      .catch(() => {});
    return () => { actif = false; };
  }, [jeton]);

  return (
    <Tabs
      tabBar={(props) => (
        <BarreOnglets
          {...props}
          onglets={estClub ? ONGLETS_CLUB : ONGLETS}
          parent={PARENT}
          actionCentrale={estClub ? undefined : { libelle: 'Créer une soirée', icone: 'plus', couleur: c.orange, onPress: () => router.push({ pathname: '/evenement-form' }) }}
        />
      )}
      screenOptions={{ headerShown: false, sceneStyle: { backgroundColor: c.bg }, animation: 'none', lazy: false }}
    >
      <Tabs.Screen name="index" options={{ title: 'Tableau de bord' }} />
      <Tabs.Screen name="evenements" options={{ title: 'Événements' }} />
      <Tabs.Screen name="terrains" options={{ title: 'Terrains', lazy: true }} />
      <Tabs.Screen name="moi" options={{ title: 'Moi' }} />
      <Tabs.Screen name="scan" options={{ href: null }} />
      <Tabs.Screen name="evenement-form" options={{ href: null }} />
      <Tabs.Screen name="abonnement" options={{ href: null }} />
      <Tabs.Screen name="photos" options={{ href: null }} />
    </Tabs>
  );
}
