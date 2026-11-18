/**
 * Photos de l'établissement — partenaire/photos.php.
 *
 * Upload (galerie ou caméra), suppression, et choix de la photo de
 * couverture (position 0). Limite de PARTENAIRE_PHOTOS_MAX côté serveur,
 * voir partenaire_photos.php.
 */

import React, { useCallback, useState } from 'react';
import { Image, Pressable, View } from 'react-native';
import { useFocusEffect } from 'expo-router';
import * as ImagePicker from 'expo-image-picker';

import { api, ErreurApi, type PhotoEtablissement, type ReponsePhotosPartenaire } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Pastille } from '../../composants/Design';
import { BoutonRetour, Encart } from '../../composants/Elements';
import { Icone } from '../../composants/Icone';
import { T, TitreEcran } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { confirmer } from '../../confirmer';
import { useJeton } from '../../session';
import { espace, fs, rayon } from '../../theme';
import { useTheme } from '../../useTheme';

export default function PhotosPartenaire() {
  const { c } = useTheme();
  const jeton = useJeton();
  const toast = useToast();

  const [donnees, setDonnees] = useState<ReponsePhotosPartenaire | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [envoi, setEnvoi] = useState(false);
  const [enCours, setEnCours] = useState<number | null>(null);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      setDonnees(await api.partenairePhotos(jeton));
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Chargement impossible.');
    }
  }, [jeton]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  async function ajouter(source: 'camera' | 'galerie') {
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
    setEnvoi(true);
    try {
      const f = new FormData();
      f.append('photo', { uri: photo.uri, name: photo.fileName ?? 'photo.jpg', type: photo.mimeType ?? 'image/jpeg' } as unknown as Blob);
      const rep = await api.partenaireAjouterPhoto(jeton, f);
      setDonnees((d) => (d ? { ...d, photos: [...d.photos, rep.photo] } : d));
      toast('Photo ajoutée.', 'success');
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setEnvoi(false);
    }
  }

  async function supprimer(photo: PhotoEtablissement) {
    if (!(await confirmer('Supprimer cette photo ?'))) return;
    setEnCours(photo.id);
    try {
      await api.partenaireSupprimerPhoto(jeton, photo.id);
      setDonnees((d) => (d ? { ...d, photos: d.photos.filter((p) => p.id !== photo.id) } : d));
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setEnCours(null);
    }
  }

  async function definirCouverture(photo: PhotoEtablissement) {
    setEnCours(photo.id);
    try {
      await api.partenaireDefinirCouverture(jeton, photo.id);
      toast('Photo de couverture mise à jour.', 'success');
      await charger();
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setEnCours(null);
    }
  }

  if (!donnees) {
    return (
      <Ecran>
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : <Chargement />}
      </Ecran>
    );
  }

  const { max, photos } = donnees;
  const complet = photos.length >= max;

  return (
    <Ecran>
      <EnTete style={{ paddingBottom: 4 }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 18 }}>
          <BoutonRetour vers="/moi" libelle="Retour à Moi" />
          <Pastille libelle={`${photos.length} / ${max} photos`} icone="image" fond={c.blanc} encre={c.noir} grande />
        </View>
        <TitreEcran avant="Photos de" surligne="l'établissement." style={{ marginBottom: 16 }} />
      </EnTete>
      <Contenu style={{ paddingTop: 0 }}>
        {complet ? (
          <Encart genre="info">Tu as atteint la limite de {max} photos. Supprimes-en une pour en ajouter une nouvelle.</Encart>
        ) : null}

        <View style={{ flexDirection: 'row', gap: 10, marginBottom: 16 }}>
          <Bouton style={{ flex: 1 }} libelle="Galerie" icone="image" chargement={envoi} desactive={complet} onPress={() => void ajouter('galerie')} />
          <Bouton style={{ flex: 1 }} libelle="Caméra" variante="contour" icone="appareil" chargement={envoi} desactive={complet} onPress={() => void ajouter('camera')} />
        </View>

        {photos.length === 0 ? (
          <View style={{ alignItems: 'center', ...espace.vide, backgroundColor: c.blanc, borderRadius: rayon.carte }}>
            <Icone nom="image" taille={32} couleur={c.gris} />
            <T taille={fs[4]} couleur={c.gris} style={{ marginTop: 12 }}>Aucune photo pour le moment.</T>
          </View>
        ) : (
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 10 }}>
            {photos.map((p, i) => (
              <View key={p.id} style={{ width: '48%' }}>
                <View style={{ borderRadius: rayon.tuile, overflow: 'hidden', aspectRatio: 1, backgroundColor: c.surface2 }}>
                  {p.url ? <Image source={{ uri: p.url }} style={{ width: '100%', height: '100%' }} /> : null}
                  {i === 0 ? (
                    <View style={{ position: 'absolute', top: 8, left: 8, backgroundColor: c.rouge, borderRadius: rayon.mini, paddingHorizontal: 9, paddingVertical: 4, flexDirection: 'row', alignItems: 'center', gap: 4 }}>
                      <Icone nom="etoile" taille={12} couleur="#FFFFFF" />
                      <T taille={fs[2]} poids={700} couleur="#FFFFFF">Couverture</T>
                    </View>
                  ) : null}
                  <Pressable
                    onPress={() => void supprimer(p)}
                    disabled={enCours === p.id}
                    accessibilityRole="button"
                    accessibilityLabel="Supprimer cette photo"
                    hitSlop={8}
                    style={{ position: 'absolute', top: 6, right: 6, width: 36, height: 36, borderRadius: rayon.mini, backgroundColor: 'rgba(17,17,22,0.6)', alignItems: 'center', justifyContent: 'center' }}
                  >
                    <Icone nom="croix" taille={14} couleur="#fff" />
                  </Pressable>
                </View>
                {i !== 0 ? (
                  <Pressable onPress={() => void definirCouverture(p)} disabled={enCours === p.id} accessibilityRole="button"
                    style={{ minHeight: 44, alignItems: 'center', justifyContent: 'center' }}>
                    <T taille={fs[4]} poids={600} couleur={c.surRougeClair}>Définir en couverture</T>
                  </Pressable>
                ) : null}
              </View>
            ))}
          </View>
        )}
      </Contenu>
    </Ecran>
  );
}
