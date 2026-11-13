/**
 * Sport — l'onglet de l'univers sport, dôme pour l'accent.
 *
 * « Ne fais pas / de sport tout seul. », puis :
 *   - les filtres par sport (Tout, Padel, Tennis…) ;
 *   - « Mes parties » quand j'en ai, en tête : c'est ce qui me concerne ;
 *   - quatre rubriques :
 *       Parties à compléter : des étudiants ont réservé un terrain et ouvert
 *         les places restantes aux inconnus, filtrables par niveau ;
 *       Terrains libres : les créneaux que les clubs ont mis en ligne ;
 *       Clubs : les événements des clubs (tournois, stages…) ;
 *       Mes abonnements : les événements d'étudiants que je peux voir —
 *         ceux des personnes qui m'ont accepté, et ceux des associations.
 *
 * Tout vient d'un seul appel (api/v1/sport.php). Créer un événement est
 * gratuit et le réserve à mes abonnés (sport_squad.php).
 */

import React, { useCallback, useState } from 'react';
import { Pressable, ScrollView, Text, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';

import { actions, api, ErreurApi, type ReponseSport, type Squad } from '../../api';
import { NIVEAUX_SQUAD, SPORTS, TYPES_SQUAD } from '../../catalogue';
import { Bouton } from '../../composants/Bouton';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Pilule, Separateur } from '../../composants/Elements';
import { Feuille } from '../../composants/Feuille';
import { Case, Champ, ChampDate, isoDateHeure, Selecteur } from '../../composants/Formulaire';
import { Icone } from '../../composants/Icone';
import { Marque } from '../../composants/Marque';
import { CarteCreneau, CarteEvenementSport, VideSport } from '../../composants/Sport';
import { Display, Mono, T, TitreEcran } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { confirmer } from '../../confirmer';
import { court, dateFr, majuscules } from '../../format';
import { libelleVisibilite, niveauCompatible } from '../../regles';
import { useJeton } from '../../session';
import { fixe, fs, gutter, lh, police, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

type Rubrique = 'parties' | 'terrains' | 'clubs' | 'abonnements';

const RUBRIQUES: { code: Rubrique; libelle: string }[] = [
  { code: 'parties', libelle: 'Parties à compléter' },
  { code: 'terrains', libelle: 'Terrains libres' },
  { code: 'clubs', libelle: 'Clubs' },
  { code: 'abonnements', libelle: 'Mes abonnements' },
];

const NIVEAUX = [
  { code: 'tous', libelle: 'Tous niveaux' },
  { code: 'debutant', libelle: 'Débutant' },
  { code: 'inter', libelle: 'Inter.' },
  { code: 'avance', libelle: 'Avancé' },
];

function ouvrirCreneau(id: number) {
  router.push({ pathname: '/creneau/[id]', params: { id: String(id) } });
}

// ─── Événement d'étudiant (squad) ───────────────────────────────────────────

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
      toast('Tu participes !', 'success');
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
        {majuscules(TYPES_SQUAD[s.type] ?? s.type)} · {dateFr(s.date_heure, 'D H\\hi')} ·{' '}
        <Text style={{ color: s.visibilite === 'public' ? c.surBleuClair : c.surLimeClair }}>
          {majuscules(s.cree_par_association ? 'Association' : libelleVisibilite(s.visibilite))}
        </Text>
      </Mono>
      <Display taille={fs[6]} interligne={lh.tight} style={{ marginBottom: 6, paddingRight: 70 }}>{s.titre}</Display>
      <T taille={fs[3]} couleur={c.grisFonce} style={{ marginBottom: 12 }}>
        {s.est_createur ? 'Ton événement' : `Par ${court(s.createur.prenom, s.createur.nom)}`}{s.lieu ? ` · ${s.lieu}` : ''}
      </T>
      {s.description ? <T taille={fs[3]} couleur={c.grisFonce} style={{ marginBottom: 12 }}>{Array.from(s.description).slice(0, 80).join('')}{Array.from(s.description).length > 80 ? '…' : ''}</T> : null}

      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, paddingTop: 15, borderTopWidth: 1, borderTopColor: c.grisClair }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
          <View style={{ flexDirection: 'row' }}>
            {initiales.map((l, i) => (
              <View key={i} style={{ width: 35, height: 35, borderRadius: 18, marginLeft: i === 0 ? 0 : -15, backgroundColor: c.blanc, alignItems: 'center', justifyContent: 'center' }}>
                <View style={{ width: 30, height: 30, borderRadius: 15, backgroundColor: c.bleu, alignItems: 'center', justifyContent: 'center' }}>
                  <Text style={{ fontFamily: police.display, fontSize: fs[1], color: fixe.surLave }}>{l}</Text>
                </View>
              </View>
            ))}
          </View>
          <T taille={fs[3]} poids={500} couleur={c.grisFonce} style={{ marginLeft: 3 }}>{membres}/{s.places.quota}</T>
        </View>

        {s.est_createur
          ? cta('Gérer', { backgroundColor: 'transparent', borderColor: c.noir }, c.noir, { onPress: onGerer, icone: 'engrenage' })
          : s.deja_membre
            ? cta('Inscrit', { backgroundColor: c.surface2, borderColor: c.line2 }, c.grisFonce, { disabled: true, icone: 'check' })
            : rejoint
              ? cta(null, { backgroundColor: c.bleu }, fixe.surLave, { disabled: true, icone: 'check' })
              : complet
                ? cta('Complet', { backgroundColor: c.surface2, borderColor: c.line2 }, c.grisFonce, { disabled: true })
                : cta(attente ? '…' : 'Rejoindre', { backgroundColor: c.bleu }, fixe.surLave, { onPress: rejoindre, disabled: attente })}
      </View>
    </View>
  );
}

// ─── Écran ──────────────────────────────────────────────────────────────────

export default function Sport() {
  const { c } = useTheme();
  const jeton = useJeton();
  const toast = useToast();
  const [donnees, setDonnees] = useState<ReponseSport | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);
  const [sport, setSport] = useState('');
  const [rubrique, setRubrique] = useState<Rubrique>('parties');
  const [niveau, setNiveau] = useState('tous');

  const [creation, setCreation] = useState(false);
  const [gestion, setGestion] = useState<number | null>(null);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      setDonnees(await api.sport(jeton, sport || undefined));
    } catch (e) {
      setErreur(e instanceof Error ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
    }
  }, [jeton, sport]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  const sports = [{ code: '', libelle: 'Tout' }, ...(donnees?.sports ?? Object.entries(SPORTS).map(([code, libelle]) => ({ code, libelle })))];
  const parties = (donnees?.parties ?? []).filter((p) => niveauCompatible(p.niveau, niveau));

  const nombre: Record<Rubrique, number> = {
    parties: donnees?.parties.length ?? 0,
    terrains: donnees?.libres.length ?? 0,
    clubs: donnees?.evenements.length ?? 0,
    abonnements: donnees?.squads.length ?? 0,
  };

  return (
    <Ecran
      rafraichit={rafraichit}
      onRafraichir={() => { setRafraichit(true); void charger(); }}
      horsDefilement={
        <>
          <FeuilleCreation visible={creation} onClose={() => setCreation(false)} onCree={(m) => { toast(m, 'success'); setCreation(false); setRubrique('abonnements'); void charger(); }} />
          <FeuilleGestion squadId={gestion} onClose={() => setGestion(null)} onSupprime={() => { setGestion(null); void charger(); }} />
        </>
      }
    >
      <EnTete>
        <View style={{ marginBottom: 14 }}>
          <Marque taille={fs[6]} accent={c.bleu} />
        </View>
        <TitreEcran lignes={['Ne fais pas', 'de sport tout seul.']} accent={c.surBleuClair} />
      </EnTete>

      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ flexGrow: 0 }} contentContainerStyle={{ gap: 8, paddingHorizontal: gutter, paddingBottom: 8 }}>
        {sports.map((s) => (
          <Pilule key={s.code || 'tout'} libelle={s.libelle} actif={sport === s.code} accent={c.bleu} onPress={() => setSport(s.code)} />
        ))}
      </ScrollView>

      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ flexGrow: 0 }} contentContainerStyle={{ gap: 8, paddingHorizontal: gutter, paddingBottom: 16 }}>
        {RUBRIQUES.map((r) => (
          <Pilule
            key={r.code}
            libelle={donnees && nombre[r.code] > 0 ? `${r.libelle} · ${nombre[r.code]}` : r.libelle}
            actif={rubrique === r.code}
            accent={c.bleu}
            onPress={() => setRubrique(r.code)}
          />
        ))}
      </ScrollView>

      <Contenu>
        <Bouton libelle="+ Créer un événement" variante="contour" plein onPress={() => setCreation(true)} style={{ marginBottom: 16 }} />

        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : donnees === null ? <Chargement /> : (
          <>
            {donnees.mes_parties.length > 0 ? (
              <>
                <Mono accessibilityRole="header" style={{ marginBottom: 12 }}>Mes parties</Mono>
                {donnees.mes_parties.map((p) => (
                  <CarteCreneau key={`m${p.id}`} cr={p} action={p.statut === 'annule' ? 'Annulée' : 'Ma partie'} onPress={() => ouvrirCreneau(p.id)} />
                ))}
                <Separateur />
              </>
            ) : null}

            {rubrique === 'parties' ? (
              <>
                <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ flexGrow: 0, marginBottom: 14 }} contentContainerStyle={{ gap: 8 }}>
                  {NIVEAUX.map((n) => (
                    <Pilule key={n.code} libelle={n.libelle} actif={niveau === n.code} accent={c.bleu} onPress={() => setNiveau(n.code)} />
                  ))}
                </ScrollView>
                {parties.map((p) => <CarteCreneau key={p.id} cr={p} action="Compléter" onPress={() => ouvrirCreneau(p.id)} />)}
                {parties.length === 0 ? (
                  <VideSport titre="Aucune partie à compléter." texte="Réserve un terrain libre et ouvre les places restantes : d'autres viendront compléter." />
                ) : null}
              </>
            ) : null}

            {rubrique === 'terrains' ? (
              <>
                {donnees.libres.map((p) => <CarteCreneau key={p.id} cr={p} action="Réserver" onPress={() => ouvrirCreneau(p.id)} />)}
                {donnees.libres.length === 0 ? <VideSport titre="Aucun terrain libre." texte="Les clubs n'ont pas encore mis de créneaux en ligne." /> : null}
              </>
            ) : null}

            {rubrique === 'clubs' ? (
              <>
                {donnees.evenements.map((ev) => (
                  <CarteEvenementSport key={ev.id} ev={ev} onPress={() => router.push({ pathname: '/evenement/[id]', params: { id: String(ev.id) } })} />
                ))}
                {donnees.evenements.length === 0 ? <VideSport titre="Aucun événement de club." texte="Tournois, stages et initiations apparaîtront ici." /> : null}
              </>
            ) : null}

            {rubrique === 'abonnements' ? (
              <>
                {donnees.squads.map((s) => <CarteSquad key={s.id} s={s} onGerer={() => setGestion(s.id)} />)}
                {donnees.squads.length === 0 ? (
                  <VideSport titre="Rien chez tes abonnements." texte="Tu vois ici les événements des personnes qui ont accepté que tu les suives." />
                ) : null}
              </>
            ) : null}
          </>
        )}
      </Contenu>
    </Ecran>
  );
}

// ─── Créer un événement ─────────────────────────────────────────────────────

function FeuilleCreation({ visible, onClose, onCree }: { visible: boolean; onClose: () => void; onCree: (message: string) => void }) {
  const { c } = useTheme();
  const jeton = useJeton();
  const toast = useToast();
  const [titre, setTitre] = useState('');
  const [type, setType] = useState('running');
  const [niveau, setNiveau] = useState('tous');
  const [date, setDate] = useState<Date | null>(null);
  const [quota, setQuota] = useState('10');
  const [lieu, setLieu] = useState('');
  const [description, setDescription] = useState('');
  const [association, setAssociation] = useState(false);
  const [attente, setAttente] = useState(false);

  async function creer() {
    setAttente(true);
    try {
      const rep = await api.creerEvenementSport(jeton, {
        titre: titre.trim(), type, niveau, date_heure: date ? isoDateHeure(date) : '',
        quota: parseInt(quota, 10) || 0, lieu: lieu.trim(), description: description.trim(),
        cree_par_association: association,
      });
      setTitre(''); setDate(null); setQuota('10'); setLieu(''); setDescription(''); setAssociation(false);
      onCree(rep.message ?? 'Événement publié.');
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setAttente(false);
    }
  }

  return (
    <Feuille visible={visible} onClose={onClose}>
      <Display taille={fs[7]} style={{ marginBottom: 20 }}>Créer un événement</Display>
      <Champ etiquette="Titre" value={titre} onChangeText={setTitre} placeholder="Sortie Puy-de-Dôme" />
      <View style={{ flexDirection: 'row', gap: 12 }}>
        <Selecteur style={{ flex: 1 }} etiquette="Sport" valeur={type} onChange={setType}
          options={Object.entries(SPORTS).map(([valeur, libelle]) => ({ valeur, libelle }))} />
        <Selecteur style={{ flex: 1 }} etiquette="Niveau" valeur={niveau} onChange={setNiveau}
          options={NIVEAUX.map((n) => ({ valeur: n.code, libelle: n.code === 'tous' ? 'Tous' : n.libelle }))} />
      </View>
      <View style={{ flexDirection: 'row', gap: 12 }}>
        <View style={{ flex: 1 }}>
          <ChampDate etiquette="Date & heure" mode="datetime" valeur={date} onChange={setDate} min={new Date()} />
        </View>
        <Champ style={{ flex: 1 }} etiquette="Max participants" value={quota} onChangeText={(v) => setQuota(v.replace(/[^0-9]/g, ''))} keyboardType="number-pad" />
      </View>
      <Champ etiquette="Lieu de rendez-vous" value={lieu} onChangeText={setLieu} placeholder="Parking Royat" />
      <Champ etiquette="Description" value={description} onChangeText={setDescription} placeholder="Détails sur la sortie..." multiligne />
      <Case coche={association} onChange={setAssociation} style={{ marginBottom: 12 }}>
        <T taille={fs[4]}>Créé par une association étudiante</T>
      </Case>
      <View style={{ flexDirection: 'row', gap: 10, alignItems: 'flex-start', backgroundColor: c.surface2, borderRadius: rayon.md, padding: 14, marginBottom: 16 }}>
        <Icone nom={association ? 'personnes' : 'cadenas'} taille={16} couleur={c.grisFonce} />
        <T taille={fs[3]} couleur={c.grisFonce} style={{ flex: 1 }}>
          {association
            ? 'Public : un événement d’association est visible de tous les étudiants.'
            : 'Gratuit et réservé à tes abonnés : seules les personnes dont tu as accepté la demande le voient et peuvent le rejoindre.'}
        </T>
      </View>
      <Bouton libelle="Publier" variante="bleu" plein chargement={attente} onPress={creer} />
      <Bouton libelle="Annuler" variante="contour" plein onPress={onClose} style={{ marginTop: 8 }} />
    </Feuille>
  );
}

// ─── Gérer mon événement ────────────────────────────────────────────────────

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
    if (squadId === null || !(await confirmer('Retirer cette personne de l’événement ?'))) return;
    try {
      await actions.retirerMembre(jeton, squadId, membreId);
      setDonnees((d) => d && { ...d, membres: d.membres.filter((m) => m.id !== membreId) });
      toast('Participant retiré', 'success');
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur', 'error');
    }
  }

  async function supprimer() {
    if (squadId === null || !(await confirmer('Veux-tu vraiment supprimer définitivement cet événement ?'))) return;
    setSuppression(true);
    try {
      await actions.supprimerSquad(jeton, squadId);
      toast('Événement supprimé', 'success');
      onSupprime();
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setSuppression(false);
    }
  }

  return (
    <Feuille visible={squadId !== null} onClose={onClose}>
      <Display taille={fs[7]} style={{ marginBottom: 20 }}>Gérer mon événement</Display>
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
                  <Pressable onPress={() => retirer(m.id)} accessibilityRole="button" accessibilityLabel="Retirer ce participant" hitSlop={10}>
                    <Icone nom="croix" taille={16} couleur={c.surRougeClair} />
                  </Pressable>
                )}
              </View>
            ))}
          </View>
          <Separateur />
          <Bouton libelle="Supprimer définitivement l'événement" variante="alerte" plein chargement={suppression} onPress={supprimer} style={{ marginTop: 16 }} />
          <Bouton libelle="Fermer" variante="contour" plein onPress={onClose} style={{ marginTop: 8 }} />
        </>
      )}
    </Feuille>
  );
}
