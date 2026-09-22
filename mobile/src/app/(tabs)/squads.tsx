/**
 * Squads — les sorties sportives a venir.
 *
 * Reprend .squad-card : badge de niveau, type et date en surtitre, titre,
 * lieu, membres, et le bouton qui change selon qu'on est createur, membre, ou
 * ni l'un ni l'autre.
 */

import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import Feather from '@expo/vector-icons/Feather';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { actions, api, ErreurApi, type Squad } from '../../api';
import { useJeton } from '../../session';
import { useChargement } from '../../useChargement';
import { useTheme } from '../../useTheme';
import { espace, rayon, taille } from '../../theme';
import { Vide } from '../../composants/Vide';

const TYPES: Record<string, string> = {
  running: 'Running',
  velo: 'Vélo',
  muscu: 'Muscu',
  autre: 'Autre',
};

const NIVEAUX: Record<string, string> = {
  tous: 'Tous',
  debutant: 'Débutant',
  inter: 'Inter.',
  avance: 'Avancé',
};

function dateFr(iso: string): string {
  const d = new Date(iso.replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return '';
  const jours = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
  return `${jours[d.getDay()]} ${d.getDate()}/${d.getMonth() + 1} · ${d.getHours()}h${String(d.getMinutes()).padStart(2, '0')}`;
}

function CarteSquad({ squad: s, surAction }: { squad: Squad; surAction: () => void }) {
  const jeton = useJeton();
  const { c, ombre } = useTheme();
  const [enCours, setEnCours] = useState(false);

  async function basculer() {
    if (enCours) return;
    setEnCours(true);
    try {
      if (s.est_createur) {
        // Supprimer sa propre squad retire tout le monde : on confirme.
        Alert.alert('Supprimer ce squad ?', 'Les membres en seront retirés.', [
          { text: 'Annuler', style: 'cancel', onPress: () => setEnCours(false) },
          {
            text: 'Supprimer',
            style: 'destructive',
            onPress: async () => {
              try {
                await actions.supprimerSquad(jeton, s.id);
                surAction();
              } catch (e) {
                Alert.alert('Impossible', e instanceof ErreurApi ? e.message : 'Réessaie.');
              } finally {
                setEnCours(false);
              }
            },
          },
        ]);
        return;
      }

      if (s.deja_membre) await actions.quitterSquad(jeton, s.id);
      else await actions.rejoindreSquad(jeton, s.id);
      surAction();
    } catch (e) {
      Alert.alert('Impossible', e instanceof ErreurApi ? e.message : 'Réessaie.');
    } finally {
      if (!s.est_createur) setEnCours(false);
    }
  }

  const libelle = s.est_createur
    ? 'Supprimer'
    : s.deja_membre
      ? 'Quitter'
      : s.places.complet
        ? 'Complet'
        : 'Rejoindre';

  const desactive = enCours || (!s.deja_membre && !s.est_createur && s.places.complet);

  return (
    <View style={[st.carte, { backgroundColor: c.blanc, borderColor: c.grisClair }, ombre('sm')]}>
      <View style={st.enTete}>
        <Text style={[st.surtitre, { color: c.surBleuClair }]}>
          {(TYPES[s.type] ?? s.type).toUpperCase()} · {dateFr(s.date_heure)}
        </Text>
        <View style={[st.badge, { backgroundColor: c.bleuClair }]}>
          <Text style={[st.badgeTexte, { color: c.surBleuClair }]}>
            {NIVEAUX[s.niveau] ?? s.niveau}
          </Text>
        </View>
      </View>

      <Text style={[st.titre, { color: c.noir }]}>{s.titre}</Text>

      {s.lieu !== '' && (
        <View style={st.ligne}>
          <Feather name="map-pin" size={13} color={c.gris} />
          <Text style={[st.meta, { color: c.gris }]}>{s.lieu}</Text>
        </View>
      )}

      {s.description !== '' && (
        <Text style={[st.description, { color: c.grisFonce }]} numberOfLines={2}>
          {s.description}
        </Text>
      )}

      <View style={[st.pied, { borderTopColor: c.grisClair }]}>
        <View style={st.ligne}>
          <Feather name="users" size={14} color={c.noir} />
          <Text style={[st.places, { color: c.noir }]}>
            {s.places.membres}
            {s.places.quota > 0 ? `/${s.places.quota}` : ''}
          </Text>
          <Text style={[st.meta, { color: c.gris }]} numberOfLines={1}>
            {s.membres
              .slice(0, 3)
              .map((m) => m.prenom)
              .join(', ')}
            {s.membres.length > 3 ? ` +${s.membres.length - 3}` : ''}
          </Text>
        </View>

        <Pressable
          onPress={basculer}
          disabled={desactive}
          style={({ pressed }) => [
            st.bouton,
            {
              backgroundColor: s.est_createur
                ? c.dangerClair
                : s.deja_membre
                  ? c.surface2
                  : c.noir,
              borderColor: s.deja_membre && !s.est_createur ? c.grisClair : 'transparent',
              borderWidth: s.deja_membre && !s.est_createur ? 1 : 0,
              opacity: desactive ? 0.5 : pressed ? 0.85 : 1,
            },
          ]}
        >
          {enCours ? (
            <ActivityIndicator size="small" color={s.est_createur ? c.danger : c.bg} />
          ) : (
            <Text
              style={{
                color: s.est_createur ? c.danger : s.deja_membre ? c.grisFonce : c.bg,
                fontSize: taille.base,
                fontWeight: '700',
              }}
            >
              {libelle}
            </Text>
          )}
        </Pressable>
      </View>
    </View>
  );
}

export default function Squads() {
  const jeton = useJeton();
  const { c } = useTheme();
  const marges = useSafeAreaInsets();

  const { donnees, chargement, rafraichit, erreur, recharger, rafraichir } = useChargement(
    useCallback(() => api.squads(jeton), [jeton]),
  );

  return (
    <View style={{ flex: 1, backgroundColor: c.bg, paddingTop: marges.top }}>
      <View style={st.entete}>
        <Text style={[st.marque, { color: c.noir }]}>
          StudentLink <Text style={{ color: c.rouge, fontStyle: 'italic' }}>/ Squads</Text>
        </Text>
      </View>

      <Text style={[st.hero, { color: c.noir }]}>
        Ne cours plus{'\n'}
        <Text style={{ fontStyle: 'italic' }}>tout seul.</Text>
      </Text>

      {chargement ? (
        <View style={st.centre}>
          <ActivityIndicator color={c.rouge} />
        </View>
      ) : erreur !== null ? (
        <Vide
          icone="wifi-off"
          titre="Rien n'arrive"
          texte={erreur}
          action={{ libelle: 'Réessayer', onPress: recharger }}
        />
      ) : (
        <FlatList
          data={donnees?.squads ?? []}
          keyExtractor={(s) => String(s.id)}
          renderItem={({ item }) => <CarteSquad squad={item} surAction={rafraichir} />}
          contentContainerStyle={{ paddingHorizontal: espace.lg, paddingBottom: espace.xxl }}
          ListEmptyComponent={
            <Vide
              icone="users"
              titre="Pas encore de squads"
              texte="Aucune sortie prévue pour le moment. Crée la première depuis le site."
            />
          }
          refreshControl={
            <RefreshControl refreshing={rafraichit} onRefresh={rafraichir} tintColor={c.rouge} />
          }
        />
      )}
    </View>
  );
}

const st = StyleSheet.create({
  entete: { paddingHorizontal: espace.lg, paddingTop: espace.base },
  marque: { fontSize: taille.titre, fontWeight: '700' },
  hero: {
    paddingHorizontal: espace.lg,
    fontSize: taille.grand,
    fontWeight: '900',
    lineHeight: 32,
    marginTop: espace.lg,
    marginBottom: espace.md,
  },
  centre: { flex: 1, alignItems: 'center', justifyContent: 'center' },

  carte: { borderWidth: 1, borderRadius: rayon.base, padding: espace.md, marginBottom: espace.base },
  enTete: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  surtitre: { fontSize: taille.xs, fontWeight: '700', letterSpacing: 0.5, flex: 1 },
  badge: { borderRadius: rayon.pill, paddingHorizontal: espace.sm, paddingVertical: 2 },
  badgeTexte: { fontSize: 10, fontWeight: '900' },
  titre: { fontSize: taille.titre, fontWeight: '900', marginTop: espace.sm },
  ligne: { flexDirection: 'row', alignItems: 'center', gap: espace.xs },
  meta: { fontSize: taille.base, flexShrink: 1 },
  description: { fontSize: taille.texte, marginTop: espace.sm, lineHeight: 19 },
  pied: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: espace.base,
    borderTopWidth: 1,
    marginTop: espace.base,
    paddingTop: espace.base,
  },
  places: { fontSize: taille.base, fontWeight: '700' },
  bouton: {
    borderRadius: rayon.bouton,
    paddingHorizontal: espace.md,
    paddingVertical: espace.sm,
    minWidth: 92,
    minHeight: 34,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
