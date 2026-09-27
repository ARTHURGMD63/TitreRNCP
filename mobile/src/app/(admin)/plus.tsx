/**
 * Plus — le reste du back-office fondateurs (admin/plus.tsx).
 *
 * Le tableau de bord, le CRM et les finances restent sur le site : usage
 * hebdomadaire, formulaires denses, graphique — pensés pour un grand écran,
 * pas pour un usage sur le pouce entre deux scans.
 */

import React from 'react';
import { Pressable, View } from 'react-native';
import * as WebBrowser from 'expo-web-browser';

import { adresseServeur } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { Contenu, EnTete, Ecran } from '../../composants/Ecran';
import { Icone, type NomIcone } from '../../composants/Icone';
import { Mono, T, TitreEcran } from '../../composants/Texte';
import { useSession } from '../../session';
import { fs, rayon } from '../../theme';
import { useTheme } from '../../useTheme';

const LIENS: { chemin: string; libelle: string; description: string; icone: NomIcone }[] = [
  { chemin: '/admin/index.php', libelle: 'Tableau de bord', description: 'North Star, alertes, pipeline, point mort', icone: 'activite' },
  { chemin: '/admin/clients.php', libelle: 'Clients', description: 'Pipeline commercial des établissements', icone: 'personnes' },
  { chemin: '/admin/finances.php', libelle: 'Finances', description: 'Trésorerie, registre, graphique 12 mois', icone: 'piece' },
];

export default function PlusAdmin() {
  const { c } = useTheme();
  const { deconnexion } = useSession();

  return (
    <Ecran>
      <EnTete>
        <TitreEcran lignes={['Plus.']} />
      </EnTete>
      <Contenu style={{ paddingTop: 0 }}>
        <Mono style={{ marginBottom: 12 }}>Sur le site</Mono>
        {LIENS.map((l) => (
          <Pressable
            key={l.chemin}
            onPress={() => WebBrowser.openBrowserAsync(`${adresseServeur()}${l.chemin}`)}
            accessibilityRole="link"
            style={{ flexDirection: 'row', alignItems: 'center', gap: 14, backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.base, padding: 16, marginBottom: 12 }}
          >
            <View style={{ width: 40, height: 40, borderRadius: 20, backgroundColor: c.surface2, alignItems: 'center', justifyContent: 'center' }}>
              <Icone nom={l.icone} taille={18} couleur={c.noir} />
            </View>
            <View style={{ flex: 1 }}>
              <T taille={fs[4]} poids={700}>{l.libelle}</T>
              <T taille={fs[2]} couleur={c.gris}>{l.description}</T>
            </View>
            <T taille={fs[4]} couleur={c.gris}>→</T>
          </Pressable>
        ))}

        <Bouton libelle="Se déconnecter" variante="contour" plein onPress={deconnexion} style={{ marginTop: 20 }} />
      </Contenu>
    </Ecran>
  );
}
