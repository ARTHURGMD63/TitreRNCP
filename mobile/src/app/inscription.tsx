/**
 * Inscription — auth/register.php.
 *
 * Le sélecteur Étudiant·e / Partenaire, puis les mêmes champs dans le même
 * ordre : prénom et nom côte à côte, email, mot de passe, date de naissance
 * (réservée aux majeurs, bornée comme le champ du site), école et promo,
 * centres d'intérêt ; ou, pour un partenaire, l'établissement. Les
 * conditions générales s'ouvrent depuis la case, comme les liens du site.
 *
 * Toutes les vérifications décisives restent celles du serveur
 * (api/v1/inscription.php, mêmes règles et mêmes messages que le site).
 */

import React, { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, Text, View } from 'react-native';
import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { adresseServeur, ErreurApi } from '../api';
import { ECOLES, INTERETS, PROMOS, TYPES_ETABLISSEMENT } from '../catalogue';
import { Bouton } from '../composants/Bouton';
import { Encart } from '../composants/Elements';
import { Aide, Case, Champ, ChampDate, Etiquette, isoDate, SelecteurInterets, Selecteur } from '../composants/Formulaire';
import { Marque } from '../composants/Marque';
import { T, TitreEcran } from '../composants/Texte';
import { useSession } from '../session';
import { fs, lh, rayon, sans } from '../theme';
import { useTheme } from '../useTheme';

type Type = 'etudiant' | 'partenaire';

export default function Inscription() {
  const { c } = useTheme();
  const { inscription, enCours } = useSession();
  const { top, bottom } = useSafeAreaInsets();

  const [type, setType] = useState<Type>('etudiant');
  const [prenom, setPrenom] = useState('');
  const [nom, setNom] = useState('');
  const [email, setEmail] = useState('');
  const [motDePasse, setMotDePasse] = useState('');
  const [naissance, setNaissance] = useState<Date | null>(null);
  const [ecole, setEcole] = useState('');
  const [promo, setPromo] = useState('');
  const [interets, setInterets] = useState<string[]>([]);
  const [etabNom, setEtabNom] = useState('');
  const [etabType, setEtabType] = useState('bar');
  const [ville, setVille] = useState('Clermont-Ferrand');
  const [cgu, setCgu] = useState(false);
  const [erreur, setErreur] = useState<string | null>(null);

  const aujourdhui = new Date();
  const borneMajeur = new Date(aujourdhui.getFullYear() - 18, aujourdhui.getMonth(), aujourdhui.getDate());
  const bornePlancher = new Date(aujourdhui.getFullYear() - 120, aujourdhui.getMonth(), aujourdhui.getDate());

  async function creer() {
    setErreur(null);
    try {
      await inscription({
        type, prenom: prenom.trim(), nom: nom.trim(), email: email.trim(), password: motDePasse,
        date_naissance: naissance ? isoDate(naissance) : '',
        ecole, promo, interets,
        etablissement_nom: etabNom.trim(), etablissement_type: etabType, ville: ville.trim(),
        cgu,
      });
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Erreur lors de la création du compte.');
    }
  }

  const ouvrir = (chemin: string) => WebBrowser.openBrowserAsync(`${adresseServeur()}${chemin}`);

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: c.bg }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView
        keyboardShouldPersistTaps="handled"
        contentContainerStyle={{ paddingTop: 24 + top, paddingBottom: 46 + bottom, paddingHorizontal: 24, width: '100%', maxWidth: 460, alignSelf: 'center' }}
      >
        <View style={{ marginBottom: 34 }}>
          <Marque />
        </View>

        <TitreEcran lignes={['Rejoins la', 'communauté.']} taille={fs[8]} style={{ marginBottom: 30 }} />

        {/* .type-toggle */}
        <View style={{ flexDirection: 'row', gap: 4, padding: 5, borderRadius: rayon.pill, backgroundColor: c.blanc, marginBottom: 24 }}>
          {(['etudiant', 'partenaire'] as Type[]).map((t) => {
            const actif = t === type;
            return (
              <Pressable
                key={t}
                onPress={() => setType(t)}
                accessibilityRole="tab"
                accessibilityState={{ selected: actif }}
                style={{ flex: 1, padding: 12, minHeight: 44, borderRadius: rayon.pill, alignItems: 'center', justifyContent: 'center', backgroundColor: actif ? c.noir : 'transparent' }}
              >
                <Text style={{ fontFamily: sans(600), fontSize: fs[4], color: actif ? c.bg : c.gris }}>
                  {t === 'etudiant' ? 'Étudiant·e' : 'Partenaire'}
                </Text>
              </Pressable>
            );
          })}
        </View>

        {erreur ? <Encart genre="erreur">{erreur}</Encart> : null}

        <View style={{ flexDirection: 'row', gap: 12 }}>
          <Champ style={{ flex: 1 }} etiquette="Prénom" value={prenom} onChangeText={setPrenom} placeholder="Arthur" autoComplete="given-name" textContentType="givenName" />
          <Champ style={{ flex: 1 }} etiquette="Nom" value={nom} onChangeText={setNom} placeholder="Martin" autoComplete="family-name" textContentType="familyName" />
        </View>

        <Champ etiquette="Email" value={email} onChangeText={setEmail} placeholder="arthur@uca.fr" keyboardType="email-address" autoCapitalize="none" autoCorrect={false} autoComplete="email" textContentType="emailAddress" />
        <Champ etiquette="Mot de passe" value={motDePasse} onChangeText={setMotDePasse} placeholder="••••••••" secureTextEntry autoComplete="new-password" textContentType="newPassword" />

        <ChampDate
          etiquette="Date de naissance"
          valeur={naissance}
          onChange={setNaissance}
          min={bornePlancher}
          max={borneMajeur}
          aide="Linkee donne accès à des soirées en bar et en discothèque : l’inscription est réservée aux personnes majeures."
        />

        {type === 'etudiant' ? (
          <>
            <View style={{ flexDirection: 'row', gap: 12 }}>
              <Selecteur style={{ flex: 1 }} etiquette="École" valeur={ecole} onChange={setEcole}
                options={[{ valeur: '', libelle: '— Choisir —' }, ...ECOLES.map((e) => ({ valeur: e, libelle: e }))]} />
              <Selecteur style={{ flex: 1 }} etiquette="Promo" valeur={promo} onChange={setPromo}
                options={[{ valeur: '', libelle: '—' }, ...PROMOS.map((p) => ({ valeur: p, libelle: p }))]} />
            </View>

            <View style={{ marginBottom: 16 }}>
              <Etiquette>Centres d&apos;intérêt</Etiquette>
              <Aide style={{ marginTop: 0, marginBottom: 10 }}>
                Ils servent à te proposer des étudiants qui aiment les mêmes choses que toi. Tu pourras les changer quand tu veux depuis ton profil.
              </Aide>
              <SelecteurInterets catalogue={INTERETS} choisis={interets} onChange={setInterets} />
            </View>
          </>
        ) : (
          <>
            <Champ etiquette="Nom de l'établissement" value={etabNom} onChangeText={setEtabNom} placeholder="Le Bec qui Pique" />
            <View style={{ flexDirection: 'row', gap: 12 }}>
              <Selecteur style={{ flex: 1 }} etiquette="Type" valeur={etabType} onChange={setEtabType} options={TYPES_ETABLISSEMENT} />
              <Champ style={{ flex: 1 }} etiquette="Ville" value={ville} onChangeText={setVille} />
            </View>
          </>
        )}

        <Case coche={cgu} onChange={setCgu} style={{ marginTop: 10, alignItems: 'flex-start' }}>
          <T taille={fs[2]} couleur={c.grisFonce} interligne={lh.normal}>
            J’accepte les{' '}
            <Text onPress={() => ouvrir('/cgu.php')} style={{ textDecorationLine: 'underline', color: c.grisFonce }}>conditions générales</Text>
            {' '}et la{' '}
            <Text onPress={() => ouvrir('/confidentialite.php')} style={{ textDecorationLine: 'underline', color: c.grisFonce }}>politique de confidentialité</Text>.
          </T>
        </Case>

        <Bouton libelle="Créer mon compte" plein onPress={creer} chargement={enCours} style={{ marginTop: 14 }} />

        <View style={{ flexDirection: 'row', justifyContent: 'center', flexWrap: 'wrap', marginTop: 20 }}>
          <T taille={fs[4]} couleur={c.gris}>Déjà un compte ? </T>
          <Text onPress={() => router.navigate('/login')} accessibilityRole="link" style={{ fontFamily: sans(700), fontSize: fs[4], color: c.noir }}>
            Se connecter
          </Text>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}
