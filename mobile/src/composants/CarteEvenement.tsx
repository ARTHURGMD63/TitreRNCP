/**
 * La carte d'événement du hub (Explorer).
 *
 * Refonte 2026-11 : une seule carte, accent pour la première de la liste,
 * blanche pour les suivantes. Une offre flash garde son décompte, sa
 * réduction et son compte à rebours ; seul l'habillage change.
 *
 * Suivre le lieu et s'inscrire passent par les mêmes points d'API que
 * toujours, avec les mêmes libellés d'erreur et les mêmes toasts. Les
 * confettis ne partent qu'après une inscription réussie.
 */

import React, { useEffect, useState } from 'react';
import { Animated, Pressable, Text, View } from 'react-native';
import { router } from 'expo-router';

import { actions, ErreurApi, type Evenement } from '../api';
import { LIBELLES_TYPE } from '../catalogue';
import { dateFr, ts } from '../format';
import { prixEvenement } from '../regles';
import { useJeton } from '../session';
import { fixe, fs, rayon, sans } from '../theme';
import { useTheme } from '../useTheme';
import { PressableAnime } from './Animations';
import { BoutonAction, BoutonContour, CarteRefonte, Pastille, QuiYVa, useRubrique } from './Design';
import { Icone } from './Icone';
import { T } from './Texte';
import { useToast } from './Toast';

// ─── Comptes à rebours ──────────────────────────────────────────────────────

function useMaintenant(actif = true) {
  const [maintenant, setMaintenant] = useState(() => Date.now());
  useEffect(() => {
    if (!actif) return;
    const id = setInterval(() => setMaintenant(Date.now()), 1000);
    return () => clearInterval(id);
  }, [actif]);
  return maintenant;
}

/** Un point qui respire (opacité 1 → 0,25 → 1), pour dire « ça se passe maintenant ». */
function usePouls() {
  const [opacite] = useState(() => new Animated.Value(1));
  useEffect(() => {
    const boucle = Animated.loop(
      Animated.sequence([
        Animated.timing(opacite, { toValue: 0.25, duration: 650, useNativeDriver: true }),
        Animated.timing(opacite, { toValue: 1, duration: 650, useNativeDriver: true }),
      ])
    );
    boucle.start();
    return () => boucle.stop();
  }, [opacite]);
  return opacite;
}

/**
 * « En cours » : le point vert respire pour dire « ça se passe
 * maintenant ». Sur une carte accent, la pastille est un voile blanc.
 */
export function PastilleEnCours({ quoi = 'Soirée', surAccent, petite }: { quoi?: string; surAccent?: boolean; petite?: boolean }) {
  const { c } = useTheme();
  const opacite = usePouls();
  return (
    <View
      accessible accessibilityLabel={`${quoi} en cours`}
      style={{ flexDirection: 'row', alignItems: 'center', gap: 6, paddingVertical: petite ? 3 : 5, paddingHorizontal: petite ? 8 : 10, borderRadius: petite ? 8 : rayon.sm, backgroundColor: surAccent ? 'rgba(255,255,255,0.18)' : c.surface2 }}
    >
      <Animated.View style={{ width: 7, height: 7, borderRadius: 4, backgroundColor: fixe.enCours, opacity: opacite }} />
      <Text style={{ fontFamily: sans(500), fontSize: petite ? fs[2] : fs[3], color: surAccent ? '#FFFFFF' : c.noir }}>En cours</Text>
    </View>
  );
}

/** Pendant statique de PastilleEnCours pour ce qui n'a pas encore commencé. */
export function PastilleAVenir({ quoi = 'Soirée', surAccent, petite }: { quoi?: string; surAccent?: boolean; petite?: boolean }) {
  const { c } = useTheme();
  return (
    <View
      accessible accessibilityLabel={`${quoi} à venir`}
      style={{ flexDirection: 'row', alignItems: 'center', paddingVertical: petite ? 3 : 5, paddingHorizontal: petite ? 8 : 10, borderRadius: petite ? 8 : rayon.sm, backgroundColor: surAccent ? 'rgba(255,255,255,0.18)' : c.surface2 }}
    >
      <Text style={{ fontFamily: sans(500), fontSize: petite ? fs[2] : fs[3], color: surAccent ? '#FFFFFF' : c.noir }}>À venir</Text>
    </View>
  );
}

/** « Flash · 12min 05s », puis « Expiré » (app.js, [data-expiry]). */
function libelleFlash(expiry: number, maintenant: number) {
  const diff = Math.max(0, expiry * 1000 - maintenant);
  if (diff <= 0) return 'Expiré';
  const h = Math.floor(diff / 3600000);
  const mins = Math.floor((diff % 3600000) / 60000);
  const secs = Math.floor((diff % 60000) / 1000);
  // Au-delà d'une heure, les secondes n'apprennent rien : « 2h55 ».
  if (h > 0) return `Flash · ${h}h${mins < 10 ? '0' : ''}${mins}`;
  return `Flash · ${mins}min ${secs < 10 ? '0' : ''}${secs}s`;
}

/** « dans 2h05 », « dans 12min04s », « dans 3j », « EN COURS » (app.js). */
export function libelleDebut(debut: number, maintenant: number) {
  const diff = debut * 1000 - maintenant;
  if (diff <= 0) return 'EN COURS';
  const h = Math.floor(diff / 3600000);
  const m = Math.floor((diff % 3600000) / 60000);
  const s = Math.floor((diff % 60000) / 1000);
  if (h > 48) return `dans ${Math.floor(h / 24)}j`;
  if (h > 0) return `dans ${h}h${m < 10 ? '0' : ''}${m}`;
  return `dans ${m}min${s < 10 ? '0' : ''}${s}s`;
}

// ─── Suivre un lieu (.btn-suivre-lieu) ──────────────────────────────────────

/**
 * « + Suivre » / « Suivi ». `media` : posé sur une carte accent ou une
 * photo — contour blanc translucide.
 */
function BoutonSuivreLieu({ etabId, suivi, media }: { etabId: number; suivi: boolean; media?: boolean }) {
  const { c } = useTheme();
  const jeton = useJeton();
  const toast = useToast();
  const [etat, setEtat] = useState<'none' | 'accepted'>(suivi ? 'accepted' : 'none');
  const [attente, setAttente] = useState(false);

  async function basculer() {
    if (attente) return;
    setAttente(true);
    try {
      const rep = await actions.suivi(jeton, etat === 'none' ? 'follow' : 'unfollow', 'etablissement', etabId);
      const nouvel = rep.etat === 'accepted' ? 'accepted' : 'none';
      setEtat(nouvel);
      toast(rep.message || (rep.etat === 'none' ? 'Abonnement retiré' : 'Demande envoyée'));
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setAttente(false);
    }
  }

  const abonne = etat === 'accepted';
  const fond = abonne ? (media ? '#FFFFFF' : c.noir) : 'transparent';
  const encre = abonne ? (media ? '#111116' : c.surEncre) : media ? '#FFFFFF' : c.noir;
  const filet = abonne ? fond : media ? 'rgba(255,255,255,0.45)' : c.line2;

  return (
    <PressableAnime
      onPress={basculer}
      accessibilityRole="button"
      accessibilityLabel={abonne ? 'Ne plus suivre ce lieu' : 'Suivre ce lieu'}
      hitSlop={8}
      style={{ flexShrink: 0, flexDirection: 'row', alignItems: 'center', gap: 4, borderWidth: 1, borderColor: filet, borderRadius: 8, minHeight: 28, paddingHorizontal: 10, backgroundColor: fond }}
    >
      {attente ? (
        <Text style={{ fontFamily: sans(600), fontSize: fs[2], color: encre }}>…</Text>
      ) : (
        <>
          {abonne ? <Icone nom="check" taille={12} couleur={encre} trait={2.5} /> : null}
          <Text style={{ fontFamily: sans(600), fontSize: fs[2], color: encre }}>{abonne ? 'Suivi' : '+ Suivre'}</Text>
        </>
      )}
    </PressableAnime>
  );
}

// ─── S'inscrire (.btn-join-event) ───────────────────────────────────────────

function useInscription(ev: Evenement) {
  const jeton = useJeton();
  const toast = useToast();
  const [etat, setEtat] = useState<'libre' | 'attente' | 'inscrit' | 'vient'>(ev.deja_inscrit ? 'inscrit' : 'libre');
  const [libelleErreur, setLibelleErreur] = useState<string | null>(null);
  // Le serveur a le dernier mot : quand la liste rechargée dit autre chose
  // (pass annulé depuis l'onglet Pass), la carte suit au lieu de garder
  // l'état de sa première apparition.
  const [inscritVu, setInscritVu] = useState(ev.deja_inscrit);
  if (ev.deja_inscrit !== inscritVu) {
    setInscritVu(ev.deja_inscrit);
    setEtat(ev.deja_inscrit ? 'inscrit' : 'libre');
    setLibelleErreur(null);
  }
  // Refonte : les confettis ne partent qu'après la réussite réelle.
  const [fete, setFete] = useState(0);

  async function inscrire() {
    setEtat('attente');
    try {
      await actions.inscrire(jeton, ev.id);
      setEtat('vient');
      setFete((n) => n + 1);
      toast('Tu es inscrit ! Rendez-vous ce soir.', 'success');
    } catch (e) {
      const msg = e instanceof ErreurApi ? e.message : 'Erreur réseau';
      // Comme le site : le bouton affiche la raison du refus (« Complet »).
      setLibelleErreur(e instanceof ErreurApi && e.statut !== 0 ? msg : '→ je rejoins');
      setEtat('libre');
      toast(msg, 'error');
    }
  }
  return { etat, libelleErreur, inscrire, fete };
}

// ─── Carte d'événement ──────────────────────────────────────────────────────

/**
 * Gabarit fin des cartes d'événement (2026-11-16) : sur téléphone, la
 * première version prenait la moitié de l'écran pour une seule soirée.
 * Partagé avec CarteCulture.
 */
export const TITRE = 18;
export const STYLE_CARTE = { padding: 14, gap: 8, borderRadius: 20 } as const;

/**
 *  - en haut : pastille de statut (« En cours », « À venir », ou le
 *    décompte d'une offre flash) et pastille de date ;
 *  - réduction, gratuité, sponsorisé, s'il y a lieu ;
 *  - le surtitre (style de musique, sinon type de lieu), le titre ;
 *  - le lieu, ses places et sa note, avec « + Suivre » ;
 *  - le compte à rebours de la première offre flash, comme avant ;
 *  - « qui y va », si des amis y vont ;
 *  - « Inviter » et l'action principale.
 *
 * La description et la jauge d'inscription vivent dans la fiche de
 * l'événement, qui les affichait déjà.
 */
function Carte({ ev, accent, premiere, flash, onInviter }: { ev: Evenement; accent: boolean; premiere: boolean; flash: boolean; onInviter: () => void }) {
  const { c } = useTheme();
  const bleu = useRubrique('bleu');
  const maintenant = useMaintenant(flash);
  const { etat, libelleErreur, inscrire, fete } = useInscription(ev);
  const inscrit = etat === 'inscrit' || etat === 'vient';
  const ouvrir = () => router.push({ pathname: '/evenement/[id]', params: { id: String(ev.id) } });
  const reduction = ev.reduction ?? 0;
  const prix = prixEvenement(ev);

  const encre = accent ? '#FFFFFF' : c.noir;
  const secondaire = accent ? bleu.surAccent2 : c.gris;
  const voile = 'rgba(255,255,255,0.18)';
  const dateType = `${dateFr(ev.date_heure, 'D j M')} · ${LIBELLES_TYPE[ev.type] ?? ev.type}`;
  const surtitre = ev.style_musique ? libelleStyle(ev.style_musique) : LIBELLES_TYPE[ev.type] ?? ev.type;

  return (
    <CarteRefonte accent={accent ? bleu.accent : undefined} style={STYLE_CARTE}>
      {/* Une seule ligne de pastilles : statut, date, puis remise et mentions. */}
      <View style={{ flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 6 }}>
        {flash && ev.flash_expiry ? (
          <Pastille petite libelle={libelleFlash(ts(ev.flash_expiry), maintenant)} icone="eclair" fond={accent ? voile : c.surface2} encre={encre} />
        ) : ev.en_cours ? <PastilleEnCours petite surAccent={accent} /> : <PastilleAVenir petite surAccent={accent} />}
        <Pastille petite libelle={dateType} fond={accent ? '#FFFFFF' : c.noir} encre={accent ? '#111116' : c.surEncre} style={{ flexShrink: 1 }} />
        {reduction > 0 ? (
          <Pastille petite libelle={`-${reduction}% ${ev.is_gratuit ? '· entrée gratuite' : 'sur conso'}`} fond={accent ? voile : c.succesClair} encre={accent ? '#FFFFFF' : c.succes} />
        ) : null}
        {prix ? <Pastille petite libelle={`Prix ${prix.final}`} fond={accent ? voile : c.surface2} encre={encre} /> : null}
        {ev.is_gratuit && reduction <= 0 ? <Pastille petite libelle="Gratuit" fond={accent ? voile : c.surface2} encre={encre} /> : null}
        {ev.sponsorise ? <Pastille petite libelle="Sponsorisé" fond={accent ? voile : c.surface2} encre={accent ? '#FFFFFF' : c.grisFonce} /> : null}
      </View>

      <Pressable onPress={ouvrir} accessibilityRole="link" accessibilityHint="Ouvre la fiche de l'événement" style={{ gap: 1, marginTop: 2 }}>
        <Text style={{ fontFamily: sans(600), fontSize: 10, letterSpacing: 0.06 * 10, textTransform: 'uppercase', color: secondaire }}>{surtitre}</Text>
        <Text numberOfLines={2} style={{ fontFamily: sans(700), fontSize: TITRE, lineHeight: TITRE * 1.2, letterSpacing: -0.02 * TITRE, color: encre }}>{ev.titre}</Text>
      </Pressable>

      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10 }}>
        <Pressable onPress={ouvrir} style={{ flex: 1, minWidth: 0 }}>
          <T taille={fs[3]} couleur={secondaire} numberOfLines={1}>
            {ev.etablissement.nom} · {ev.places.inscrits}/{ev.places.quota} places
          </T>
          {ev.etablissement.nb_avis > 0 ? (
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 3, marginTop: 2 }}>
              <Icone nom="etoile" taille={12} couleur={accent ? '#FFFFFF' : c.surOrangeClair} />
              <T taille={fs[2]} poids={700} couleur={accent ? '#FFFFFF' : c.surOrangeClair}>{formaterNote(ev.etablissement.note)}</T>
              <T taille={fs[2]} couleur={secondaire}>({ev.etablissement.nb_avis} avis)</T>
            </View>
          ) : null}
        </Pressable>
        <BoutonSuivreLieu etabId={ev.etablissement.id} suivi={ev.etablissement.suivi} media={accent} />
      </View>

      {flash && premiere ? (
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
          <Icone nom="horloge" taille={14} couleur={secondaire} />
          <T taille={fs[3]} couleur={secondaire}>Commence</T>
          <T taille={fs[3]} poids={700} couleur={encre} style={{ fontVariant: ['tabular-nums'] }}>{libelleDebut(ts(ev.date_heure), maintenant)}</T>
        </View>
      ) : null}

      <QuiYVa prenoms={ev.amis.prenoms} nb={ev.amis.nb} surAccent={accent} fond={accent ? bleu.accent : c.blanc} />

      {/* Les actions se rangent à droite, à la taille de leur libellé. */}
      <View style={{ flexDirection: 'row', justifyContent: 'flex-end', gap: 8, marginTop: 2 }}>
        <BoutonContour iconeSeule libelle="Inviter" icone="ajout-personne" onPress={onInviter} surAccent={accent} />
        <BoutonAction
          fin
          style={{ minWidth: 124 }}
          libelle={inscrit ? 'Inscrit' : ev.places.complet ? 'Complet' : libelleErreur ?? "S'inscrire"}
          accent={bleu.accent}
          surAccent={accent}
          fait={inscrit}
          desactive={inscrit || ev.places.complet}
          chargement={etat === 'attente'}
          onPress={inscrire}
          confettis={fete}
        />
      </View>
    </CarteRefonte>
  );
}

// ─── Commun ─────────────────────────────────────────────────────────────────

/** Libellés des styles de musique, reçus avec le fil (libelleStyleMusique()). */
let STYLES: Record<string, string> = {};
export function enregistrerStyles(liste: { code: string; libelle: string }[]) {
  STYLES = Object.fromEntries(liste.map((s) => [s.code, s.libelle]));
}
export function libelleStyle(code: string) {
  return STYLES[code] ?? code;
}

/** La note telle que PHP l'imprime : « 4.5 », « 4 ». */
function formaterNote(n: number | null) {
  if (n === null) return '';
  return String(Math.round(n * 10) / 10);
}

/**
 * @param accent   La 1ʳᵉ carte de la liste : aplat de la couleur de la rubrique.
 * @param premiere La première offre flash encore valable : elle porte le
 *        compte à rebours avant le début, comme avant.
 */
export function CarteEvenement({ ev, premiere, accent = false, onInviter }: { ev: Evenement; premiere: boolean; accent?: boolean; onInviter: () => void }) {
  const [instant] = useState(() => Date.now() / 1000);
  const flash = ev.is_flash && ts(ev.flash_expiry) > instant;
  return <Carte ev={ev} accent={accent} premiere={premiere} flash={flash} onInviter={onInviter} />;
}

export { BoutonSuivreLieu };
