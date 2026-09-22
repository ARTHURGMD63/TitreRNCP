/**
 * Moi — le profil et la deconnexion.
 *
 * Volontairement court pour l'instant : les donnees affichees ici viennent de
 * la session, donc du meme appel que la garde de lancement. Le reste de la
 * page profil (badges, niveau, abonnements) suivra avec son point d'API.
 */

import Feather from '@expo/vector-icons/Feather';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { BASE_API } from '../../api';
import { useSession } from '../../session';
import { useTheme } from '../../useTheme';
import { espace, rayon, taille } from '../../theme';

export default function Moi() {
  const { profil, deconnexion } = useSession();
  const { c, ombre } = useTheme();
  const marges = useSafeAreaInsets();

  function confirmerDeconnexion() {
    // Une deconnexion se confirme : elle est instantanee et il faut ressaisir
    // son mot de passe pour revenir.
    Alert.alert('Se déconnecter ?', 'Tu devras te reconnecter pour retrouver tes pass.', [
      { text: 'Annuler', style: 'cancel' },
      { text: 'Se déconnecter', style: 'destructive', onPress: () => void deconnexion() },
    ]);
  }

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: c.bg }}
      contentContainerStyle={{
        paddingTop: marges.top + espace.base,
        paddingHorizontal: espace.lg,
        paddingBottom: espace.xxl,
      }}
    >
      <Text style={[s.marque, { color: c.noir }]}>
        StudentLink <Text style={{ color: c.rouge, fontStyle: 'italic' }}>/ Moi</Text>
      </Text>

      <View style={[s.carte, { backgroundColor: c.blanc, borderColor: c.grisClair }, ombre('sm')]}>
        <View style={[s.rond, { backgroundColor: c.bleu }]}>
          <Text style={s.initiale}>{(profil?.prenom ?? '?').charAt(0).toUpperCase()}</Text>
        </View>

        <Text style={[s.nom, { color: c.noir }]}>
          {profil?.prenom} {profil?.nom}
        </Text>
        <Text style={[s.sous, { color: c.gris }]}>
          {[profil?.ecole, profil?.promo].filter(Boolean).join(' · ') || profil?.email}
        </Text>

        {(profil?.interets.length ?? 0) > 0 && (
          <View style={s.interets}>
            {profil?.interets.map((i) => (
              <View key={i} style={[s.etiquette, { backgroundColor: c.rougeClair }]}>
                <Text style={{ color: c.surRougeClair, fontSize: taille.base, fontWeight: '700' }}>
                  {i}
                </Text>
              </View>
            ))}
          </View>
        )}
      </View>

      <Pressable
        onPress={confirmerDeconnexion}
        style={({ pressed }) => [
          s.action,
          { borderColor: c.grisClair, backgroundColor: c.blanc, opacity: pressed ? 0.8 : 1 },
        ]}
      >
        <Feather name="log-out" size={18} color={c.danger} />
        <Text style={{ color: c.danger, fontSize: taille.texte, fontWeight: '700' }}>
          Se déconnecter
        </Text>
      </Pressable>

      {/* Utile pendant le portage : savoir a quel serveur l'application parle
          evite de chercher une panne d'API alors qu'on vise la mauvaise
          machine. */}
      <Text style={[s.serveur, { color: c.gris }]}>{BASE_API}</Text>
    </ScrollView>
  );
}

const s = StyleSheet.create({
  marque: { fontSize: taille.titre, fontWeight: '700' },
  carte: { borderWidth: 1, borderRadius: rayon.base, padding: espace.lg, marginTop: espace.lg, alignItems: 'center' },
  rond: { width: 72, height: 72, borderRadius: 36, alignItems: 'center', justifyContent: 'center' },
  initiale: { color: '#FFFFFF', fontSize: 30, fontWeight: '900' },
  nom: { fontSize: taille.titre, fontWeight: '900', marginTop: espace.base },
  sous: { fontSize: taille.texte, marginTop: 2 },
  interets: { flexDirection: 'row', flexWrap: 'wrap', gap: espace.sm, marginTop: espace.md, justifyContent: 'center' },
  etiquette: { borderRadius: rayon.pill, paddingHorizontal: espace.base, paddingVertical: 4 },
  action: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: espace.sm,
    borderWidth: 1,
    borderRadius: rayon.bouton,
    paddingVertical: espace.md,
    marginTop: espace.lg,
  },
  serveur: { fontSize: taille.xs, textAlign: 'center', marginTop: espace.xl },
});
