/**
 * Le hub — transposition d'explore.php.
 *
 * Reprend l'ecran web element par element : le selecteur ÉVÉNEMENTS /
 * PERSONNES, la cloche de notifications, le titre editorial, les deux rangees
 * de filtres (type puis musique), et le fil de cartes.
 */

import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import Feather from '@expo/vector-icons/Feather';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { api, type Evenement } from '../../api';
import { useJeton } from '../../session';
import { useChargement } from '../../useChargement';
import { useTheme } from '../../useTheme';
import { espace, taille } from '../../theme';
import { CarteEvenement } from '../../composants/CarteEvenement';
import { RangeeFiltres, SelecteurSegmente } from '../../composants/Filtres';
import { Vide } from '../../composants/Vide';

/** Les filtres de type, dans l'ordre du web. */
const TYPES = [
  { code: 'all', libelle: 'Tout' },
  { code: 'pour-moi', libelle: 'Pour moi' },
  { code: 'bar', libelle: 'Bars' },
  { code: 'boite', libelle: 'Boîtes' },
  { code: 'resto', libelle: 'Restos' },
];

const VUES = [
  { code: 'events' as const, libelle: 'ÉVÉNEMENTS' },
  { code: 'people' as const, libelle: 'PERSONNES' },
];

export default function Hub() {
  const jeton = useJeton();
  const { c } = useTheme();
  const marges = useSafeAreaInsets();

  const [vue, setVue] = useState<'events' | 'people'>('events');
  const [type, setType] = useState<string>('all');
  const [musique, setMusique] = useState<string>('');

  const { donnees, chargement, rafraichit, erreur, recharger, rafraichir } = useChargement(
    useCallback(
      () => api.evenements(jeton, { type, musique: musique || undefined }),
      [jeton, type, musique],
    ),
  );

  const evenements = donnees?.evenements ?? [];

  // « Toute musique » en tête, puis le catalogue rendu par l'API — qui le tient
  // de musique.php, la même source que le web.
  const stylesMusique = [
    { code: '', libelle: 'Toute musique' },
    ...(donnees?.filtres.styles_musique ?? []),
  ];

  return (
    <View style={{ flex: 1, backgroundColor: c.bg, paddingTop: marges.top }}>
      {/* En-tête : marque et cloche */}
      <View style={s.entete}>
        <Text style={[s.marque, { color: c.noir }]}>
          StudentLink <Text style={{ color: c.rouge, fontStyle: 'italic' }}>/ Hub</Text>
        </Text>

        <Pressable style={[s.cloche, { borderColor: c.grisClair, backgroundColor: c.blanc }]}>
          <Feather name="bell" size={18} color={c.noir} />
        </Pressable>
      </View>

      <View style={s.segmenteBloc}>
        <SelecteurSegmente options={VUES} valeur={vue} surChangement={setVue} />
      </View>

      {vue === 'people' ? (
        <Vide
          icone="users"
          titre="Trouve tes futurs potes"
          texte="L'annuaire arrive : il attend son point d'API. Il reste accessible depuis le site."
        />
      ) : (
        <>
          <Text style={[s.titre, { color: c.noir }]}>
            Les bons plans{'\n'}
            <Text style={{ fontStyle: 'italic' }}>du moment.</Text>
          </Text>

          <View style={s.filtres}>
            <RangeeFiltres options={TYPES} valeur={type} surChangement={setType} />
          </View>
          <View style={s.filtres}>
            <RangeeFiltres
              options={stylesMusique}
              valeur={musique}
              surChangement={setMusique}
              variante="musique"
            />
          </View>

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
              keyExtractor={(e: Evenement) => String(e.id)}
              renderItem={({ item }) => (
                <CarteEvenement evenement={item} surAction={rafraichir} surInviter={() => {}} />
              )}
              contentContainerStyle={{
                paddingHorizontal: espace.lg,
                paddingTop: espace.md,
                paddingBottom: espace.xxl,
              }}
              ListEmptyComponent={
                <Vide
                  icone="calendar"
                  titre="Aucune soirée"
                  texte={
                    type === 'pour-moi'
                      ? 'Suis des lieux et des étudiants pour voir leurs soirées ici.'
                      : musique !== ''
                        ? 'Aucune soirée avec ce style de musique.'
                        : 'Rien de prévu pour le moment. Reviens bientôt.'
                  }
                />
              }
              refreshControl={
                <RefreshControl refreshing={rafraichit} onRefresh={rafraichir} tintColor={c.rouge} />
              }
            />
          )}
        </>
      )}
    </View>
  );
}

const s = StyleSheet.create({
  entete: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: espace.lg,
    paddingTop: espace.base,
  },
  marque: { fontSize: taille.titre, fontWeight: '700' },
  cloche: {
    width: 40,
    height: 40,
    borderRadius: 20,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  segmenteBloc: { marginTop: espace.md },
  titre: {
    paddingHorizontal: espace.lg,
    fontSize: taille.grand,
    fontWeight: '900',
    lineHeight: 32,
    marginTop: espace.lg,
    marginBottom: espace.md,
  },
  // Chaque rangée dans son propre conteneur de hauteur libre : c'est ce qui
  // empêche la FlatList de les comprimer.
  filtres: { marginBottom: espace.sm },
  centre: { flex: 1, alignItems: 'center', justifyContent: 'center' },
});
