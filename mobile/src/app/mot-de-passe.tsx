/**
 * Mot de passe oublié — auth/forgot.php.
 *
 * La réponse est la même que l'adresse existe ou non : l'écran ne dit jamais
 * qui est inscrit. Le lien reçu par e-mail ouvre la page de réinitialisation
 * du site. En local, rien ne part : le lien s'affiche en « Mode dev », comme
 * sur le site.
 */

import React, { useState } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, Text, View } from 'react-native';
import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { api, ErreurApi } from '../api';
import { Bouton } from '../composants/Bouton';
import { BoutonRetour, Encart } from '../composants/Elements';
import { Champ } from '../composants/Formulaire';
import { Icone } from '../composants/Icone';
import { T, TitreEcran } from '../composants/Texte';
import { fs, lh, sans } from '../theme';
import { useTheme } from '../useTheme';

export default function MotDePasseOublie() {
  const { c } = useTheme();
  const { top, bottom } = useSafeAreaInsets();
  const [email, setEmail] = useState('');
  const [envoye, setEnvoye] = useState(false);
  const [lienDev, setLienDev] = useState<string | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [enCours, setEnCours] = useState(false);

  async function envoyer() {
    setEnCours(true);
    setErreur(null);
    try {
      const rep = await api.motDePasseOublie(email.trim());
      setLienDev(rep.lien_dev);
      setEnvoye(true);
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Adresse email invalide.');
    } finally {
      setEnCours(false);
    }
  }

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: c.bg }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView
        keyboardShouldPersistTaps="handled"
        contentContainerStyle={{ flexGrow: 1, justifyContent: 'center', paddingTop: 46 + top, paddingBottom: 46 + bottom, paddingHorizontal: 24, width: '100%', maxWidth: 460, alignSelf: 'center' }}
      >
        <View style={{ marginBottom: 34 }}>
          <BoutonRetour vers="/login" libelle="Retour à la connexion" />
        </View>

        <TitreEcran lignes={['Mot de passe', 'oublié ?']} taille={fs[8]} style={{ marginBottom: 30 }} />

        {envoye ? (
          <>
            <Encart genre="ok">
              Si cet email est associé à un compte, tu recevras un lien de réinitialisation dans quelques minutes.
            </Encart>
            {lienDev ? (
              <Encart genre="info">
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                  <Icone nom="outil" taille={16} couleur={c.noir} />
                  <T taille={fs[3]} poids={700}>Mode dev — lien de réinitialisation :</T>
                </View>
                <Text onPress={() => WebBrowser.openBrowserAsync(lienDev)} style={{ fontFamily: sans(400), fontSize: fs[3], color: c.surRougeClair, textDecorationLine: 'underline', marginTop: 4 }}>
                  {lienDev}
                </Text>
              </Encart>
            ) : null}
          </>
        ) : (
          <>
            {erreur ? <Encart genre="erreur">{erreur}</Encart> : null}
            <T taille={fs[5]} couleur={c.grisFonce} interligne={lh.normal} style={{ marginBottom: 24 }}>
              Saisis ton adresse email. Si elle est associée à un compte, tu recevras un lien valable <T taille={fs[5]} poids={700} couleur={c.grisFonce}>1 heure</T>.
            </T>
            <Champ
              etiquette="Adresse email"
              value={email}
              onChangeText={setEmail}
              placeholder="arthur@uca.fr"
              keyboardType="email-address"
              autoCapitalize="none"
              autoCorrect={false}
              autoComplete="username"
              autoFocus
              onSubmitEditing={envoyer}
            />
            <Bouton libelle="Envoyer le lien" plein onPress={envoyer} chargement={enCours} style={{ marginTop: 8 }} />
          </>
        )}

        <View style={{ alignItems: 'center', marginTop: 20 }}>
          <Text onPress={() => router.navigate('/login')} accessibilityRole="link" style={{ fontFamily: sans(500), fontSize: fs[4], color: c.gris, minHeight: 44, paddingVertical: 12 }}>
            Retour à la connexion
          </Text>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}
