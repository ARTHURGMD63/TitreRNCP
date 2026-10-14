/**
 * Mot de passe oublié — en deux temps, sans quitter l'application.
 *
 *  1. L'adresse : un code à six chiffres part par e-mail. La réponse est la
 *     même que l'adresse existe ou non, l'écran ne dit jamais qui est inscrit.
 *  2. Le code et le nouveau mot de passe, saisis ici. Pas de lien vers une page
 *     du site : l'application se suffit à elle-même.
 *
 * En local rien ne part : le code s'affiche en « Mode dev ».
 */

import React, { useState } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, Text, View } from 'react-native';
import { router } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { api, ErreurApi } from '../api';
import { Bouton } from '../composants/Bouton';
import { BoutonRetour, Encart } from '../composants/Elements';
import { Champ } from '../composants/Formulaire';
import { Icone } from '../composants/Icone';
import { T, TitreEcran } from '../composants/Texte';
import { fs, lh, sans } from '../theme';
import { useTheme } from '../useTheme';

type Etape = 'adresse' | 'code' | 'fini';

export default function MotDePasseOublie() {
  const { c } = useTheme();
  const { top, bottom } = useSafeAreaInsets();
  const [etape, setEtape] = useState<Etape>('adresse');
  const [email, setEmail] = useState('');
  const [code, setCode] = useState('');
  const [motDePasse, setMotDePasse] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [codeDev, setCodeDev] = useState<string | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [enCours, setEnCours] = useState(false);

  async function demanderCode() {
    setEnCours(true);
    setErreur(null);
    try {
      const rep = await api.motDePasseOublie(email.trim());
      setCodeDev(rep.code_dev);
      setCode('');
      setEtape('code');
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Adresse email invalide.');
    } finally {
      setEnCours(false);
    }
  }

  async function valider() {
    if (!/^\d{6}$/.test(code.trim())) {
      setErreur('Le code a six chiffres.');
      return;
    }
    if (motDePasse !== confirmation) {
      setErreur('Les deux mots de passe ne sont pas identiques.');
      return;
    }
    setEnCours(true);
    setErreur(null);
    try {
      await api.reinitialiserMotDePasse(email.trim(), code.trim(), motDePasse);
      setEtape('fini');
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Réinitialisation impossible, réessaie.');
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

        <TitreEcran
          lignes={etape === 'fini' ? ['Mot de passe', 'changé.'] : etape === 'code' ? ['Saisis', 'ton code.'] : ['Mot de passe', 'oublié ?']}
          taille={fs[8]}
          style={{ marginBottom: 30 }}
        />

        {etape === 'fini' ? (
          <>
            <Encart genre="ok">
              Ton mot de passe est changé. Tu as été déconnecté de tes autres appareils.
            </Encart>
            <Bouton libelle="Se connecter" plein onPress={() => router.replace('/login')} />
          </>
        ) : etape === 'code' ? (
          <>
            <Encart genre="ok">
              Si cet email est associé à un compte, un code à six chiffres vient de partir. Il est valable 15 minutes.
            </Encart>
            {codeDev ? (
              <Encart genre="info">
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                  <Icone nom="outil" taille={16} couleur={c.noir} />
                  <T taille={fs[3]} poids={700}>Mode dev — ton code : {codeDev}</T>
                </View>
              </Encart>
            ) : null}
            {erreur ? <Encart genre="erreur">{erreur}</Encart> : null}

            <Champ
              etiquette="Code reçu par email"
              value={code}
              onChangeText={(t) => setCode(t.replace(/\D/g, '').slice(0, 6))}
              placeholder="123456"
              keyboardType="number-pad"
              maxLength={6}
              autoComplete="one-time-code"
              textContentType="oneTimeCode"
              autoFocus
            />
            <Champ
              etiquette="Nouveau mot de passe"
              value={motDePasse}
              onChangeText={setMotDePasse}
              placeholder="8 caractères minimum"
              secureTextEntry
              autoComplete="new-password"
              textContentType="newPassword"
            />
            <Champ
              etiquette="Confirme le mot de passe"
              value={confirmation}
              onChangeText={setConfirmation}
              placeholder="••••••••"
              secureTextEntry
              autoComplete="new-password"
              textContentType="newPassword"
              returnKeyType="go"
              onSubmitEditing={valider}
            />
            <Bouton libelle="Changer mon mot de passe" plein onPress={valider} chargement={enCours} style={{ marginTop: 8 }} />

            <T taille={fs[3]} couleur={c.gris} interligne={lh.normal} style={{ marginTop: 20, textAlign: 'center' }}>
              Pas reçu ? Vérifie tes spams, patiente une minute, puis{' '}
              <Text onPress={() => { setErreur(null); setEtape('adresse'); }} accessibilityRole="link" style={{ fontFamily: sans(600), textDecorationLine: 'underline', color: c.grisFonce }}>
                redemande un code
              </Text>.
            </T>
          </>
        ) : (
          <>
            {erreur ? <Encart genre="erreur">{erreur}</Encart> : null}
            <T taille={fs[5]} couleur={c.grisFonce} interligne={lh.normal} style={{ marginBottom: 24 }}>
              Saisis ton adresse email. Si elle est associée à un compte, tu recevras un code à six chiffres valable <T taille={fs[5]} poids={700} couleur={c.grisFonce}>15 minutes</T>.
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
              onSubmitEditing={demanderCode}
            />
            <Bouton libelle="Recevoir un code" plein onPress={demanderCode} chargement={enCours} style={{ marginTop: 8 }} />
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
