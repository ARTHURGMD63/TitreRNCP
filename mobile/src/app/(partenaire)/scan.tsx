/**
 * Scan des pass à l'entrée — la modale de partenaire/dashboard.php, en
 * plein écran natif.
 *
 * Même règle que le site : un scan à la fois (on ignore les suivants tant
 * que la requête précédente n'a pas répondu), un bip et une carte de
 * profil sur succès (photo, nom, école — pas qu'un texte), un message rouge
 * sur échec, puis reprise automatique — le scanner reste ouvert pour
 * enchaîner les entrées, contrairement au site qui recharge toute la page.
 *
 * La liste de qui a été check-in reste visible pendant tout le scan (pastille
 * en haut, feuille au tap) : c'est elle qui répond à « qui j'ai eu ce soir ».
 */

import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Pressable, Text, View } from 'react-native';
import { CameraView, useCameraPermissions, type BarcodeScanningResult } from 'expo-camera';
import { router, useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { api, ErreurApi, type PersonneCheckin } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { Avatar } from '../../composants/Elements';
import { Feuille } from '../../composants/Feuille';
import { Icone } from '../../composants/Icone';
import { Display, T } from '../../composants/Texte';
import { useJeton } from '../../session';
import { fixe, fs, lh, rayon, sans } from '../../theme';

type Personne = { id: number; prenom: string; nom: string; ecole: string | null; promo: string | null; photo_url: string | null };

export default function ScanPass() {
  const jeton = useJeton();
  const { top, bottom } = useSafeAreaInsets();
  const { evenementId } = useLocalSearchParams<{ evenementId: string }>();
  const [permission, demanderPermission] = useCameraPermissions();

  const [messageEchec, setMessageEchec] = useState<string | null>(null);
  const [personneValidee, setPersonneValidee] = useState<Personne | null>(null);
  const [checkins, setCheckins] = useState<PersonneCheckin[]>([]);
  const [listeOuverte, setListeOuverte] = useState(false);
  const enTraitement = useRef(false);

  // La liste connue au moment d'ouvrir le scanner : elle s'enrichit ensuite
  // localement à chaque scan reussi, sans re-appeler le tableau de bord.
  useEffect(() => {
    api.partenaireDashboard(jeton).then((rep) => {
      if (rep.evenement?.id === Number(evenementId)) setCheckins(rep.stats.checkins);
    }).catch(() => {});
  }, [jeton, evenementId]);

  const scanner = useCallback(async (resultat: BarcodeScanningResult) => {
    if (enTraitement.current) return;
    enTraitement.current = true;

    let succes = false;
    try {
      const rep = await api.partenaireScan(jeton, { qr_code: resultat.data, event_id: Number(evenementId) });
      succes = true;
      setPersonneValidee(rep.personne);
      setCheckins((liste) => [{ ...rep.personne, checkin_le: new Date().toISOString() }, ...liste]);
    } catch (e) {
      setMessageEchec(e instanceof ErreurApi ? e.message : 'Erreur de connexion');
    } finally {
      setTimeout(() => {
        enTraitement.current = false;
        setPersonneValidee(null);
        setMessageEchec(null);
      }, succes ? 2200 : 2000);
    }
  }, [jeton, evenementId]);

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

  const cadre = personneValidee ? '#C8F547' : messageEchec ? '#FF5424' : fixe.craie;

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

      <Pressable
        onPress={() => setListeOuverte(true)}
        accessibilityRole="button"
        accessibilityLabel={`${checkins.length} personnes déjà check-in, voir la liste`}
        style={{ position: 'absolute', top: top + 16, left: 20, flexDirection: 'row', alignItems: 'center', gap: 7, height: 40, paddingHorizontal: 14, borderRadius: 20, backgroundColor: 'rgba(17,16,19,0.6)' }}
      >
        <Icone nom="personnes" taille={16} couleur={fixe.craie} />
        <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: fixe.craie }}>{checkins.length}</Text>
      </Pressable>

      <View
        pointerEvents="none"
        style={{
          position: 'absolute', left: 40, right: 40, top: '50%', marginTop: -125,
          height: 250, borderRadius: rayon.md, borderWidth: 3, borderColor: cadre,
        }}
      />

      {personneValidee ? (
        <View style={{ position: 'absolute', left: 24, right: 24, bottom: bottom + 40, backgroundColor: fixe.craie, borderRadius: rayon.base, padding: 16, flexDirection: 'row', alignItems: 'center', gap: 14 }}>
          <Avatar photo={personneValidee.photo_url} prenom={personneValidee.prenom} taille={52} fond="#5B8CFF" />
          <View style={{ flex: 1 }}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 2 }}>
              <Icone nom="valide" taille={16} couleur="#4D6B00" />
              <Text style={{ fontFamily: sans(700), fontSize: fs[2], color: '#4D6B00' }}>CHECK-IN VALIDÉ</Text>
            </View>
            <Text style={{ fontFamily: sans(700), fontSize: fs[5], color: fixe.basalte }}>{personneValidee.prenom} {personneValidee.nom}</Text>
            <Text style={{ fontFamily: sans(500), fontSize: fs[3], color: '#67626D' }}>
              {personneValidee.ecole ?? '—'}{personneValidee.promo ? ' · ' + personneValidee.promo : ''}
            </Text>
          </View>
        </View>
      ) : messageEchec ? (
        <View style={{ position: 'absolute', left: 0, right: 0, bottom: bottom + 40, alignItems: 'center', paddingHorizontal: 24 }}>
          <Text style={{ fontFamily: sans(700), fontSize: fs[5], color: '#FF5424', textAlign: 'center' }}>{messageEchec}</Text>
        </View>
      ) : (
        <View style={{ position: 'absolute', left: 0, right: 0, bottom: bottom + 40, alignItems: 'center', paddingHorizontal: 24 }}>
          <Text style={{ fontFamily: sans(700), fontSize: fs[5], color: fixe.craie, textAlign: 'center' }}>En attente de scan...</Text>
        </View>
      )}

      <Feuille visible={listeOuverte} onClose={() => setListeOuverte(false)}>
        <Display taille={fs[7]} style={{ marginBottom: 16 }}>
          {checkins.length} check-in{checkins.length > 1 ? 's' : ''}
        </Display>
        {checkins.length === 0 ? (
          <T taille={fs[4]} couleur="#67626D">Personne pour l&apos;instant.</T>
        ) : (
          checkins.map((p, i) => (
            <View key={`${p.id}-${i}`} style={{ flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 10, borderBottomWidth: i < checkins.length - 1 ? 1 : 0, borderBottomColor: '#E8E2D6' }}>
              <Avatar photo={p.photo_url} prenom={p.prenom} taille={38} fond="#5B8CFF" />
              <View style={{ flex: 1 }}>
                <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: fixe.basalte }}>{p.prenom} {p.nom}</Text>
                <Text style={{ fontFamily: sans(500), fontSize: fs[2], color: '#67626D' }}>{p.ecole ?? '—'}{p.promo ? ' · ' + p.promo : ''}</Text>
              </View>
            </View>
          ))
        )}
      </Feuille>
    </View>
  );
}
