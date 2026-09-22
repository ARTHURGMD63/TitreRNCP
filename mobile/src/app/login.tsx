/**
 * Ecran de connexion.
 *
 * Reprend le ton du web — « Content de te revoir. », Playfair en italique sur
 * le second mot — parce qu'une application qui ne ressemble pas au site
 * ressemble a une autre application.
 */

import { useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { ErreurApi } from '../api';
import { useSession } from '../session';
import { useTheme } from '../useTheme';
import { espace, rayon, taille } from '../theme';

export default function Connexion() {
  const { connexion, enCours } = useSession();
  const { c } = useTheme();
  const marges = useSafeAreaInsets();

  const [email, setEmail] = useState('');
  const [motDePasse, setMotDePasse] = useState('');
  const [erreur, setErreur] = useState<string | null>(null);

  const pret = email.trim() !== '' && motDePasse !== '' && !enCours;

  async function envoyer() {
    if (!pret) return;
    setErreur(null);
    try {
      await connexion(email, motDePasse);
      // La garde de _layout.tsx bascule sur les onglets : rien a faire ici.
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Connexion impossible.');
    }
  }

  return (
    <KeyboardAvoidingView
      style={{ flex: 1, backgroundColor: c.bg }}
      // Sur iOS le clavier recouvre le formulaire sans ce decalage ; sur
      // Android le systeme redimensionne deja la fenetre.
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <ScrollView
        contentContainerStyle={[
          s.contenu,
          { paddingTop: marges.top + espace.xxl, paddingBottom: marges.bottom + espace.xl },
        ]}
        keyboardShouldPersistTaps="handled"
      >
        <Text style={[s.marque, { color: c.noir }]}>
          StudentLink <Text style={{ color: c.rouge, fontStyle: 'italic' }}>/ Explorer</Text>
        </Text>

        <Text style={[s.titre, { color: c.noir }]}>
          Content de{'\n'}
          <Text style={{ fontStyle: 'italic' }}>te revoir.</Text>
        </Text>

        {erreur !== null && (
          <View style={[s.erreur, { backgroundColor: c.dangerClair, borderColor: c.danger }]}>
            <Text style={{ color: c.danger, fontSize: taille.texte }}>{erreur}</Text>
          </View>
        )}

        <Text style={[s.etiquette, { color: c.grisFonce }]}>E-MAIL</Text>
        <TextInput
          value={email}
          onChangeText={setEmail}
          placeholder="arthur@uca.fr"
          placeholderTextColor={c.gris}
          autoCapitalize="none"
          autoCorrect={false}
          keyboardType="email-address"
          textContentType="username"
          style={[s.champ, { backgroundColor: c.blanc, borderColor: c.grisClair, color: c.noir }]}
        />

        <Text style={[s.etiquette, { color: c.grisFonce }]}>MOT DE PASSE</Text>
        <TextInput
          value={motDePasse}
          onChangeText={setMotDePasse}
          placeholder="••••••••"
          placeholderTextColor={c.gris}
          secureTextEntry
          // Laisse iOS proposer le trousseau plutot qu'une saisie a la main.
          textContentType="password"
          onSubmitEditing={envoyer}
          returnKeyType="go"
          style={[s.champ, { backgroundColor: c.blanc, borderColor: c.grisClair, color: c.noir }]}
        />

        <Pressable
          onPress={envoyer}
          disabled={!pret}
          style={({ pressed }) => [
            s.bouton,
            {
              backgroundColor: c.noir,
              opacity: !pret ? 0.5 : pressed ? 0.85 : 1,
            },
          ]}
        >
          {enCours ? (
            <ActivityIndicator color={c.bg} />
          ) : (
            <Text style={[s.boutonTexte, { color: c.bg }]}>→ Se connecter</Text>
          )}
        </Pressable>

        <View style={[s.demo, { backgroundColor: c.surface2, borderColor: c.grisClair }]}>
          <Text style={{ color: c.grisFonce, fontSize: taille.base, fontWeight: '700' }}>
            Comptes de démo
          </Text>
          <Text style={{ color: c.gris, fontSize: taille.base, marginTop: espace.xs }}>
            arthur@uca.fr / password
          </Text>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const s = StyleSheet.create({
  contenu: { paddingHorizontal: espace.lg, flexGrow: 1 },
  marque: { fontSize: taille.corps, fontWeight: '700', marginBottom: espace.xxl },
  titre: { fontSize: taille.hero, fontWeight: '900', lineHeight: 40, marginBottom: espace.xl },
  etiquette: {
    fontSize: taille.xs,
    fontWeight: '700',
    letterSpacing: 1,
    marginBottom: espace.sm,
    marginTop: espace.md,
  },
  champ: {
    borderWidth: 1,
    borderRadius: rayon.sm,
    paddingHorizontal: espace.md,
    paddingVertical: espace.base,
    // 16 pt minimum : en dessous, iOS zoome sur le champ a la mise au point.
    fontSize: taille.corps,
  },
  bouton: {
    marginTop: espace.xl,
    borderRadius: rayon.bouton,
    paddingVertical: espace.md,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 52,
  },
  boutonTexte: { fontSize: taille.texte, fontWeight: '700', letterSpacing: 0.5 },
  erreur: {
    borderWidth: 1,
    borderRadius: rayon.sm,
    padding: espace.base,
    marginBottom: espace.sm,
  },
  demo: {
    marginTop: espace.xxl,
    borderWidth: 1,
    borderRadius: rayon.sm,
    padding: espace.md,
  },
});
