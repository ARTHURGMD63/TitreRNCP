/**
 * Moi, côté établissement — pas d'équivalent sur le site (le nom/type/
 * adresse ne se réglaient jusqu'ici qu'à l'inscription). L'identité du
 * compte, la fiche établissement modifiable, et la déconnexion.
 *
 * Refonte 2026-11 : logo en tête, l'identité dans une carte bleue, la fiche
 * dans une carte blanche, puis Photos et Abonnement en raccourcis.
 */

import React, { useCallback, useState } from 'react';
import { Text, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import MapView, { Marker, PROVIDER_DEFAULT, type MapPressEvent } from 'react-native-maps';

import { ErreurApi, api } from '../../api';
import { Apparition, PressableAnime } from '../../composants/Animations';
import { Bouton } from '../../composants/Bouton';
import { CarreIcone, EnTeteApp, Pastille, useRubrique } from '../../composants/Design';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Avatar, Encart } from '../../composants/Elements';
import { Icone, type NomIcone } from '../../composants/Icone';
import { Champ, Selecteur } from '../../composants/Formulaire';
import { T, TitreSection } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { useJeton, useSession } from '../../session';
import { fs, halo, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

// Clermont-Ferrand par défaut, tant qu'aucune position n'est connue.
const REGION_DEFAUT = { latitude: 45.7772, longitude: 3.087, latitudeDelta: 0.08, longitudeDelta: 0.08 };

const TYPES = [
  { valeur: 'bar', libelle: 'Bar' },
  { valeur: 'boite', libelle: 'Boîte de nuit' },
  { valeur: 'resto', libelle: 'Restaurant' },
  { valeur: 'afterwork', libelle: 'Afterwork' },
  { valeur: 'club', libelle: 'Club sportif' },
];

/** Un raccourci : carré d'icône, libellé, chevron. */
function Raccourci({ icone, libelle, detail, onPress }: { icone: NomIcone; libelle: string; detail: string; onPress: () => void }) {
  const { c } = useTheme();
  const bleu = useRubrique('bleu');
  return (
    <PressableAnime onPress={onPress} accessibilityRole="link" accessibilityLabel={libelle}
      style={{ flexDirection: 'row', alignItems: 'center', gap: 14, padding: 14, borderRadius: rayon.tuile, backgroundColor: c.blanc, marginBottom: 10 }}>
      <CarreIcone icone={icone} fond={bleu.doux} encre={bleu.texteDoux} taille={40} />
      <View style={{ flex: 1 }}>
        <T taille={fs[5]} poids={700}>{libelle}</T>
        <T taille={fs[4]} couleur={c.gris}>{detail}</T>
      </View>
      <Icone nom="chevron-d" taille={20} couleur={c.gris} />
    </PressableAnime>
  );
}

export default function MoiPartenaire() {
  const { c } = useTheme();
  const bleu = useRubrique('bleu');
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
      <EnTete style={{ paddingBottom: 4 }}>
        <Apparition index={0}>
          <EnTeteApp />
        </Apparition>
      </EnTete>
      <Contenu style={{ paddingTop: 0 }}>
        <Apparition index={1}>
          <View style={[{ backgroundColor: bleu.accent, borderRadius: rayon.carte, padding: 20, gap: 16, marginBottom: 16 }, halo(bleu.accent)]}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 16 }}>
              <Avatar prenom={nom || compte.prenom} taille={64} fond="#FFFFFF" />
              <View style={{ flex: 1, minWidth: 0 }}>
                <Text accessibilityRole="header" numberOfLines={2} style={{ fontFamily: sans(700), fontSize: 26, lineHeight: 26 * 1.15, letterSpacing: -0.035 * 26, color: '#FFFFFF' }}>{nom || 'Mon établissement'}</Text>
                <T taille={fs[5]} couleur={bleu.surAccent2} style={{ marginTop: 2 }}>{compte.prenom} {compte.nom}</T>
              </View>
            </View>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
              <Pastille libelle={TYPES.find((t) => t.valeur === type)?.libelle ?? type} fond="rgba(255,255,255,0.18)" encre="#FFFFFF" />
              {ville ? <Pastille libelle={ville} icone="epingle" fond="rgba(255,255,255,0.18)" encre="#FFFFFF" /> : null}
              <Pastille libelle={compte.email} fond="rgba(255,255,255,0.18)" encre="#FFFFFF" style={{ flexShrink: 1 }} />
            </View>
          </View>
        </Apparition>

        {succes ? <Encart genre="ok">{succes}</Encart> : null}
        {erreurEnregistrement ? <Encart genre="erreur">{erreurEnregistrement}</Encart> : null}

        <Apparition index={2}>
        <View style={{ backgroundColor: c.blanc, borderRadius: rayon.carte, padding: 20, marginBottom: 16 }}>
          <TitreSection style={{ marginBottom: 14 }}>Mon établissement</TitreSection>
          <Champ etiquette="Nom" value={nom} onChangeText={setNom} />
          <Selecteur etiquette="Type" valeur={type} onChange={setType} options={TYPES} />
          <Champ etiquette="Ville" value={ville} onChangeText={setVille} />
          <Champ etiquette="Adresse" value={adresse} onChangeText={setAdresse} placeholder="Numéro et rue" />

          <T taille={fs[4]} poids={500} couleur={c.gris} style={{ marginBottom: 4 }}>Position sur la carte</T>
          <T taille={fs[4]} couleur={c.gris} style={{ marginBottom: 10 }}>
            Touche la carte pour placer l&apos;épingle — c&apos;est elle qui te situe sur la carte interactive des soirées.
          </T>
          <View style={{ height: 220, borderRadius: rayon.bouton, overflow: 'hidden', marginBottom: 16, borderWidth: 1, borderColor: c.line2, backgroundColor: c.surface2 }}>
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
        </Apparition>

        <Apparition index={3}>
          <Raccourci icone="image" libelle="Photos de l'établissement" detail="La galerie de ta page" onPress={() => router.push('/photos')} />
          <Raccourci icone="carte" libelle="Mon abonnement" detail="Ta formule et ses options" onPress={() => router.push('/abonnement')} />
        </Apparition>

        <Bouton testID="deconnexion" libelle="Se déconnecter" icone="deconnexion" variante="contour" plein onPress={deconnexion} style={{ marginTop: 10 }} />
        <Bouton libelle="Supprimer mon compte" variante="texte" plein onPress={() => router.push('/supprimer-compte' as never)} style={{ marginTop: 12 }} />
      </Contenu>
    </Ecran>
  );
}
