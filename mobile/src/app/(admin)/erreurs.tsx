/**
 * Erreurs — le journal des erreurs du serveur et des plantages de
 * l'application (api/v1/admin_erreurs.php), pour corriger vite.
 *
 * Une même erreur répétée tient sur une carte, avec son nombre
 * d'occurrences et ses dates. Toucher une carte déplie le détail (pile
 * d'appels, adresse appelée, plateforme et version de l'application), qu'on
 * peut sélectionner pour le copier. « Corrigée » la range ; si elle se
 * reproduit, elle revient d'elle-même dans la liste.
 */

import React, { useCallback, useState } from 'react';
import { Pressable, View } from 'react-native';
import { useFocusEffect } from 'expo-router';

import { api, ErreurApi, type ErreurJournal, type FiltreErreurs } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Badge, BoutonRetour, Segments } from '../../composants/Elements';
import { Icone } from '../../composants/Icone';
import { T, TitreEcran } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { confirmer } from '../../confirmer';
import { dateFr } from '../../format';
import { useJeton } from '../../session';
import { fs, mono, rayon } from '../../theme';
import { useTheme } from '../../useTheme';

const FILTRES: { code: FiltreErreurs; libelle: string }[] = [
  { code: 'ouvertes', libelle: 'À traiter' },
  { code: 'serveur', libelle: 'Serveur' },
  { code: 'app', libelle: 'App' },
  { code: 'resolues', libelle: 'Corrigées' },
];

function CarteErreur({ e, onChange }: { e: ErreurJournal; onChange: () => void }) {
  const { c } = useTheme();
  const jeton = useJeton();
  const toast = useToast();
  const [ouvert, setOuvert] = useState(false);
  const [enCours, setEnCours] = useState(false);
  const resolue = e.resolu_le !== null;

  async function basculer() {
    setEnCours(true);
    try {
      await api.adminErreurAction(jeton, { action: resolue ? 'rouvrir' : 'resoudre', id: e.id });
      toast(resolue ? 'Erreur rouverte.' : 'Erreur marquée corrigée.');
      onChange();
    } catch (err) {
      toast(err instanceof ErreurApi ? err.message : 'Erreur réseau', 'error');
    } finally {
      setEnCours(false);
    }
  }

  const contexte = e.contexte ? Object.entries(e.contexte).filter(([, v]) => v !== '' && v !== null && v !== false) : [];

  return (
    <View style={{ backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.base, padding: 16, marginBottom: 12 }}>
      <Pressable onPress={() => setOuvert(!ouvert)} accessibilityRole="button" accessibilityState={{ expanded: ouvert }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 8, flexWrap: 'wrap' }}>
          <Badge libelle={e.source === 'app' ? 'APP' : 'SERVEUR'} fond={c.surface2} encre={c.grisFonce} filet={c.line2} />
          {e.occurrences > 1 ? <Badge libelle={`${e.occurrences.toLocaleString('fr-FR')} fois`} fond={c.dangerClair} encre={c.danger} /> : null}
          {resolue ? <Badge libelle="CORRIGÉE" icone="check" fond={c.succesClair} encre={c.succes} /> : null}
        </View>
        <View style={{ flexDirection: 'row', gap: 8 }}>
          <T taille={fs[4]} poids={700} style={{ flex: 1 }} numberOfLines={ouvert ? undefined : 3}>{e.message}</T>
          <View style={{ transform: [{ rotate: ouvert ? '180deg' : '0deg' }] }}><Icone nom="chevron-b" taille={18} couleur={c.gris} /></View>
        </View>
        <T taille={fs[2]} couleur={c.gris} style={{ marginTop: 6 }}>
          Dernière fois {dateFr(e.derniere_le, 'D j M à H\\hi')}
          {e.occurrences > 1 ? ` · première ${dateFr(e.premiere_le, 'D j M')}` : ''}
          {resolue && e.resolu_le ? ` · corrigée ${dateFr(e.resolu_le, 'D j M')}` : ''}
        </T>
      </Pressable>

      {ouvert ? (
        <View style={{ marginTop: 12, gap: 10 }}>
          {contexte.length > 0 ? (
            <View style={{ gap: 2 }}>
              {contexte.map(([cle, valeur]) => (
                <T key={cle} taille={fs[2]} couleur={c.grisFonce} selectable>
                  <T taille={fs[2]} poids={700}>{cle}</T> : {typeof valeur === 'string' ? valeur : JSON.stringify(valeur)}
                </T>
              ))}
            </View>
          ) : null}
          {e.detail ? (
            <View style={{ backgroundColor: c.surface2, borderRadius: rayon.sm, padding: 10 }}>
              <T taille={fs[1]} couleur={c.grisFonce} selectable style={{ fontFamily: mono(400) }}>{e.detail}</T>
            </View>
          ) : null}
          <Bouton libelle={resolue ? 'Rouvrir' : 'Marquer corrigée'} variante={resolue ? 'contour' : 'primaire'} chargement={enCours} onPress={basculer} />
        </View>
      ) : null}
    </View>
  );
}

export default function ErreursAdmin() {
  const { c } = useTheme();
  const jeton = useJeton();
  const toast = useToast();
  const [filtre, setFiltre] = useState<FiltreErreurs>('ouvertes');
  const [erreurs, setErreurs] = useState<ErreurJournal[] | null>(null);
  const [echec, setEchec] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);

  const charger = useCallback(async (f: FiltreErreurs = filtre) => {
    try {
      setEchec(null);
      setErreurs((await api.adminErreurs(jeton, f)).erreurs);
    } catch (e) {
      setEchec(e instanceof ErreurApi ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
    }
  }, [jeton, filtre]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  async function toutCorriger() {
    const source = filtre === 'serveur' || filtre === 'app' ? filtre : undefined;
    if (!(await confirmer('Marquer toutes ces erreurs comme corrigées ? Celles qui se reproduiront reviendront dans la liste.'))) return;
    try {
      const r = await api.adminErreurAction(jeton, { action: 'resoudre_tout', source });
      toast(`${r.resolues ?? 0} erreur(s) marquée(s) corrigée(s).`);
      void charger();
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    }
  }

  return (
    <Ecran rafraichit={rafraichit} onRafraichir={() => { setRafraichit(true); void charger(); }}>
      <EnTete>
        <BoutonRetour style={{ marginBottom: 16 }} />
        <TitreEcran lignes={['Erreurs.']} />
      </EnTete>
      <Contenu style={{ paddingTop: 0 }}>
        <Segments options={FILTRES} valeur={filtre} onChange={(f) => { setFiltre(f); setErreurs(null); void charger(f); }} style={{ marginBottom: 16 }} />
        {erreurs === null ? (
          echec ? <Erreur message={echec} onReessayer={() => charger()} /> : <Chargement />
        ) : erreurs.length === 0 ? (
          <View style={{ alignItems: 'center', paddingVertical: 40, gap: 10 }}>
            <Icone nom="valide" taille={28} couleur={c.succes} />
            <T taille={fs[4]} couleur={c.gris}>{filtre === 'resolues' ? 'Aucune erreur corrigée récemment.' : 'Rien à signaler.'}</T>
          </View>
        ) : (
          <>
            {filtre !== 'resolues' ? (
              <Bouton libelle="Tout marquer corrigé" variante="contour" plein onPress={toutCorriger} style={{ marginBottom: 14 }} />
            ) : null}
            {erreurs.map((e) => <CarteErreur key={e.id} e={e} onChange={() => charger()} />)}
          </>
        )}
      </Contenu>
    </Ecran>
  );
}
