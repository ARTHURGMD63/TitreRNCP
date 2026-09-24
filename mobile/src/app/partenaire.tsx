/**
 * Compte partenaire ou admin connecté depuis l'application.
 *
 * L'espace pro (tableau de bord, création d'événements, scan des pass) et le
 * back-office vivent sur le site, dans l'univers clair « linkee pro ». Le
 * site y envoie un partenaire dès sa connexion ; l'application fait de même,
 * en ouvrant la page dans le navigateur.
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

export default function EspacePartenaire() {
  const { c } = useTheme();
  const { profil, deconnexion } = useSession();
  const { top, bottom } = useSafeAreaInsets();
  const admin = profil?.type === 'admin';

  return (
    <View style={{ flex: 1, backgroundColor: c.bg, justifyContent: 'center', paddingTop: 46 + top, paddingBottom: 46 + bottom, paddingHorizontal: 24 }}>
      <View style={{ marginBottom: 34 }}>
        <Marque suffixe={admin ? 'interne' : 'pro'} />
      </View>
      <TitreEcran lignes={['Ton espace', 'est sur le site.']} taille={fs[8]} style={{ marginBottom: 20 }} />
      <T taille={fs[5]} couleur={c.grisFonce} interligne={lh.normal} style={{ marginBottom: 28 }}>
        {admin
          ? 'Le back-office se gère depuis un navigateur.'
          : 'Tableau de bord, création d’événements et scan des pass se font depuis Linkee pro, sur le site.'}
      </T>
      <Bouton
        libelle={admin ? 'Ouvrir le back-office' : 'Ouvrir Linkee pro'}
        plein
        onPress={() => WebBrowser.openBrowserAsync(`${adresseServeur()}${admin ? '/admin/index.php' : '/partenaire/dashboard.php'}`)}
      />
      <Bouton libelle="Se déconnecter" variante="contour" plein onPress={deconnexion} style={{ marginTop: 12 }} />
    </View>
  );
}
