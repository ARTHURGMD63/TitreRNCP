/**
 * Les filtres de l'onglet Sport : le niveau (parties à compléter et
 * événements d'étudiants), et depuis
 * le 2026-11-16 la distance autour d'un point posé sur la carte.
 *
 * On touche la carte pour placer l'épingle (ou « Ma position »), on choisit
 * 1, 2, 5, 10, 15 ou 20 km : le cercle montre la zone retenue. Le serveur garde
 * alors les parties, terrains et événements dont le club est dans le rayon
 * (api/v1/sport.php, sqlDansRayon()). Les événements d'étudiants n'ont qu'un
 * lieu en texte libre : ils ne sont pas filtrés, et le panneau le dit.
 *
 * Comme PanneauFiltres, le panneau travaille sur un brouillon : rien ne
 * change à l'écran avant « Appliquer ».
 */

import React, { useRef, useState } from 'react';
import { Text, View } from 'react-native';
import MapView, { Circle, Marker, PROVIDER_DEFAULT, type MapPressEvent } from 'react-native-maps';
import * as Location from 'expo-location';

import { PressableAnime } from './Animations';
import { Feuille } from './Feuille';
import { Icone } from './Icone';
import { T } from './Texte';
import { useToast } from './Toast';
import { fs, rayon, sans } from '../theme';
import { useTheme } from '../useTheme';

export type FiltreDistance = { lat: number; lng: number; rayon: number };

/** Les rayons acceptés par le serveur (SPORT_RAYONS_KM). */
export const RAYONS_KM = [1, 2, 5, 10, 15, 20] as const;

/** Clermont-Ferrand : là où l'épingle tombe si l'on n'a encore rien choisi. */
const CENTRE_DEFAUT = { latitude: 45.7772, longitude: 3.087 };

/** Un écart de latitude qui montre tout le cercle, avec un peu de marge. */
const deltaPour = (km: number) => (km * 2.6) / 111;

function Chip({ libelle, actif, onPress }: { libelle: string; actif: boolean; onPress: () => void }) {
  const { c } = useTheme();
  return (
    <PressableAnime
      onPress={onPress}
      accessibilityRole="radio"
      accessibilityState={{ selected: actif }}
      style={{ minHeight: 44, paddingHorizontal: 16, justifyContent: 'center', borderRadius: rayon.bouton, backgroundColor: actif ? c.noir : c.surface2 }}
    >
      <Text style={{ fontFamily: sans(500), fontSize: fs[5], color: actif ? c.surEncre : c.noir }}>{libelle}</Text>
    </PressableAnime>
  );
}

function Section({ children }: { children: string }) {
  const { c } = useTheme();
  return (
    <T taille={fs[2]} poids={600} couleur={c.gris} style={{ textTransform: 'uppercase', letterSpacing: 0.06 * fs[2], marginBottom: 12 }}>
      {children}
    </T>
  );
}

export function FiltresSport<K extends string>({ visible, onClose, niveaux, niveau, distance, onAppliquer, accent }: {
  visible: boolean;
  onClose: () => void;
  niveaux: { code: K; libelle: string }[];
  niveau: K;
  distance: FiltreDistance | null;
  onAppliquer: (niveau: K, distance: FiltreDistance | null) => void;
  accent: string;
}) {
  const { c } = useTheme();
  const toast = useToast();
  const carte = useRef<MapView>(null);

  const [brouillonNiveau, setBrouillonNiveau] = useState(niveau);
  const [point, setPoint] = useState(distance ? { latitude: distance.lat, longitude: distance.lng } : null);
  const [km, setKm] = useState<number | null>(distance?.rayon ?? null);
  const [localisation, setLocalisation] = useState(false);

  // À chaque ouverture, le brouillon repart des filtres en vigueur.
  const [vu, setVu] = useState(visible);
  if (visible !== vu) {
    setVu(visible);
    if (visible) {
      setBrouillonNiveau(niveau);
      setPoint(distance ? { latitude: distance.lat, longitude: distance.lng } : null);
      setKm(distance?.rayon ?? null);
    }
  }

  function centrer(p: { latitude: number; longitude: number }, rayonKm: number | null) {
    const d = deltaPour(rayonKm ?? 10);
    carte.current?.animateToRegion({ ...p, latitudeDelta: d, longitudeDelta: d }, 400);
  }

  function choisirRayon(r: number | null) {
    setKm(r);
    // Un rayon sans point : l'épingle tombe au centre de la vue par défaut,
    // qu'on déplace ensuite d'un appui.
    if (r !== null && point === null) setPoint(CENTRE_DEFAUT);
    if (r !== null) centrer(point ?? CENTRE_DEFAUT, r);
  }

  function placer(e: MapPressEvent) {
    setPoint(e.nativeEvent.coordinate);
    if (km === null) setKm(10);
  }

  async function maPosition() {
    setLocalisation(true);
    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') {
        toast('Autorise la localisation, ou touche la carte pour placer le point.', 'error');
        return;
      }
      const pos = await Location.getCurrentPositionAsync({});
      const p = { latitude: pos.coords.latitude, longitude: pos.coords.longitude };
      setPoint(p);
      const r = km ?? 10;
      setKm(r);
      centrer(p, r);
    } catch {
      toast('Position introuvable. Touche la carte pour placer le point.', 'error');
    } finally {
      setLocalisation(false);
    }
  }

  const depart = distance ? { latitude: distance.lat, longitude: distance.lng } : CENTRE_DEFAUT;
  const deltaDepart = deltaPour(distance?.rayon ?? 10);
  const actifs = (brouillonNiveau !== niveaux[0]?.code ? 1 : 0) + (km !== null && point !== null ? 1 : 0);

  return (
    <Feuille visible={visible} onClose={onClose}>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 18 }}>
        <T taille={24} poids={700} accessibilityRole="header" style={{ letterSpacing: -0.03 * 24 }}>Filtres</T>
        <PressableAnime onPress={onClose} accessibilityRole="button" accessibilityLabel="Fermer les filtres"
          style={{ width: 44, height: 44, borderRadius: rayon.bouton, backgroundColor: c.surface2, alignItems: 'center', justifyContent: 'center' }}>
          <Icone nom="croix" taille={20} couleur={c.noir} />
        </PressableAnime>
      </View>

      <Section>Autour d&apos;un point</Section>
      <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 12 }}>
        <Chip libelle="Partout" actif={km === null} onPress={() => choisirRayon(null)} />
        {RAYONS_KM.map((r) => <Chip key={r} libelle={`${r} km`} actif={km === r} onPress={() => choisirRayon(r)} />)}
      </View>

      <View style={{ height: 230, borderRadius: rayon.md, overflow: 'hidden', borderWidth: 1, borderColor: c.line2, backgroundColor: c.surface2 }}>
        <MapView
          ref={carte}
          provider={PROVIDER_DEFAULT}
          style={{ flex: 1 }}
          initialRegion={{ ...depart, latitudeDelta: deltaDepart, longitudeDelta: deltaDepart }}
          onPress={placer}
          showsPointsOfInterests={false}
          toolbarEnabled={false}
        >
          {point && km !== null ? (
            <>
              <Circle center={point} radius={km * 1000} strokeColor={accent} strokeWidth={2} fillColor={`${accent}22`} />
              <Marker coordinate={point} draggable onDragEnd={(e) => setPoint(e.nativeEvent.coordinate)} pinColor={accent} />
            </>
          ) : null}
        </MapView>
      </View>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, marginTop: 10, marginBottom: 6 }}>
        <T taille={fs[3]} couleur={c.gris} style={{ flex: 1 }}>
          {km === null ? 'Touche la carte pour placer le point.' : `Clubs à moins de ${km} km du point. Touche la carte pour le déplacer.`}
        </T>
        <PressableAnime onPress={maPosition} disabled={localisation} accessibilityRole="button" accessibilityLabel="Utiliser ma position"
          style={{ flexDirection: 'row', alignItems: 'center', gap: 6, minHeight: 40, paddingHorizontal: 12, borderRadius: rayon.bouton, backgroundColor: c.surface2 }}>
          <Icone nom="epingle" taille={16} couleur={c.noir} />
          <Text style={{ fontFamily: sans(600), fontSize: fs[4], color: c.noir }}>{localisation ? '…' : 'Ma position'}</Text>
        </PressableAnime>
      </View>
      <T taille={fs[3]} couleur={c.gris} style={{ marginBottom: 22 }}>
        Les événements de tes abonnements n&apos;ont pas de position : ils restent tous affichés.
      </T>

      <Section>Niveau</Section>
      <T taille={fs[3]} couleur={c.gris} style={{ marginTop: -6, marginBottom: 12 }}>
        Trie les parties à compléter et les événements de tes abonnements. Une partie « tous niveaux » reste toujours visible.
      </T>
      <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 24 }}>
        {niveaux.map((o) => <Chip key={o.code} libelle={o.libelle} actif={brouillonNiveau === o.code} onPress={() => setBrouillonNiveau(o.code)} />)}
      </View>

      <PressableAnime
        onPress={() => {
          onAppliquer(brouillonNiveau, km !== null && point !== null ? { lat: point.latitude, lng: point.longitude, rayon: km } : null);
          onClose();
        }}
        accessibilityRole="button"
        style={{ minHeight: 46, borderRadius: rayon.md, backgroundColor: accent, alignItems: 'center', justifyContent: 'center' }}
      >
        <Text style={{ fontFamily: sans(700), fontSize: fs[5] + 1, color: '#FFFFFF' }}>{actifs ? `Appliquer · ${actifs}` : 'Appliquer'}</Text>
      </PressableAnime>
    </Feuille>
  );
}
