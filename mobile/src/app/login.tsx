/**
 * Connexion — auth/login.php.
 *
 * Le logo, « Content de te revoir. », les deux champs, « Mot de passe
 * oublié ? » aligné à droite, la pilule lave, le lien vers l'inscription.
 * Mêmes textes, même ordre.
 */

import React, { useState } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, Text, View } from 'react-native';
import { Link, router } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { ErreurApi } from '../api';
import { Bouton } from '../composants/Bouton';
import { Encart } from '../composants/Elements';
import { Champ } from '../composants/Formulaire';
import { Marque } from '../composants/Marque';
import { T, TitreEcran } from '../composants/Texte';
import { connexionComplete, messageConnexion } from '../regles';
import { useSession } from '../session';
import { fs, sans } from '../theme';
import { useTheme } from '../useTheme';

export default function Connexion() {
  const { c } = useTheme();
  const { connexion, enCours } = useSession();
  const { top, bottom } = useSafeAreaInsets();
  const [email, setEmail] = useState('');
  const [motDePasse, setMotDePasse] = useState('');
  const [erreur, setErreur] = useState<string | null>(null);

  async function valider() {
    if (!connexionComplete(email, motDePasse)) {
      setErreur('Merci de remplir tous les champs.');
      return;
    }
    setErreur(null);
    try {
      await connexion(email, motDePasse);
    } catch (e) {
      // Mêmes phrases que le site : « Email ou mot de passe incorrect. »
      setErreur(messageConnexion(e instanceof ErreurApi ? { code: e.code, message: e.message } : null));
    }
  }

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: c.bg }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView
        keyboardShouldPersistTaps="handled"
        contentContainerStyle={{ flexGrow: 1, justifyContent: 'center', paddingTop: 46 + top, paddingBottom: 46 + bottom, paddingHorizontal: 24, width: '100%', maxWidth: 460, alignSelf: 'center' }}
      >
        <View style={{ marginBottom: 34 }}>
          <Marque />
        </View>

        <TitreEcran lignes={['Content de te', 'revoir.']} taille={fs[9]} style={{ marginBottom: 30 }} />

        {erreur ? <View testID="connexion-erreur"><Encart genre="erreur">{erreur}</Encart></View> : null}

        <Champ
          etiquette="Email"
          testID="connexion-email"
          value={email}
          onChangeText={setEmail}
          placeholder="arthur@uca.fr"
          keyboardType="email-address"
          autoCapitalize="none"
          autoCorrect={false}
          autoComplete="username"
          textContentType="username"
          returnKeyType="next"
        />
        <Champ
          etiquette="Mot de passe"
          testID="connexion-mot-de-passe"
          value={motDePasse}
          onChangeText={setMotDePasse}
          placeholder="••••••••"
          secureTextEntry
          autoComplete="current-password"
          textContentType="password"
          returnKeyType="go"
          onSubmitEditing={valider}
        />

        <View style={{ alignItems: 'flex-end', marginTop: -4, marginBottom: 18 }}>
          <Link href="/mot-de-passe" style={{ minHeight: 44, paddingVertical: 12 }}>
            <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: c.surRougeClair }}>Mot de passe oublié ?</Text>
          </Link>
        </View>

        <Bouton testID="connexion-valider" libelle="Se connecter" plein onPress={valider} chargement={enCours} />

        <View style={{ flexDirection: 'row', justifyContent: 'center', flexWrap: 'wrap', marginTop: 20 }}>
          <T taille={fs[4]} couleur={c.gris}>Pas encore de compte ? </T>
          <Text onPress={() => router.push('/inscription')} accessibilityRole="link" style={{ fontFamily: sans(700), fontSize: fs[4], color: c.noir }}>
            Créer un compte
          </Text>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}
