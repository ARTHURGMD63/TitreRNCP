/**
 * Compte administrateur connecté depuis l'application.
 *
 * Le back-office fondateurs (CRM, finances, modération, tableau de bord)
 * vit sur le site, dans l'univers clair « linkee interne ». L'espace
 * partenaire, lui, a son propre groupe de routes natif : voir
 * `mobile/src/app/(partenaire)/`.
 */

import React from 'react';
import { View } from 'react-native';
import * as WebBrowser from 'expo-web-browser';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { adresseServeur } from '../api';
import { Bouton } from '../composants/Bouton';
import { Marque } from '../composants/Marque';
import { T, TitreEcran } from '../composants/Texte';
import { useSession } from '../session';
import { fs, lh } from '../theme';
import { useTheme } from '../useTheme';

export default function EspaceAdmin() {
  const { c } = useTheme();
  const { deconnexion } = useSession();
  const { top, bottom } = useSafeAreaInsets();

  return (
    <View style={{ flex: 1, backgroundColor: c.bg, justifyContent: 'center', paddingTop: 46 + top, paddingBottom: 46 + bottom, paddingHorizontal: 24 }}>
      <View style={{ marginBottom: 34 }}>
        <Marque suffixe="interne" />
      </View>
      <TitreEcran lignes={['Ton espace', 'est sur le site.']} taille={fs[8]} style={{ marginBottom: 20 }} />
      <T taille={fs[5]} couleur={c.grisFonce} interligne={lh.normal} style={{ marginBottom: 28 }}>
        Le back-office se gère depuis un navigateur.
      </T>
      <Bouton
        libelle="Ouvrir le back-office"
        plein
        onPress={() => WebBrowser.openBrowserAsync(`${adresseServeur()}/admin/index.php`)}
      />
      <Bouton libelle="Se déconnecter" variante="contour" plein onPress={deconnexion} style={{ marginTop: 12 }} />
    </View>
  );
}
