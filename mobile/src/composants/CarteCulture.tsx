/**
 * La carte d'un événement de l'espace Culture.
 *
 * Même gabarit que la carte des soirées (CarteEvenement) — pastilles de
 * statut, surtitre, titre, amis qui y vont, « Inviter » et l'inscription —
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
import { Pressable, Text, View } from 'react-native';
import { router } from 'expo-router';

import { api, ErreurApi, type EvenementCulture } from '../api';
import { LIBELLES_CULTURE } from '../catalogue';
import { libelleBoutonCulture, libellePlaces, libellePrix, surPlusieursJours, type EtatInscriptionCulture } from '../culture';
import { dateFr, majuscules, ts } from '../format';
import { useJeton } from '../session';
import { fs, lh, sans } from '../theme';
import { useTheme } from '../useTheme';
import { PastilleAVenir, PastilleEnCours, STYLE_CARTE, TITRE } from './CarteEvenement';
import { BoutonAction, BoutonContour, CarteRefonte, Pastille, QuiYVa, useRubrique } from './Design';
import { Icone } from './Icone';
import { T } from './Texte';
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
  // Refonte : les confettis ne partent qu'après une inscription réussie.
  const [fete, setFete] = useState(0);

  async function inscrire() {
    setEtat('attente');
    try {
      const rep = await api.cultureInscription(jeton, ev.id);
      setInscrits(rep.inscrits);
      setEtat('inscrit');
      setFete((n) => n + 1);
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

  return { etat, inscrits, inscrire, annuler, fete };
}

/**
 * Refonte 2026-11 : la 1ʳᵉ carte de la liste est violette (`accent`), les
 * suivantes blanches. Dans l'ordre : pastilles de statut et « À la une »,
 * le prix à droite ; le surtitre et le titre ; l'organisateur en gras ; le
 * lieu et ses places sur une ligne ; la description ; « qui y va » ;
 * « Inviter » et « S'inscrire ». La jauge reste dans la fiche.
 */
export function CarteCulture({ ev, onInviter, accent = false }: { ev: EvenementCulture; onInviter: () => void; accent?: boolean }) {
  const { c } = useTheme();
  const violet = useRubrique('culture');
  const { etat, inscrits, inscrire, fete } = useInscriptionCulture(ev);
  const places = inscrits === null ? ev.places : { ...ev.places, inscrits };
  const inscrit = etat === 'inscrit';
  const ouvrir = () => router.push({ pathname: '/culture/[id]', params: { id: String(ev.id) } });

  const encre = accent ? '#FFFFFF' : c.noir;
  const secondaire = accent ? violet.surAccent2 : c.gris;
  const voile = 'rgba(255,255,255,0.18)';

  return (
    <CarteRefonte accent={accent ? violet.accent : undefined} style={STYLE_CARTE}>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, flexShrink: 1, flexWrap: 'wrap' }}>
          {ev.en_cours ? <PastilleEnCours petite quoi="Événement" surAccent={accent} /> : <PastilleAVenir petite quoi="Événement" surAccent={accent} />}
          {ev.mis_en_avant ? <Pastille petite libelle="À la une" icone="etoile-fine" fond={accent ? voile : violet.doux} encre={accent ? '#FFFFFF' : violet.texteDoux} /> : null}
        </View>
        <Pastille petite libelle={libellePrix(ev.prix)} fond={accent ? '#FFFFFF' : c.noir} encre={accent ? '#111116' : c.surEncre} />
      </View>

      <Pressable onPress={ouvrir} accessibilityRole="link" accessibilityHint="Ouvre la fiche de l'événement" style={{ gap: 1, marginTop: 2 }}>
        <Text style={{ fontFamily: sans(600), fontSize: 10, letterSpacing: 0.06 * 10, textTransform: 'uppercase', color: secondaire }}>{etiquetteCulture(ev)}</Text>
        <Text numberOfLines={2} style={{ fontFamily: sans(700), fontSize: TITRE, lineHeight: TITRE * 1.2, letterSpacing: -0.02 * TITRE, color: encre }}>{ev.titre}</Text>
      </Pressable>

      <Pressable onPress={ouvrir} style={{ gap: 3 }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
          <Icone nom="musee" taille={14} couleur={encre} />
          <T numberOfLines={1} taille={fs[3]} poids={600} couleur={encre} style={{ flexShrink: 1 }}>{ev.organisateur}</T>
        </View>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
          <Icone nom="epingle" taille={14} couleur={secondaire} />
          <T numberOfLines={1} taille={fs[3]} couleur={secondaire} style={{ flexShrink: 1 }}>{ev.lieu} · {libellePlaces(places)}</T>
        </View>
      </Pressable>

      {ev.description ? (
        <T taille={fs[3]} couleur={secondaire} interligne={lh.snug} numberOfLines={2}>
          {Array.from(ev.description).length > 100 ? Array.from(ev.description).slice(0, 100).join('') + '…' : ev.description}
        </T>
      ) : null}

      <QuiYVa prenoms={ev.amis.prenoms} nb={ev.amis.nb} surAccent={accent} fond={accent ? violet.accent : c.blanc} />

      {/* Les actions se rangent à droite, à la taille de leur libellé. */}
      <View style={{ flexDirection: 'row', justifyContent: 'flex-end', gap: 8, marginTop: 2 }}>
        <BoutonContour iconeSeule libelle="Inviter" icone="ajout-personne" onPress={onInviter} surAccent={accent} />
        <BoutonAction
          fin
          style={{ minWidth: 124 }}
          libelle={libelleBoutonCulture(etat === 'attente' ? 'libre' : etat, places.complet)}
          accent={violet.accent}
          surAccent={accent}
          fait={inscrit}
          desactive={inscrit || places.complet}
          chargement={etat === 'attente'}
          onPress={inscrire}
          confettis={fete}
        />
      </View>
    </CarteRefonte>
  );
}
