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
 *
 * Tant que le type n'est pas connu, aucun onglet n'est deviné : un club qui
 * recevait la barre d'un bar (profil en échec, erreur avalée) ne voyait pas
 * ses terrains. Le chargement, puis l'erreur et « Réessayer » s'affichent à
 * la place.
 */

import { useCallback, useEffect, useState } from 'react';
import { router, Tabs } from 'expo-router';

import { api, ErreurApi } from '../../api';
import { BarreOnglets, type OngletBarre } from '../../composants/BarreOnglets';
import { Chargement, Ecran, Erreur } from '../../composants/Ecran';
import { useJeton } from '../../session';
import { useTheme } from '../../useTheme';

const ONGLETS: OngletBarre[] = [
  // « Tableau de bord » tronquait en « Tableau de b… » sur 375 px ; la barre des clubs dit déjà « Tableau ».
  { route: 'index', libelle: 'Tableau', icone: 'appareil', court: 'Accueil' },
  { route: 'evenements', libelle: 'Événements', icone: 'calendrier', court: 'Soirées' },
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
  'evenement-suivi': 'evenements',
  abonnement: 'moi',
  photos: 'moi',
};

export default function OngletsPartenaire() {
  const { c } = useTheme();
  const jeton = useJeton();
  /** null : pas encore connu. */
  const [estClub, setEstClub] = useState<boolean | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [essai, setEssai] = useState(0);
  const reessayer = useCallback(() => { setErreur(null); setEssai((n) => n + 1); }, []);

  useEffect(() => {
    let actif = true;
    api.partenaireProfil(jeton)
      .then((r) => { if (actif) setEstClub(r.etablissement.type === 'club'); })
      .catch((e) => { if (actif) setErreur(e instanceof ErreurApi ? e.message : 'Chargement impossible.'); });
    return () => { actif = false; };
  }, [jeton, essai]);

  if (estClub === null) {
    return (
      <Ecran avecBarre={false}>
        {erreur ? <Erreur message={erreur} onReessayer={reessayer} /> : <Chargement />}
      </Ecran>
    );
  }

  return (
    <Tabs
      tabBar={(props) => (
        <BarreOnglets
          {...props}
          onglets={estClub ? ONGLETS_CLUB : ONGLETS}
          parent={PARENT}
          actionCentrale={estClub ? undefined : { libelle: 'Créer une soirée', icone: 'plus', couleur: c.rouge, onPress: () => router.push({ pathname: '/evenement-form' }) }}
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
      <Tabs.Screen name="evenement-suivi" options={{ href: null }} />
      <Tabs.Screen name="abonnement" options={{ href: null }} />
      <Tabs.Screen name="photos" options={{ href: null }} />
    </Tabs>
  );
}
