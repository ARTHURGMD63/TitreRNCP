/**
 * Connexion — auth/login.php.
 *
 * Le logo, « Content de te revoir. », les deux champs, « Mot de passe
 * oublié ? » aligné à droite, la pilule lave, le lien vers l'inscription et
 * le bloc des comptes de démonstration en mono. Mêmes textes, même ordre.
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
import { useSession } from '../session';
import { fs, lsEm, mono, rayon, sans } from '../theme';
import { useTheme } from '../useTheme';

export default function Connexion() {
  const { c } = useTheme();
  const { connexion, enCours } = useSession();
  const { top, bottom } = useSafeAreaInsets();
  const [email, setEmail] = useState('');
  const [motDePasse, setMotDePasse] = useState('');
  const [erreur, setErreur] = useState<string | null>(null);

  async function valider() {
    if (!email.trim() || !motDePasse) {
      setErreur('Merci de remplir tous les champs.');
      return;
    }
    setErreur(null);
    try {
      await connexion(email, motDePasse);
    } catch (e) {
      // Mêmes phrases que le site : « Email ou mot de passe incorrect. »
      if (e instanceof ErreurApi && e.code === 'identifiants') setErreur('Email ou mot de passe incorrect.');
      else if (e instanceof ErreurApi && e.code === 'trop_de_tentatives') setErreur('Trop de tentatives. Réessaye dans 15 minutes.');
      else setErreur(e instanceof ErreurApi ? e.message : 'Connexion impossible.');
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

        {erreur ? <Encart genre="erreur">{erreur}</Encart> : null}

        <Champ
          etiquette="Email"
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

        <Bouton libelle="Se connecter" plein onPress={valider} chargement={enCours} />

        <View style={{ flexDirection: 'row', justifyContent: 'center', flexWrap: 'wrap', marginTop: 20 }}>
          <T taille={fs[4]} couleur={c.gris}>Pas encore de compte ? </T>
          <Text onPress={() => router.push('/inscription')} accessibilityRole="link" style={{ fontFamily: sans(700), fontSize: fs[4], color: c.noir }}>
            Créer un compte
          </Text>
        </View>

        <View style={{ marginTop: 32, paddingVertical: 16, paddingHorizontal: 18, backgroundColor: c.blanc, borderRadius: rayon.md }}>
          <Text style={{ fontFamily: mono(500), fontSize: fs[2], color: c.noir, textTransform: 'uppercase', letterSpacing: lsEm.label * fs[2], marginBottom: 4 }}>
            Comptes de démo
          </Text>
          <Text style={{ fontFamily: mono(400), fontSize: fs[2], lineHeight: fs[2] * 1.7, color: c.gris }}>
            Étudiant : arthur@uca.fr / password{'\n'}Partenaire : jean@lebecquipique.fr / password
          </Text>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}
