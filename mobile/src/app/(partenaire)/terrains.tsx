/**
 * Terrains — l'espace d'un club sportif (onglet affiché pour les clubs seuls).
 *
 * Trois choses, de haut en bas :
 *   - le planning des sept prochains jours : chaque créneau, libre, réservé
 *     (par qui, combien de places ouvertes) ou annulé ; toucher un créneau
 *     libre le retire, toucher un créneau réservé l'annule ;
 *   - « Publier des créneaux » : un terrain, un jour, une plage horaire, une
 *     récurrence — l'application calcule les horaires (debutsCreneaux), le
 *     serveur écarte ce qui est passé ou se chevauche ;
 *   - les terrains eux-mêmes : nom, sport, joueurs, durée, prix.
 *
 * Le prix est celui du terrain pour un créneau, réglé au club par les
 * joueurs : Outsee n'encaisse rien. La publication est comprise dans la
 * formule Club.
 */

import React, { useCallback, useState } from 'react';
import { Pressable, ScrollView, View } from 'react-native';
import { useFocusEffect } from 'expo-router';

import { api, ErreurApi, type Creneau, type ReponseTerrainsClub, type TerrainClub } from '../../api';
import { SPORTS } from '../../catalogue';
import { Bouton } from '../../composants/Bouton';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Encart, Pilule } from '../../composants/Elements';
import { Feuille } from '../../composants/Feuille';
import { Case, Champ, ChampDate, isoDate, Selecteur } from '../../composants/Formulaire';
import { Icone } from '../../composants/Icone';
import { libelleSport } from '../../composants/Sport';
import { Display, Mono, T, TitreEcran } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { confirmer } from '../../confirmer';
import { dateFr } from '../../format';
import { debutsCreneaux, formatEuros, joursRecurrents } from '../../regles';
import { useJeton } from '../../session';
import { fs, rayon } from '../../theme';
import { useTheme } from '../../useTheme';

const RECURRENCES = [
  { valeur: '1', libelle: 'Ce jour seulement' },
  { valeur: '2', libelle: '2 semaines' },
  { valeur: '4', libelle: '4 semaines' },
  { valeur: '8', libelle: '8 semaines' },
];

function LigneCreneau({ cr, onToucher }: { cr: Creneau; onToucher: () => void }) {
  const { c } = useTheme();
  const libre = cr.statut === 'libre';
  const annule = cr.statut === 'annule';
  const etat = libre
    ? 'Libre'
    : annule
      ? 'Annulé'
      : `Réservé par ${cr.organisateur?.prenom ?? 'un joueur'} · ${cr.places.prises}/${cr.places.total}${cr.ouvert && cr.places.restantes > 0 ? ' · ouverte' : ''}`;
  const couleur = libre ? c.gris : annule ? c.danger : c.surBleuClair;
  return (
    <Pressable
      onPress={annule ? undefined : onToucher}
      accessibilityRole="button"
      accessibilityLabel={`${cr.terrain.nom} ${dateFr(cr.debut, 'H\\hi')}, ${etat}`}
      style={{ flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12, borderTopWidth: 1, borderTopColor: c.grisClair }}
    >
      <Mono couleur={c.noir} style={{ width: 52 }}>{dateFr(cr.debut, 'H\\hi')}</Mono>
      <View style={{ flex: 1 }}>
        <T taille={fs[3]} poids={700}>{cr.terrain.nom}</T>
        <T taille={fs[2]} couleur={couleur}>{etat}</T>
      </View>
      {!annule ? <Icone nom="croix" taille={14} couleur={c.gris} /> : null}
    </Pressable>
  );
}

export default function Terrains() {
  const { c } = useTheme();
  const jeton = useJeton();
  const toast = useToast();

  const [donnees, setDonnees] = useState<ReponseTerrainsClub | null>(null);
  const [planning, setPlanning] = useState<Creneau[] | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);
  const [edition, setEdition] = useState<TerrainClub | 'nouveau' | null>(null);
  const [publication, setPublication] = useState(false);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      const [t, p] = await Promise.all([api.clubTerrains(jeton), api.clubCreneaux(jeton, isoDate(new Date()), 7)]);
      setDonnees(t);
      setPlanning(p.creneaux);
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
    }
  }, [jeton]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  async function toucherCreneau(cr: Creneau) {
    const texte = cr.statut === 'reserve'
      ? `Annuler la partie de ${cr.organisateur?.prenom ?? 'ce joueur'} ? Ses ${cr.places.prises} joueur(s) verront l'annulation.`
      : 'Retirer ce créneau libre ?';
    if (!(await confirmer(texte))) return;
    try {
      const rep = await api.clubSupprimerCreneau(jeton, cr.id);
      toast(rep.message ?? 'Fait.', 'success');
      void charger();
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    }
  }

  if (!donnees || !planning) {
    return (
      <Ecran>
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : <Chargement />}
      </Ecran>
    );
  }

  const actifs = donnees.terrains.filter((t) => t.actif);
  const parJour = planning.reduce<Record<string, Creneau[]>>((acc, cr) => {
    const jour = cr.debut.slice(0, 10);
    (acc[jour] ??= []).push(cr);
    return acc;
  }, {});

  return (
    <Ecran
      rafraichit={rafraichit}
      onRafraichir={() => { setRafraichit(true); void charger(); }}
      horsDefilement={
        <>
          <FeuilleTerrain
            terrain={edition}
            onClose={() => setEdition(null)}
            onEnregistre={(rep, message) => { setDonnees(rep); setEdition(null); toast(message, 'success'); }}
          />
          <FeuillePublication
            visible={publication}
            terrains={actifs}
            onClose={() => setPublication(false)}
            onPublie={(message) => { setPublication(false); toast(message, 'success'); void charger(); }}
          />
        </>
      }
    >
      <EnTete>
        <TitreEcran lignes={['Mes', 'terrains.']} accent={c.surBleuClair} />
        <T taille={fs[3]} couleur={c.gris} style={{ marginTop: 10 }}>
          Mets tes créneaux en ligne : les étudiants réservent entre amis ou complètent une partie. Le prix se règle chez toi.
        </T>
      </EnTete>

      <Contenu style={{ paddingTop: 0 }}>
        {actifs.length === 0 ? (
          <Encart genre="info">
            <T taille={fs[3]} couleur={c.grisFonce}>Ajoute un premier terrain pour pouvoir publier des créneaux.</T>
          </Encart>
        ) : (
          <Bouton libelle="+ Publier des créneaux" variante="bleu" plein onPress={() => setPublication(true)} style={{ marginBottom: 20 }} />
        )}

        <Mono accessibilityRole="header" style={{ marginBottom: 8 }}>Les 7 prochains jours</Mono>
        {planning.length === 0 ? (
          <T taille={fs[3]} couleur={c.gris} style={{ marginBottom: 20 }}>Aucun créneau en ligne cette semaine.</T>
        ) : (
          Object.entries(parJour).map(([jour, liste]) => (
            <View key={jour} style={{ backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.base, paddingHorizontal: 16, paddingTop: 14, marginBottom: 12 }}>
              <T taille={fs[4]} poids={700} style={{ marginBottom: 6 }}>{dateFr(`${jour} 12:00:00`, 'l j F')}</T>
              {liste.map((cr) => <LigneCreneau key={cr.id} cr={cr} onToucher={() => toucherCreneau(cr)} />)}
            </View>
          ))
        )}

        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 16, marginBottom: 12 }}>
          <Mono accessibilityRole="header">Terrains</Mono>
          <Pressable onPress={() => setEdition('nouveau')} accessibilityRole="button" hitSlop={8} style={{ flexDirection: 'row', alignItems: 'center', gap: 4 }}>
            <Icone nom="plus" taille={14} couleur={c.surBleuClair} />
            <T taille={fs[3]} poids={700} couleur={c.surBleuClair}>Ajouter</T>
          </Pressable>
        </View>
        {donnees.terrains.map((t) => (
          <Pressable
            key={t.id}
            onPress={() => setEdition(t)}
            accessibilityRole="button"
            style={{ backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.base, padding: 16, marginBottom: 10, opacity: t.actif ? 1 : 0.6 }}
          >
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: 10 }}>
              <T taille={fs[4]} poids={700} style={{ flex: 1 }}>{t.nom}</T>
              <Icone nom="outil" taille={14} couleur={c.gris} />
            </View>
            <T taille={fs[3]} couleur={c.gris}>
              {libelleSport(t.sport)} · {t.joueurs} joueurs · {t.duree_min} min · {formatEuros(t.prix)}{t.actif ? '' : ' · désactivé'}
            </T>
            <T taille={fs[2]} couleur={c.gris} style={{ marginTop: 4 }}>
              {t.creneaux_libres} libre{t.creneaux_libres > 1 ? 's' : ''} · {t.creneaux_reserves} réservé{t.creneaux_reserves > 1 ? 's' : ''} à venir
            </T>
          </Pressable>
        ))}
      </Contenu>
    </Ecran>
  );
}

// ─── Ajouter / modifier un terrain ──────────────────────────────────────────

function FeuilleTerrain({ terrain, onClose, onEnregistre }: {
  terrain: TerrainClub | 'nouveau' | null;
  onClose: () => void;
  onEnregistre: (rep: ReponseTerrainsClub, message: string) => void;
}) {
  const jeton = useJeton();
  const toast = useToast();
  const [vu, setVu] = useState(terrain);
  const [nom, setNom] = useState('');
  const [sport, setSport] = useState('padel');
  const [joueurs, setJoueurs] = useState('4');
  const [duree, setDuree] = useState('90');
  const [prix, setPrix] = useState('0');
  const [actif, setActif] = useState(true);
  const [attente, setAttente] = useState(false);

  // Chaque ouverture repart de la fiche du terrain touché (ou d'une fiche vierge).
  if (terrain !== vu) {
    setVu(terrain);
    const t = terrain && terrain !== 'nouveau' ? terrain : null;
    setNom(t?.nom ?? '');
    setSport(t?.sport ?? 'padel');
    setJoueurs(String(t?.joueurs ?? 4));
    setDuree(String(t?.duree_min ?? 90));
    setPrix(String(t?.prix ?? 0));
    setActif(t?.actif ?? true);
  }
  const existant = terrain && terrain !== 'nouveau' ? terrain : null;

  async function enregistrer() {
    setAttente(true);
    try {
      const rep = await api.clubEnregistrerTerrain(jeton, {
        id: existant?.id, nom: nom.trim(), sport,
        joueurs: parseInt(joueurs, 10) || 0, duree_min: parseInt(duree, 10) || 0,
        prix: Number(prix.replace(',', '.')) || 0, actif,
      });
      onEnregistre(rep, rep.message ?? 'Enregistré.');
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setAttente(false);
    }
  }

  async function supprimer() {
    if (!existant || !(await confirmer('Supprimer ce terrain et ses créneaux libres ?'))) return;
    setAttente(true);
    try {
      const rep = await api.clubSupprimerTerrain(jeton, existant.id);
      onEnregistre(rep, rep.message ?? 'Terrain supprimé.');
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setAttente(false);
    }
  }

  return (
    <Feuille visible={terrain !== null} onClose={onClose}>
      <Display taille={fs[7]} style={{ marginBottom: 20 }}>{existant ? 'Modifier le terrain' : 'Nouveau terrain'}</Display>
      <Champ etiquette="Nom" value={nom} onChangeText={setNom} placeholder="Terrain 1, couvert" />
      <Selecteur etiquette="Sport" valeur={sport} onChange={setSport} options={Object.entries(SPORTS).map(([valeur, libelle]) => ({ valeur, libelle }))} />
      <View style={{ flexDirection: 'row', gap: 12 }}>
        <Champ style={{ flex: 1 }} etiquette="Joueurs" value={joueurs} onChangeText={(v) => setJoueurs(v.replace(/[^0-9]/g, ''))} keyboardType="number-pad" />
        <Champ style={{ flex: 1 }} etiquette="Durée (min)" value={duree} onChangeText={(v) => setDuree(v.replace(/[^0-9]/g, ''))} keyboardType="number-pad" />
      </View>
      <Champ etiquette="Prix du créneau (€)" value={prix} onChangeText={setPrix} keyboardType="decimal-pad" aide="Prix du terrain entier, réglé chez toi. L'app affiche la part par joueur." />
      {existant ? (
        <Case coche={actif} onChange={setActif} style={{ marginBottom: 16 }}>Terrain actif (réservable)</Case>
      ) : null}
      <Bouton libelle="Enregistrer" variante="bleu" plein chargement={attente} onPress={enregistrer} />
      {existant ? <Bouton libelle="Supprimer le terrain" variante="alerte" plein onPress={supprimer} style={{ marginTop: 8 }} /> : null}
      <Bouton libelle="Annuler" variante="contour" plein onPress={onClose} style={{ marginTop: 8 }} />
    </Feuille>
  );
}

// ─── Publier des créneaux ───────────────────────────────────────────────────

function FeuillePublication({ visible, terrains, onClose, onPublie }: {
  visible: boolean;
  terrains: TerrainClub[];
  onClose: () => void;
  onPublie: (message: string) => void;
}) {
  const { c } = useTheme();
  const jeton = useJeton();
  const toast = useToast();
  const [terrainId, setTerrainId] = useState<number | null>(null);
  const [jour, setJour] = useState<Date | null>(null);
  const [de, setDe] = useState('09:00');
  const [a, setA] = useState('22:00');
  const [semaines, setSemaines] = useState('1');
  const [attente, setAttente] = useState(false);

  const terrain = terrains.find((t) => t.id === terrainId) ?? terrains[0] ?? null;
  const debuts = terrain && jour ? debutsCreneaux(joursRecurrents(isoDate(jour), Number(semaines)), de, a, terrain.duree_min) : [];

  async function publier() {
    if (!terrain || debuts.length === 0) return;
    setAttente(true);
    try {
      const rep = await api.clubPublierCreneaux(jeton, terrain.id, debuts);
      onPublie(rep.message ?? 'Créneaux en ligne.');
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setAttente(false);
    }
  }

  return (
    <Feuille visible={visible} onClose={onClose}>
      <Display taille={fs[7]} style={{ marginBottom: 20 }}>Publier des créneaux</Display>
      <Mono style={{ marginBottom: 8 }}>Terrain</Mono>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ flexGrow: 0, marginBottom: 16 }} contentContainerStyle={{ gap: 8 }}>
        {terrains.map((t) => (
          <Pilule key={t.id} libelle={t.nom} actif={terrain?.id === t.id} accent={c.bleu} onPress={() => setTerrainId(t.id)} />
        ))}
      </ScrollView>
      <ChampDate etiquette="Jour" mode="date" valeur={jour} onChange={setJour} min={new Date()} />
      <View style={{ flexDirection: 'row', gap: 12 }}>
        <Champ style={{ flex: 1 }} etiquette="De" value={de} onChangeText={setDe} placeholder="09:00" keyboardType="numbers-and-punctuation" />
        <Champ style={{ flex: 1 }} etiquette="À" value={a} onChangeText={setA} placeholder="22:00" keyboardType="numbers-and-punctuation" />
      </View>
      <Selecteur etiquette="Récurrence" valeur={semaines} onChange={setSemaines} options={RECURRENCES} />
      <T taille={fs[3]} couleur={c.grisFonce} style={{ marginBottom: 16 }}>
        {terrain && jour
          ? debuts.length > 0
            ? `${debuts.length} créneau${debuts.length > 1 ? 'x' : ''} de ${terrain.duree_min} min, à ${formatEuros(terrain.prix)} chacun.`
            : 'Aucun créneau entier dans cette plage horaire.'
          : 'Choisis un jour pour voir les créneaux.'}
      </T>
      <Bouton libelle="Mettre en ligne" variante="bleu" plein chargement={attente} desactive={debuts.length === 0} onPress={publier} />
      <Bouton libelle="Annuler" variante="contour" plein onPress={onClose} style={{ marginTop: 8 }} />
    </Feuille>
  );
}
