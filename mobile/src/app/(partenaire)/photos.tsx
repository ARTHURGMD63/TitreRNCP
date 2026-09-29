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
import { Encart } from '../../composants/Elements';
import { Icone } from '../../composants/Icone';
import { Mono, T, TitreEcran } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { confirmer } from '../../confirmer';
import { useJeton } from '../../session';
import { rayon, fs } from '../../theme';
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
      <EnTete>
        <TitreEcran lignes={['Photos de', "l'établissement."]} />
        <Mono couleur={c.gris} style={{ marginTop: 10 }}>{photos.length} / {max}</Mono>
      </EnTete>
      <Contenu style={{ paddingTop: 0 }}>
        {complet ? (
          <Encart genre="info">Tu as atteint la limite de {max} photos. Supprimes-en une pour en ajouter une nouvelle.</Encart>
        ) : null}

        <View style={{ flexDirection: 'row', gap: 10, marginBottom: 20 }}>
          <Bouton style={{ flex: 1 }} libelle="Galerie" variante="contour" icone="image" chargement={envoi} desactive={complet} onPress={() => void ajouter('galerie')} />
          <Bouton style={{ flex: 1 }} libelle="Caméra" variante="contour" chargement={envoi} desactive={complet} onPress={() => void ajouter('camera')} />
        </View>

        {photos.length === 0 ? (
          <View style={{ alignItems: 'center', paddingVertical: 40 }}>
            <Icone nom="image" taille={32} couleur={c.gris} />
            <T taille={fs[3]} couleur={c.gris} style={{ marginTop: 12 }}>Aucune photo pour le moment.</T>
          </View>
        ) : (
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 10 }}>
            {photos.map((p, i) => (
              <View key={p.id} style={{ width: '48%' }}>
                <View style={{ borderRadius: rayon.md, overflow: 'hidden', borderWidth: 1, borderColor: c.grisClair, aspectRatio: 1, backgroundColor: c.grisClair }}>
                  {p.url ? <Image source={{ uri: p.url }} style={{ width: '100%', height: '100%' }} /> : null}
                  {i === 0 ? (
                    <View style={{ position: 'absolute', top: 8, left: 8, backgroundColor: c.lime, borderRadius: rayon.pill, paddingHorizontal: 9, paddingVertical: 3, flexDirection: 'row', alignItems: 'center', gap: 4 }}>
                      <Icone nom="etoile" taille={11} couleur={c.noir} />
                      <T taille={fs[1]} poids={700}>Couverture</T>
                    </View>
                  ) : null}
                  <Pressable
                    onPress={() => void supprimer(p)}
                    disabled={enCours === p.id}
                    accessibilityRole="button"
                    accessibilityLabel="Supprimer cette photo"
                    style={{ position: 'absolute', top: 8, right: 8, width: 28, height: 28, borderRadius: 14, backgroundColor: 'rgba(0,0,0,0.55)', alignItems: 'center', justifyContent: 'center' }}
                  >
                    <Icone nom="croix" taille={14} couleur="#fff" />
                  </Pressable>
                </View>
                {i !== 0 ? (
                  <Pressable onPress={() => void definirCouverture(p)} disabled={enCours === p.id} style={{ marginTop: 6, alignItems: 'center' }}>
                    <T taille={fs[2]} couleur={c.gris}>Définir en couverture</T>
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
