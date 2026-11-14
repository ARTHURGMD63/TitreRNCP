/**
 * La carte d'un événement de l'espace Culture.
 *
 * Même gabarit que la carte classique des soirées (CarteEvenement) — pastille
 * « En cours » / « À venir », étiquette en mono teintée, titre, amis qui y
 * vont, description coupée, places, « Inviter » et l'inscription, jauge —
 * pour qu'on la lise sans réapprendre. Ce qui change dit ce qui diffère :
 *
 *  - l'organisateur (la ville, un musée) à la place de l'établissement, avec
 *    l'icône du musée ; pas de note ni de « + Suivre » ;
 *  - une exposition en cours s'annonce « JUSQU'AU 3 DÉC » plutôt que par son
 *    jour d'ouverture, passé depuis longtemps ;
 *  - l'entrée libre (quota 0) n'a pas de jauge : « 412 inscrits » n'y serait
 *    rapporté à rien ;
 *  - « À la une » quand la ville le met en avant.
 */

import React, { useState } from 'react';
import { Pressable, View } from 'react-native';
import { router } from 'expo-router';

import { api, ErreurApi, type EvenementCulture } from '../api';
import { LIBELLES_CULTURE } from '../catalogue';
import { libelleAmis, libelleBoutonCulture, libellePlaces, libellePrix, pourcentagePlaces, surPlusieursJours, type EtatInscriptionCulture } from '../culture';
import { dateFr, majuscules, ts } from '../format';
import { useJeton } from '../session';
import { fs, haloLave, lh, rayon } from '../theme';
import { useTheme } from '../useTheme';
import { Bouton } from './Bouton';
import { PastilleAVenir, PastilleEnCours } from './CarteEvenement';
import { Badge, Jauge } from './Elements';
import { Icone } from './Icone';
import { Display, Mono, T } from './Texte';
import { useToast } from './Toast';

/** « EXPO · JUSQU'AU 3 DÉC », « CONCERT · SAM 15 NOV · 20H30 ». */
export function etiquetteCulture(ev: Pick<EvenementCulture, 'categorie' | 'date_heure' | 'date_fin'>): string {
  const categorie = majuscules(LIBELLES_CULTURE[ev.categorie] ?? ev.categorie);
  if (surPlusieursJours(ts(ev.date_heure), ts(ev.date_fin))) {
    return `${categorie} · ${majuscules(`Jusqu'au ${dateFr(ev.date_fin, 'j M')}`)}`;
  }
  return `${categorie} · ${majuscules(dateFr(ev.date_heure, 'D j M · H\\hi'))}`;
}

/** L'inscription, partagée par la carte et la fiche. */
export function useInscriptionCulture(ev: { id: number; deja_inscrit: boolean }) {
  const jeton = useJeton();
  const toast = useToast();
  const [etat, setEtat] = useState<EtatInscriptionCulture>(ev.deja_inscrit ? 'inscrit' : 'libre');
  const [inscrits, setInscrits] = useState<number | null>(null);

  async function inscrire() {
    setEtat('attente');
    try {
      const rep = await api.cultureInscription(jeton, ev.id);
      setInscrits(rep.inscrits);
      setEtat('inscrit');
      toast('Tu es inscrit ! Invite tes potes.', 'success');
    } catch (e) {
      setEtat('libre');
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    }
  }

  async function annuler() {
    setEtat('attente');
    try {
      const rep = await api.cultureInscription(jeton, ev.id, true);
      setInscrits(rep.inscrits);
      setEtat('libre');
      toast('Inscription annulée');
    } catch (e) {
      setEtat('inscrit');
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    }
  }

  return { etat, inscrits, inscrire, annuler };
}

export function CarteCulture({ ev, onInviter }: { ev: EvenementCulture; onInviter: () => void }) {
  const { c, ombre } = useTheme();
  const { etat, inscrits, inscrire } = useInscriptionCulture(ev);
  const places = inscrits === null ? ev.places : { ...ev.places, inscrits };
  const pct = pourcentagePlaces(places);
  const amis = libelleAmis(ev.amis);
  const inscrit = etat === 'inscrit';
  const ouvrir = () => router.push({ pathname: '/culture/[id]', params: { id: String(ev.id) } });

  return (
    <View
      style={[
        { backgroundColor: c.blanc, padding: 20, borderRadius: rayon.base, borderWidth: 1, borderColor: c.grisClair, marginBottom: 20 },
        ev.en_cours ? [{ borderWidth: 2, borderColor: c.rouge }, haloLave] : ombre('base'),
      ]}
    >
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 10 }}>
        {ev.en_cours ? <PastilleEnCours quoi="Événement" /> : <PastilleAVenir quoi="Événement" />}
        {ev.mis_en_avant ? <Badge libelle="À la une" icone="etoile-fine" fond={c.rougeClair} encre={c.surRougeClair} style={{ gap: 4 }} /> : null}
      </View>

      <View style={{ flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 8, marginBottom: 8 }}>
        <Mono couleur={c.surRougeClair}>{etiquetteCulture(ev)}</Mono>
        {ev.is_gratuit
          ? <Badge libelle="GRATUIT" fond={c.noir} encre={c.bg} />
          : <Badge libelle={libellePrix(ev.prix)} fond={c.surface2} encre={c.grisFonce} filet={c.line2} />}
      </View>

      <Pressable onPress={ouvrir} accessibilityRole="link">
        <Display taille={fs[6]} interligne={lh.tight} style={{ marginBottom: 8 }}>{ev.titre}</Display>
      </Pressable>

      <Pressable onPress={ouvrir} style={{ gap: 4, marginBottom: 4 }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
          <Icone nom="musee" taille={14} couleur={c.grisFonce} />
          <T numberOfLines={1} taille={fs[3]} poids={600} couleur={c.grisFonce} style={{ flexShrink: 1 }}>{ev.organisateur}</T>
        </View>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
          <Icone nom="epingle" taille={14} couleur={c.gris} />
          <T numberOfLines={1} taille={fs[3]} couleur={c.gris} style={{ flexShrink: 1 }}>{ev.lieu}</T>
        </View>
      </Pressable>

      {amis ? (
        <View style={{ marginTop: 8, flexDirection: 'row', alignItems: 'center', gap: 4 }}>
          <Icone nom="personnes" taille={14} couleur={c.surRougeClair} />
          <T taille={fs[3]} poids={600} couleur={c.surRougeClair}>{amis}</T>
        </View>
      ) : null}

      {ev.description ? (
        <T taille={fs[3]} couleur={c.grisFonce} interligne={lh.snug} style={{ marginTop: 12 }}>
          {Array.from(ev.description).length > 100 ? Array.from(ev.description).slice(0, 100).join('') + '...' : ev.description}
        </T>
      ) : null}

      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8, marginTop: 14 }}>
        <T taille={fs[3]} couleur={c.gris} style={{ flexShrink: 1 }}>{libellePlaces(places)}</T>
        <View style={{ flexDirection: 'row', gap: 6 }}>
          <Pressable
            onPress={onInviter}
            accessibilityRole="button"
            accessibilityLabel={`Inviter un ami à ${ev.titre}`}
            style={{ flexDirection: 'row', alignItems: 'center', gap: 5, borderWidth: 1.5, borderColor: c.line2, borderRadius: rayon.pill, paddingVertical: 8, paddingHorizontal: 14, minHeight: 44 }}
          >
            <Icone nom="ajout-personne" taille={13} couleur={c.noir} />
            <T taille={fs[3]} poids={700}>Inviter</T>
          </Pressable>
          <Bouton
            variante="primaire"
            libelle={libelleBoutonCulture(etat === 'attente' ? 'libre' : etat, places.complet)}
            icone={inscrit ? 'check' : undefined}
            desactive={inscrit || places.complet}
            chargement={etat === 'attente'}
            onPress={inscrire}
            taillePolice={fs[3]}
            style={{ paddingVertical: 8, paddingHorizontal: 18 }}
          />
        </View>
      </View>

      {pct !== null ? (
        <>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 14, marginBottom: 6 }}>
            <Mono>Taux d&apos;inscription</Mono>
            <Mono>{pct}%</Mono>
          </View>
          <Jauge pourcentage={pct} piste={c.grisClair} remplissage={c.rouge} />
        </>
      ) : null}
    </View>
  );
}
