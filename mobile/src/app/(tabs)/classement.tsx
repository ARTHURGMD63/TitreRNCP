/**
 * Classement XP — classement.php.
 *
 * Le rond de retour et « Classement », les trois périmètres (Amis, Mon
 * école, Clermont), le podium 2 · 1 · 3 (la première marche en volt), puis la
 * liste où ma ligne est l'aplat lave, et « Plus que N XP pour dépasser X ».
 * Les deux cas vides du site : école non renseignée, personne de suivi.
 */

import React, { useCallback, useState } from 'react';
import { Pressable, Text, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';

import { api, type LigneClassement, type ReponseClassement } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Avatar, BoutonRetour, Segments } from '../../composants/Elements';
import { Display, T } from '../../composants/Texte';
import { court, nombre } from '../../format';
import { useJeton } from '../../session';
import { fixe, fs, lh, mono, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

type Portee = 'amis' | 'ecole' | 'ville';

export default function Classement() {
  const { c, ombre } = useTheme();
  const jeton = useJeton();
  const [portee, setPortee] = useState<Portee>('amis');
  const [donnees, setDonnees] = useState<ReponseClassement | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      setDonnees(await api.classement(jeton, portee));
    } catch (e) {
      setErreur(e instanceof Error ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
    }
  }, [jeton, portee]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  const ouvrir = (l: LigneClassement) => (l.moi ? router.navigate('/moi') : router.push({ pathname: '/etudiant/[id]', params: { id: String(l.id) } }));

  const lignes = donnees?.lignes ?? [];
  const podium = lignes.slice(0, 3);
  const suite = lignes.slice(3);
  const couleursPodium = [c.bleu, c.orange, c.rouge];

  const videCarte = (texte: string, bouton: string, vers: () => void) => (
    <View style={[{ backgroundColor: c.blanc, borderRadius: rayon.base, borderWidth: 1, borderColor: c.grisClair, paddingVertical: 36, paddingHorizontal: 18, alignItems: 'center' }, ombre('sm')]}>
      <T taille={fs[5]} couleur={c.grisFonce} style={{ textAlign: 'center', marginBottom: 14 }}>{texte}</T>
      <Bouton libelle={bouton} onPress={vers} style={{ alignSelf: 'center' }} />
    </View>
  );

  return (
    <Ecran rafraichit={rafraichit} onRafraichir={() => { setRafraichit(true); void charger(); }}>
      <EnTete>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 14, marginBottom: 22 }}>
          <BoutonRetour vers="/moi" libelle="Retour à mon profil" />
          <Display taille={fs[8]} interligne={lh.tight} accessibilityRole="header">Classement</Display>
        </View>
        <Segments
          options={[{ code: 'amis', libelle: 'Amis' }, { code: 'ecole', libelle: 'Mon école' }, { code: 'ville', libelle: 'Clermont' }]}
          valeur={portee}
          onChange={(p) => { setPortee(p as Portee); setDonnees(null); }}
        />
      </EnTete>

      <Contenu style={{ paddingTop: 12 }}>
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : donnees === null ? <Chargement /> : donnees.sans_ecole ? (
          videCarte("Ton école n'est pas renseignée : impossible de te classer avec elle.", 'Compléter mon profil', () => router.navigate('/moi'))
        ) : donnees.sans_amis ? (
          videCarte('Tu ne suis encore personne : ton classement entre amis est vide.', 'Trouver des étudiants', () => router.navigate({ pathname: '/', params: { vue: 'people' } }))
        ) : (
          <>
            {podium.length ? (
              <View style={{ flexDirection: 'row', gap: 10, alignItems: 'flex-end', marginTop: 8, marginBottom: 22 }}>
                {[1, 0, 2].map((i) => {
                  const l = podium[i];
                  if (!l) return <View key={i} style={{ flex: 1 }} />;
                  const premier = i === 0;
                  return (
                    <Pressable key={i} onPress={() => ouvrir(l)} style={{ flex: 1, alignItems: 'center', gap: 8, minWidth: 0 }}>
                      <View>
                        {/* box-shadow: 0 0 0 3px var(--bg), 0 0 0 5px var(--lime) — hors du flux */}
                        {premier ? <View style={{ position: 'absolute', top: -5, left: -5, right: -5, bottom: -5, borderRadius: 40, borderWidth: 2, borderColor: c.lime }} /> : null}
                        <Avatar photo={l.photo_url} prenom={l.prenom} taille={premier ? 64 : 52} fond={couleursPodium[i]} />
                      </View>
                      <T numberOfLines={1} taille={fs[3]} poids={700}>{l.moi ? 'Toi' : l.prenom}</T>
                      <View style={{
                        alignSelf: 'stretch', borderRadius: rayon.md, borderWidth: 1, alignItems: 'center', justifyContent: 'center', gap: 4, paddingVertical: 12, paddingHorizontal: 6,
                        backgroundColor: premier ? c.lime : c.blanc, borderColor: premier ? c.lime : c.grisClair, minHeight: premier ? 118 : i === 1 ? 92 : 76,
                      }}>
                        <Display taille={fs[8]} couleur={premier ? fixe.surLave : c.noir}>{l.rang}</Display>
                        <Text numberOfLines={1} style={{ fontFamily: mono(400), fontSize: fs[1], color: premier ? 'rgba(17,16,19,0.72)' : c.gris }}>{nombre(l.xp)} XP</Text>
                      </View>
                    </Pressable>
                  );
                })}
              </View>
            ) : null}

            <View style={{ gap: 8 }}>
              {suite.map((l) => <Ligne key={l.id} l={l} onPress={() => ouvrir(l)} />)}
              {donnees.moi && !lignes.some((l) => l.id === donnees.moi!.id) ? (
                <>
                  <Text style={{ textAlign: 'center', color: c.gris, fontFamily: mono(400), fontSize: fs[2], paddingVertical: 2 }}>···</Text>
                  <Ligne l={{ ...donnees.moi, photo_url: null }} onPress={() => router.navigate('/moi')} />
                </>
              ) : null}
            </View>

            {donnees.devant ? (
              <T taille={fs[3]} couleur={c.gris} style={{ marginTop: 18, textAlign: 'center' }}>
                Plus que {nombre(donnees.devant.ecart)} XP pour dépasser {donnees.devant.prenom}.
              </T>
            ) : donnees.moi ? (
              <T taille={fs[3]} couleur={c.gris} style={{ marginTop: 18, textAlign: 'center' }}>Tu es en tête. Garde la cadence.</T>
            ) : null}
          </>
        )}
      </Contenu>
    </Ecran>
  );
}

function Ligne({ l, onPress }: { l: LigneClassement; onPress: () => void }) {
  const { c } = useTheme();
  const moi = l.moi;
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="link"
      style={{ flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12, paddingHorizontal: 16, borderRadius: rayon.md, borderWidth: 1, backgroundColor: moi ? c.rouge : c.blanc, borderColor: moi ? c.rouge : c.grisClair }}
    >
      <Text style={{ fontFamily: mono(400), fontSize: fs[3], color: moi ? fixe.surLave : c.gris, minWidth: 16 }}>{l.rang}</Text>
      <Avatar photo={l.photo_url} prenom={l.prenom} taille={34} fond={moi ? '#F5F1E8' : c.bleu} />
      <Text numberOfLines={1} style={{ flex: 1, fontFamily: sans(700), fontSize: fs[4], color: moi ? fixe.surLave : c.noir }}>{moi ? 'Toi' : court(l.prenom, l.nom)}</Text>
      <Text style={{ fontFamily: mono(400), fontSize: fs[3], color: moi ? fixe.surLave : c.grisFonce }}>{nombre(l.xp)}</Text>
    </Pressable>
  );
}
