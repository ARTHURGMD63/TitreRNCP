/**
 * Moi, côté établissement — pas d'équivalent sur le site (le nom/type/
 * adresse ne se réglaient jusqu'ici qu'à l'inscription). L'identité du
 * compte, la fiche établissement modifiable, et la déconnexion.
 */

import React, { useCallback, useState } from 'react';
import { View } from 'react-native';
import { useFocusEffect } from 'expo-router';
import MapView, { Marker, PROVIDER_DEFAULT, type MapPressEvent } from 'react-native-maps';

import { ErreurApi, api } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Encart } from '../../composants/Elements';
import { Champ, Selecteur } from '../../composants/Formulaire';
import { Mono, T, TitreEcran } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { useJeton, useSession } from '../../session';
import { fs, rayon } from '../../theme';
import { useTheme } from '../../useTheme';

// Clermont-Ferrand par défaut, tant qu'aucune position n'est connue.
const REGION_DEFAUT = { latitude: 45.7772, longitude: 3.087, latitudeDelta: 0.08, longitudeDelta: 0.08 };

const TYPES = [
  { valeur: 'bar', libelle: 'Bar' },
  { valeur: 'boite', libelle: 'Boîte de nuit' },
  { valeur: 'resto', libelle: 'Restaurant' },
  { valeur: 'afterwork', libelle: 'Afterwork' },
];

export default function MoiPartenaire() {
  const { c } = useTheme();
  const jeton = useJeton();
  const toast = useToast();
  const { deconnexion } = useSession();

  const [compte, setCompte] = useState<{ prenom: string; nom: string; email: string } | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [enregistrement, setEnregistrement] = useState(false);
  const [erreurEnregistrement, setErreurEnregistrement] = useState<string | null>(null);
  const [succes, setSucces] = useState<string | null>(null);

  const [nom, setNom] = useState('');
  const [type, setType] = useState('bar');
  const [ville, setVille] = useState('');
  const [adresse, setAdresse] = useState('');
  const [position, setPosition] = useState<{ latitude: number; longitude: number } | null>(null);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      const rep = await api.partenaireProfil(jeton);
      setCompte(rep.compte);
      setNom(rep.etablissement.nom);
      setType(rep.etablissement.type);
      setVille(rep.etablissement.ville);
      setAdresse(rep.etablissement.adresse);
      if (rep.etablissement.latitude !== null && rep.etablissement.longitude !== null) {
        setPosition({ latitude: rep.etablissement.latitude, longitude: rep.etablissement.longitude });
      }
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Chargement impossible.');
    }
  }, [jeton]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  function placerEpingle(e: MapPressEvent) {
    setPosition(e.nativeEvent.coordinate);
  }

  async function enregistrer() {
    setEnregistrement(true);
    setErreurEnregistrement(null);
    setSucces(null);
    try {
      const rep = await api.partenaireEnregistrerProfil(jeton, {
        nom, type, ville, adresse,
        ...(position ? { latitude: position.latitude, longitude: position.longitude } : {}),
      });
      setSucces(rep.message ?? 'Établissement mis à jour.');
      toast(rep.message ?? 'Établissement mis à jour.', 'success');
    } catch (e) {
      setErreurEnregistrement(e instanceof ErreurApi ? e.message : 'Erreur réseau');
    } finally {
      setEnregistrement(false);
    }
  }

  if (!compte) {
    return (
      <Ecran>
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : <Chargement />}
      </Ecran>
    );
  }

  return (
    <Ecran>
      <EnTete>
        <TitreEcran lignes={[compte.prenom, compte.nom]} />
        <T taille={fs[3]} couleur={c.gris} style={{ marginTop: 6 }}>{compte.email}</T>
      </EnTete>
      <Contenu style={{ paddingTop: 0 }}>
        {succes ? <Encart genre="ok">{succes}</Encart> : null}
        {erreurEnregistrement ? <Encart genre="erreur">{erreurEnregistrement}</Encart> : null}

        <View style={{ backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.base, padding: 20, marginBottom: 20 }}>
          <Mono style={{ marginBottom: 16 }}>Mon établissement</Mono>
          <Champ etiquette="Nom" value={nom} onChangeText={setNom} />
          <Selecteur etiquette="Type" valeur={type} onChange={setType} options={TYPES} />
          <Champ etiquette="Ville" value={ville} onChangeText={setVille} />
          <Champ etiquette="Adresse" value={adresse} onChangeText={setAdresse} placeholder="Numéro et rue" />

          <Mono couleur={c.gris} style={{ marginBottom: 8 }}>Position sur la carte</Mono>
          <T taille={fs[2]} couleur={c.gris} style={{ marginBottom: 10 }}>
            Touche la carte pour placer l&apos;épingle — c&apos;est elle qui te situe sur la carte interactive des soirées.
          </T>
          <View style={{ height: 220, borderRadius: rayon.md, overflow: 'hidden', marginBottom: 16, borderWidth: 1, borderColor: c.grisClair }}>
            <MapView
              provider={PROVIDER_DEFAULT}
              style={{ flex: 1 }}
              initialRegion={position ? { ...position, latitudeDelta: 0.02, longitudeDelta: 0.02 } : REGION_DEFAUT}
              onPress={placerEpingle}
            >
              {position ? (
                <Marker
                  coordinate={position}
                  draggable
                  onDragEnd={(e) => setPosition(e.nativeEvent.coordinate)}
                  pinColor={c.rouge}
                />
              ) : null}
            </MapView>
          </View>

          <Bouton libelle="Enregistrer" plein chargement={enregistrement} onPress={enregistrer} />
        </View>

        <Bouton libelle="Se déconnecter" variante="contour" plein onPress={deconnexion} />
      </Contenu>
    </Ecran>
  );
}
