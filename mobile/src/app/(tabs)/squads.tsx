/**
 * Sport — l'onglet de l'univers sport, dôme pour l'accent.
 *
 * « Ne fais pas / de sport tout seul. », puis :
 *   - les filtres par sport (Tout, Padel, Tennis…), et le panneau Filtres :
 *     niveau, et distance (5 à 20 km) autour d'un point posé sur la carte ;
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
import { Apparition, PressableAnime } from '../../composants/Animations';
import { Bouton } from '../../composants/Bouton';
import { AvatarsEmpiles, EnTeteApp, Pastille, rangeeChips, Tuile, useRubrique } from '../../composants/Design';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Pilule, Separateur } from '../../composants/Elements';
import { Feuille } from '../../composants/Feuille';
import { FiltresSport, type FiltreDistance } from '../../composants/FiltresSport';
import { Case, Champ, ChampDate, isoDateHeure, Selecteur } from '../../composants/Formulaire';
import { Icone, type NomIcone } from '../../composants/Icone';
import { CarteCreneau, CarteEvenementSport, VideSport } from '../../composants/Sport';
import { Display, Mono, T, TitreEcran, TitreSection } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { confirmer } from '../../confirmer';
import { court, dateFr } from '../../format';
import { libelleVisibilite, niveauCompatible } from '../../regles';
import { useJeton } from '../../session';
import { couleursCategorie, fs, gutter, rayon, sans, taille } from '../../theme';
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

/** Les icônes des tuiles de rubrique et des chips de sport. */
const ICONES_RUBRIQUE: Record<Rubrique, NomIcone> = {
  parties: 'ajout-personne',
  terrains: 'terrain',
  clubs: 'club',
  abonnements: 'personnes',
};

const ICONES_SPORT: Record<string, NomIcone> = {
  padel: 'raquette', tennis: 'balle', golf: 'drapeau', squash: 'balle', badminton: 'raquette',
  foot5: 'balle', basket: 'balle', escalade: 'activite', running: 'activite', velo: 'activite',
  muscu: 'activite', yoga: 'soleil', autre: 'trophee',
};

/** La couleur du carré d'un sport : celle de la maquette, sinon une teinte de la palette. */
function couleurSport(code: string): string {
  const palette = ['#3B82F6', '#22B573', '#14B8A6', '#FF8A3D', '#F0508A', '#8B5CF6', '#FFB020'];
  return couleursCategorie[code] ?? palette[Array.from(code).reduce((n, ch) => n + ch.charCodeAt(0), 0) % palette.length];
}

function ouvrirCreneau(id: number) {
  router.push({ pathname: '/creneau/[id]', params: { id: String(id) } });
}

// ─── Événement d'étudiant (squad) ───────────────────────────────────────────

function CarteSquad({ s, onGerer }: { s: Squad; onGerer: () => void }) {
  const { c } = useTheme();
  const sport = useRubrique('sport');
  const jeton = useJeton();
  const toast = useToast();
  const [rejoint, setRejoint] = useState(false);
  const [attente, setAttente] = useState(false);
  const [membres, setMembres] = useState(s.places.membres);
  const complet = s.places.membres >= s.places.quota;

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
    <PressableAnime
      onPress={props.onPress}
      disabled={props.disabled}
      accessibilityRole="button"
      accessibilityLabel={libelle ?? 'Inscrit'}
      style={[{ flexDirection: 'row', alignItems: 'center', gap: 5, minHeight: 36, minWidth: 40, justifyContent: 'center', paddingHorizontal: 12, borderRadius: rayon.sm, borderWidth: 1, borderColor: 'transparent' }, style]}
    >
      {props.icone ? <Icone nom={props.icone} taille={14} couleur={couleur} trait={props.icone === 'check' ? 2.5 : 2} /> : null}
      {libelle ? <Text style={{ fontFamily: sans(600), fontSize: fs[4], color: couleur }}>{libelle}</Text> : null}
    </PressableAnime>
  );

  return (
    <View style={{ borderRadius: rayon.carte, padding: 14, marginBottom: 16, backgroundColor: c.blanc, gap: 8 }}>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
        <Pastille libelle={NIVEAUX_SQUAD[s.niveau] ?? s.niveau} fond={sport.doux} encre={sport.texteDoux} />
        <Pastille libelle={dateFr(s.date_heure, 'D j M · H\\hi')} fond={c.noir} encre={c.surEncre} style={{ flexShrink: 1 }} />
      </View>

      <View style={{ gap: 1 }}>
        <Text style={{ fontFamily: sans(600), fontSize: 10, letterSpacing: 0.06 * 10, textTransform: 'uppercase', color: c.gris, marginTop: 2 }}>
          {TYPES_SQUAD[s.type] ?? s.type} ·{' '}
          <Text style={{ color: s.visibilite === 'public' ? c.gris : c.succes }}>
            {s.cree_par_association ? 'Association' : libelleVisibilite(s.visibilite)}
          </Text>
        </Text>
        <Text numberOfLines={2} style={{ fontFamily: sans(700), fontSize: taille.titreCarte, lineHeight: taille.titreCarte * 1.2, letterSpacing: -0.02 * taille.titreCarte, color: c.noir }}>{s.titre}</Text>
        <T taille={fs[3]} couleur={c.gris} numberOfLines={1} style={{ marginTop: 2 }}>
          {s.est_createur ? 'Ton événement' : `Par ${court(s.createur.prenom, s.createur.nom)}`}{s.lieu ? ` · ${s.lieu}` : ''}
        </T>
        {s.description ? <T taille={fs[3]} couleur={c.gris} numberOfLines={2}>{Array.from(s.description).slice(0, 80).join('')}{Array.from(s.description).length > 80 ? '…' : ''}</T> : null}
      </View>

      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, paddingTop: 10, marginTop: 2, borderTopWidth: 1, borderTopColor: c.grisClair }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
          <AvatarsEmpiles personnes={s.membres.slice(0, 3).map((m, i) => ({ cle: `${m.prenom}${i}`, prenom: m.prenom }))} fond={c.blanc} taille={22} />
          <T taille={fs[3]} couleur={c.gris}>{membres}/{s.places.quota}</T>
        </View>

        {s.est_createur
          ? cta('Gérer', { backgroundColor: 'transparent', borderColor: c.line2 }, c.noir, { onPress: onGerer, icone: 'engrenage' })
          : s.deja_membre
            ? cta('Inscrit', { backgroundColor: c.surface2 }, c.grisFonce, { disabled: true, icone: 'check' })
            : rejoint
              ? cta(null, { backgroundColor: sport.accent }, '#FFFFFF', { disabled: true, icone: 'check' })
              : complet
                ? cta('Complet', { backgroundColor: c.surface2 }, c.grisFonce, { disabled: true })
                : cta(attente ? '…' : 'Rejoindre', { backgroundColor: sport.accent }, '#FFFFFF', { onPress: rejoindre, disabled: attente })}
      </View>
    </View>
  );
}

// ─── Écran ──────────────────────────────────────────────────────────────────

export default function Sport() {
  const jeton = useJeton();
  const toast = useToast();
  const [donnees, setDonnees] = useState<ReponseSport | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);
  const [sport, setSport] = useState('');
  const [rubrique, setRubrique] = useState<Rubrique>('parties');
  const [niveau, setNiveau] = useState('tous');
  const [distance, setDistance] = useState<FiltreDistance | null>(null);

  const [creation, setCreation] = useState(false);
  const [gestion, setGestion] = useState<number | null>(null);
  const [panneau, setPanneau] = useState(false);
  const accent = useRubrique('sport');

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      setDonnees(await api.sport(jeton, sport || undefined, distance ?? undefined));
    } catch (e) {
      setErreur(e instanceof Error ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
    }
  }, [jeton, sport, distance]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  const sports = [{ code: '', libelle: 'Tout' }, ...(donnees?.sports ?? Object.entries(SPORTS).map(([code, libelle]) => ({ code, libelle })))];
  const parties = (donnees?.parties ?? []).filter((p) => niveauCompatible(p.niveau, niveau));
  const nbFiltres = (niveau !== 'tous' ? 1 : 0) + (distance ? 1 : 0);
  const resumeFiltres = [
    niveau !== 'tous' ? `niveau : ${NIVEAUX.find((n) => n.code === niveau)?.libelle}` : null,
    distance ? `à moins de ${distance.rayon} km` : null,
  ].filter(Boolean).join(', ');
  /** Le complément des listes vides quand la distance filtre. */
  const horsZone = distance ? ` à moins de ${distance.rayon} km` : '';

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
          <FiltresSport
            visible={panneau}
            onClose={() => setPanneau(false)}
            niveaux={NIVEAUX}
            niveau={niveau}
            distance={distance}
            onAppliquer={(n, d) => { setNiveau(n); setDistance(d); }}
            accent={accent.accent}
          />
        </>
      }
    >
      <EnTete style={{ paddingBottom: 4 }}>
        <Apparition index={0}>
          <EnTeteApp
            rubrique="sport"
            // « Créer un événement » : la même feuille qu'avant, depuis l'en-tête.
            action={{ libelle: 'Créer', accessibilityLabel: 'Créer un événement', icone: 'plus', onPress: () => setCreation(true) }}
            onNotifications={() => router.navigate('/notifications')}
          />
        </Apparition>
        <Apparition index={1}>
          <TitreEcran avant="Ne fais pas de sport" surligne="tout seul." rubrique="sport" style={{ marginBottom: 16 }} />
        </Apparition>
      </EnTete>

      <Apparition index={2}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ flexGrow: 0, marginBottom: 20 }} contentContainerStyle={rangeeChips}>
          {/* Le niveau (parties à compléter seulement) et la distance sont
              dans le panneau Filtres. */}
          <Pilule
            libelle={nbFiltres ? `Filtres · ${nbFiltres}` : 'Filtres'}
            icone="reglages"
            contour
            actif={false}
            onPress={() => setPanneau(true)}
            accessibilityLabel={nbFiltres ? `Filtres, ${resumeFiltres}` : 'Filtres'}
          />
          {distance ? (
            <Pilule
              libelle={`${distance.rayon} km`}
              icone="epingle"
              actif
              onPress={() => setDistance(null)}
              accessibilityLabel={`Rayon de ${distance.rayon} km, retirer`}
            />
          ) : null}
          {sports.map((s) => (
            <Pilule
              key={s.code || 'tout'}
              libelle={s.libelle}
              actif={sport === s.code}
              onPress={() => setSport(s.code)}
              icone={s.code ? ICONES_SPORT[s.code] ?? 'trophee' : undefined}
              carre={s.code ? couleurSport(s.code) : undefined}
            />
          ))}
        </ScrollView>
      </Apparition>

      <Apparition index={3}>
        {/* Les rubriques en tuiles ; la quatrième déborde et invite à défiler. */}
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ flexGrow: 0, marginBottom: 20 }} contentContainerStyle={{ gap: 10, paddingHorizontal: gutter }}>
          {RUBRIQUES.map((r) => (
            <Tuile
              key={r.code}
              icone={ICONES_RUBRIQUE[r.code]}
              libelle={r.libelle}
              compteur={donnees ? nombre[r.code] : undefined}
              actif={rubrique === r.code}
              accentCompteur={accent.accent}
              onPress={() => setRubrique(r.code)}
              style={{ width: 112 }}
            />
          ))}
        </ScrollView>
      </Apparition>

      <Contenu>
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : donnees === null ? <Chargement /> : (
          <Apparition index={4}>
            {donnees.mes_parties.length > 0 ? (
              <>
                <TitreSection style={{ marginBottom: 12 }}>Mes parties</TitreSection>
                {donnees.mes_parties.map((p) => (
                  <CarteCreneau key={`m${p.id}`} cr={p} action={p.statut === 'annule' ? 'Annulée' : 'Ma partie'} onPress={() => ouvrirCreneau(p.id)} />
                ))}
                <View style={{ height: 8 }} />
              </>
            ) : null}

            {rubrique === 'parties' ? (
              <>
                {parties.map((p) => <CarteCreneau key={p.id} cr={p} action="Compléter" onPress={() => ouvrirCreneau(p.id)} />)}
                {parties.length === 0 ? (
                  <VideSport titre={`Aucune partie à compléter${horsZone}.`} texte="Réserve un terrain libre et ouvre les places restantes : d'autres viendront compléter." />
                ) : null}
              </>
            ) : null}

            {rubrique === 'terrains' ? (
              <>
                {donnees.libres.map((p) => <CarteCreneau key={p.id} cr={p} action="Réserver" onPress={() => ouvrirCreneau(p.id)} />)}
                {donnees.libres.length === 0 ? <VideSport titre={`Aucun terrain libre${horsZone}.`} texte="Les clubs n'ont pas encore mis de créneaux en ligne." /> : null}
              </>
            ) : null}

            {rubrique === 'clubs' ? (
              <>
                {donnees.evenements.map((ev) => (
                  <CarteEvenementSport key={ev.id} ev={ev} onPress={() => router.push({ pathname: '/evenement/[id]', params: { id: String(ev.id) } })} />
                ))}
                {donnees.evenements.length === 0 ? <VideSport titre={`Aucun événement de club${horsZone}.`} texte="Tournois, stages et initiations apparaîtront ici." /> : null}
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
          </Apparition>
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
      <Display taille={fs[6]} style={{ marginBottom: 20 }}>Créer un événement</Display>
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
      <Bouton libelle="Publier" variante="sport" plein chargement={attente} onPress={creer} />
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
      <Display taille={fs[6]} style={{ marginBottom: 20 }}>Gérer mon événement</Display>
      {donnees === null ? (
        <T taille={fs[4]} style={{ textAlign: 'center', padding: 14 }}>Chargement...</T>
      ) : (
        <>
          <Mono accessibilityRole="header" style={{ marginBottom: 12 }}>Participants inscrits</Mono>
          <View style={{ gap: 8, marginBottom: 24 }}>
            {donnees.membres.map((m) => (
              <View key={m.id} style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 12, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.sm, backgroundColor: c.blanc }}>
                <View>
                  <T taille={fs[4]} poids={700}>{court(m.prenom, m.nom)}</T>
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
