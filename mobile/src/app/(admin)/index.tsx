/**
 * Modération — admin/moderation.php.
 *
 * Les signalements de profils, à traiter ou déjà traités, avec le compte
 * de récidive (nombre de signalements visant la même personne).
 */

import React, { useCallback, useState } from 'react';
import { Pressable, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';

import { api, ErreurApi, type Signalement } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Avatar, Badge, Segments } from '../../composants/Elements';
import { T, TitreEcran } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { dateFr } from '../../format';
import { useJeton } from '../../session';
import { fs, rayon } from '../../theme';
import { useTheme } from '../../useTheme';

const LIBELLES_MOTIF: Record<string, string> = {
  harcelement: 'Harcèlement',
  contenu_inapproprie: 'Contenu inapproprié',
  usurpation: "Usurpation d'identité",
  spam: 'Spam',
  autre: 'Autre',
};

function CarteSignalement({ s, traitable, onTraite }: { s: Signalement; traitable: boolean; onTraite: () => void }) {
  const { c } = useTheme();
  const toast = useToast();
  const jeton = useJeton();
  const [enCours, setEnCours] = useState(false);

  async function traiter() {
    setEnCours(true);
    try {
      await api.adminTraiterSignalement(jeton, s.id);
      toast('Signalement traité.');
      onTraite();
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setEnCours(false);
    }
  }

  return (
    <View style={{ backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.base, padding: 16, marginBottom: 12 }}>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', gap: 10, marginBottom: 10 }}>
        <Pressable
          onPress={() => router.push({ pathname: '/etudiant/[id]', params: { id: String(s.personne.id) } })}
          style={{ flexDirection: 'row', alignItems: 'center', gap: 10, flex: 1 }}
        >
          <Avatar photo={s.personne.photo_url} prenom={s.personne.prenom} taille={40} fond={c.rouge} />
          <View style={{ flex: 1 }}>
            <T taille={fs[2]} couleur={c.gris}>Personne signalée</T>
            <T taille={fs[4]} poids={700}>{s.personne.prenom} {s.personne.nom}</T>
          </View>
        </Pressable>
        <View style={{ alignItems: 'flex-end', gap: 6 }}>
          {s.total_cible > 1 ? <Badge libelle={`${s.total_cible} signalements`} fond={c.dangerClair} encre={c.danger} /> : null}
          <Badge libelle={LIBELLES_MOTIF[s.motif] ?? s.motif} fond={c.surface2} encre={c.grisFonce} filet={c.line2} />
        </View>
      </View>

      {s.details ? (
        <View style={{ backgroundColor: c.surface2, borderRadius: rayon.sm, padding: 12, marginBottom: 12 }}>
          <T taille={fs[3]} couleur={c.grisFonce}>{s.details}</T>
        </View>
      ) : null}

      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingTop: 12, borderTopWidth: 1, borderTopColor: c.grisClair, gap: 12 }}>
        <View style={{ flex: 1 }}>
          <T taille={fs[2]} couleur={c.gris}>
            Signalé par {s.signale_par} · {dateFr(s.created_at, 'j M Y')}
            {s.traite_par ? `\nTraité par ${s.traite_par} le ${s.traite_le ? dateFr(s.traite_le, 'j M Y') : ''}` : ''}
          </T>
        </View>
        {traitable ? (
          <Bouton libelle="Traité" chargement={enCours} onPress={traiter} />
        ) : null}
      </View>
    </View>
  );
}

export default function Moderation() {
  const jeton = useJeton();
  const [filtre, setFiltre] = useState<'nouveau' | 'traite'>('nouveau');
  const [donnees, setDonnees] = useState<{ nb_nouveaux: number; signalements: Signalement[] } | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);

  const charger = useCallback(async (f = filtre) => {
    try {
      setErreur(null);
      setDonnees(await api.adminModeration(jeton, f));
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
    }
  }, [jeton, filtre]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  if (!donnees) {
    return (
      <Ecran>
        {erreur ? <Erreur message={erreur} onReessayer={() => charger()} /> : <Chargement />}
      </Ecran>
    );
  }

  return (
    <Ecran rafraichit={rafraichit} onRafraichir={() => { setRafraichit(true); void charger(); }}>
      <EnTete>
        <TitreEcran lignes={['Modération.']} />
        <T taille={fs[4]} couleur="#67626D" style={{ marginTop: 6 }}>
          {donnees.nb_nouveaux} signalement{donnees.nb_nouveaux > 1 ? 's' : ''} à traiter
        </T>
      </EnTete>
      <Contenu style={{ paddingTop: 0 }}>
        <Segments
          options={[{ code: 'nouveau', libelle: 'À traiter' }, { code: 'traite', libelle: 'Traités' }]}
          valeur={filtre}
          onChange={(v) => { setFiltre(v); void charger(v); }}
          style={{ marginBottom: 20 }}
        />
        {donnees.signalements.length === 0 ? (
          <T taille={fs[4]} couleur="#67626D" style={{ textAlign: 'center', marginTop: 24 }}>
            {filtre === 'nouveau' ? 'Aucun signalement en attente.' : 'Aucun signalement traité.'}
          </T>
        ) : (
          donnees.signalements.map((s) => (
            <CarteSignalement key={s.id} s={s} traitable={filtre === 'nouveau'} onTraite={() => charger()} />
          ))
        )}
      </Contenu>
    </Ecran>
  );
}
