/**
 * Carte interactive des soirées — nouveauté propre à l'application, sans
 * équivalent web : explore.php liste les événements, ici on les situe.
 *
 * Une épingle par établissement qui a une position connue (réglée par le
 * partenaire dans son profil, ou déduite de son adresse — voir
 * includes/geocodage.php) : un établissement qui n'a jamais rien réglé
 * reste listé normalement dans Explorer, simplement absent d'ici. Taper une
 * épingle ouvre un résumé, puis la fiche complète.
 *
 * L'épingle est dessinée à la main (rond + pointe), pas le pin par défaut du
 * système — trop générique pour porter la charte. Le point bleu « où je
 * suis » vient d'expo-location, demandé une seule fois à l'ouverture.
 */

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Animated, Pressable, Text, View } from 'react-native';
import MapView, { Marker, PROVIDER_DEFAULT } from 'react-native-maps';
import * as Location from 'expo-location';
import { router, useFocusEffect } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { api, type Evenement } from '../../api';
import { reserveBarre } from '../../composants/BarreOnglets';
import { BoutonRetour } from '../../composants/Elements';
import { Icone } from '../../composants/Icone';
import { Display, Mono, T } from '../../composants/Texte';
import { dateFr } from '../../format';
import { useJeton } from '../../session';
import { fixe, fs, lh, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

// Clermont-Ferrand, cœur de la charte : la carte s'ouvre là par défaut, avant
// même d'avoir chargé la moindre soirée ou localisé qui que ce soit.
const REGION_DEFAUT = { latitude: 45.7772, longitude: 3.087, latitudeDelta: 0.08, longitudeDelta: 0.08 };

/** L'épingle : un rond de couleur avec sa pointe, pas le pin système. */
function Epingle({ enCours, actif }: { enCours: boolean; actif: boolean }) {
  const { c } = useTheme();
  const [opacite] = useState(() => new Animated.Value(1));

  useEffect(() => {
    if (!enCours) return;
    const boucle = Animated.loop(
      Animated.sequence([
        Animated.timing(opacite, { toValue: 0.35, duration: 700, useNativeDriver: true }),
        Animated.timing(opacite, { toValue: 1, duration: 700, useNativeDriver: true }),
      ])
    );
    boucle.start();
    return () => boucle.stop();
  }, [enCours, opacite]);

  const teinte = enCours ? c.lime : c.rouge;
  const taille = actif ? 40 : 32;

  return (
    <View style={{ alignItems: 'center' }}>
      {enCours ? (
        <Animated.View
          style={{
            position: 'absolute', top: -4, width: taille + 8, height: taille + 8, borderRadius: (taille + 8) / 2,
            backgroundColor: teinte, opacity: Animated.multiply(opacite, 0.35),
          }}
        />
      ) : null}
      <View
        style={{
          width: taille, height: taille, borderRadius: taille / 2, backgroundColor: teinte,
          borderWidth: 3, borderColor: fixe.craie, alignItems: 'center', justifyContent: 'center',
          shadowColor: '#000', shadowOpacity: 0.25, shadowRadius: 4, shadowOffset: { width: 0, height: 2 }, elevation: 4,
        }}
      >
        <Icone nom="epingle" taille={actif ? 18 : 14} couleur={enCours ? fixe.basalte : fixe.surLave} />
      </View>
      {/* La pointe : un petit losange à moitié caché derrière le rond. */}
      <View
        style={{
          width: 10, height: 10, backgroundColor: teinte, borderWidth: 3, borderColor: fixe.craie,
          transform: [{ rotate: '45deg' }], marginTop: -8, borderTopWidth: 0, borderLeftWidth: 0,
        }}
      />
    </View>
  );
}

export default function CarteEvenements() {
  const { c } = useTheme();
  const jeton = useJeton();
  const { top, bottom } = useSafeAreaInsets();
  const carteRef = useRef<MapView>(null);

  const [evenements, setEvenements] = useState<Evenement[] | null>(null);
  const [selection, setSelection] = useState<Evenement | null>(null);
  const [autorisationPosition, setAutorisationPosition] = useState<'inconnue' | 'accordee' | 'refusee'>('inconnue');

  const charger = useCallback(async () => {
    try {
      const r = await api.evenements(jeton, { type: 'all' });
      setEvenements(r.evenements);
    } catch {
      setEvenements([]);
    }
  }, [jeton]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  const localisables = useMemo(
    () => (evenements ?? []).filter((e) => e.etablissement.latitude !== null && e.etablissement.longitude !== null),
    [evenements]
  );

  const region = useMemo(() => {
    if (localisables.length === 0) return REGION_DEFAUT;
    const lats = localisables.map((e) => e.etablissement.latitude as number);
    const lons = localisables.map((e) => e.etablissement.longitude as number);
    const min = (l: number[]) => Math.min(...l);
    const max = (l: number[]) => Math.max(...l);
    return {
      latitude: (min(lats) + max(lats)) / 2,
      longitude: (min(lons) + max(lons)) / 2,
      latitudeDelta: Math.max(0.05, (max(lats) - min(lats)) * 1.8),
      longitudeDelta: Math.max(0.05, (max(lons) - min(lons)) * 1.8),
    };
  }, [localisables]);

  async function meLocaliser() {
    const { status } = await Location.requestForegroundPermissionsAsync();
    if (status !== 'granted') {
      setAutorisationPosition('refusee');
      return;
    }
    setAutorisationPosition('accordee');
    const position = await Location.getCurrentPositionAsync({});
    carteRef.current?.animateToRegion({
      latitude: position.coords.latitude,
      longitude: position.coords.longitude,
      latitudeDelta: 0.03,
      longitudeDelta: 0.03,
    }, 500);
  }

  // Le point bleu « où je suis » peut s'afficher dès que la permission est
  // déjà là (accordée lors d'une visite précédente), sans attendre un tap.
  useEffect(() => {
    Location.getForegroundPermissionsAsync().then(({ status }) => {
      if (status === 'granted') setAutorisationPosition('accordee');
    });
  }, []);

  // Réserve la hauteur de la barre flottante (voir BarreOnglets.tsx) : sans
  // elle, la carte de résumé passait derrière l'onglet actif au lieu de
  // s'arrêter au-dessus.
  const espaceBarre = reserveBarre(bottom);

  return (
    <View style={{ flex: 1, backgroundColor: c.bg }}>
      <MapView
        ref={carteRef}
        provider={PROVIDER_DEFAULT}
        style={{ flex: 1 }}
        initialRegion={REGION_DEFAUT}
        region={evenements === null ? undefined : region}
        showsUserLocation={autorisationPosition === 'accordee'}
        showsMyLocationButton={false}
        mapPadding={{ top: 0, right: 0, bottom: espaceBarre, left: 0 }}
      >
        {localisables.map((e) => (
          <Marker
            key={e.id}
            coordinate={{ latitude: e.etablissement.latitude as number, longitude: e.etablissement.longitude as number }}
            anchor={{ x: 0.5, y: 1 }}
            onPress={() => setSelection(e)}
            tracksViewChanges={false}
          >
            <Epingle enCours={e.en_cours} actif={selection?.id === e.id} />
          </Marker>
        ))}
      </MapView>

      <View pointerEvents="box-none" style={{ position: 'absolute', top: top + 16, left: 20, right: 20, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
        <BoutonRetour media />
        <View style={{ backgroundColor: fixe.basalte, borderRadius: rayon.pill, paddingVertical: 8, paddingHorizontal: 16 }}>
          <T taille={fs[3]} poids={700} couleur={fixe.craie}>
            {evenements === null ? 'Chargement…' : `${localisables.length} soirée${localisables.length > 1 ? 's' : ''} sur la carte`}
          </T>
        </View>
      </View>

      {/* Bouton « me localiser », posé juste au-dessus de la réserve de la
          barre du bas, jamais dessous. */}
      <Pressable
        onPress={meLocaliser}
        accessibilityRole="button"
        accessibilityLabel="Me localiser"
        style={[
          { position: 'absolute', right: 16, bottom: espaceBarre + (selection ? 132 : 16), width: 44, height: 44, borderRadius: 22, backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, alignItems: 'center', justifyContent: 'center' },
        ]}
      >
        <Icone nom={autorisationPosition === 'refusee' ? 'interdit' : 'epingle'} taille={20} couleur={autorisationPosition === 'refusee' ? c.gris : c.noir} />
      </Pressable>

      {/* La légende : deux points, pour comprendre les couleurs d'un coup d'œil. */}
      <View
        pointerEvents="none"
        style={{ position: 'absolute', left: 16, bottom: espaceBarre + (selection ? 132 : 16), backgroundColor: c.blanc, borderRadius: rayon.md, borderWidth: 1, borderColor: c.grisClair, paddingVertical: 8, paddingHorizontal: 12, gap: 4 }}
      >
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
          <View style={{ width: 10, height: 10, borderRadius: 5, backgroundColor: c.rouge }} />
          <T taille={fs[1]} couleur={c.grisFonce}>À venir</T>
        </View>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
          <View style={{ width: 10, height: 10, borderRadius: 5, backgroundColor: c.lime }} />
          <T taille={fs[1]} couleur={c.grisFonce}>En cours</T>
        </View>
      </View>

      {selection ? (
        <Pressable
          onPress={() => router.push({ pathname: '/evenement/[id]', params: { id: String(selection.id) } })}
          style={{ position: 'absolute', left: 16, right: 16, bottom: espaceBarre + 16, backgroundColor: c.blanc, borderRadius: rayon.base, padding: 16, flexDirection: 'row', alignItems: 'center', gap: 14, borderWidth: 1, borderColor: c.grisClair, shadowColor: '#000', shadowOpacity: 0.15, shadowRadius: 8, shadowOffset: { width: 0, height: 2 }, elevation: 6 }}
        >
          <View style={{ flex: 1, minWidth: 0 }}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 4 }}>
              {selection.en_cours ? (
                <View style={{ paddingVertical: 2, paddingHorizontal: 8, borderRadius: rayon.pill, backgroundColor: c.lime }}>
                  <Text style={{ fontFamily: sans(700), fontSize: fs[1], color: fixe.basalte, textTransform: 'uppercase' }}>En cours</Text>
                </View>
              ) : null}
              <Mono couleur={c.gris}>{dateFr(selection.date_heure, 'D j M · H\\hi')}</Mono>
            </View>
            <Display taille={fs[5]} interligne={lh.tight}>{selection.titre}</Display>
            <T taille={fs[3]} couleur={c.grisFonce}>{selection.etablissement.nom}</T>
          </View>
          <Pressable onPress={() => setSelection(null)} hitSlop={10} accessibilityLabel="Fermer">
            <Icone nom="croix" taille={18} couleur={c.gris} />
          </Pressable>
        </Pressable>
      ) : null}
    </View>
  );
}
