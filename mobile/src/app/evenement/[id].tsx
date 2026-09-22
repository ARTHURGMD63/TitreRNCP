/**
 * La fiche d'une soiree — transposition de view_event.php.
 *
 * Le hero porte la photo du lieu quand elle existe, l'aplat de couleur n'etant
 * que le repli — comme sur le web, ou le texte reste lisible grace au voile
 * degrade et jamais grace a un assombrissement global de l'image.
 */

import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import Feather from '@expo/vector-icons/Feather';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { actions, api, ErreurApi } from '../../api';
import { useJeton } from '../../session';
import { useChargement } from '../../useChargement';
import { useTheme } from '../../useTheme';
import { espace, fixe, rayon, taille } from '../../theme';
import { Vide } from '../../composants/Vide';

function dateComplete(iso: string): string {
  const d = new Date(iso.replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return '';

  const jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
  const mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

  return `${jours[d.getDay()]} ${d.getDate()} ${mois[d.getMonth()]} · ${d.getHours()}h${String(d.getMinutes()).padStart(2, '0')}`;
}

export default function FicheEvenement() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const jeton = useJeton();
  const { c, ombre } = useTheme();
  const marges = useSafeAreaInsets();
  const router = useRouter();

  const [enCours, setEnCours] = useState(false);

  const { donnees, chargement, erreur, rafraichir } = useChargement(
    useCallback(() => api.evenement(jeton, Number(id)), [jeton, id]),
  );

  const e = donnees?.evenement;

  async function sInscrire() {
    if (!e || enCours) return;
    setEnCours(true);
    try {
      if (e.deja_inscrit) {
        Alert.alert('Déjà inscrit', 'Retrouve ton pass dans le Wallet.');
      } else {
        await actions.inscrire(jeton, e.id);
        rafraichir();
      }
    } catch (err) {
      Alert.alert('Impossible', err instanceof ErreurApi ? err.message : 'Réessaie.');
    } finally {
      setEnCours(false);
    }
  }

  if (chargement) {
    return (
      <View style={[s.centre, { backgroundColor: c.bg }]}>
        <ActivityIndicator color={c.rouge} />
      </View>
    );
  }

  if (erreur !== null || !e) {
    return (
      <View style={{ flex: 1, backgroundColor: c.bg, paddingTop: marges.top }}>
        <Vide
          icone="alert-circle"
          titre="Soirée introuvable"
          texte={erreur ?? "Cette soirée n'existe plus."}
          action={{ libelle: 'Retour', onPress: () => router.back() }}
        />
      </View>
    );
  }

  const photo = e.photos[0]?.url ?? null;
  const couleurHero = e.is_flash ? c.rouge : c.bleu;

  return (
    <View style={{ flex: 1, backgroundColor: c.bg }}>
      <ScrollView contentContainerStyle={{ paddingBottom: marges.bottom + 100 }}>
        {/* Hero : photo du lieu, ou aplat de marque en repli */}
        <View style={[s.hero, { backgroundColor: couleurHero }]}>
          {photo !== null && (
            <Image source={{ uri: photo }} style={s.heroPhoto} resizeMode="cover" />
          )}
          {/* Le voile ne s'applique qu'au bas du hero : le texte reste lisible
              sans assombrir toute la photo. */}
          <View style={s.voile} />

          <Pressable
            onPress={() => router.back()}
            style={[s.retour, { top: marges.top + espace.sm }]}
          >
            <Feather name="arrow-left" size={20} color={fixe.surMedia} />
          </Pressable>

          <View style={s.heroTexte}>
            {e.is_flash && (
              <View style={[s.badgeFlash, { backgroundColor: fixe.surMedia }]}>
                <Text style={{ color: c.rouge, fontSize: taille.xs, fontWeight: '900' }}>
                  FLASH
                </Text>
              </View>
            )}
            <Text style={[s.heroTitre, { color: fixe.surMedia }]}>{e.titre}</Text>
            <Text style={[s.heroLieu, { color: fixe.surMedia }]}>
              {e.etablissement.nom} · {e.etablissement.ville}
            </Text>
          </View>
        </View>

        <View style={s.corps}>
          {/* Date et adresse */}
          <View style={[s.bloc, { backgroundColor: c.blanc, borderColor: c.grisClair }, ombre('sm')]}>
            <View style={s.ligne}>
              <Feather name="calendar" size={16} color={c.gris} />
              <Text style={[s.ligneTexte, { color: c.noir }]}>{dateComplete(e.date_heure)}</Text>
            </View>
            {e.etablissement.adresse !== '' && (
              <View style={[s.ligne, { marginTop: espace.base }]}>
                <Feather name="map-pin" size={16} color={c.gris} />
                <Text style={[s.ligneTexte, { color: c.noir }]}>{e.etablissement.adresse}</Text>
              </View>
            )}
            {e.reduction !== null && e.reduction > 0 && (
              <View style={[s.ligne, { marginTop: espace.base }]}>
                <Feather name="tag" size={16} color={c.rouge} />
                <Text style={[s.ligneTexte, { color: c.rouge, fontWeight: '900' }]}>
                  −{e.reduction}% sur place
                  {e.prix_normal !== null
                    ? ` · au lieu de ${e.prix_normal.toFixed(2).replace('.', ',')} €`
                    : ''}
                </Text>
              </View>
            )}
            {e.is_gratuit && (
              <View style={[s.ligne, { marginTop: espace.base }]}>
                <Feather name="gift" size={16} color={c.succes} />
                <Text style={[s.ligneTexte, { color: c.succes, fontWeight: '900' }]}>
                  Entrée gratuite
                </Text>
              </View>
            )}
          </View>

          {/* Description */}
          {e.description !== '' && (
            <Text style={[s.description, { color: c.grisFonce }]}>{e.description}</Text>
          )}

          {/* Amis qui y vont */}
          {e.amis.length > 0 && (
            <View style={s.section}>
              <Text style={[s.sectionTitre, { color: c.noir }]}>
                {e.amis.length} personne{e.amis.length > 1 ? 's' : ''} que tu suis y {e.amis.length > 1 ? 'vont' : 'va'}
              </Text>
              <View style={s.amis}>
                {e.amis.map((a) => (
                  <View key={a.id} style={s.ami}>
                    {a.photo_url !== null ? (
                      <Image source={{ uri: a.photo_url }} style={s.amiPhoto} />
                    ) : (
                      <View style={[s.amiPhoto, s.amiInitiale, { backgroundColor: c.bleu }]}>
                        <Text style={{ color: fixe.surMedia, fontWeight: '900' }}>
                          {a.prenom.charAt(0).toUpperCase()}
                        </Text>
                      </View>
                    )}
                    <Text style={[s.amiPrenom, { color: c.grisFonce }]} numberOfLines={1}>
                      {a.prenom}
                    </Text>
                  </View>
                ))}
              </View>
            </View>
          )}

          {/* Places */}
          <View style={s.section}>
            <View style={s.ligneTaux}>
              <Text style={[s.tauxLabel, { color: c.noir }]}>
                {e.places.inscrits}/{e.places.quota} places
              </Text>
              {e.places.pourcentage !== null && (
                <Text style={[s.tauxLabel, { color: c.noir }]}>{e.places.pourcentage}%</Text>
              )}
            </View>
            <View style={[s.jauge, { backgroundColor: c.grisClair }]}>
              <View
                style={[
                  s.jaugeRemplie,
                  {
                    width: `${Math.min(100, e.places.pourcentage ?? 0)}%`,
                    backgroundColor: e.places.complet ? c.danger : c.rouge,
                  },
                ]}
              />
            </View>
          </View>

          {/* Autres photos du lieu */}
          {e.photos.length > 1 && (
            <View style={s.section}>
              <Text style={[s.sectionTitre, { color: c.noir }]}>Le lieu</Text>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} style={s.galerie}>
                {e.photos.slice(1).map((p, i) => (
                  <Image key={i} source={{ uri: p.url }} style={s.galeriePhoto} />
                ))}
              </ScrollView>
            </View>
          )}
        </View>
      </ScrollView>

      {/* Barre d'action, fixée en bas — comme le bouton du web */}
      <View
        style={[
          s.barre,
          {
            backgroundColor: c.blanc,
            borderTopColor: c.grisClair,
            paddingBottom: marges.bottom + espace.base,
          },
        ]}
      >
        <Pressable
          onPress={sInscrire}
          disabled={enCours || (e.places.complet && !e.deja_inscrit)}
          style={({ pressed }) => [
            s.boutonPrincipal,
            {
              backgroundColor: e.deja_inscrit ? c.succesClair : c.noir,
              opacity: e.places.complet && !e.deja_inscrit ? 0.5 : pressed ? 0.85 : 1,
            },
          ]}
        >
          {enCours ? (
            <ActivityIndicator color={c.bg} />
          ) : (
            <Text
              style={{
                color: e.deja_inscrit ? c.succes : c.bg,
                fontSize: taille.texte,
                fontWeight: '700',
              }}
            >
              {e.deja_inscrit
                ? '✓ Tu es inscrit — pass dans le Wallet'
                : e.places.complet
                  ? 'Complet'
                  : "S'inscrire et obtenir mon pass"}
            </Text>
          )}
        </Pressable>
      </View>
    </View>
  );
}

const s = StyleSheet.create({
  centre: { flex: 1, alignItems: 'center', justifyContent: 'center' },

  hero: { height: 260, justifyContent: 'flex-end', overflow: 'hidden' },
  heroPhoto: { ...StyleSheet.absoluteFill, width: '100%', height: '100%' },
  voile: {
    ...StyleSheet.absoluteFill,
    backgroundColor: 'rgba(0,0,0,0.35)',
  },
  retour: {
    position: 'absolute',
    left: espace.md,
    width: 38,
    height: 38,
    borderRadius: 19,
    backgroundColor: 'rgba(0,0,0,0.35)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  heroTexte: { padding: espace.lg },
  badgeFlash: {
    alignSelf: 'flex-start',
    borderRadius: rayon.pill,
    paddingHorizontal: espace.sm,
    paddingVertical: 3,
    marginBottom: espace.sm,
  },
  heroTitre: { fontSize: taille.hero, fontWeight: '900', lineHeight: 38 },
  heroLieu: { fontSize: taille.texte, marginTop: espace.xs, opacity: 0.9 },

  corps: { padding: espace.lg },
  bloc: { borderWidth: 1, borderRadius: rayon.base, padding: espace.md },
  ligne: { flexDirection: 'row', alignItems: 'center', gap: espace.sm },
  ligneTexte: { fontSize: taille.texte, flexShrink: 1 },

  description: { fontSize: taille.texte, lineHeight: 22, marginTop: espace.lg },

  section: { marginTop: espace.xl },
  sectionTitre: { fontSize: taille.corps, fontWeight: '900', marginBottom: espace.base },

  amis: { flexDirection: 'row', flexWrap: 'wrap', gap: espace.md },
  ami: { alignItems: 'center', width: 64 },
  amiPhoto: { width: 48, height: 48, borderRadius: 24 },
  amiInitiale: { alignItems: 'center', justifyContent: 'center' },
  amiPrenom: { fontSize: taille.sm, marginTop: espace.xs },

  ligneTaux: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: espace.sm },
  tauxLabel: { fontSize: taille.base, fontWeight: '700' },
  jauge: { height: 6, borderRadius: 3, overflow: 'hidden' },
  jaugeRemplie: { height: '100%', borderRadius: 3 },

  galerie: { flexGrow: 0 },
  galeriePhoto: { width: 140, height: 100, borderRadius: rayon.sm, marginRight: espace.sm },

  barre: {
    borderTopWidth: 1,
    paddingHorizontal: espace.lg,
    paddingTop: espace.base,
  },
  boutonPrincipal: {
    borderRadius: rayon.bouton,
    paddingVertical: espace.md,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 52,
  },
});
