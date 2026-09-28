/**
 * Carte interactive des soirées — nouveauté propre à l'application, sans
 * équivalent web : explore.php liste les événements, ici on les situe.
 *
 * Une épingle par établissement qui a une position connue (réglée par le
 * partenaire dans son profil, ou déduite de son adresse — voir
 * includes/geocodage.php) : un établissement qui n'a jamais rien réglé
 * reste listé normalement dans Explorer, simplement absent d'ici. Taper une
 * épingle ouvre un résumé, puis la fiche complète.
 */

import React, { useCallback, useMemo, useState } from 'react';
import { Pressable, Text, View } from 'react-native';
import MapView, { Marker, PROVIDER_DEFAULT } from 'react-native-maps';
import { router, useFocusEffect } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { api, type Evenement } from '../../api';
import { BoutonRetour } from '../../composants/Elements';
import { Icone } from '../../composants/Icone';
import { Display, Mono, T } from '../../composants/Texte';
import { dateFr } from '../../format';
import { useJeton } from '../../session';
import { fixe, fs, lh, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

// Clermont-Ferrand, cœur de la charte : la carte s'ouvre là par défaut, avant
// même d'avoir chargé la moindre soirée.
const REGION_DEFAUT = { latitude: 45.7772, longitude: 3.087, latitudeDelta: 0.08, longitudeDelta: 0.08 };

export default function CarteEvenements() {
  const { c } = useTheme();
  const jeton = useJeton();
  const { top, bottom } = useSafeAreaInsets();

  const [evenements, setEvenements] = useState<Evenement[] | null>(null);
  const [selection, setSelection] = useState<Evenement | null>(null);

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
    const latitude = (min(lats) + max(lats)) / 2;
    const longitude = (min(lons) + max(lons)) / 2;
    return {
      latitude,
      longitude,
      latitudeDelta: Math.max(0.05, (max(lats) - min(lats)) * 1.8),
      longitudeDelta: Math.max(0.05, (max(lons) - min(lons)) * 1.8),
    };
  }, [localisables]);

  return (
    <View style={{ flex: 1, backgroundColor: c.bg }}>
      <MapView
        provider={PROVIDER_DEFAULT}
        style={{ flex: 1 }}
        initialRegion={REGION_DEFAUT}
        region={evenements === null ? undefined : region}
      >
        {localisables.map((e) => (
          <Marker
            key={e.id}
            coordinate={{ latitude: e.etablissement.latitude as number, longitude: e.etablissement.longitude as number }}
            pinColor={e.en_cours ? c.lime : c.rouge}
            onPress={() => setSelection(e)}
          />
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

      {selection ? (
        <Pressable
          onPress={() => router.push({ pathname: '/evenement/[id]', params: { id: String(selection.id) } })}
          style={{ position: 'absolute', left: 16, right: 16, bottom: bottom + 16, backgroundColor: c.blanc, borderRadius: rayon.base, padding: 16, flexDirection: 'row', alignItems: 'center', gap: 14, borderWidth: 1, borderColor: c.grisClair }}
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
