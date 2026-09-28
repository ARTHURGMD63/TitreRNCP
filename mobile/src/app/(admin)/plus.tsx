/**
 * Plus — le reste du back-office fondateurs (admin/plus.tsx).
 *
 * Le tableau de bord et le CRM restent sur le site : usage hebdomadaire,
 * formulaires denses, graphique — pensés pour un grand écran, pas pour un
 * usage sur le pouce entre deux scans. Les finances, elles, ont un écran
 * natif de consultation (finances.tsx) : les fondateurs doivent pouvoir
 * vérifier la trésorerie sans changer d'application.
 */

import React from 'react';
import { Pressable, View } from 'react-native';
import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';

import { adresseServeur } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { Contenu, EnTete, Ecran } from '../../composants/Ecran';
import { Icone, type NomIcone } from '../../composants/Icone';
import { Mono, T, TitreEcran } from '../../composants/Texte';
import { useSession } from '../../session';
import { fs, rayon } from '../../theme';
import { useTheme } from '../../useTheme';

const LIEN_FINANCES = { libelle: 'Finances', description: 'Trésorerie, MRR, derniers mouvements', icone: 'piece' as NomIcone };

const LIENS_SITE: { chemin: string; libelle: string; description: string; icone: NomIcone }[] = [
  { chemin: '/admin/index.php', libelle: 'Tableau de bord', description: 'North Star, alertes, pipeline, point mort', icone: 'activite' },
  { chemin: '/admin/clients.php', libelle: 'Clients', description: 'Pipeline commercial des établissements', icone: 'personnes' },
];

function LigneLien({ libelle, description, icone, onPress }: { libelle: string; description: string; icone: NomIcone; onPress: () => void }) {
  const { c } = useTheme();
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="link"
      style={{ flexDirection: 'row', alignItems: 'center', gap: 14, backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.base, padding: 16, marginBottom: 12 }}
    >
      <View style={{ width: 40, height: 40, borderRadius: 20, backgroundColor: c.surface2, alignItems: 'center', justifyContent: 'center' }}>
        <Icone nom={icone} taille={18} couleur={c.noir} />
      </View>
      <View style={{ flex: 1 }}>
        <T taille={fs[4]} poids={700}>{libelle}</T>
        <T taille={fs[2]} couleur={c.gris}>{description}</T>
      </View>
      <T taille={fs[4]} couleur={c.gris}>→</T>
    </Pressable>
  );
}

export default function PlusAdmin() {
  const { deconnexion } = useSession();

  return (
    <Ecran>
      <EnTete>
        <TitreEcran lignes={['Plus.']} />
      </EnTete>
      <Contenu style={{ paddingTop: 0 }}>
        <Mono style={{ marginBottom: 12 }}>Dans l&apos;application</Mono>
        <LigneLien {...LIEN_FINANCES} onPress={() => router.navigate('/finances')} />

        <Mono style={{ marginTop: 8, marginBottom: 12 }}>Sur le site</Mono>
        {LIENS_SITE.map((l) => (
          <LigneLien key={l.chemin} libelle={l.libelle} description={l.description} icone={l.icone}
            onPress={() => WebBrowser.openBrowserAsync(`${adresseServeur()}${l.chemin}`)} />
        ))}

        <Bouton libelle="Se déconnecter" variante="contour" plein onPress={deconnexion} style={{ marginTop: 20 }} />
      </Contenu>
    </Ecran>
  );
}
