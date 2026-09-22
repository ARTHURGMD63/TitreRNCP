/**
 * Explore — le fil des soirees.
 *
 * Reprend la carte du web : etiquette de type et date, titre, lieu, « X et Y
 * y vont », places restantes et taux d'inscription.
 */

import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { api } from '../../api';
import { useJeton } from '../../session';
import { useChargement } from '../../useChargement';
import { useTheme } from '../../useTheme';
import { espace, rayon, taille } from '../../theme';
import { CarteEvenement } from '../../composants/CarteEvenement';
import { Vide } from '../../composants/Vide';

/** Les filtres de type, dans l'ordre du web. */
const FILTRES = [
  { code: 'all', libelle: 'Tout' },
  { code: 'pour-moi', libelle: 'Pour moi' },
  { code: 'bar', libelle: 'Bars' },
  { code: 'boite', libelle: 'Boîtes' },
  { code: 'resto', libelle: 'Restos' },
] as const;

export default function Explore() {
  const jeton = useJeton();
  const { c } = useTheme();
  const marges = useSafeAreaInsets();

  const [filtre, setFiltre] = useState<string>('all');

  const { donnees, chargement, rafraichit, erreur, recharger, rafraichir } =
    useChargement(useCallback(() => api.evenements(jeton, { type: filtre }), [jeton, filtre]));

  const evenements = donnees?.evenements ?? [];

  return (
    <View style={{ flex: 1, backgroundColor: c.bg, paddingTop: marges.top }}>
      <View style={s.entete}>
        <Text style={[s.marque, { color: c.noir }]}>
          StudentLink <Text style={{ color: c.rouge, fontStyle: 'italic' }}>/ Hub</Text>
        </Text>
      </View>

      <Text style={[s.titre, { color: c.noir }]}>
        Les bons plans{'\n'}
        <Text style={{ fontStyle: 'italic' }}>du moment.</Text>
      </Text>

      {/* Les filtres defilent horizontalement : cinq pastilles ne tiennent pas
          sur la largeur d'un telephone, et les empiler mangerait l'ecran avant
          la premiere carte. */}
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={s.filtres}
      >
        {FILTRES.map((f) => {
          const actif = filtre === f.code;
          return (
            <Pressable
              key={f.code}
              onPress={() => setFiltre(f.code)}
              style={[
                s.pastille,
                {
                  backgroundColor: actif ? c.noir : c.blanc,
                  borderColor: actif ? c.noir : c.grisClair,
                },
              ]}
            >
              <Text
                style={{
                  color: actif ? c.bg : c.grisFonce,
                  fontSize: taille.base,
                  fontWeight: '700',
                }}
              >
                {f.libelle}
              </Text>
            </Pressable>
          );
        })}
      </ScrollView>

      {chargement ? (
        <View style={s.centre}>
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
          data={evenements}
          keyExtractor={(e) => String(e.id)}
          renderItem={({ item }) => <CarteEvenement evenement={item} />}
          contentContainerStyle={{
            paddingHorizontal: espace.lg,
            paddingBottom: espace.xxl,
          }}
          ListEmptyComponent={
            <Vide
              icone="calendar"
              titre="Aucune soirée"
              texte={
                filtre === 'pour-moi'
                  ? "Suis des lieux et des étudiants pour voir leurs soirées ici."
                  : 'Rien de prévu pour le moment. Reviens bientôt.'
              }
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

const s = StyleSheet.create({
  entete: { paddingHorizontal: espace.lg, paddingTop: espace.base },
  marque: { fontSize: taille.titre, fontWeight: '700' },
  titre: {
    paddingHorizontal: espace.lg,
    fontSize: taille.grand,
    fontWeight: '900',
    lineHeight: 32,
    marginTop: espace.lg,
    marginBottom: espace.md,
  },
  filtres: { paddingHorizontal: espace.lg, gap: espace.sm, paddingBottom: espace.md },
  pastille: {
    borderWidth: 1,
    borderRadius: rayon.pill,
    paddingHorizontal: espace.md,
    paddingVertical: espace.sm,
  },
  centre: { flex: 1, alignItems: 'center', justifyContent: 'center' },
});
