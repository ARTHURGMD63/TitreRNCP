/**
 * Carte interactive des soirées — nouveauté propre à l'application, sans
 * équivalent web : explore.php liste les événements, ici on les situe.
 *
 * Un repère par soirée dont l'établissement a une position connue (réglée
 * par le partenaire dans son profil, ou déduite de son adresse — voir
 * includes/geocodage.php) : un établissement qui n'a jamais rien réglé reste
 * listé normalement dans Explorer, simplement absent d'ici.
 *
 * Le repère est une bulle : le nombre d'inscrits dans une pastille, puis le
 * nom de la soirée. La pastille prend la couleur de l'affluence (quatre
 * paliers, voir palierAffluence dans regles.ts) ; le nombre reste écrit,
 * pour ne jamais dépendre de la couleur seule. Le nom ne s'affiche que là où
 * il a la place : sur une carte dense, deux bulles voisines se réduisent à
 * leur pastille plutôt que de se recouvrir. « En cours » n'est plus une
 * couleur mais un halo qui pulse sous la pointe.
 *
 * Toucher un repère fait monter un carrousel de cartes en bas de l'écran.
 * Le faire glisser de gauche à droite passe à la soirée voisine (d'ouest en
 * est) et recentre la carte dessus ; toucher la carte ouvre la fiche,
 * toucher le fond de carte referme le carrousel.
 *
 * Deux soirées trop proches pour rester tapables séparément se regroupent en
 * une pastille « 3 soirées ». Le seuil suit le zoom affiché, par crans d'un
 * demi-niveau : la carte ne se redessine pas à chaque glissement du doigt,
 * seulement quand l'échelle change vraiment.
 */

import React, { memo, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  Animated, Easing, FlatList, Platform, Pressable, Text, View, useWindowDimensions,
  type NativeScrollEvent, type NativeSyntheticEvent,
} from 'react-native';
import Svg, { Path } from 'react-native-svg';
import MapView, { Marker, PROVIDER_DEFAULT, type MapStyleElement, type Region } from 'react-native-maps';
import * as Location from 'expo-location';
import { router, useFocusEffect } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { api, type Evenement } from '../../api';
import { reserveBarre } from '../../composants/BarreOnglets';
import { Badge, BoutonRetour } from '../../composants/Elements';
import { Icone } from '../../composants/Icone';
import { Display, Mono, T } from '../../composants/Texte';
import { LIBELLES_TYPE } from '../../catalogue';
import { dateFr } from '../../format';
import { libelleInscrits, libellesAffluence, palierAffluence } from '../../regles';
import { useJeton } from '../../session';
import { encreSur, fs, lh, rayon, sans, type Couleurs } from '../../theme';
import { useTheme } from '../../useTheme';

// Clermont-Ferrand, cœur de la charte : la carte s'ouvre là par défaut, avant
// même d'avoir chargé la moindre soirée ou localisé qui que ce soit.
const REGION_DEFAUT: Region = { latitude: 45.7772, longitude: 3.087, latitudeDelta: 0.08, longitudeDelta: 0.08 };

// Géométrie du repère. La hauteur est fixe, sélectionné ou non : l'ancrage
// sur la pointe ne bouge donc jamais.
const HAUTEUR_BULLE = 32;
const POINTE = 7;
/** Sous la pointe, la place du halo « en cours ». */
const SOCLE = 7;
const HAUTEUR_REPERE = HAUTEUR_BULLE + POINTE + SOCLE;
/** Android ancre en proportion de l'image, Apple Plans en points depuis le centre. */
const ANCRE_REPERE = { x: 0.5, y: (HAUTEUR_BULLE + POINTE) / HAUTEUR_REPERE };
const DECALAGE_REPERE = { x: 0, y: -(HAUTEUR_BULLE + POINTE - HAUTEUR_REPERE / 2) };
const ANCRE_CENTRE = { x: 0.5, y: 0.5 };

// Géométrie du carrousel.
const HAUTEUR_CARTE = 132;
const ECART_CARTES = 10;

// Emprise d'un repère, en part de la région affichée (largeur × hauteur
// d'écran). Deux pastilles plus proches que cela se regroupent ; deux bulles
// plus proches que cela perdent leur nom.
const EMPRISE_PASTILLE = { lat: 0.05, lon: 0.09 };
const EMPRISE_NOM = { lat: 0.06, lon: 0.38 };

/**
 * Le fond de carte de Google (Android) : sans commerces ni arrêts, pour que
 * les soirées soient seules à parler. La nuit, il reprend le basalte de la
 * charte. Apple Plans suit le thème par userInterfaceStyle.
 */
const STYLE_JOUR: MapStyleElement[] = [
  { featureType: 'poi', stylers: [{ visibility: 'off' }] },
  { featureType: 'transit', stylers: [{ visibility: 'off' }] },
];
const STYLE_NUIT: MapStyleElement[] = [
  { elementType: 'geometry', stylers: [{ color: '#17151A' }] },
  { elementType: 'labels.text.fill', stylers: [{ color: '#8A858F' }] },
  { elementType: 'labels.text.stroke', stylers: [{ color: '#111013' }] },
  { elementType: 'labels.icon', stylers: [{ visibility: 'off' }] },
  { featureType: 'poi', stylers: [{ visibility: 'off' }] },
  { featureType: 'poi.park', elementType: 'geometry', stylers: [{ visibility: 'on' }, { color: '#19201A' }] },
  { featureType: 'transit', stylers: [{ visibility: 'off' }] },
  { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#26232B' }] },
  { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#36323B' }] },
  { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#0F1D3A' }] },
];

/** La couleur d'un palier d'affluence : dôme, volt, moutarde, puis rose vif. */
function teinteAffluence(c: Couleurs, inscrits: number): string {
  return [c.bleu, c.lime, c.orange, c.alerteVif][palierAffluence(inscrits)];
}

/** Ce qu'un lecteur d'écran annonce sur un repère ou une carte. */
function etiquetteEvenement(e: Evenement): string {
  const quand = e.en_cours ? 'en cours' : dateFr(e.date_heure, 'l j M à H\\hi');
  return `${e.titre}, ${e.etablissement.nom}, ${quand}, ${libelleInscrits(e.places.inscrits)}`;
}

const latitudeDe = (e: Evenement) => e.etablissement.latitude as number;
const longitudeDe = (e: Evenement) => e.etablissement.longitude as number;

type Grappe = { cle: string; latitude: number; longitude: number; evenements: Evenement[] };

/**
 * Regroupe les soirées trop proches pour rester tapables séparément. Le
 * seuil vient de l'échelle affichée : resserré en vue rapprochée, large en
 * vue d'ensemble — pas une distance fixe en mètres, qui sur-regrouperait dès
 * qu'on dézoome un peu.
 *
 * La clé d'une grappe est faite des soirées qu'elle contient, pas de sa
 * position : tant que ses membres ne changent pas, son repère reste le même
 * à l'écran au lieu d'être détruit et recréé à chaque zoom.
 */
function grouper(evenements: Evenement[], seuilLat: number, seuilLon: number): Grappe[] {
  const grappes: Omit<Grappe, 'cle'>[] = [];
  for (const e of evenements) {
    const latitude = latitudeDe(e);
    const longitude = longitudeDe(e);
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
  return grappes.map((g) => ({ ...g, cle: g.evenements.map((e) => e.id).join('-') }));
}

/** La région qui contient toutes les soirées, avec de l'air autour. */
function regionEnsemble(evenements: Evenement[]): Region {
  const lats = evenements.map(latitudeDe);
  const lons = evenements.map(longitudeDe);
  const [latMin, latMax] = [Math.min(...lats), Math.max(...lats)];
  const [lonMin, lonMax] = [Math.min(...lons), Math.max(...lons)];
  return {
    latitude: (latMin + latMax) / 2,
    longitude: (lonMin + lonMax) / 2,
    latitudeDelta: Math.max(0.05, (latMax - latMin) * 1.8),
    longitudeDelta: Math.max(0.05, (lonMax - lonMin) * 1.8),
  };
}

/** L'échelle au demi-niveau de zoom près : en dessous, rien ne se regroupe autrement. */
const cran = (delta: number) => 2 ** (Math.round(Math.log2(delta) * 2) / 2);

/**
 * Sur Android, un repère personnalisé est une image figée : la carte ne le
 * redessine que si on le lui demande, et le lui demander en permanence coûte
 * cher. On le demande donc un court instant après chaque changement
 * d'apparence, puis on relâche.
 */
function useRedessin(signature: string): boolean {
  const [dessinee, setDessinee] = useState<string | null>(null);
  useEffect(() => {
    const minuterie = setTimeout(() => setDessinee(signature), 400);
    return () => clearTimeout(minuterie);
  }, [signature]);
  return dessinee !== signature;
}

/** La bulle d'une soirée : pastille du nombre d'inscrits, nom, pointe. */
const Bulle = memo(function Bulle({ inscrits, titre, enCours, actif, nomVisible }: {
  inscrits: number; titre: string; enCours: boolean; actif: boolean; nomVisible: boolean;
}) {
  const { c, ombre } = useTheme();
  const [pouls] = useState(() => new Animated.Value(0));

  useEffect(() => {
    if (!enCours) return;
    const boucle = Animated.loop(
      Animated.sequence([
        Animated.timing(pouls, { toValue: 1, duration: 800, easing: Easing.out(Easing.quad), useNativeDriver: true }),
        Animated.timing(pouls, { toValue: 0, duration: 800, easing: Easing.in(Easing.quad), useNativeDriver: true }),
      ])
    );
    boucle.start();
    return () => boucle.stop();
  }, [enCours, pouls]);

  const teinte = teinteAffluence(c, inscrits);
  const fond = actif ? teinte : c.blanc;
  const bordure = actif ? teinte : c.line2;
  const encre = actif ? encreSur(teinte) : c.noir;

  return (
    <View style={{ height: HAUTEUR_REPERE, alignItems: 'center' }}>
      {enCours ? (
        <Animated.View
          style={{
            // Un anneau posé au sol, centré sur la pointe : une onde, pas un
            // aplat — il ne doit jamais masquer la pointe qu'il entoure.
            position: 'absolute', bottom: 1, width: 26, height: 12, borderRadius: 6, borderWidth: 2, borderColor: teinte,
            opacity: pouls.interpolate({ inputRange: [0, 1], outputRange: [0.9, 0.15] }),
            transform: [{ scale: pouls.interpolate({ inputRange: [0, 1], outputRange: [0.5, 1.15] }) }],
          }}
        />
      ) : null}
      <View
        style={[
          {
            height: HAUTEUR_BULLE, borderRadius: HAUTEUR_BULLE / 2, flexDirection: 'row', alignItems: 'center', gap: 6,
            paddingLeft: 2, paddingRight: nomVisible ? 10 : 2, backgroundColor: fond, borderWidth: 1, borderColor: bordure,
          },
          ombre('base'),
        ]}
      >
        <View style={{ minWidth: 26, height: 26, borderRadius: 13, paddingHorizontal: 5, alignItems: 'center', justifyContent: 'center', backgroundColor: actif ? encre : teinte }}>
          <Text style={{ fontFamily: sans(700), fontSize: fs[2], color: actif ? teinte : encreSur(teinte), fontVariant: ['tabular-nums'] }}>{inscrits}</Text>
        </View>
        {nomVisible ? (
          <Text numberOfLines={1} style={{ fontFamily: sans(700), fontSize: fs[2], color: encre, maxWidth: 132 }}>{titre}</Text>
        ) : null}
      </View>
      {/* La pointe recouvre d'un point le filet du bas de la bulle : les deux
          ne font qu'une seule forme, sans couture. */}
      <Svg width={14} height={POINTE + 1} viewBox={`0 0 14 ${POINTE + 1}`} style={{ marginTop: -1 }}>
        <Path d={`M0 0L7 ${POINTE}L14 0Z`} fill={fond} />
        <Path d={`M0 0L7 ${POINTE}L14 0`} fill="none" stroke={bordure} strokeWidth={1} />
      </Svg>
    </View>
  );
});

/** Le repère d'une soirée isolée. */
function RepereSoiree({ evenement, actif, nomVisible, onPress }: {
  evenement: Evenement; actif: boolean; nomVisible: boolean; onPress: (e: Evenement) => void;
}) {
  const { mode } = useTheme();
  const inscrits = evenement.places.inscrits;
  const redessin = useRedessin(`${mode}|${actif}|${nomVisible}|${inscrits}|${evenement.titre}`);
  return (
    <Marker
      coordinate={{ latitude: latitudeDe(evenement), longitude: longitudeDe(evenement) }}
      anchor={ANCRE_REPERE}
      centerOffset={DECALAGE_REPERE}
      zIndex={actif ? 10 : palierAffluence(inscrits)}
      onPress={() => onPress(evenement)}
      stopPropagation
      // Le halo d'une soirée en cours bouge sans arrêt : elle seule reste suivie.
      tracksViewChanges={redessin || evenement.en_cours}
      accessibilityLabel={etiquetteEvenement(evenement)}
    >
      <Bulle inscrits={inscrits} titre={evenement.titre} enCours={evenement.en_cours} actif={actif} nomVisible={actif || nomVisible} />
    </Marker>
  );
}

/**
 * Le repère d'un regroupement : « 3 soirées », jamais un nombre seul, qu'on
 * prendrait pour un nombre d'inscrits. Sa pastille a la couleur de la soirée
 * la plus fréquentée du lot.
 */
function RepereGrappe({ grappe, onPress }: { grappe: Grappe; onPress: (g: Grappe) => void }) {
  const { c, mode, ombre } = useTheme();
  const n = grappe.evenements.length;
  const teinte = teinteAffluence(c, Math.max(...grappe.evenements.map((e) => e.places.inscrits)));
  const redessin = useRedessin(`${mode}|${n}|${teinte}`);
  return (
    <Marker
      coordinate={{ latitude: grappe.latitude, longitude: grappe.longitude }}
      anchor={ANCRE_CENTRE}
      zIndex={5}
      onPress={() => onPress(grappe)}
      stopPropagation
      tracksViewChanges={redessin}
      accessibilityLabel={`${n} soirées à cet endroit, dont ${grappe.evenements[0].etablissement.nom}`}
    >
      <View
        style={[
          {
            height: HAUTEUR_BULLE, borderRadius: HAUTEUR_BULLE / 2, flexDirection: 'row', alignItems: 'center', gap: 6,
            paddingLeft: 9, paddingRight: 11, backgroundColor: c.blanc, borderWidth: 1, borderColor: c.line2,
          },
          ombre('base'),
        ]}
      >
        <View style={{ width: 12, height: 12, borderRadius: 6, backgroundColor: teinte }} />
        <Text style={{ fontFamily: sans(700), fontSize: fs[2], color: c.noir }}>{n} soirées</Text>
      </View>
    </Marker>
  );
}

/** Une carte du carrousel : le résumé d'une soirée, d'un coup d'œil. */
const CarteSoiree = memo(function CarteSoiree({ evenement, actif, largeur, onPress }: {
  evenement: Evenement; actif: boolean; largeur: number; onPress: (e: Evenement) => void;
}) {
  const { c, ombre } = useTheme();
  const teinte = teinteAffluence(c, evenement.places.inscrits);
  const reduction = evenement.reduction ?? 0;
  const remise = reduction > 0 ? `-${reduction}%` : evenement.is_gratuit ? 'Gratuit' : null;
  const amis = evenement.amis.nb > 0 ? evenement.amis : null;
  const type = LIBELLES_TYPE[evenement.etablissement.type];

  return (
    <Pressable
      onPress={() => onPress(evenement)}
      accessibilityRole="button"
      accessibilityLabel={etiquetteEvenement(evenement)}
      accessibilityHint={actif ? 'Ouvre la fiche de la soirée' : 'Affiche cette soirée sur la carte'}
      style={[
        {
          width: largeur, height: HAUTEUR_CARTE, backgroundColor: c.blanc, borderRadius: rayon.base, padding: 16,
          justifyContent: 'space-between', borderWidth: 1.5, borderColor: actif ? teinte : c.grisClair,
        },
        ombre('lg'),
      ]}
    >
      <View>
        <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8, marginBottom: 6 }}>
          {evenement.en_cours
            ? <Badge libelle="En cours" fond={c.lime} encre={encreSur(c.lime)} />
            : <Mono couleur={c.gris}>{dateFr(evenement.date_heure, 'D j M · H\\hi')}</Mono>}
          {remise ? <Badge libelle={remise} fond={c.rougeClair} encre={c.surRougeClair} /> : null}
        </View>
        <Display taille={fs[5]} interligne={lh.tight} numberOfLines={1}>{evenement.titre}</Display>
        <T taille={fs[3]} couleur={c.grisFonce} numberOfLines={1} style={{ marginTop: 2 }}>
          {evenement.etablissement.nom}{type ? ` · ${type}` : ''}
        </T>
      </View>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
        <View style={{ width: 10, height: 10, borderRadius: 5, backgroundColor: teinte }} />
        <T taille={fs[3]} poids={700}>{libelleInscrits(evenement.places.inscrits)}</T>
        {evenement.places.complet ? <T taille={fs[3]} poids={700} couleur={c.danger}>· Complet</T> : null}
        <View style={{ flex: 1, minWidth: 0 }}>
          {amis ? (
            <T taille={fs[3]} poids={600} couleur={c.surRougeClair} numberOfLines={1}>
              · {amis.prenoms.slice(0, 2).join(', ')}{amis.nb > 2 ? ` +${amis.nb - 2}` : ''} {amis.nb > 1 ? 'y vont' : 'y va'}
            </T>
          ) : null}
        </View>
        <Icone nom="fleche-d" taille={16} couleur={c.gris} />
      </View>
    </Pressable>
  );
});

export default function CarteEvenements() {
  const { c, sombre } = useTheme();
  const jeton = useJeton();
  const { top, bottom } = useSafeAreaInsets();
  const { width: largeurEcran, height: hauteurEcran } = useWindowDimensions();
  const carteRef = useRef<MapView>(null);
  const listeRef = useRef<FlatList<Evenement>>(null);

  const [evenements, setEvenements] = useState<Evenement[] | null>(null);
  const [selectionId, setSelectionId] = useState<number | null>(null);
  const [autorisationPosition, setAutorisationPosition] = useState<'inconnue' | 'accordee' | 'refusee'>('inconnue');
  const [prete, setPrete] = useState(false);
  // L'échelle affichée, au demi-niveau de zoom près : c'est elle qui décide
  // des regroupements. La région exacte, elle, reste dans une référence —
  // déplacer la carte ne redessine donc rien.
  const [echelle, setEchelle] = useState({ lat: cran(REGION_DEFAUT.latitudeDelta), lon: cran(REGION_DEFAUT.longitudeDelta) });
  const regionRef = useRef<Region>(REGION_DEFAUT);
  const cadree = useRef(false);
  // Le carrousel ne pilote la carte que quand c'est le doigt qui le fait
  // défiler, pas quand il rejoint de lui-même la soirée d'un repère touché.
  const defileAuDoigt = useRef(false);
  const [glissement] = useState(() => new Animated.Value(0));

  const charger = useCallback(async () => {
    try {
      const r = await api.evenements(jeton, { type: 'all' });
      setEvenements(r.evenements);
    } catch {
      setEvenements([]);
    }
  }, [jeton]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  // Les soirées situables, d'ouest en est : c'est l'ordre du carrousel, pour
  // que glisser vers la droite déplace aussi la carte vers la droite. Deux
  // soirées au même endroit se suivent, la plus proche dans le temps d'abord.
  const localisables = useMemo(
    () => (evenements ?? [])
      .filter((e) => e.etablissement.latitude !== null && e.etablissement.longitude !== null)
      .sort((a, b) => longitudeDe(a) - longitudeDe(b) || latitudeDe(a) - latitudeDe(b) || a.date_heure.localeCompare(b.date_heure)),
    [evenements]
  );

  const indexSelection = selectionId === null ? -1 : localisables.findIndex((e) => e.id === selectionId);
  const ouvert = indexSelection >= 0;

  const grappes = useMemo(
    () => grouper(localisables, echelle.lat * EMPRISE_PASTILLE.lat, echelle.lon * EMPRISE_PASTILLE.lon),
    [localisables, echelle]
  );

  // Une bulle garde son nom tant qu'aucun autre repère ne tombe dessous.
  const nomsVisibles = useMemo(() => {
    const visibles = new Set<string>();
    for (const g of grappes) {
      const gene = grappes.some(
        (h) => h !== g
          && Math.abs(h.latitude - g.latitude) < echelle.lat * EMPRISE_NOM.lat
          && Math.abs(h.longitude - g.longitude) < echelle.lon * EMPRISE_NOM.lon
      );
      if (!gene) visibles.add(g.cle);
    }
    return visibles;
  }, [grappes, echelle]);

  // Premier cadrage sur l'ensemble des soirées, une seule fois : revenir sur
  // l'onglet recharge les soirées mais laisse la carte là où on l'avait mise.
  useEffect(() => {
    if (!prete || cadree.current || localisables.length === 0) return;
    cadree.current = true;
    carteRef.current?.animateToRegion(regionEnsemble(localisables), 400);
  }, [prete, localisables]);

  useEffect(() => {
    Animated.timing(glissement, { toValue: ouvert ? 1 : 0, duration: 260, easing: Easing.out(Easing.cubic), useNativeDriver: true }).start();
  }, [ouvert, glissement]);

  // Réserve la hauteur de la barre flottante (voir BarreOnglets.tsx) : le
  // carrousel s'arrête au-dessus de l'onglet actif, jamais derrière.
  const espaceBarre = reserveBarre(bottom);
  const largeurCarte = Math.min(largeurEcran - 60, 340);
  const pas = largeurCarte + ECART_CARTES;

  function auChangementRegion(region: Region) {
    regionRef.current = region;
    const suivante = { lat: cran(region.latitudeDelta), lon: cran(region.longitudeDelta) };
    setEchelle((e) => (e.lat === suivante.lat && e.lon === suivante.lon ? e : suivante));
  }

  /** Amène une soirée au milieu de ce qui reste visible au-dessus du carrousel. */
  function centrerSur(e: Evenement) {
    const masque = (HAUTEUR_CARTE + 16) / 2 / Math.max(1, hauteurEcran - espaceBarre);
    carteRef.current?.animateCamera(
      { center: { latitude: latitudeDe(e) - regionRef.current.latitudeDelta * masque, longitude: longitudeDe(e) } },
      { duration: 350 }
    );
  }

  function choisirRepere(e: Evenement) {
    const index = localisables.findIndex((x) => x.id === e.id);
    if (index < 0) return;
    defileAuDoigt.current = false;
    // Déjà ouvert, le carrousel glisse jusqu'à la carte ; fermé, il monte
    // directement à la bonne.
    listeRef.current?.scrollToOffset({ offset: index * pas, animated: ouvert });
    setSelectionId(e.id);
    centrerSur(e);
  }

  function ouvrirGrappe(grappe: Grappe) {
    // Deux établissements à la même adresse ne se sépareront jamais en
    // zoomant : au plancher, on ouvre le carrousel sur la première soirée
    // (les autres la suivent) plutôt que de zoomer indéfiniment dans le vide.
    const region = regionRef.current;
    if (region.latitudeDelta <= 0.004) {
      choisirRepere(grappe.evenements[0]);
      return;
    }
    carteRef.current?.animateToRegion({
      latitude: grappe.latitude,
      longitude: grappe.longitude,
      latitudeDelta: Math.max(0.002, region.latitudeDelta / 2.5),
      longitudeDelta: Math.max(0.002, region.longitudeDelta / 2.5),
    }, 400);
  }

  function auDefilement(ev: NativeSyntheticEvent<NativeScrollEvent>) {
    if (!defileAuDoigt.current) return;
    const index = Math.round(ev.nativeEvent.contentOffset.x / pas);
    const e = localisables[Math.max(0, Math.min(localisables.length - 1, index))];
    if (e && e.id !== selectionId) {
      setSelectionId(e.id);
      centrerSur(e);
    }
  }

  function toucherCarte(e: Evenement) {
    // La carte voisine, qui dépasse sur le côté, devient la soirée courante ;
    // la carte courante ouvre sa fiche.
    if (e.id !== selectionId) choisirRepere(e);
    else router.push({ pathname: '/evenement/[id]', params: { id: String(e.id) } });
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

  const teintes = [c.bleu, c.lime, c.orange, c.alerteVif];

  return (
    <View style={{ flex: 1, backgroundColor: c.bg }}>
      <MapView
        ref={carteRef}
        provider={PROVIDER_DEFAULT}
        style={{ flex: 1 }}
        initialRegion={REGION_DEFAUT}
        userInterfaceStyle={sombre ? 'dark' : 'light'}
        customMapStyle={sombre ? STYLE_NUIT : STYLE_JOUR}
        showsPointsOfInterests={false}
        showsUserLocation={autorisationPosition === 'accordee'}
        showsMyLocationButton={false}
        showsCompass={false}
        toolbarEnabled={false}
        // La carte reste à plat, nord en haut : c'est ce qui permet de savoir
        // quelles bulles se touchent sans interroger la carte à chaque image.
        rotateEnabled={false}
        pitchEnabled={false}
        // Le recentrage sur un repère touché est le nôtre (il tient compte
        // du carrousel), pas celui de Google.
        moveOnMarkerPress={false}
        mapPadding={{ top: 0, right: 0, bottom: espaceBarre, left: 0 }}
        onMapReady={() => setPrete(true)}
        onRegionChangeComplete={auChangementRegion}
        onPress={(ev) => { if (ev.nativeEvent.action !== 'marker-press') setSelectionId(null); }}
      >
        {grappes.map((g) => (g.evenements.length === 1 ? (
          <RepereSoiree
            key={g.cle}
            evenement={g.evenements[0]}
            actif={g.evenements[0].id === selectionId}
            nomVisible={nomsVisibles.has(g.cle)}
            onPress={choisirRepere}
          />
        ) : (
          <RepereGrappe key={g.cle} grappe={g} onPress={ouvrirGrappe} />
        )))}
      </MapView>

      <View pointerEvents="box-none" style={{ position: 'absolute', top: top + 16, left: 20, right: 20, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
        <BoutonRetour media />
        <View style={{ backgroundColor: c.blanc, borderWidth: 1, borderColor: c.line2, borderRadius: rayon.pill, paddingVertical: 8, paddingHorizontal: 16 }}>
          <T taille={fs[3]} poids={700}>
            {evenements === null ? 'Chargement…' : `${localisables.length} soirée${localisables.length > 1 ? 's' : ''} sur la carte`}
          </T>
        </View>
      </View>

      {/* Seconde rangée, fixe : la légende des couleurs à gauche, « me
          localiser » à droite. Plus rien ne saute quand le carrousel monte. */}
      <View pointerEvents="box-none" style={{ position: 'absolute', top: top + 72, left: 20, right: 20, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
        <View
          accessible
          accessibilityLabel={`Couleur des repères selon le nombre d'inscrits : ${libellesAffluence().join(', ')}`}
          style={{ flexDirection: 'row', alignItems: 'center', gap: 10, backgroundColor: c.blanc, borderWidth: 1, borderColor: c.line2, borderRadius: rayon.pill, paddingVertical: 7, paddingHorizontal: 12 }}
        >
          <Icone nom="personnes" taille={14} couleur={c.grisFonce} />
          {libellesAffluence().map((libelle, i) => (
            <View key={libelle} style={{ flexDirection: 'row', alignItems: 'center', gap: 4 }}>
              <View style={{ width: 10, height: 10, borderRadius: 5, backgroundColor: teintes[i] }} />
              <T taille={fs[1]} poids={600} couleur={c.grisFonce}>{libelle}</T>
            </View>
          ))}
        </View>
        <Pressable
          onPress={meLocaliser}
          accessibilityRole="button"
          accessibilityLabel="Me localiser"
          style={{ width: 44, height: 44, borderRadius: 22, backgroundColor: c.blanc, borderWidth: 1, borderColor: c.line2, alignItems: 'center', justifyContent: 'center' }}
        >
          <Icone nom={autorisationPosition === 'refusee' ? 'interdit' : 'epingle'} taille={20} couleur={autorisationPosition === 'refusee' ? c.gris : c.noir} />
        </Pressable>
      </View>

      <Animated.View
        pointerEvents={ouvert ? 'box-none' : 'none'}
        accessibilityElementsHidden={!ouvert}
        importantForAccessibility={ouvert ? 'auto' : 'no-hide-descendants'}
        style={{
          position: 'absolute', left: 0, right: 0, bottom: espaceBarre + 16,
          opacity: glissement,
          transform: [{ translateY: glissement.interpolate({ inputRange: [0, 1], outputRange: [HAUTEUR_CARTE + 40, 0] }) }],
        }}
      >
        <FlatList
          ref={listeRef}
          data={localisables}
          keyExtractor={(e) => String(e.id)}
          horizontal
          showsHorizontalScrollIndicator={false}
          snapToInterval={pas}
          decelerationRate="fast"
          // Android accroche sinon plusieurs cartes d'un seul geste.
          disableIntervalMomentum={Platform.OS === 'android'}
          contentContainerStyle={{ paddingHorizontal: (largeurEcran - largeurCarte) / 2, gap: ECART_CARTES }}
          getItemLayout={(_, index) => ({ length: pas, offset: pas * index, index })}
          scrollEventThrottle={16}
          onTouchStart={() => { defileAuDoigt.current = true; }}
          onScrollBeginDrag={() => { defileAuDoigt.current = true; }}
          onScroll={auDefilement}
          renderItem={({ item }) => (
            <CarteSoiree evenement={item} actif={item.id === selectionId} largeur={largeurCarte} onPress={toucherCarte} />
          )}
        />
      </Animated.View>
    </View>
  );
}
