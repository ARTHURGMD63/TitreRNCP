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
 * L'épingle est une vraie goutte dessinée en SVG (pas le pin générique du
 * système, ni un simple rond) : rouge à venir, vert-volt et pulsante en
 * cours, avec l'icône du type d'établissement dans la tête — plus lisible
 * qu'une couleur seule, et une tête plus grande fait une bien meilleure
 * cible au doigt que l'ancienne goutte de 36 px. Le point bleu « où je
 * suis » vient d'expo-location, demandé une seule fois à l'ouverture.
 *
 * Deux soirées proches (même rue, centre-ville un samedi) se regroupent en
 * une pastille avec un nombre plutôt que de se chevaucher — sans elle, la
 * seconde épingle devenait injoignable au doigt. Le seuil de regroupement
 * suit le zoom réel de la carte (onRegionChangeComplete), pas la région
 * figée au chargement : sinon il resterait celui du premier cadrage,
 * beaucoup trop large une fois zoomé sur un quartier.
 */

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Animated, Pressable, Text, View } from 'react-native';
import Svg, { Path } from 'react-native-svg';
import MapView, { Marker, PROVIDER_DEFAULT, type Region } from 'react-native-maps';
import * as Location from 'expo-location';
import { router, useFocusEffect } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { api, type Evenement } from '../../api';
import { reserveBarre } from '../../composants/BarreOnglets';
import { BoutonRetour } from '../../composants/Elements';
import { Icone, type NomIcone } from '../../composants/Icone';
import { Display, Mono, T } from '../../composants/Texte';
import { LIBELLES_TYPE } from '../../catalogue';
import { dateFr } from '../../format';
import { useJeton } from '../../session';
import { fixe, fs, lh, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

// Clermont-Ferrand, cœur de la charte : la carte s'ouvre là par défaut, avant
// même d'avoir chargé la moindre soirée ou localisé qui que ce soit.
const REGION_DEFAUT = { latitude: 45.7772, longitude: 3.087, latitudeDelta: 0.08, longitudeDelta: 0.08 };

// La silhouette de la goutte seule (sans le trou de la version site) : la
// tête accueille maintenant l'icône du type d'établissement, à la place.
const TRACE_EPINGLE = 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z';

// Aucune icône « bar » ou « restaurant » dans le jeu du site (includes/icons.php) :
// on reste dans le catalogue existant plutôt que d'en inventer une, avec le
// rapprochement le plus honnête pour chaque type.
const ICONE_TYPE: Record<string, NomIcone> = {
  bar: 'flamme',
  boite: 'musique',
  resto: 'carte',
  afterwork: 'soleil',
};

/** Ce qu'un lecteur d'écran annonce sur une épingle isolée. */
function etiquetteEvenement(e: Evenement): string {
  const quand = e.en_cours ? 'en cours' : dateFr(e.date_heure, 'l j M à H\\hi');
  return `${e.titre}, ${e.etablissement.nom}, ${quand}`;
}

type Grappe = { latitude: number; longitude: number; evenements: Evenement[] };

/**
 * Regroupe les soirées trop proches pour rester tapables séparément. Le
 * seuil vient du zoom réel affiché (deltas de la région visible) : resserré
 * en vue rapprochée, large en vue d'ensemble — pas une distance fixe en
 * mètres, qui sur-regrouperait dès qu'on dézoome un peu.
 */
function grouper(evenements: Evenement[], seuilLat: number, seuilLon: number): Grappe[] {
  const grappes: Grappe[] = [];
  for (const e of evenements) {
    const latitude = e.etablissement.latitude as number;
    const longitude = e.etablissement.longitude as number;
    const existante = grappes.find(
      (g) => Math.abs(g.latitude - latitude) < seuilLat && Math.abs(g.longitude - longitude) < seuilLon
    );
    if (existante) {
      existante.evenements.push(e);
      // Recentre sur la moyenne du groupe, pas sur le premier arrivé.
      const n = existante.evenements.length;
      existante.latitude += (latitude - existante.latitude) / n;
      existante.longitude += (longitude - existante.longitude) / n;
    } else {
      grappes.push({ latitude, longitude, evenements: [e] });
    }
  }
  return grappes;
}

/** La pastille de regroupement : un rond avec un nombre, pas une épingle. */
function PastilleGrappe({ grappe, couleur }: { grappe: Grappe; couleur: string }) {
  return (
    <View style={{ width: 48, height: 48, borderRadius: 24, backgroundColor: couleur, borderWidth: 3, borderColor: fixe.craie, alignItems: 'center', justifyContent: 'center', shadowColor: '#000', shadowOpacity: 0.3, shadowRadius: 3, shadowOffset: { width: 0, height: 2 }, elevation: 5 }}>
      <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: fixe.craie }}>{grappe.evenements.length}</Text>
    </View>
  );
}

/**
 * L'épingle : une goutte pleine, son icône de type dans la tête. La tête fait
 * 44 px même au repos — en dessous, une cible au doigt redevient pénible sur
 * une carte dense (règle des 44 pt iOS / 48 dp Android).
 */
function Epingle({ type, enCours, actif }: { type: string; enCours: boolean; actif: boolean }) {
  const { c } = useTheme();
  const [opacite] = useState(() => new Animated.Value(1));

  useEffect(() => {
    if (!enCours) return;
    const boucle = Animated.loop(
      Animated.sequence([
        Animated.timing(opacite, { toValue: 0.3, duration: 700, useNativeDriver: true }),
        Animated.timing(opacite, { toValue: 1, duration: 700, useNativeDriver: true }),
      ])
    );
    boucle.start();
    return () => boucle.stop();
  }, [enCours, opacite]);

  const teinte = enCours ? c.lime : c.rouge;
  const taille = actif ? 56 : 44;
  // Centre de la tête ronde dans le viewBox 24×24 du tracé : (12, 9).
  const centreTeteY = taille * (9 / 24);

  return (
    <View style={{ width: taille, height: taille, alignItems: 'center', justifyContent: 'flex-end' }}>
      {enCours ? (
        <Animated.View
          style={{
            position: 'absolute', bottom: taille * 0.05, width: taille * 0.55, height: taille * 0.22,
            borderRadius: taille * 0.22, backgroundColor: teinte, opacity: Animated.multiply(opacite, 0.4),
            transform: [{ scaleX: 1.6 }],
          }}
        />
      ) : null}
      <View style={{ shadowColor: '#000', shadowOpacity: 0.3, shadowRadius: 3, shadowOffset: { width: 0, height: 2 }, elevation: 5 }}>
        <Svg width={taille} height={taille} viewBox="0 0 24 24">
          <Path d={TRACE_EPINGLE} fill={teinte} stroke={fixe.craie} strokeWidth={1} />
        </Svg>
        <View pointerEvents="none" style={{ position: 'absolute', top: centreTeteY - taille * 0.19, left: 0, right: 0, alignItems: 'center' }}>
          <Icone nom={ICONE_TYPE[type] ?? 'epingle'} taille={Math.round(taille * 0.38)} couleur={fixe.craie} trait={2.4} />
        </View>
      </View>
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
  // La région réellement affichée. Mise à jour par onRegionChangeComplete,
  // aussi bien après un geste de la personne qu'après un recadrage
  // automatique (celui-ci anime la carte vers `region`, ce qui déclenche le
  // même événement) : le seuil de regroupement suit toujours le zoom réel.
  const [camera, setCamera] = useState<Region>(REGION_DEFAUT);

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

  const grappes = useMemo(() => {
    const seuilLat = camera.latitudeDelta * 0.06;
    const seuilLon = camera.longitudeDelta * 0.06;
    return grouper(localisables, seuilLat, seuilLon);
  }, [localisables, camera]);

  function ouvrirGrappe(grappe: Grappe) {
    // Deux établissements à la même adresse ne se sépareront jamais en
    // zoomant : au plancher, on affiche directement le premier plutôt que de
    // zoomer indéfiniment dans le vide.
    if (camera.latitudeDelta <= 0.004) {
      setSelection(grappe.evenements[0]);
      return;
    }
    carteRef.current?.animateToRegion({
      latitude: grappe.latitude,
      longitude: grappe.longitude,
      latitudeDelta: Math.max(0.002, camera.latitudeDelta / 2.5),
      longitudeDelta: Math.max(0.002, camera.longitudeDelta / 2.5),
    }, 400);
  }

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
        onRegionChangeComplete={setCamera}
      >
        {grappes.map((g) => {
          if (g.evenements.length === 1) {
            const e = g.evenements[0];
            return (
              <Marker
                key={e.id}
                coordinate={{ latitude: g.latitude, longitude: g.longitude }}
                anchor={{ x: 0.5, y: 1 }}
                onPress={() => setSelection(e)}
                tracksViewChanges={false}
                accessibilityLabel={etiquetteEvenement(e)}
              >
                <Epingle type={e.etablissement.type} enCours={e.en_cours} actif={selection?.id === e.id} />
              </Marker>
            );
          }
          const enCours = g.evenements.some((e) => e.en_cours);
          return (
            <Marker
              key={`grappe-${g.latitude}-${g.longitude}`}
              coordinate={{ latitude: g.latitude, longitude: g.longitude }}
              onPress={() => ouvrirGrappe(g)}
              tracksViewChanges={false}
              accessibilityLabel={`${g.evenements.length} soirées à cet endroit, dont ${g.evenements[0].etablissement.nom}`}
            >
              <PastilleGrappe grappe={g} couleur={enCours ? c.lime : c.rouge} />
            </Marker>
          );
        })}
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

      {/* La légende : les couleurs (à venir / en cours), puis les icônes de
          type — sans elles, une flamme ou une note de musique dans une
          épingle ne veut rien dire du premier coup d'œil. */}
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
        <View style={{ height: 1, backgroundColor: c.grisClair, marginVertical: 2 }} />
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', width: 160, gap: 6 }}>
          {Object.entries(ICONE_TYPE).map(([type, icone]) => (
            <View key={type} style={{ flexDirection: 'row', alignItems: 'center', gap: 4, width: '48%' }}>
              <Icone nom={icone} taille={11} couleur={c.grisFonce} trait={2.2} />
              <T taille={fs[1]} couleur={c.grisFonce}>{LIBELLES_TYPE[type] ?? type}</T>
            </View>
          ))}
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
