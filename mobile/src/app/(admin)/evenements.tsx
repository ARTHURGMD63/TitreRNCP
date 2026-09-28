/**
 * Vue globale des soirées — admin/evenements.php, lecture seule.
 *
 * Toutes les soirées, tous établissements confondus : repérer celles sous
 * 60 % de présence.
 */

import React, { useCallback, useState } from 'react';
import { View } from 'react-native';
import { useFocusEffect } from 'expo-router';

import { actions, api, ErreurApi, type EvenementAdmin, type ReponseEvenementsAdmin } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Badge, Jauge } from '../../composants/Elements';
import { Selecteur } from '../../composants/Formulaire';
import { T, TitreEcran } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { confirmer } from '../../confirmer';
import { dateFr, majuscules } from '../../format';
import { useJeton } from '../../session';
import { fs, rayon } from '../../theme';
import { useTheme } from '../../useTheme';

const LIBELLES_TYPE: Record<string, string> = { bar: 'Bar', boite: 'Boîte', resto: 'Resto', afterwork: 'Afterwork' };

function CarteEvenementAdmin({ e, onSupprime }: { e: EvenementAdmin; onSupprime: (id: number) => void }) {
  const { c } = useTheme();
  const jeton = useJeton();
  const toast = useToast();
  const [suppression, setSuppression] = useState(false);
  const remplissage = e.quota > 0 ? Math.min(100, Math.round((e.inscrits / e.quota) * 100)) : 0;
  const couleurTaux = e.taux_presence === null ? c.gris : e.taux_presence < 60 ? c.danger : c.succes;

  async function supprimer() {
    if (!(await confirmer(`Supprimer définitivement « ${e.titre} » ? Cette action est irréversible.`))) return;
    setSuppression(true);
    try {
      await actions.adminSupprimerEvenement(jeton, e.id);
      toast('Événement supprimé', 'success');
      onSupprime(e.id);
    } catch (err) {
      toast(err instanceof ErreurApi ? err.message : 'Erreur réseau', 'error');
      setSuppression(false);
    }
  }

  return (
    <View style={{ backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.base, padding: 16, marginBottom: 12 }}>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', gap: 10, marginBottom: 6 }}>
        <View style={{ flex: 1 }}>
          <T taille={fs[4]} poids={700}>{e.titre}</T>
          <T taille={fs[2]} couleur={c.gris}>{e.etablissement.nom} · {e.etablissement.ville}</T>
        </View>
        <Badge libelle={majuscules(LIBELLES_TYPE[e.type] ?? e.type)} fond={c.surface2} encre={c.grisFonce} filet={c.line2} />
      </View>

      <T taille={fs[2]} couleur={c.gris} style={{ marginBottom: 10 }}>
        {dateFr(e.date_heure, 'D j M Y')} · {e.passe ? 'passée' : 'à venir'}
      </T>

      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10, marginBottom: 12 }}>
        <T taille={fs[3]} poids={700}>{e.inscrits}/{e.quota}</T>
        <View style={{ flex: 1 }}><Jauge pourcentage={remplissage} piste={c.grisClair} remplissage={c.rouge} hauteur={6} /></View>
        {e.taux_presence !== null ? (
          <T taille={fs[3]} poids={700} couleur={couleurTaux}>{e.taux_presence}% présents</T>
        ) : null}
      </View>

      <Bouton libelle="Supprimer" variante="alerte" chargement={suppression} onPress={supprimer} />
    </View>
  );
}

export default function EvenementsAdmin() {
  const { c } = useTheme();
  const jeton = useJeton();

  const [periode, setPeriode] = useState<'tous' | 'avenir' | 'passes'>('tous');
  const [type, setType] = useState('');
  const [donnees, setDonnees] = useState<ReponseEvenementsAdmin | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      setDonnees(await api.adminEvenements(jeton, { periode, type: type || undefined }));
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
    }
  }, [jeton, periode, type]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  if (!donnees) {
    return (
      <Ecran>
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : <Chargement />}
      </Ecran>
    );
  }

  return (
    <Ecran rafraichit={rafraichit} onRafraichir={() => { setRafraichit(true); void charger(); }}>
      <EnTete>
        <TitreEcran lignes={['Soirées.']} />
        <T taille={fs[4]} couleur={c.gris} style={{ marginTop: 6 }}>
          {donnees.evenements.length} affichée{donnees.evenements.length > 1 ? 's' : ''} · {donnees.repere.taux_presence_global !== null ? `${donnees.repere.taux_presence_global.toFixed(1)}% de présence globale` : 'taux global —'}
        </T>
      </EnTete>
      <Contenu style={{ paddingTop: 0 }}>
        <View style={{ flexDirection: 'row', gap: 10, marginBottom: 20 }}>
          <Selecteur
            style={{ flex: 1 }} marge={0} etiquette="Période" valeur={periode} onChange={(v) => setPeriode(v as typeof periode)}
            options={[{ valeur: 'tous', libelle: 'Toutes' }, { valeur: 'avenir', libelle: 'À venir' }, { valeur: 'passes', libelle: 'Passées' }]}
          />
          <Selecteur
            style={{ flex: 1 }} marge={0} etiquette="Type" valeur={type} onChange={setType}
            options={[{ valeur: '', libelle: 'Tous' }, ...Object.entries(LIBELLES_TYPE).map(([valeur, libelle]) => ({ valeur, libelle }))]}
          />
        </View>

        {donnees.evenements.length === 0 ? (
          <T taille={fs[4]} couleur={c.gris} style={{ textAlign: 'center', marginTop: 24 }}>Aucune soirée ne correspond.</T>
        ) : (
          donnees.evenements.map((e) => (
            <CarteEvenementAdmin key={e.id} e={e} onSupprime={(id) => setDonnees((d) => d && { ...d, evenements: d.evenements.filter((x) => x.id !== id) })} />
          ))
        )}
      </Contenu>
    </Ecran>
  );
}
