/**
 * Base étudiants — admin/utilisateurs.php, lecture seule.
 *
 * Recherche, filtre école/activité, et pour chacun : sorties, présences,
 * squads, économies, dernière réservation.
 */

import React, { useCallback, useState } from 'react';
import { View } from 'react-native';
import { useFocusEffect } from 'expo-router';

import { api, ErreurApi, type EtudiantAdmin, type ReponseEtudiantsAdmin } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Avatar, Segments } from '../../composants/Elements';
import { Champ, Selecteur } from '../../composants/Formulaire';
import { T, TitreEcran } from '../../composants/Texte';
import { dateFr, nombre } from '../../format';
import { useJeton } from '../../session';
import { fs, rayon } from '../../theme';
import { useTheme } from '../../useTheme';

function LigneEtudiant({ e }: { e: EtudiantAdmin }) {
  const { c } = useTheme();
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: c.grisClair }}>
      <Avatar photo={e.photo_url} prenom={e.prenom} taille={40} fond={c.bleu} />
      <View style={{ flex: 1, minWidth: 0 }}>
        <T taille={fs[4]} poids={700}>{e.prenom} {e.nom}</T>
        <T taille={fs[2]} couleur={c.gris}>{e.ecole ?? '—'}{e.promo ? ' · ' + e.promo : ''}</T>
        <T taille={fs[2]} couleur={c.gris} style={{ marginTop: 2 }}>
          {e.sorties} sorties · {e.presences} présences · {e.squads} squads · {nombre(Math.round(e.economies))}€
        </T>
      </View>
      <T taille={fs[2]} couleur={c.gris} style={{ textAlign: 'right' }}>
        {e.derniere_activite ? dateFr(e.derniere_activite, 'j M') : 'jamais'}
      </T>
    </View>
  );
}

export default function EtudiantsAdmin() {
  const { c } = useTheme();
  const jeton = useJeton();

  const [q, setQ] = useState('');
  const [ecole, setEcole] = useState('');
  const [etat, setEtat] = useState<'' | 'actifs' | 'dormants'>('');
  const [page, setPage] = useState(1);
  const [donnees, setDonnees] = useState<ReponseEtudiantsAdmin | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);
  const [suite, setSuite] = useState(false);

  const charger = useCallback(async (p = 1) => {
    try {
      setErreur(null);
      const rep = await api.adminEtudiants(jeton, { q, ecole, etat: etat || undefined, p });
      setDonnees((d) => (p === 1 || !d ? rep : { ...rep, etudiants: [...d.etudiants, ...rep.etudiants] }));
      setPage(p);
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
      setSuite(false);
    }
  }, [jeton, q, ecole, etat]);

  useFocusEffect(useCallback(() => { void charger(1); }, [charger]));

  if (!donnees) {
    return (
      <Ecran>
        {erreur ? <Erreur message={erreur} onReessayer={() => charger(1)} /> : <Chargement />}
      </Ecran>
    );
  }

  return (
    <Ecran rafraichit={rafraichit} onRafraichir={() => { setRafraichit(true); void charger(1); }}>
      <EnTete>
        <TitreEcran lignes={['Étudiants.']} />
        <T taille={fs[4]} couleur={c.gris} style={{ marginTop: 6 }}>
          {donnees.pagination.total} résultat{donnees.pagination.total > 1 ? 's' : ''} sur {donnees.repere.total}
        </T>
      </EnTete>
      <Contenu style={{ paddingTop: 0 }}>
        <Champ value={q} onChangeText={setQ} onSubmitEditing={() => charger(1)} placeholder="Nom, e-mail, centre d'intérêt…" returnKeyType="search" marge={10} />
        <View style={{ flexDirection: 'row', gap: 10, marginBottom: 12 }}>
          <Selecteur
            style={{ flex: 1 }} marge={0} etiquette="École" valeur={ecole} onChange={(v) => { setEcole(v); }}
            options={[{ valeur: '', libelle: 'Toutes' }, ...donnees.filtres.ecoles_disponibles.map((e) => ({ valeur: e, libelle: e }))]}
          />
        </View>
        <Segments
          options={[{ code: '', libelle: 'Tous' }, { code: 'actifs', libelle: 'Actifs' }, { code: 'dormants', libelle: 'Dormants' }]}
          valeur={etat}
          onChange={setEtat}
          style={{ marginBottom: 10 }}
        />
        <Bouton libelle="Filtrer" variante="contour" onPress={() => charger(1)} style={{ marginBottom: 20, alignSelf: 'flex-start' }} />

        {donnees.etudiants.length === 0 ? (
          <T taille={fs[4]} couleur={c.gris} style={{ textAlign: 'center', marginTop: 24 }}>Aucun étudiant ne correspond.</T>
        ) : (
          <View style={{ backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.base, paddingHorizontal: 16 }}>
            {donnees.etudiants.map((e) => <LigneEtudiant key={e.id} e={e} />)}
          </View>
        )}

        {page < donnees.pagination.pages ? (
          <Bouton libelle="Voir plus" variante="contour" plein chargement={suite}
            onPress={() => { setSuite(true); void charger(page + 1); }} style={{ marginTop: 16 }} />
        ) : null}
      </Contenu>
    </Ecran>
  );
}
