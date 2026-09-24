/**
 * Squads — squads.php, dans l'univers sport : le dôme remplace la lave pour
 * l'accent du titre, la pilule active et l'action principale.
 *
 * « Ne cours plus / seul·e. », les filtres de sport (côté appareil, comme le
 * site), le bandeau de suggestion, « + Créer un squad », puis les cartes :
 * niveau en haut à droite, étiquette « RUNNING · MAR 07H00 », lieu,
 * description, pastilles des membres, et l'action — Gérer, Rejoint, Complet
 * ou Rejoindre. Les deux feuilles du site : créer un squad, gérer le sien.
 */

import React, { useCallback, useState } from 'react';
import { Pressable, ScrollView, Text, View } from 'react-native';
import { useFocusEffect } from 'expo-router';

import { actions, api, ErreurApi, type Squad } from '../../api';
import { NIVEAUX_SQUAD, TYPES_SQUAD } from '../../catalogue';
import { Bouton } from '../../composants/Bouton';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Pilule, Separateur } from '../../composants/Elements';
import { Feuille } from '../../composants/Feuille';
import { Champ, ChampDate, isoDateHeure, Selecteur } from '../../composants/Formulaire';
import { Icone } from '../../composants/Icone';
import { Display, Mono, T, TitreEcran } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { confirmer } from '../../confirmer';
import { court, dateFr, majuscules } from '../../format';
import { useJeton } from '../../session';
import { fixe, fs, gutter, lh, police, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

const FILTRES = [
  { code: 'all', libelle: 'Tout' },
  { code: 'running', libelle: 'Running' },
  { code: 'velo', libelle: 'Vélo' },
  { code: 'muscu', libelle: 'Muscu' },
  { code: 'autre', libelle: 'Autre' },
];

function CarteSquad({ s, onGerer }: { s: Squad; onGerer: () => void }) {
  const { c, ombre } = useTheme();
  const jeton = useJeton();
  const toast = useToast();
  const [rejoint, setRejoint] = useState(false);
  const [attente, setAttente] = useState(false);
  const [membres, setMembres] = useState(s.places.membres);
  const complet = s.places.membres >= s.places.quota;
  const initiales = s.membres.slice(0, 3).map((m) => (m.prenom || '?').charAt(0).toUpperCase());

  async function rejoindre() {
    setAttente(true);
    try {
      const rep = await actions.rejoindreSquad(jeton, s.id);
      setRejoint(true);
      if (rep.membres) setMembres(rep.membres);
      toast('Tu rejoins le squad !', 'success');
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setAttente(false);
    }
  }

  const cta = (libelle: string | null, style: object, couleur: string, props: { onPress?: () => void; disabled?: boolean; icone?: 'check' | 'engrenage' }) => (
    <Pressable
      onPress={props.onPress}
      disabled={props.disabled}
      accessibilityRole="button"
      style={({ pressed }) => [{ flexDirection: 'row', alignItems: 'center', gap: 4, paddingVertical: 11, paddingHorizontal: 18, borderRadius: rayon.pill, borderWidth: 1.5, borderColor: 'transparent', transform: pressed && !props.disabled ? [{ scale: 0.98 }] : [] }, style]}
    >
      {props.icone ? <Icone nom={props.icone} taille={props.icone === 'engrenage' ? 12 : 16} couleur={couleur} /> : null}
      {libelle ? <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: couleur }}>{libelle}</Text> : null}
    </Pressable>
  );

  return (
    <View style={[{ borderRadius: rayon.base, padding: 20, marginBottom: 14, borderWidth: 1, borderColor: c.grisClair, backgroundColor: c.blanc, overflow: 'hidden' }, ombre('base')]}>
      <View style={{ position: 'absolute', top: 16, right: 16, borderWidth: 1, borderColor: c.line2, borderRadius: rayon.pill, paddingVertical: 4, paddingHorizontal: 10 }}>
        <T taille={fs[2]} poids={500} couleur={c.grisFonce}>{NIVEAUX_SQUAD[s.niveau] ?? s.niveau}</T>
      </View>

      <Mono couleur={c.surBleuClair} style={{ marginBottom: 6, paddingRight: 96 }}>
        {majuscules(TYPES_SQUAD[s.type] ?? s.type)} · {dateFr(s.date_heure, 'D H\\hi')}
      </Mono>
      <Display taille={fs[6]} interligne={lh.tight} style={{ marginBottom: 6, paddingRight: 70 }}>{s.titre}</Display>
      {s.lieu ? <T taille={fs[3]} couleur={c.grisFonce} style={{ marginBottom: 12 }}>{s.lieu}</T> : null}
      {s.description ? <T taille={fs[3]} couleur={c.grisFonce} style={{ marginBottom: 12 }}>{Array.from(s.description).slice(0, 80).join('')}…</T> : null}

      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, paddingTop: 15, borderTopWidth: 1, borderTopColor: c.grisClair }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
          <View style={{ flexDirection: 'row' }}>
            {initiales.map((l, i) => (
              <View key={i} style={{ width: 30 + 5, height: 30 + 5, borderRadius: 18, marginLeft: i === 0 ? 0 : -10 - 5, backgroundColor: c.blanc, alignItems: 'center', justifyContent: 'center' }}>
                <View style={{ width: 30, height: 30, borderRadius: 15, backgroundColor: c.bleu, alignItems: 'center', justifyContent: 'center' }}>
                  <Text style={{ fontFamily: police.display, fontSize: fs[1], color: fixe.surLave }}>{l}</Text>
                </View>
              </View>
            ))}
          </View>
          <T taille={fs[3]} poids={500} couleur={c.grisFonce} style={{ marginLeft: 9 - 6 }}>{membres}/{s.places.quota}</T>
        </View>

        {s.est_createur
          ? cta('Gérer', { backgroundColor: 'transparent', borderColor: c.noir }, c.noir, { onPress: onGerer, icone: 'engrenage' })
          : s.deja_membre
            ? cta('Rejoint', { backgroundColor: c.surface2, borderColor: c.line2 }, c.grisFonce, { disabled: true, icone: 'check' })
            : rejoint
              ? cta(null, { backgroundColor: c.bleu }, fixe.surLave, { disabled: true, icone: 'check' })
              : complet
                ? cta('Complet', { backgroundColor: c.surface2, borderColor: c.line2 }, c.grisFonce, { disabled: true })
                : cta(attente ? '…' : 'Rejoindre', { backgroundColor: c.bleu }, fixe.surLave, { onPress: rejoindre, disabled: attente })}
      </View>
    </View>
  );
}

export default function Squads() {
  const { c } = useTheme();
  const jeton = useJeton();
  const toast = useToast();
  const [squads, setSquads] = useState<Squad[] | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);
  const [filtre, setFiltre] = useState('all');

  const [creation, setCreation] = useState(false);
  const [gestion, setGestion] = useState<number | null>(null);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      setSquads((await api.squads(jeton)).squads);
    } catch (e) {
      setErreur(e instanceof Error ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
    }
  }, [jeton]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  const visibles = (squads ?? []).filter((s) => filtre === 'all' || s.type === filtre);

  return (
    <Ecran
      rafraichit={rafraichit}
      onRafraichir={() => { setRafraichit(true); void charger(); }}
      horsDefilement={
        <>
          <FeuilleCreation visible={creation} onClose={() => setCreation(false)} onCree={() => { toast('Squad créé !', 'success'); setCreation(false); void charger(); }} />
          <FeuilleGestion squadId={gestion} onClose={() => setGestion(null)} onSupprime={() => { setGestion(null); void charger(); }} />
        </>
      }
    >
      <EnTete>
        <TitreEcran lignes={['Ne cours plus', 'seul·e.']} accent={c.surBleuClair} />
      </EnTete>

      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ flexGrow: 0 }} contentContainerStyle={{ gap: 8, paddingHorizontal: gutter, paddingBottom: 16 }}>
        {FILTRES.map((f) => (
          <Pilule key={f.code} libelle={f.libelle} actif={filtre === f.code} accent={c.bleu} onPress={() => setFiltre(f.code)} />
        ))}
      </ScrollView>

      <Contenu>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 13, backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.md, paddingVertical: 16, paddingHorizontal: 18, marginBottom: 16 }}>
          <Icone nom="eclair" taille={20} couleur={c.surBleuClair} />
          <View style={{ flex: 1 }}>
            <T taille={fs[3]} poids={700} interligne={lh.snug}>3 squads pour ton niveau</T>
            <T taille={fs[3]} couleur={c.gris} interligne={lh.snug}>Running inter. · &lt; 5 min à pied</T>
          </View>
        </View>

        <Bouton libelle="+ Créer un squad" variante="contour" plein onPress={() => setCreation(true)} style={{ marginBottom: 16 }} />

        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : squads === null ? <Chargement /> : (
          <>
            {squads.length === 0 ? (
              <View style={{ alignItems: 'center', paddingVertical: 48 }}>
                <View style={{ marginBottom: 12 }}><Icone nom="marque-page" taille={40} couleur={c.gris} /></View>
                <T taille={fs[5]} poids={600} couleur={c.gris}>Pas encore de squads.</T>
                <T taille={fs[3]} couleur={c.gris} style={{ marginTop: 6 }}>Crée le premier !</T>
              </View>
            ) : null}

            {visibles.map((s) => <CarteSquad key={s.id} s={s} onGerer={() => setGestion(s.id)} />)}

            {squads.length > 0 && visibles.length === 0 ? (
              <View style={{ alignItems: 'center', paddingVertical: 40 }}>
                <T taille={fs[5]} poids={600} couleur={c.gris}>Aucun squad dans cette catégorie.</T>
                <T taille={fs[3]} couleur={c.gris} style={{ marginTop: 6, textAlign: 'center' }}>Crée le premier, ou reviens à « Tout ».</T>
              </View>
            ) : null}
          </>
        )}
      </Contenu>
    </Ecran>
  );
}

// ─── Créer un squad ─────────────────────────────────────────────────────────

function FeuilleCreation({ visible, onClose, onCree }: { visible: boolean; onClose: () => void; onCree: () => void }) {
  const jeton = useJeton();
  const toast = useToast();
  const [titre, setTitre] = useState('');
  const [type, setType] = useState('running');
  const [niveau, setNiveau] = useState('tous');
  const [date, setDate] = useState<Date | null>(null);
  const [quota, setQuota] = useState('10');
  const [lieu, setLieu] = useState('');
  const [description, setDescription] = useState('');
  const [attente, setAttente] = useState(false);

  async function creer() {
    setAttente(true);
    try {
      await actions.creerSquad(jeton, {
        titre: titre.trim(), type, niveau, date_heure: date ? isoDateHeure(date) : '',
        quota: parseInt(quota, 10) || 0, lieu: lieu.trim(), description: description.trim(),
      });
      setTitre(''); setDate(null); setQuota('10'); setLieu(''); setDescription('');
      onCree();
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setAttente(false);
    }
  }

  return (
    <Feuille visible={visible} onClose={onClose}>
      <Display taille={fs[7]} style={{ marginBottom: 20 }}>Créer un squad</Display>
      <Champ etiquette="Titre" value={titre} onChangeText={setTitre} placeholder="Sortie Puy-de-Dôme" />
      <View style={{ flexDirection: 'row', gap: 12 }}>
        <Selecteur style={{ flex: 1 }} etiquette="Sport" valeur={type} onChange={setType}
          options={Object.entries(TYPES_SQUAD).map(([valeur, libelle]) => ({ valeur, libelle }))} />
        <Selecteur style={{ flex: 1 }} etiquette="Niveau" valeur={niveau} onChange={setNiveau}
          options={[{ valeur: 'tous', libelle: 'Tous' }, { valeur: 'debutant', libelle: 'Débutant' }, { valeur: 'inter', libelle: 'Inter.' }, { valeur: 'avance', libelle: 'Avancé' }]} />
      </View>
      <View style={{ flexDirection: 'row', gap: 12 }}>
        <View style={{ flex: 1 }}>
          <ChampDate etiquette="Date & heure" mode="datetime" valeur={date} onChange={setDate} min={new Date()} />
        </View>
        <Champ style={{ flex: 1 }} etiquette="Max participants" value={quota} onChangeText={(v) => setQuota(v.replace(/[^0-9]/g, ''))} keyboardType="number-pad" />
      </View>
      <Champ etiquette="Lieu de rendez-vous" value={lieu} onChangeText={setLieu} placeholder="Parking Royat" />
      <Champ etiquette="Description" value={description} onChangeText={setDescription} placeholder="Détails sur la sortie..." multiligne />
      <Bouton libelle="Créer le squad" variante="bleu" plein chargement={attente} onPress={creer} />
      <Bouton libelle="Annuler" variante="contour" plein onPress={onClose} style={{ marginTop: 8 }} />
    </Feuille>
  );
}

// ─── Gérer mon Squad ────────────────────────────────────────────────────────

function FeuilleGestion({ squadId, onClose, onSupprime }: { squadId: number | null; onClose: () => void; onSupprime: () => void }) {
  const { c } = useTheme();
  const jeton = useJeton();
  const toast = useToast();
  const [donnees, setDonnees] = useState<Awaited<ReturnType<typeof api.squadMembres>> | null>(null);
  const [suppression, setSuppression] = useState(false);

  const [idVu, setIdVu] = useState(squadId);
  if (squadId !== idVu) {
    setIdVu(squadId);
    setDonnees(null);
  }

  React.useEffect(() => {
    if (squadId === null) return;
    api.squadMembres(jeton, squadId).then(setDonnees).catch(() => toast('Erreur de chargement', 'error'));
  }, [squadId, jeton, toast]);

  async function retirer(membreId: number) {
    if (squadId === null || !(await confirmer('Retirer cette personne du groupe ?'))) return;
    try {
      await actions.retirerMembre(jeton, squadId, membreId);
      setDonnees((d) => d && { ...d, membres: d.membres.filter((m) => m.id !== membreId) });
      toast('Membre retiré', 'success');
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur', 'error');
    }
  }

  async function supprimer() {
    if (squadId === null || !(await confirmer('Veux-tu vraiment supprimer définitivement ce groupe ?'))) return;
    setSuppression(true);
    try {
      await actions.supprimerSquad(jeton, squadId);
      toast('Squad supprimé !', 'success');
      onSupprime();
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setSuppression(false);
    }
  }

  return (
    <Feuille visible={squadId !== null} onClose={onClose}>
      <Display taille={fs[7]} style={{ marginBottom: 20 }}>Gérer mon Squad</Display>
      {donnees === null ? (
        <T taille={fs[5]} style={{ textAlign: 'center', padding: 20 }}>Chargement...</T>
      ) : (
        <>
          <Mono accessibilityRole="header" style={{ marginBottom: 12 }}>Participants inscrits</Mono>
          <View style={{ gap: 8, marginBottom: 24 }}>
            {donnees.membres.map((m) => (
              <View key={m.id} style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 12, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.sm, backgroundColor: c.blanc }}>
                <View>
                  <T taille={fs[5]} poids={700}>{court(m.prenom, m.nom)}</T>
                  <T taille={12} couleur={c.gris}>{m.ecole || 'Étudiant'}</T>
                </View>
                {m.id === donnees.my_id ? (
                  <T taille={12} couleur={c.grisFonce} style={{ paddingRight: 8 }}>Créateur</T>
                ) : (
                  <Pressable onPress={() => retirer(m.id)} accessibilityRole="button" accessibilityLabel="Retirer ce membre" hitSlop={10}>
                    <Icone nom="croix" taille={16} couleur={c.surRougeClair} />
                  </Pressable>
                )}
              </View>
            ))}
          </View>
          <Separateur />
          <Bouton libelle="Supprimer définitivement le Squad" variante="alerte" plein chargement={suppression} onPress={supprimer} style={{ marginTop: 16 }} />
          <Bouton libelle="Fermer" variante="contour" plein onPress={onClose} style={{ marginTop: 8 }} />
        </>
      )}
    </Feuille>
  );
}
