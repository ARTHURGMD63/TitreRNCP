/**
 * Culture — publier les événements que la ville met en avant.
 *
 * Il n'existe pas de compte « ville » : l'équipe publie pour elle. Une liste
 * (à venir, puis terminés) et un formulaire en feuille pour créer, modifier ou
 * supprimer. Les règles de saisie sont celles du serveur
 * (cultureValiderSaisie(), includes/culture.php) : le formulaire affiche son
 * refus tel quel plutôt que d'en recopier une partie.
 */

import React, { useCallback, useEffect, useState } from 'react';
import { Pressable, View } from 'react-native';

import { api, ErreurApi, type CategorieCulture, type EvenementCultureAdmin } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { etiquetteCulture } from '../../composants/CarteCulture';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Badge, BoutonRetour, Encart } from '../../composants/Elements';
import { Feuille } from '../../composants/Feuille';
import { Case, Champ, ChampDate, isoDateHeure, Selecteur } from '../../composants/Formulaire';
import { Display, Mono, T } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { confirmer } from '../../confirmer';
import { libellePlaces } from '../../culture';
import { lireDate } from '../../format';
import { useJeton } from '../../session';
import { fs, lh, rayon } from '../../theme';
import { useTheme } from '../../useTheme';

type Brouillon = {
  id: number | null;
  titre: string;
  description: string;
  categorie: string;
  organisateur: string;
  lieu: string;
  ville: string;
  debut: Date | null;
  fin: Date | null;
  quota: string;
  prix: string;
  lien: string;
  mis_en_avant: boolean;
};

const VIDE: Brouillon = {
  id: null, titre: '', description: '', categorie: 'expo', organisateur: 'Ville de Clermont-Ferrand',
  lieu: '', ville: 'Clermont-Ferrand', debut: null, fin: null, quota: '0', prix: '0', lien: '', mis_en_avant: false,
};

function depuis(e: EvenementCultureAdmin): Brouillon {
  return {
    id: e.id, titre: e.titre, description: e.description, categorie: e.categorie, organisateur: e.organisateur,
    lieu: e.lieu, ville: e.ville, debut: lireDate(e.date_heure), fin: lireDate(e.date_fin),
    quota: String(e.places.quota), prix: String(e.prix), lien: e.lien ?? '', mis_en_avant: e.mis_en_avant,
  };
}

function Ligne({ e, onPress }: { e: EvenementCultureAdmin; onPress: () => void }) {
  const { c } = useTheme();
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`Modifier ${e.titre}`}
      style={{ backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.base, padding: 16, marginBottom: 12, gap: 4, opacity: e.termine ? 0.65 : 1 }}
    >
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
        <Mono couleur={c.surRougeClair}>{etiquetteCulture(e)}</Mono>
        {e.mis_en_avant ? <Badge libelle="À la une" fond={c.rougeClair} encre={c.surRougeClair} /> : null}
        {e.en_cours ? <Badge libelle="En cours" fond={c.surface2} encre={c.grisFonce} filet={c.line2} /> : null}
      </View>
      <T taille={fs[5]} poids={700}>{e.titre}</T>
      <T taille={fs[3]} couleur={c.gris}>{e.lieu} · {e.places.inscrits} inscrit{e.places.inscrits > 1 ? 's' : ''} · {libellePlaces(e.places)}</T>
    </Pressable>
  );
}

export default function CultureAdmin() {
  const { c } = useTheme();
  const jeton = useJeton();
  const toast = useToast();
  const [donnees, setDonnees] = useState<{ a_venir: EvenementCultureAdmin[]; termines: EvenementCultureAdmin[]; categories: CategorieCulture[] } | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);
  const [brouillon, setBrouillon] = useState<Brouillon | null>(null);
  const [refus, setRefus] = useState<string | null>(null);
  const [envoi, setEnvoi] = useState<'enregistrer' | 'supprimer' | null>(null);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      setDonnees(await api.adminCulture(jeton));
    } catch (e) {
      setErreur(e instanceof Error ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
    }
  }, [jeton]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void charger();
  }, [charger]);

  const ouvrir = (b: Brouillon) => { setRefus(null); setBrouillon(b); };
  const modifier = (champ: Partial<Brouillon>) => setBrouillon((b) => (b ? { ...b, ...champ } : b));

  async function enregistrer() {
    if (!brouillon) return;
    setEnvoi('enregistrer');
    setRefus(null);
    try {
      await api.adminEnregistrerCulture(jeton, {
        id: brouillon.id ?? undefined,
        titre: brouillon.titre,
        description: brouillon.description,
        categorie: brouillon.categorie,
        organisateur: brouillon.organisateur,
        lieu: brouillon.lieu,
        ville: brouillon.ville,
        date_heure: brouillon.debut ? isoDateHeure(brouillon.debut) : '',
        date_fin: brouillon.fin ? isoDateHeure(brouillon.fin) : '',
        quota: Number(brouillon.quota.replace(/\s/g, '')) || 0,
        prix: Number(brouillon.prix.replace(',', '.')) || 0,
        lien: brouillon.lien,
        mis_en_avant: brouillon.mis_en_avant,
      });
      toast(brouillon.id ? 'Événement modifié' : 'Événement publié', 'success');
      setBrouillon(null);
      void charger();
    } catch (e) {
      setRefus(e instanceof ErreurApi ? e.message : 'Erreur réseau');
    } finally {
      setEnvoi(null);
    }
  }

  async function supprimer() {
    if (!brouillon?.id) return;
    if (!(await confirmer('Supprimer cet événement ? Les inscriptions seront perdues.'))) return;
    setEnvoi('supprimer');
    try {
      await api.adminSupprimerCulture(jeton, brouillon.id);
      toast('Événement supprimé');
      setBrouillon(null);
      void charger();
    } catch (e) {
      setRefus(e instanceof ErreurApi ? e.message : 'Erreur réseau');
    } finally {
      setEnvoi(null);
    }
  }

  const options = (donnees?.categories ?? []).map((k) => ({ valeur: k.code, libelle: k.libelle }));

  return (
    <Ecran
      rafraichit={rafraichit}
      onRafraichir={() => { setRafraichit(true); void charger(); }}
      horsDefilement={
        <Feuille visible={brouillon !== null} onClose={() => setBrouillon(null)}>
          {brouillon ? (
            <>
              <Display taille={fs[7]} interligne={lh.tight} style={{ marginBottom: 18 }}>
                {brouillon.id ? 'Modifier l’événement' : 'Nouvel événement'}
              </Display>
              {refus ? <Encart genre="erreur"><T taille={fs[3]}>{refus}</T></Encart> : null}
              <Champ etiquette="Titre" value={brouillon.titre} onChangeText={(v) => modifier({ titre: v })} maxLength={255} />
              <Selecteur etiquette="Catégorie" valeur={brouillon.categorie} options={options} onChange={(v) => modifier({ categorie: v })} />
              <Champ etiquette="Organisateur" value={brouillon.organisateur} onChangeText={(v) => modifier({ organisateur: v })} maxLength={150} />
              <Champ etiquette="Lieu" value={brouillon.lieu} onChangeText={(v) => modifier({ lieu: v })} maxLength={255} placeholder="Musée d'art Roger-Quilliot" />
              <Champ etiquette="Ville" value={brouillon.ville} onChangeText={(v) => modifier({ ville: v })} maxLength={100} />
              <ChampDate etiquette="Début" mode="datetime" valeur={brouillon.debut} onChange={(d) => modifier({ debut: d })} />
              <ChampDate
                etiquette="Fin (facultatif)" mode="datetime" valeur={brouillon.fin} onChange={(d) => modifier({ fin: d })}
                min={brouillon.debut ?? undefined} aide="Indispensable pour une exposition : elle reste « en cours » jusqu'à cette date."
              />
              {brouillon.fin ? <Bouton variante="texte" libelle="Retirer la date de fin" onPress={() => modifier({ fin: null })} style={{ alignSelf: 'flex-start', marginTop: -8, marginBottom: 8 }} /> : null}
              <View style={{ flexDirection: 'row', gap: 12 }}>
                <Champ style={{ flex: 1 }} etiquette="Places" value={brouillon.quota} onChangeText={(v) => modifier({ quota: v })} keyboardType="number-pad" aide="0 = entrée libre" />
                <Champ style={{ flex: 1 }} etiquette="Prix (€)" value={brouillon.prix} onChangeText={(v) => modifier({ prix: v })} keyboardType="decimal-pad" aide="0 = gratuit" />
              </View>
              <Champ etiquette="Lien officiel (facultatif)" value={brouillon.lien} onChangeText={(v) => modifier({ lien: v })} autoCapitalize="none" keyboardType="url" placeholder="https://" />
              <Champ etiquette="Description" value={brouillon.description} onChangeText={(v) => modifier({ description: v })} multiligne numberOfLines={4} maxLength={5000} />
              <Case coche={brouillon.mis_en_avant} onChange={(v) => modifier({ mis_en_avant: v })} style={{ marginBottom: 16 }}>
                Mettre « À la une » (en tête de l&apos;espace Culture)
              </Case>
              <Bouton libelle={brouillon.id ? 'Enregistrer' : 'Publier'} plein chargement={envoi === 'enregistrer'} desactive={envoi !== null} onPress={enregistrer} />
              {brouillon.id ? (
                <Bouton libelle="Supprimer" variante="alerte" plein chargement={envoi === 'supprimer'} desactive={envoi !== null} onPress={supprimer} style={{ marginTop: 12 }} />
              ) : null}
            </>
          ) : null}
        </Feuille>
      }
    >
      <EnTete>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 14, marginBottom: 16 }}>
          <BoutonRetour vers="/plus" libelle="Retour à Plus" />
          <Display taille={fs[8]} interligne={lh.tight} accessibilityRole="header">Culture</Display>
        </View>
        <Bouton libelle="Nouvel événement" icone="plus" plein onPress={() => ouvrir(VIDE)} />
      </EnTete>

      <Contenu style={{ paddingTop: 16 }}>
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : donnees === null ? <Chargement /> : (
          <>
            <Mono style={{ marginBottom: 12 }}>À venir et en cours · {donnees.a_venir.length}</Mono>
            {donnees.a_venir.length === 0 ? (
              <T taille={fs[4]} couleur={c.gris} style={{ marginBottom: 20 }}>Aucun événement publié.</T>
            ) : donnees.a_venir.map((e) => <Ligne key={e.id} e={e} onPress={() => ouvrir(depuis(e))} />)}

            {donnees.termines.length ? (
              <>
                <Mono style={{ marginTop: 16, marginBottom: 12 }}>Terminés</Mono>
                {donnees.termines.map((e) => <Ligne key={e.id} e={e} onPress={() => ouvrir(depuis(e))} />)}
              </>
            ) : null}
          </>
        )}
      </Contenu>
    </Ecran>
  );
}
