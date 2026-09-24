/**
 * La fiche d'une soirée — view_event.php.
 *
 * Le hero porte la photo du lieu sous un voile dégradé (ou, sans photo,
 * l'aplat lave d'un flash, ou la surface rayée de la maquette), le rond de
 * retour, « Partager », l'étiquette « BAR · JEU 12 MARS · TECHNO », le titre,
 * le lieu et la réduction en pilule. Puis la jauge d'inscription, la
 * description, les photos du lieu, les amis qui y vont, la date et le lieu,
 * et « Rejoindre l'événement ».
 */

import React, { useCallback, useState } from 'react';
import { Image, ImageBackground, Pressable, ScrollView, Share, Text, View, useWindowDimensions } from 'react-native';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { LinearGradient } from 'expo-linear-gradient';
import Svg, { Defs, Pattern, Rect } from 'react-native-svg';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { actions, adresseServeur, api, ErreurApi, type EvenementDetail } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { libelleStyle } from '../../composants/CarteEvenement';
import { Chargement, Ecran, Erreur } from '../../composants/Ecran';
import { Avatar, Badge, BoutonRetour, Jauge } from '../../composants/Elements';
import { Icone, type NomIcone } from '../../composants/Icone';
import { Display, Mono, T } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { dateFr, heure, majuscules, ts } from '../../format';
import { useJeton } from '../../session';
import { fixe, fs, gutter, lh, lsEm, mono, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

function Section({ icone, children }: { icone?: NomIcone; children: string }) {
  const { c } = useTheme();
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 7, marginBottom: 12 }}>
      {icone ? <Icone nom={icone} taille={16} couleur={c.gris} /> : null}
      <Mono accessibilityRole="header">{children}</Mono>
    </View>
  );
}

/** Le repli sans photo : repeating-linear-gradient(135deg, surface 0 22px, surface-2 22px 44px). */
function Rayures({ a, b }: { a: string; b: string }) {
  return (
    <Svg style={{ position: 'absolute', top: 0, left: 0, right: 0, bottom: 0 }} width="100%" height="100%">
      <Defs>
        <Pattern id="rayures" patternUnits="userSpaceOnUse" width={62.2} height={62.2} patternTransform="rotate(45)">
          <Rect x={0} y={0} width={31.1} height={62.2} fill={a} />
          <Rect x={31.1} y={0} width={31.1} height={62.2} fill={b} />
        </Pattern>
      </Defs>
      <Rect x={0} y={0} width="100%" height="100%" fill="url(#rayures)" />
    </Svg>
  );
}

export default function FicheEvenement() {
  const { c } = useTheme();
  const jeton = useJeton();
  const toast = useToast();
  const { id } = useLocalSearchParams<{ id: string }>();
  const { top } = useSafeAreaInsets();
  const { width } = useWindowDimensions();

  const [e, setE] = useState<EvenementDetail | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [inscription, setInscription] = useState<'libre' | 'attente' | 'vient'>('libre');
  const [libelleErreur, setLibelleErreur] = useState<string | null>(null);
  const [instant] = useState(() => Date.now() / 1000);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      setE((await api.evenement(jeton, Number(id))).evenement);
    } catch (err) {
      setErreur(err instanceof Error ? err.message : 'Événement introuvable.');
    }
  }, [jeton, id]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  if (!e) {
    return (
      <Ecran avecBarre={false}>
        <View style={{ paddingHorizontal: gutter, paddingTop: 16 }}><BoutonRetour /></View>
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : <Chargement />}
      </Ecran>
    );
  }

  const flash = e.is_flash && ts(e.flash_expiry) > instant;
  const photo = e.photos[0]?.url ?? null;
  const encre = photo ? fixe.craie : flash ? fixe.surLave : c.noir;
  const accent = photo ? '#FF5424' : flash ? fixe.surLave : c.surRougeClair;
  const pct = e.places.pourcentage ?? 0;
  const style = e.style_musique ? ' · ' + majuscules(libelleStyle(e.style_musique)) : '';
  const inscrit = e.deja_inscrit || inscription === 'vient';

  async function partager() {
    try {
      await Share.share({
        title: e!.titre,
        message: `${e!.titre} — ${e!.etablissement.nom} · ${dateFr(e!.date_heure, 'D j M')}\n${adresseServeur()}/view_event.php?id=${e!.id}`,
      });
    } catch {
      // Partage annulé : rien à signaler.
    }
  }

  async function rejoindre() {
    setInscription('attente');
    try {
      await actions.inscrire(jeton, e!.id);
      setInscription('vient');
      toast('Tu es inscrit ! Rendez-vous ce soir.', 'success');
    } catch (err) {
      const msg = err instanceof ErreurApi ? err.message : 'Erreur réseau';
      setLibelleErreur(err instanceof ErreurApi && err.statut !== 0 ? msg : '→ je rejoins');
      setInscription('libre');
      toast(msg, 'error');
    }
  }

  // padding-top: 120px, et margin-top: -20px sur le site.
  const contenuHero = (
    <View style={{ paddingTop: 100 + top, paddingHorizontal: 20, paddingBottom: 32 }}>
      <Text style={{ fontFamily: mono(500), fontSize: fs[2], letterSpacing: lsEm.label * fs[2], textTransform: 'uppercase', color: accent, marginBottom: 10 }}>
        {majuscules(e.etablissement.type)} · {dateFr(e.date_heure, 'D j M')}{style}
      </Text>
      {e.sponsorise ? (
        <Badge libelle="Sponsorisé" fond="rgba(17,16,19,0.1)" encre={fixe.surLave} filet="rgba(17,16,19,0.3)" style={{ alignSelf: 'flex-start', marginBottom: 10 }} />
      ) : null}
      <View accessibilityRole="header">
        <Display taille={fs[9]} interligne={lh.display} couleur={encre}>{e.titre}</Display>
        <Display taille={fs[7]} interligne={lh.display} couleur={encre} style={{ opacity: 0.85, marginTop: 6 }}>{e.etablissement.nom}</Display>
      </View>
      {(e.reduction ?? 0) > 0 || e.is_gratuit ? (
        <View style={{ alignSelf: 'flex-start', marginTop: 18, backgroundColor: flash && !photo ? fixe.basalte : c.rouge, borderRadius: rayon.pill, paddingVertical: 8, paddingHorizontal: 20 }}>
          <Display taille={fs[7]} interligne={1.2} couleur={flash && !photo ? fixe.craie : fixe.surLave}>
            {(e.reduction ?? 0) > 0 ? `-${e.reduction}%` : 'Gratuit'}
          </Display>
        </View>
      ) : null}
    </View>
  );

  const arrondi = { borderBottomLeftRadius: rayon.xl, borderBottomRightRadius: rayon.xl, overflow: 'hidden' as const };

  return (
    <Ecran avecBarre={false} plein onRafraichir={charger}>
      {photo ? (
        <ImageBackground source={{ uri: photo }} accessibilityLabel={e.photos[0]?.legende ?? `Photo de ${e.etablissement.nom}`} style={arrondi}>
          <LinearGradient
            colors={['rgba(17,16,19,0.94)', 'rgba(17,16,19,0.74)', 'rgba(17,16,19,0.34)', 'rgba(17,16,19,0.18)']}
            locations={[0, 0.32, 0.62, 1]}
            start={{ x: 0, y: 1 }} end={{ x: 0, y: 0 }}
            style={{ position: 'absolute', top: 0, left: 0, right: 0, bottom: 0 }}
          />
          {contenuHero}
        </ImageBackground>
      ) : (
        <View style={[arrondi, { backgroundColor: flash ? c.rouge : c.blanc }]}>
          {flash ? null : <Rayures a={c.blanc} b={c.surface2} />}
          {contenuHero}
        </View>
      )}

      <BoutonRetour media style={{ position: 'absolute', top: top + 36, left: 20 }} />
      <Pressable
        onPress={partager}
        accessibilityRole="button"
        style={{ position: 'absolute', top: top + 36, right: 20, flexDirection: 'row', alignItems: 'center', gap: 7, minHeight: 44, paddingHorizontal: 18, borderRadius: rayon.pill, backgroundColor: fixe.basalte }}
      >
        <Icone nom="partage" taille={16} couleur={fixe.craie} />
        <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: fixe.craie }}>Partager</Text>
      </Pressable>

      <View style={{ paddingHorizontal: gutter, marginTop: 24 }}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
            <Icone nom="personnes" taille={20} couleur={c.grisFonce} />
            <T taille={fs[4]} poids={700}>{e.places.inscrits} / {e.places.quota} inscrits</T>
          </View>
          <Text style={{ fontFamily: mono(600), fontSize: fs[4], color: c.surRougeClair }}>{pct}%</Text>
        </View>
        <Jauge pourcentage={pct} piste={c.grisClair} remplissage={c.rouge} hauteur={8} />

        <View style={{ marginTop: 32 }}>
          <Section icone="info">À propos de l&apos;événement</Section>
          <T taille={fs[5]} interligne={lh.relaxed} couleur={c.grisFonce}>{e.description}</T>
        </View>

        {e.photos.length ? (
          <View style={{ marginTop: 32 }}>
            <Section icone="image">Le lieu en photos</Section>
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 12, paddingBottom: 8 }}>
              {e.photos.map((p, i) => (
                <View key={i}>
                  <Image source={{ uri: p.url }} accessibilityLabel={p.legende ?? `Photo de ${e.etablissement.nom}`}
                    style={{ height: 180, width: Math.min(280, width * 0.7), borderRadius: rayon.md }} resizeMode="cover" />
                  {p.legende ? <T taille={fs[2]} couleur={c.gris} style={{ marginTop: 6, maxWidth: 280 }}>{p.legende}</T> : null}
                </View>
              ))}
            </ScrollView>
          </View>
        ) : null}

        {e.amis.length ? (
          <View style={{ marginTop: 32 }}>
            <Section>Tes potes qui y vont</Section>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 10 }}>
              {e.amis.map((f) => (
                <Pressable key={f.id} onPress={() => router.push({ pathname: '/etudiant/[id]', params: { id: String(f.id) } })}
                  style={{ flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.pill, paddingVertical: 5, paddingLeft: 5, paddingRight: 14 }}>
                  <Avatar photo={f.photo_url} prenom={f.prenom} taille={24} />
                  <T taille={fs[3]} poids={600}>{f.prenom}</T>
                </Pressable>
              ))}
            </View>
          </View>
        ) : null}

        <View style={{ flexDirection: 'row', gap: 12, marginTop: 24 }}>
          <View style={{ flex: 1, backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.md, padding: 16 }}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 7, marginBottom: 8 }}>
              <Icone nom="horloge" taille={16} couleur={c.gris} />
              <Mono>Date &amp; heure</Mono>
            </View>
            <T taille={fs[4]} poids={700}>{dateFr(e.date_heure, 'j M Y')}</T>
            <Text style={{ fontFamily: mono(400), fontSize: fs[4], color: c.grisFonce }}>{heure(e.date_heure)}</Text>
          </View>
          <View style={{ flex: 1, backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.md, padding: 16 }}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 7, marginBottom: 8 }}>
              <Icone nom="epingle" taille={16} couleur={c.gris} />
              <Mono>Lieu</Mono>
            </View>
            <T taille={fs[4]} poids={700}>{e.etablissement.nom}</T>
            <T taille={fs[3]} couleur={c.gris}>{e.etablissement.ville}</T>
          </View>
        </View>

        <View style={{ marginTop: 40, marginBottom: 60 }}>
          <Bouton
            plein
            variante={inscription === 'vient' ? 'contour' : 'primaire'}
            libelle={inscription === 'vient' ? 'Inscrit' : inscrit ? 'Tu es inscrit·e' : libelleErreur ?? "Rejoindre l'événement"}
            icone={inscrit ? 'check' : undefined}
            desactive={inscrit}
            chargement={inscription === 'attente'}
            onPress={rejoindre}
            style={{ paddingVertical: 18 }}
          />
        </View>
      </View>
    </Ecran>
  );
}
