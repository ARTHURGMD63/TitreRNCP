/**
 * Modifier le profil — le formulaire « Mon Profil » qui vivait dans Moi.
 *
 * Refonte 2026-11 : il quitte la vitrine de Moi pour son propre écran,
 * ouvert par « Modifier ». Rien ne change dans ce qu'il fait : mêmes champs
 * (photo, retrait de la photo, école, promo, centres d'intérêt, compte
 * public), même chargement (moi.php), même enregistrement (profil.php), mêmes
 * messages. Les hashtags du profil sont ses centres d'intérêt : « Tes
 * hashtags » en montre l'aperçu, tel qu'il s'affichera dans Moi.
 *
 * Pas de barre d'onglets : un bouton retour en haut, « Enregistrer » fixé en
 * bas.
 */

import React, { useCallback, useRef, useState } from 'react';
import { ScrollView, View } from 'react-native';
import { useFocusEffect } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import * as ImagePicker from 'expo-image-picker';

import { api, ErreurApi, type ReponseMoi } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { useRubrique } from '../../composants/Design';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Avatar, BoutonRetour, Encart } from '../../composants/Elements';
import { Aide, Case, Etiquette, SelecteurInterets, Selecteur } from '../../composants/Formulaire';
import { T } from '../../composants/Texte';
import { useJeton, useSession } from '../../session';
import { fs, gutter, rayon } from '../../theme';
import { useTheme } from '../../useTheme';

export default function ModifierProfil() {
  const { c } = useTheme();
  const bleu = useRubrique('bleu');
  const jeton = useJeton();
  const { mettreAJour } = useSession();
  const bas = useSafeAreaInsets().bottom;
  const defilement = useRef<ScrollView>(null);

  const [donnees, setDonnees] = useState<ReponseMoi | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);

  const [ecole, setEcole] = useState('UCA');
  const [promo, setPromo] = useState('L1');
  const [interets, setInterets] = useState<string[]>([]);
  const [comptePrive, setComptePrive] = useState(true);
  const [photo, setPhoto] = useState<ImagePicker.ImagePickerAsset | null>(null);
  const [retirer, setRetirer] = useState(false);
  const [enregistrement, setEnregistrement] = useState(false);
  const [succes, setSucces] = useState<string | null>(null);
  const [erreurPhoto, setErreurPhoto] = useState<string | null>(null);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      const r = await api.pageMoi(jeton);
      setDonnees(r);
      // <select> sans valeur correspondante : le navigateur montre la première option.
      setEcole(r.moi.ecole && r.choix.ecoles.includes(r.moi.ecole) ? r.moi.ecole : r.choix.ecoles[0]);
      setPromo(r.moi.promo && r.choix.promos.includes(r.moi.promo) ? r.moi.promo : r.choix.promos[0]);
      setInterets(r.moi.interets);
      setComptePrive(r.moi.compte_prive);
    } catch (e) {
      setErreur(e instanceof Error ? e.message : 'Chargement impossible.');
    }
  }, [jeton]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  async function choisirPhoto() {
    const choix = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], allowsEditing: true, aspect: [1, 1], quality: 0.9 });
    if (!choix.canceled && choix.assets[0]) {
      setPhoto(choix.assets[0]);
      // Choisir une photo annule l'intention de la retirer.
      setRetirer(false);
    }
  }

  async function enregistrer() {
    setEnregistrement(true);
    setSucces(null);
    setErreurPhoto(null);
    try {
      const f = new FormData();
      f.append('ecole', ecole);
      f.append('promo', promo);
      f.append('compte_prive', comptePrive ? '1' : '0');
      interets.forEach((i) => f.append('interets[]', i));
      if (retirer) f.append('supprimer_photo', '1');
      if (photo && !retirer) {
        const nom = photo.fileName ?? 'photo.jpg';
        f.append('photo', { uri: photo.uri, name: nom, type: photo.mimeType ?? 'image/jpeg' } as unknown as Blob);
      }
      const rep = await api.enregistrerProfil(jeton, f);
      mettreAJour(rep.utilisateur);
      setSucces(rep.message);
      setErreurPhoto(rep.erreur_photo);
      setPhoto(null);
      setRetirer(false);
      await charger();
      defilement.current?.scrollTo({ y: 0, animated: true });
    } catch (e) {
      setErreurPhoto(e instanceof ErreurApi ? e.message : 'Erreur réseau');
    } finally {
      setEnregistrement(false);
    }
  }

  const enTete = (
    <EnTete style={{ paddingBottom: 16 }}>
      <View style={{ flexDirection: 'row', alignItems: 'center', minHeight: 46 }}>
        <BoutonRetour vers="/moi" libelle="Retour à mon profil" />
        <T taille={20} poids={700} accessibilityRole="header" style={{ flex: 1, textAlign: 'center', marginRight: 46, letterSpacing: -0.02 * 20 }}>
          Modifier le profil
        </T>
      </View>
    </EnTete>
  );

  if (!donnees) {
    return (
      <Ecran avecBarre={false}>
        {enTete}
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : <Chargement />}
      </Ecran>
    );
  }

  const { moi, choix } = donnees;
  const apercu = retirer ? null : photo?.uri ?? moi.photo_url;

  return (
    <Ecran
      avecBarre={false}
      defilementRef={defilement}
      // La place du bouton fixe : rien ne doit passer dessous.
      style={{ paddingBottom: 54 + 24 + bas + 16 }}
      horsDefilement={
        <View style={{ position: 'absolute', left: 0, right: 0, bottom: 0, paddingHorizontal: gutter, paddingTop: 12, paddingBottom: Math.max(bas, 12) + 8, backgroundColor: c.bg }}>
          <View style={{ width: '100%', maxWidth: 440 - gutter * 2, alignSelf: 'center' }}>
            <Bouton libelle="Enregistrer" plein accent={bleu.accent} chargement={enregistrement} onPress={enregistrer} />
          </View>
        </View>
      }
    >
      {enTete}

      <Contenu style={{ paddingTop: 0 }}>
        {succes ? <Encart genre="ok">{succes}</Encart> : null}
        {erreurPhoto ? <Encart genre="erreur">{erreurPhoto}</Encart> : null}

        <View style={{ backgroundColor: c.blanc, borderRadius: rayon.carte, padding: 20, marginBottom: 20 }}>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 16, marginBottom: 20 }}>
            <Avatar photo={apercu} prenom={moi.prenom} taille={72} fond={bleu.accent} />
            <View style={{ flex: 1, minWidth: 0 }}>
              <Bouton variante="contour" icone="televerser" libelle={moi.photo_url ? 'Changer la photo' : 'Choisir une photo'} onPress={choisirPhoto} />
              <T numberOfLines={1} taille={fs[4]} couleur={photo ? c.grisFonce : c.gris} style={{ marginTop: 8 }}>
                {photo ? photo.fileName ?? 'photo.jpg' : 'Aucune image choisie'}
              </T>
              <T taille={fs[4]} couleur={c.gris}>JPG, PNG ou WebP — 2 Mo maximum.</T>
              {moi.photo_url ? (
                <Case coche={retirer} onChange={(v) => { setRetirer(v); if (v) setPhoto(null); }} style={{ marginTop: 4 }}>Retirer ma photo</Case>
              ) : null}
            </View>
          </View>

          <View style={{ flexDirection: 'row', gap: 12, marginBottom: 18 }}>
            <Selecteur style={{ flex: 1 }} marge={0} etiquette="École" valeur={ecole} onChange={setEcole} options={choix.ecoles.map((e) => ({ valeur: e, libelle: e }))} />
            <Selecteur style={{ flex: 1 }} marge={0} etiquette="Promo" valeur={promo} onChange={setPromo} options={choix.promos.map((p) => ({ valeur: p, libelle: p }))} />
          </View>

          <View style={{ marginBottom: 18 }}>
            <Etiquette style={{ marginBottom: 10 }}>Centres d&apos;intérêt</Etiquette>
            <SelecteurInterets catalogue={choix.interets} choisis={interets} onChange={setInterets} />
          </View>

          <View>
            <Case coche={!comptePrive} onChange={(v) => setComptePrive(!v)}>Compte public</Case>
            <Aide>
              {comptePrive
                ? "Compte privé : tes abonnés doivent être acceptés, et tes photos de soirée ne sont visibles que d'eux."
                : 'Compte public : tout le monde peut te suivre sans demande, et voir tes photos de soirée.'}
            </Aide>
          </View>
        </View>

        {interets.length ? (
          <View>
            <Etiquette style={{ marginBottom: 10 }}>Tes hashtags</Etiquette>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }} accessibilityLabel={`Tes hashtags : ${interets.map((i) => '#' + i).join(', ')}`}>
              {interets.map((i) => (
                <View key={i} style={{ minHeight: 44, justifyContent: 'center', paddingHorizontal: 16, borderRadius: rayon.bouton, backgroundColor: c.noir }}>
                  <T taille={fs[5]} poids={500} couleur={c.surEncre}>#{i}</T>
                </View>
              ))}
            </View>
          </View>
        ) : null}
      </Contenu>
    </Ecran>
  );
}
