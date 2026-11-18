/**
 * La fiche d'une soirée — view_event.php.
 *
 * Le hero porte la photo du lieu sous un voile dégradé, sinon (depuis le
 * 2026-11-18) un dégradé de la couleur de la rubrique — bleu, ou rouge pour
 * une séance de sport — frappé en filigrane de l'icône du type de lieu, à la
 * place de la surface rayée grise. Puis le retour, « Partager », le statut
 * et la date, l'étiquette « BAR · JEU 12 MARS · TECHNO », le titre, le lieu
 * et la réduction. Puis la jauge d'inscription, la
 * description, les photos du lieu, les amis qui y vont, la date et le lieu,
 * et « Rejoindre l'événement ».
 */

import React, { useCallback, useState } from 'react';
import { Image, ImageBackground, ScrollView, Share, Text, View, useWindowDimensions } from 'react-native';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { LinearGradient } from 'expo-linear-gradient';
import * as ImagePicker from 'expo-image-picker';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { actions, adresseServeur, api, ErreurApi, type EvenementDetail, type PhotoEvenement, type ReponsePhotosEvenement } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { FORMATS_SPORT, ICONES_TYPE, LIBELLES_TYPE, SPORTS } from '../../catalogue';
import { PressableAnime } from '../../composants/Animations';
import { libelleStyle, nomsCollaborateurs, PastilleAVenir, PastilleEnCours } from '../../composants/CarteEvenement';
import { Pastille, useRubrique } from '../../composants/Design';
import { Chargement, Ecran, Erreur } from '../../composants/Ecran';
import { Avatar, BoutonRetour, Jauge } from '../../composants/Elements';
import { Feuille } from '../../composants/Feuille';
import { Champ, Selecteur } from '../../composants/Formulaire';
import { Icone, type NomIcone } from '../../composants/Icone';
import { Display, Mono, T } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { confirmer } from '../../confirmer';
import { libelleApresEchecInscription, libelleInscription, prixEvenement } from '../../regles';
import { dateFr, heure, majuscules, ts } from '../../format';
import { useJeton } from '../../session';
import { couleurs, espace, fixe, fs, gutter, lh, lsEm, mono, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

const MOTIFS_PHOTO = [
  { valeur: 'n_apparait_pas', libelle: "Je ne veux pas apparaître" },
  { valeur: 'contenu_inapproprie', libelle: 'Contenu inapproprié' },
  { valeur: 'spam', libelle: 'Spam ou publicité' },
  { valeur: 'autre', libelle: 'Autre' },
];

function Section({ icone, children }: { icone?: NomIcone; children: string }) {
  const { c } = useTheme();
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 7, marginBottom: 12 }}>
      {icone ? <Icone nom={icone} taille={16} couleur={c.gris} /> : null}
      <Mono accessibilityRole="header">{children}</Mono>
    </View>
  );
}

export default function FicheEvenement() {
  const { c } = useTheme();
  const bleu = useRubrique('bleu');
  const sport = useRubrique('sport');
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

  const [photosData, setPhotosData] = useState<ReponsePhotosEvenement | null>(null);
  const [publicationEnCours, setPublicationEnCours] = useState<'camera' | 'galerie' | null>(null);
  const [photoOuverte, setPhotoOuverte] = useState<PhotoEvenement | null>(null);
  const [suppressionEnCours, setSuppressionEnCours] = useState(false);
  const [signalementPhoto, setSignalementPhoto] = useState(false);
  const [motifPhoto, setMotifPhoto] = useState('n_apparait_pas');
  const [detailsPhoto, setDetailsPhoto] = useState('');
  const [envoiSignalement, setEnvoiSignalement] = useState(false);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      setE((await api.evenement(jeton, Number(id))).evenement);
      // L'inscription faite ici n'est qu'une avance sur le serveur : au
      // rechargement (retour de l'onglet Pass après une annulation), c'est
      // deja_inscrit qui décide, sinon le bouton restait sur « Inscrit ».
      setInscription('libre');
      setLibelleErreur(null);
    } catch (err) {
      setErreur(err instanceof Error ? err.message : 'Événement introuvable.');
    }
  }, [jeton, id]);

  const chargerPhotos = useCallback(async () => {
    try {
      setPhotosData(await api.photosEvenement(jeton, Number(id)));
    } catch {
      // Une soirée sans photo n'est pas une erreur à afficher : la section
      // reste simplement absente.
    }
  }, [jeton, id]);

  useFocusEffect(useCallback(() => { void charger(); void chargerPhotos(); }, [charger, chargerPhotos]));

  async function choisirEtPublier(source: 'camera' | 'galerie') {
    const options: ImagePicker.ImagePickerOptions = { mediaTypes: ['images'], allowsEditing: true, quality: 0.85 };
    let choix: ImagePicker.ImagePickerResult;
    if (source === 'camera') {
      const permission = await ImagePicker.requestCameraPermissionsAsync();
      if (!permission.granted) {
        toast("Autorise l'accès à la caméra pour prendre une photo.", 'error');
        return;
      }
      choix = await ImagePicker.launchCameraAsync(options);
    } else {
      choix = await ImagePicker.launchImageLibraryAsync(options);
    }
    if (choix.canceled || !choix.assets[0]) return;

    const photo = choix.assets[0];
    setPublicationEnCours(source);
    try {
      const f = new FormData();
      f.append('evenement_id', String(id));
      f.append('photo', { uri: photo.uri, name: photo.fileName ?? 'photo.jpg', type: photo.mimeType ?? 'image/jpeg' } as unknown as Blob);
      const rep = await api.publierPhotoEvenement(jeton, f);
      // Une seule photo par personne : la nouvelle remplace la sienne dans la
      // liste locale au lieu de s'y ajouter.
      setPhotosData((d) => (d ? { ...d, photos: [rep.photo, ...d.photos.filter((p) => !p.moi)] } : d));
      toast(rep.remplace ? 'Photo remplacée !' : 'Photo publiée !', 'success');
    } catch (err) {
      toast(err instanceof ErreurApi ? err.message : 'Erreur réseau', 'error');
    } finally {
      setPublicationEnCours(null);
    }
  }

  async function supprimerPhotoCourante() {
    if (!photoOuverte) return;
    if (!(await confirmer('Supprimer cette photo ?'))) return;
    setSuppressionEnCours(true);
    try {
      await api.supprimerPhotoEvenement(jeton, photoOuverte.id);
      setPhotosData((d) => (d ? { ...d, photos: d.photos.filter((p) => p.id !== photoOuverte.id) } : d));
      setPhotoOuverte(null);
    } catch (err) {
      toast(err instanceof ErreurApi ? err.message : 'Erreur réseau', 'error');
    } finally {
      setSuppressionEnCours(false);
    }
  }

  async function envoyerSignalementPhoto() {
    if (!photoOuverte) return;
    setEnvoiSignalement(true);
    try {
      await api.signalerPhotoEvenement(jeton, { photo_id: photoOuverte.id, motif: motifPhoto, details: detailsPhoto });
      toast('Signalement envoyé. Merci.', 'success');
      setSignalementPhoto(false);
      setPhotoOuverte(null);
      setMotifPhoto('n_apparait_pas');
      setDetailsPhoto('');
    } catch (err) {
      toast(err instanceof ErreurApi ? err.message : 'Erreur réseau', 'error');
    } finally {
      setEnvoiSignalement(false);
    }
  }

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
  // Sans photo, le hero est un aplat de la rubrique : texte blanc dans les
  // deux cas, secondaire en surAccent2 (4,5:1 au moins sur l'aplat).
  const teinte = e.sport ? sport : bleu;
  const encre = photo ? fixe.craie : fixe.surAccent;
  const accent = photo ? couleurs.sombre.surRougeClair : teinte.surAccent2;
  const secondaireHero = photo ? fixe.craie : teinte.surAccent2;
  const fin = ts(e.date_fin ?? null);
  const enCours = instant >= ts(e.date_heure) && (!fin || instant <= fin);
  const pct = e.places.pourcentage ?? 0;
  const style = e.style_musique ? ' · ' + majuscules(libelleStyle(e.style_musique)) : '';
  const inscrit = e.deja_inscrit || inscription === 'vient';
  // Clôture des inscriptions (v38) : celle de l'établissement, sinon le début.
  const cloture = e.inscriptions_jusqu_au ?? e.date_heure;
  const closes = e.inscriptions_ouvertes === false || ts(cloture) <= instant;

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
      setLibelleErreur(libelleApresEchecInscription(err instanceof ErreurApi ? err.statut : null, msg));
      setInscription('libre');
      toast(msg, 'error');
    }
  }

  // padding-top: 120px, et margin-top: -20px sur le site.
  const prix = prixEvenement(e);
  const contenuHero = (
    <View style={{ paddingTop: 96 + top, paddingHorizontal: gutter, paddingBottom: 28 }}>
      <View style={{ flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 6, marginBottom: 14 }}>
        {enCours ? <PastilleEnCours surAccent /> : <PastilleAVenir surAccent />}
        {flash ? <Pastille grande icone="eclair" libelle="Offre flash" fond={fixe.voileAccent} encre={fixe.surAccent} /> : null}
        {e.sponsorise ? <Pastille grande libelle="Sponsorisé" fond={fixe.voileAccent} encre={fixe.surAccent} /> : null}
      </View>
      <Text style={{ fontFamily: sans(600), fontSize: fs[2], letterSpacing: lsEm.label * fs[2], textTransform: 'uppercase', color: accent, marginBottom: 8 }}>
        {majuscules(e.sport ? `${SPORTS[e.sport] ?? e.sport}${e.format ? ` · ${FORMATS_SPORT[e.format] ?? e.format}` : ''}` : (LIBELLES_TYPE[e.etablissement.type] ?? e.etablissement.type))} · {dateFr(e.date_heure, 'D j M')}{style}
      </Text>
      <View accessibilityRole="header">
        <Display taille={fs[7]} interligne={lh.display} couleur={encre}>{e.titre}</Display>
        {/* La hiérarchie passe par la taille, plus par l'opacité : un texte
            blanc à 85 % sur le dégradé tombait sous 4,5:1. */}
        <T taille={fs[5]} poids={600} interligne={lh.snug} couleur={secondaireHero} style={{ marginTop: 6 }}>{e.etablissement.nom}</T>
        {e.collaborateurs?.length ? (
          <T taille={fs[4]} couleur={secondaireHero} style={{ marginTop: 4 }}>
            En collaboration avec <T taille={fs[4]} poids={700} couleur={encre}>{nomsCollaborateurs(e.collaborateurs)}</T>
          </T>
        ) : null}
      </View>
      {prix ? (
        <T taille={fs[5]} poids={700} couleur={encre} style={{ marginTop: 14 }}>
          {prix.barre ? `${prix.final}  ·  au lieu de ${prix.barre}` : prix.final}
        </T>
      ) : null}
      {(e.reduction ?? 0) > 0 || e.is_gratuit ? (
        <View style={{ alignSelf: 'flex-start', marginTop: 16, backgroundColor: fixe.surAccent, borderRadius: rayon.sm, paddingVertical: 7, paddingHorizontal: 14 }}>
          <Display taille={fs[5] + 1} interligne={1.2} couleur={photo ? bleu.accentDeep : teinte.accentDeep}>
            {(e.reduction ?? 0) > 0 ? `-${e.reduction}%` : 'Gratuit'}
          </Display>
        </View>
      ) : null}
    </View>
  );

  const arrondi = { borderBottomLeftRadius: rayon.xl, borderBottomRightRadius: rayon.xl, overflow: 'hidden' as const };

  return (
    <Ecran
      avecBarre={false}
      plein
      onRafraichir={charger}
      horsDefilement={
        <>
          <Feuille visible={!!photoOuverte && !signalementPhoto} onClose={() => setPhotoOuverte(null)}>
            {photoOuverte ? (
              <>
                <Image source={{ uri: photoOuverte.url ?? undefined }} resizeMode="cover"
                  style={{ width: '100%', aspectRatio: 3 / 4, borderRadius: rayon.md, marginBottom: 16 }} />
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10, marginBottom: 20 }}>
                  <Avatar photo={photoOuverte.auteur.photo_url} prenom={photoOuverte.auteur.prenom} taille={32} />
                  <T taille={fs[4]} poids={700}>{photoOuverte.auteur.prenom}</T>
                </View>
                {photoOuverte.moi ? (
                  <Bouton libelle="Supprimer ma photo" variante="alerte" plein chargement={suppressionEnCours} onPress={supprimerPhotoCourante} />
                ) : (
                  <Bouton libelle="Signaler cette photo" variante="contour" plein onPress={() => setSignalementPhoto(true)} />
                )}
              </>
            ) : null}
          </Feuille>

          <Feuille visible={signalementPhoto} onClose={() => setSignalementPhoto(false)}>
            <Display taille={fs[6]} interligne={lh.tight} style={{ marginBottom: 6, letterSpacing: lsEm.tight * fs[7] }}>Signaler cette photo</Display>
            <T taille={fs[3]} poids={500} couleur={c.grisFonce} interligne={lh.snug} style={{ marginBottom: 20 }}>
              Ton signalement est envoyé à l&apos;équipe de modération.
            </T>
            <Selecteur etiquette="Motif" valeur={motifPhoto} onChange={setMotifPhoto} options={MOTIFS_PHOTO} />
            <Champ etiquette="Précisions (facultatif)" value={detailsPhoto} onChangeText={setDetailsPhoto} multiligne maxLength={500}
              placeholder="Ce qui te dérange sur cette photo." numberOfLines={3} />
            <Bouton libelle="Envoyer le signalement" plein chargement={envoiSignalement} onPress={envoyerSignalementPhoto} />
            <Bouton libelle="Annuler" variante="contour" plein onPress={() => setSignalementPhoto(false)} style={{ marginTop: 12 }} />
          </Feuille>
        </>
      }
    >
      {photo ? (
        <ImageBackground source={{ uri: photo }} accessibilityLabel={e.photos[0]?.legende ?? `Photo de ${e.etablissement.nom}`} style={arrondi}>
          <LinearGradient
            colors={['rgba(17,17,22,0.94)', 'rgba(17,17,22,0.74)', 'rgba(17,17,22,0.34)', 'rgba(17,17,22,0.18)']}
            locations={[0, 0.32, 0.62, 1]}
            start={{ x: 0, y: 1 }} end={{ x: 0, y: 0 }}
            style={{ position: 'absolute', top: 0, left: 0, right: 0, bottom: 0 }}
          />
          {contenuHero}
        </ImageBackground>
      ) : (
        <View style={arrondi}>
          <LinearGradient
            colors={[teinte.accent, teinte.accentDeep]}
            start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }}
            style={{ position: 'absolute', top: 0, left: 0, right: 0, bottom: 0 }}
          />
          {/* Le filigrane : l'icône du type de lieu, très grande, coupée par le bord. */}
          <View pointerEvents="none" accessible={false} style={{ position: 'absolute', right: -36, bottom: -28, opacity: 0.14 }}>
            <Icone nom={(e.sport ? 'balle' : ICONES_TYPE[e.etablissement.type]) ?? 'calendrier'} taille={210} couleur="#FFFFFF" trait={1.5} />
          </View>
          {contenuHero}
        </View>
      )}

      <BoutonRetour media style={{ position: 'absolute', top: top + 36, left: gutter }} />
      {/* Même gabarit que le bouton de retour : 42 px, rayon 12, basalte. */}
      <PressableAnime
        onPress={partager}
        accessibilityRole="button"
        // 42 px visibles, 44 au toucher (constat MOB-09).
        hitSlop={1}
        style={{ position: 'absolute', top: top + 36, right: gutter, flexDirection: 'row', alignItems: 'center', gap: 7, height: 42, paddingHorizontal: 14, borderRadius: rayon.sm, backgroundColor: fixe.basalte }}
      >
        <Icone nom="partage" taille={16} couleur={fixe.craie} />
        <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: fixe.craie }}>Partager</Text>
      </PressableAnime>

      <View style={{ paddingHorizontal: gutter, marginTop: 24 }}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
            <Icone nom="personnes" taille={20} couleur={c.grisFonce} />
            <T taille={fs[4]} poids={700}>{e.places.inscrits} / {e.places.quota} inscrits</T>
          </View>
          <Text style={{ fontFamily: mono(600), fontSize: fs[4], color: teinte.texteDoux }}>{pct}%</Text>
        </View>
        <Jauge pourcentage={pct} piste={c.grisClair} remplissage={teinte.accent} hauteur={8} />

        {photosData && !photosData.reserve_participants ? (
          <View style={{ marginTop: 32 }}>
            <Section icone="appareil">{photosData.en_cours ? 'Photos de la soirée' : 'Tes photos de cette soirée'}</Section>

            {photosData.peut_publier ? (
              <View style={{ flexDirection: 'row', gap: 10, marginBottom: 14 }}>
                <Bouton
                  variante="primaire" icone="appareil" libelle="Prendre une photo"
                  chargement={publicationEnCours === 'camera'} desactive={publicationEnCours === 'galerie'}
                  onPress={() => choisirEtPublier('camera')}
                />
                <Bouton
                  variante="primaire" icone="image" libelle="Galerie"
                  chargement={publicationEnCours === 'galerie'} desactive={publicationEnCours === 'camera'}
                  onPress={() => choisirEtPublier('galerie')}
                />
              </View>
            ) : null}

            {photosData.photos.length ? (
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 12, paddingBottom: 8 }}>
                {photosData.photos.map((p) => (
                  <PressableAnime key={p.id} onPress={() => setPhotoOuverte(p)} accessibilityRole="button" accessibilityLabel={`Photo de ${p.auteur.prenom}`}>
                    <Image source={{ uri: p.url ?? undefined }} style={{ height: 200, width: 150, borderRadius: rayon.md }} resizeMode="cover" />
                    <View style={{ position: 'absolute', bottom: 8, left: 8, flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                      <Avatar photo={p.auteur.photo_url} prenom={p.auteur.prenom} taille={22} />
                      <Text style={{ fontFamily: sans(700), fontSize: fs[2], color: fixe.craie, textShadowColor: 'rgba(0,0,0,0.6)', textShadowRadius: 4 }}>{p.auteur.prenom}</Text>
                    </View>
                  </PressableAnime>
                ))}
              </ScrollView>
            ) : (
              <T taille={fs[3]} couleur={c.gris}>
                {photosData.peut_publier ? 'Sois le premier à partager une photo de cette soirée.' : 'Aucune photo pour le moment.'}
              </T>
            )}
          </View>
        ) : null}

        <View style={{ marginTop: 32 }}>
          <Section icone="info">À propos de l&apos;événement</Section>
          <T taille={fs[4]} interligne={lh.relaxed} couleur={c.grisFonce}>{e.description}</T>
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
                <PressableAnime key={f.id} onPress={() => router.push({ pathname: '/etudiant/[id]', params: { id: String(f.id) } })}
                  accessibilityRole="link" accessibilityLabel={`Profil de ${f.prenom}`}
                  style={{ flexDirection: 'row', alignItems: 'center', gap: 8, minHeight: 40, backgroundColor: c.blanc, borderRadius: rayon.sm, paddingVertical: 5, paddingLeft: 5, paddingRight: 14 }}>
                  <Avatar photo={f.photo_url} prenom={f.prenom} taille={28} />
                  <T taille={fs[3]} poids={600}>{f.prenom}</T>
                </PressableAnime>
              ))}
            </View>
          </View>
        ) : null}

        <View style={{ flexDirection: 'row', gap: 12, marginTop: 24 }}>
          <View style={{ flex: 1, backgroundColor: c.blanc, borderRadius: rayon.tuile, padding: espace.carte }}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 7, marginBottom: 8 }}>
              <Icone nom="horloge" taille={16} couleur={c.gris} />
              <Mono>Date &amp; heure</Mono>
            </View>
            <T taille={fs[4]} poids={700}>{dateFr(e.date_heure, 'j M Y')}</T>
            <Text style={{ fontFamily: mono(400), fontSize: fs[4], color: c.grisFonce }}>{heure(e.date_heure)}{e.date_fin ? ` – ${heure(e.date_fin)}` : ''}</Text>
          </View>
          <View style={{ flex: 1, backgroundColor: c.blanc, borderRadius: rayon.tuile, padding: espace.carte }}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 7, marginBottom: 8 }}>
              <Icone nom="epingle" taille={16} couleur={c.gris} />
              <Mono>Lieu</Mono>
            </View>
            <T taille={fs[4]} poids={700}>{e.etablissement.nom}</T>
            <T taille={fs[3]} couleur={c.gris}>{e.etablissement.ville}</T>
          </View>
        </View>

        {!inscrit ? (
          <View testID="evenement-cloture" style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 12, backgroundColor: closes ? c.surface2 : c.blanc, borderRadius: rayon.tuile, padding: espace.carte }}>
            <Icone nom={closes ? 'cadenas' : 'horloge'} taille={16} couleur={closes ? c.grisFonce : c.gris} />
            <T taille={fs[4]} poids={600} couleur={closes ? c.grisFonce : c.noir} style={{ flex: 1 }}>
              {closes
                ? (e.cloture_anticipee ? `Inscriptions closes depuis le ${dateFr(cloture, 'j M à H\\hi')}` : 'Inscriptions closes : la soirée a commencé')
                : `Inscriptions ouvertes jusqu'au ${dateFr(cloture, 'D j M à H\\hi')}`}
            </T>
          </View>
        ) : null}

        <View style={{ marginTop: 40, marginBottom: 60 }}>
          <Bouton
            plein
            testID="evenement-rejoindre"
            variante="primaire"
            accent={teinte.accent}
            libelle={libelleInscription(inscription, !!e.deja_inscrit, libelleErreur, e.places.complet, closes)}
            fait={inscrit}
            desactive={e.places.complet || closes}
            chargement={inscription === 'attente'}
            onPress={rejoindre}
          />
        </View>
      </View>
    </Ecran>
  );
}
