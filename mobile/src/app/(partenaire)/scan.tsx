/**
 * Scan des pass à l'entrée — la modale de partenaire/dashboard.php, en
 * plein écran natif.
 *
 * Même règle que le site : un scan à la fois (on ignore les suivants tant
 * que la requête précédente n'a pas répondu), un bip et un message vert sur
 * succès, rouge sur échec, puis reprise automatique — le scanner reste
 * ouvert pour enchaîner les entrées, contrairement au site qui recharge
 * toute la page.
 */

import React, { useCallback, useRef, useState } from 'react';
import { Pressable, Text, View } from 'react-native';
import { CameraView, useCameraPermissions, type BarcodeScanningResult } from 'expo-camera';
import { router, useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { api, ErreurApi } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { Icone } from '../../composants/Icone';
import { Display, T } from '../../composants/Texte';
import { useJeton } from '../../session';
import { fixe, fs, lh, rayon, sans } from '../../theme';

export default function ScanPass() {
  const jeton = useJeton();
  const { top, bottom } = useSafeAreaInsets();
  const { evenementId } = useLocalSearchParams<{ evenementId: string }>();
  const [permission, demanderPermission] = useCameraPermissions();

  const [message, setMessage] = useState("En attente de scan...");
  const [genre, setGenre] = useState<'attente' | 'succes' | 'echec'>('attente');
  const enTraitement = useRef(false);

  const scanner = useCallback(async (resultat: BarcodeScanningResult) => {
    if (enTraitement.current) return;
    enTraitement.current = true;

    let succes = false;
    try {
      const rep = await api.partenaireScan(jeton, { qr_code: resultat.data, event_id: Number(evenementId) });
      succes = true;
      setGenre('succes');
      setMessage(rep.message ?? 'Check-in validé');
    } catch (e) {
      setGenre('echec');
      setMessage(e instanceof ErreurApi ? e.message : 'Erreur de connexion');
    } finally {
      setTimeout(() => {
        enTraitement.current = false;
        setGenre('attente');
        setMessage('En attente de scan...');
      }, succes ? 1500 : 2000);
    }
  }, [jeton, evenementId]);

  const couleurMessage = genre === 'succes' ? '#C8F547' : genre === 'echec' ? '#FF5424' : fixe.craie;

  if (!permission) {
    return <View style={{ flex: 1, backgroundColor: fixe.basalte }} />;
  }

  if (!permission.granted) {
    return (
      <View style={{ flex: 1, backgroundColor: fixe.basalte, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 32, paddingTop: top, paddingBottom: bottom }}>
        <Icone nom="appareil" taille={40} couleur={fixe.craie} />
        <Display taille={fs[6]} couleur={fixe.craie} style={{ textAlign: 'center', marginTop: 16, marginBottom: 10 }}>
          Accès à la caméra nécessaire
        </Display>
        <T taille={fs[4]} couleur={fixe.craie} interligne={lh.normal} style={{ textAlign: 'center', marginBottom: 24, opacity: 0.8 }}>
          Pour scanner les pass à l&apos;entrée, autorise Linkee à utiliser la caméra.
        </T>
        <Bouton libelle="Autoriser la caméra" plein onPress={() => void demanderPermission()} style={{ marginBottom: 12 }} />
        <Bouton libelle="Retour" variante="contour" plein onPress={() => router.back()}
          styleTexte={{ color: fixe.craie }} style={{ borderColor: fixe.craie }} />
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: fixe.basalte }}>
      <CameraView
        style={{ flex: 1 }}
        facing="back"
        barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
        onBarcodeScanned={(r) => void scanner(r)}
      />

      <Pressable
        onPress={() => router.back()}
        accessibilityRole="button"
        accessibilityLabel="Fermer le scanner"
        style={{ position: 'absolute', top: top + 16, right: 20, width: 40, height: 40, borderRadius: 20, backgroundColor: 'rgba(17,16,19,0.6)', alignItems: 'center', justifyContent: 'center' }}
      >
        <Icone nom="croix" taille={20} couleur={fixe.craie} />
      </Pressable>

      <View
        pointerEvents="none"
        style={{
          position: 'absolute', left: 40, right: 40, top: '50%', marginTop: -125,
          height: 250, borderRadius: rayon.md, borderWidth: 3,
          borderColor: genre === 'attente' ? fixe.craie : couleurMessage,
        }}
      />

      <View style={{ position: 'absolute', left: 0, right: 0, bottom: bottom + 40, alignItems: 'center', paddingHorizontal: 24 }}>
        <Text style={{ fontFamily: sans(700), fontSize: fs[5], color: couleurMessage, textAlign: 'center' }}>{message}</Text>
      </View>
    </View>
  );
}
