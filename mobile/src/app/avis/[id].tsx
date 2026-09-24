/**
 * Laisser un avis — avis.php.
 *
 * « ← Retour au pass », le lieu en lave, le titre de la soirée, sa date en
 * mono, la carte « Ta note » et ses cinq étoiles moutarde, le commentaire
 * facultatif, puis « Envoyer mon avis » (ou « Modifier mon avis »).
 */

import React, { useCallback, useState } from 'react';
import { Pressable, Text, TextInput, View } from 'react-native';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';

import { api, ErreurApi, type ReponseAvis } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { Chargement, Ecran, Erreur } from '../../composants/Ecran';
import { Encart } from '../../composants/Elements';
import { Icone } from '../../composants/Icone';
import { Display, Mono } from '../../composants/Texte';
import { useJeton } from '../../session';
import { fs, lh, mono, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

export default function Avis() {
  const { c } = useTheme();
  const jeton = useJeton();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [donnees, setDonnees] = useState<ReponseAvis | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [note, setNote] = useState(0);
  const [commentaire, setCommentaire] = useState('');
  const [focus, setFocus] = useState(false);
  const [envoi, setEnvoi] = useState(false);
  const [succes, setSucces] = useState(false);
  const [erreurForm, setErreurForm] = useState<string | null>(null);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      const r = await api.avis(jeton, Number(id));
      setDonnees(r);
      setNote(r.avis?.note ?? 0);
      setCommentaire(r.avis?.commentaire ?? '');
    } catch (e) {
      // Pas de passage validé : le site renvoie vers le pass.
      if (e instanceof ErreurApi && e.code === 'no_checkin') { router.replace('/wallet'); return; }
      setErreur(e instanceof Error ? e.message : 'Chargement impossible.');
    }
  }, [jeton, id]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  async function envoyer() {
    setEnvoi(true);
    setErreurForm(null);
    try {
      await api.envoyerAvis(jeton, Number(id), note, commentaire);
      setSucces(true);
      setDonnees((d) => d && { ...d, avis: { note, commentaire } });
    } catch (e) {
      setErreurForm(e instanceof ErreurApi ? e.message : "Erreur lors de l'enregistrement.");
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <Ecran avecBarre={false} style={{ paddingHorizontal: 24, paddingTop: 32, paddingBottom: 100 }}>
      <Text onPress={() => router.navigate('/wallet')} accessibilityRole="link" suppressHighlighting style={{ fontFamily: sans(400), fontSize: fs[4], lineHeight: fs[4] * 1.5, color: c.gris, alignSelf: 'flex-start' }}>
        ← Retour au pass
      </Text>

      {erreur ? <Erreur message={erreur} onReessayer={charger} /> : !donnees ? <Chargement /> : (
        <>
          <Mono couleur={c.surRougeClair} style={{ marginTop: 24, marginBottom: 8 }}>{donnees.evenement.etablissement}</Mono>
          <Display taille={fs[8]} interligne={lh.tight} accessibilityRole="header" style={{ marginBottom: 8 }}>{donnees.evenement.titre}</Display>
          <Text style={{ fontFamily: mono(400), fontSize: fs[3], color: c.gris, marginBottom: 28 }}>{donnees.evenement.date}</Text>

          {succes ? <Encart genre="ok">Merci pour ton avis !</Encart> : null}
          {erreurForm ? <Encart genre="erreur">{erreurForm}</Encart> : null}

          <View style={{ backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.base, paddingTop: 22, paddingHorizontal: 16, paddingBottom: 10 }}>
            <Mono style={{ textAlign: 'center' }}>Ta note</Mono>
            <View accessibilityRole="radiogroup" style={{ flexDirection: 'row', gap: 8, justifyContent: 'center', marginVertical: 14 }}>
              {[1, 2, 3, 4, 5].map((i) => {
                const pleine = i <= note;
                return (
                  <Pressable
                    key={i}
                    onPress={() => setNote(i)}
                    accessibilityRole="radio"
                    accessibilityState={{ checked: note === i }}
                    accessibilityLabel={`${i} étoile${i > 1 ? 's' : ''}`}
                    // Sur le site l'étoile garde la taille d'icône (20 px) posée sur la
                    // ligne de base d'un corps de 48 px : même encombrement ici.
                    hitSlop={{ top: 12, bottom: 4, left: 4, right: 4 }}
                    style={{ height: 56, justifyContent: 'flex-end', paddingBottom: 9, transform: pleine ? [{ scale: 1.1 }] : [] }}
                  >
                    <Icone nom="etoile" taille={20} couleur={pleine ? c.orange : c.line2} plein={pleine ? c.orange : 'none'} />
                  </Pressable>
                );
              })}
            </View>
          </View>

          <View style={{ flexDirection: 'row', alignItems: 'baseline', marginTop: 24, marginBottom: 8 }}>
            <Mono>Ton commentaire </Mono>
            <Mono poids={400} majuscules={false}>(optionnel)</Mono>
          </View>
          <TextInput
            value={commentaire}
            onChangeText={setCommentaire}
            placeholder="Raconte-nous ta soirée..."
            placeholderTextColor={c.gris}
            multiline
            maxLength={1000}
            textAlignVertical="top"
            onFocus={() => setFocus(true)}
            onBlur={() => setFocus(false)}
            accessibilityHint="Maximum 1000 caractères"
            style={{ minHeight: 130, borderWidth: 1, borderColor: focus ? c.rouge : c.grisClair, backgroundColor: c.blanc, color: c.noir, borderRadius: rayon.md, padding: 16, fontFamily: sans(400), fontSize: fs[5] }}
          />

          <Bouton libelle={donnees.avis ? 'Modifier mon avis' : 'Envoyer mon avis'} plein chargement={envoi} onPress={envoyer} style={{ marginTop: 24 }} />
        </>
      )}
    </Ecran>
  );
}
