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
import { ActivityIndicator, Animated, AppState, Easing, Image, Pressable, Text, View } from 'react-native';
import { router } from 'expo-router';

import { actions, ErreurApi, type Evenement } from '../api';
import { ICONES_TYPE } from '../catalogue';
import { dateFr, dureeCourte, heure, ts } from '../format';
import { creerHorloge } from '../horloge';
import { libelleApresEchecInscription } from '../regles';
import { useJeton } from '../session';
import { couleursCategorie, espace, fixe, fs, rayon, sans } from '../theme';
import { useTheme } from '../useTheme';
import { PressableAnime, useMouvementReduit } from './Animations';
import { BoutonAction, BoutonContour, CarteRefonte, Pastille, QuiYVa, useRubrique } from './Design';
import { Jauge } from './Elements';
import { Icone } from './Icone';
import { T } from './Texte';
import { useToast } from './Toast';

// ─── Comptes à rebours ──────────────────────────────────────────────────────

/**
 * Une seule minuterie d'une seconde pour toutes les cartes (horloge.ts),
 * arrêtée quand l'application passe en arrière-plan : personne ne regarde
 * le décompte, la batterie n'a pas à le payer.
 */
const horloge = creerHorloge(1000);
let suitAppState = false;

function suivrePremierPlan() {
  if (suitAppState) return;
  suitAppState = true;
  if (AppState.currentState && AppState.currentState !== 'active') horloge.pause();
  AppState.addEventListener('change', (etat) => {
    if (etat === 'active') horloge.reprendre();
    else horloge.pause();
  });
}

/**
 * L'heure, rafraîchie chaque seconde tant que `actif`. `jusqua` (ms) arrête
 * l'horloge une fois cet instant passé : le décompte avant le début d'une
 * soirée n'a plus rien à compter ensuite.
 */
function useMaintenant(actif = true, jusqua?: number) {
  const [maintenant, setMaintenant] = useState(() => Date.now());
  useEffect(() => {
    if (!actif) return;
    suivrePremierPlan();
    let desabonner: (() => void) | null = null;
    desabonner = horloge.abonner((t) => {
      setMaintenant(t);
      if (jusqua !== undefined && t >= jusqua && desabonner) {
        desabonner();
        desabonner = null;
      }
    });
    return () => { desabonner?.(); };
  }, [actif, jusqua]);
  return maintenant;
}

/**
 * Un point qui respire (opacité 1 → 0,4 → 1, en sinus sur 1,8 s), pour dire
 * « ça se passe maintenant ». Le battement linéaire de 1,3 s clignotait
 * plus qu'il ne respirait ; immobile en mouvement réduit.
 */
function usePouls() {
  const reduit = useMouvementReduit();
  const [opacite] = useState(() => new Animated.Value(1));
  useEffect(() => {
    if (reduit) { opacite.setValue(1); return; }
    const courbe = Easing.inOut(Easing.sin);
    const boucle = Animated.loop(
      Animated.sequence([
        Animated.timing(opacite, { toValue: 0.4, duration: 900, easing: courbe, useNativeDriver: true }),
        Animated.timing(opacite, { toValue: 1, duration: 900, easing: courbe, useNativeDriver: true }),
      ])
    );
    boucle.start();
    return () => boucle.stop();
  }, [opacite, reduit]);
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
      style={{ flexDirection: 'row', alignItems: 'center', gap: 6, paddingVertical: petite ? 3 : 5, paddingHorizontal: petite ? 8 : 10, borderRadius: petite ? 8 : rayon.sm, backgroundColor: surAccent ? fixe.voileAccent : c.surface2 }}
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
      style={{ flexDirection: 'row', alignItems: 'center', paddingVertical: petite ? 3 : 5, paddingHorizontal: petite ? 8 : 10, borderRadius: petite ? 8 : rayon.sm, backgroundColor: surAccent ? fixe.voileAccent : c.surface2 }}
    >
      <Text style={{ fontFamily: sans(500), fontSize: petite ? fs[2] : fs[3], color: surAccent ? '#FFFFFF' : c.noir }}>À venir</Text>
    </View>
  );
}

/** « Flash · 12min05s », « Flash · 2h55 », « Flash · 1j11h », puis « Expiré » (app.js, [data-expiry]). */
function libelleFlash(expiry: number, maintenant: number) {
  const diff = expiry * 1000 - maintenant;
  if (diff <= 0) return 'Expiré';
  return `Flash · ${dureeCourte(diff)}`;
}

/** « dans 1j11h », « dans 2h05 », « dans 12min04s », « EN COURS » (app.js). */
export function libelleDebut(debut: number, maintenant: number) {
  const diff = debut * 1000 - maintenant;
  if (diff <= 0) return 'EN COURS';
  return `dans ${dureeCourte(diff)}`;
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
  const filet = abonne ? fond : media ? fixe.filetMedia : c.line2;

  return (
    <PressableAnime
      onPress={basculer}
      accessibilityRole="button"
      accessibilityLabel={abonne ? 'Ne plus suivre ce lieu' : 'Suivre ce lieu'}
      accessibilityState={{ busy: attente, checked: abonne }}
      disabled={attente}
      hitSlop={8}
      style={{ flexShrink: 0, flexDirection: 'row', alignItems: 'center', gap: 4, borderWidth: 1.5, borderColor: filet, borderRadius: 8, minHeight: 30, paddingHorizontal: 10, backgroundColor: fond }}
    >
      {/* Même règle que les autres boutons : en attente, la roue prend la
          place de l'icône et le libellé reste, la largeur ne bouge pas. */}
      {attente ? <ActivityIndicator size="small" color={encre} style={{ width: 12, height: 12, transform: [{ scale: 0.7 }] }} />
        : abonne ? <Icone nom="check" taille={12} couleur={encre} trait={2.5} /> : null}
      <Text style={{ fontFamily: sans(600), fontSize: fs[2], color: encre }}>{abonne ? 'Suivi' : '+ Suivre'}</Text>
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
      setLibelleErreur(libelleApresEchecInscription(e instanceof ErreurApi ? e.statut : null, msg));
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
/** Le titre de la carte d'événement, plus grand depuis la refonte du 2026-11-19. */
const TITRE_GRAND = 20;
export const STYLE_CARTE = { padding: espace.carte, gap: espace.ecart, borderRadius: rayon.carte } as const;

/** Le carré de catégorie des cartes, à gauche du titre (36 px, comme les chips du hub en plus grand). */
export const CARRE_CATEGORIE = 36;

/** Hauteur de la photo du lieu en haut de la carte. */
const HAUTEUR_MEDIA = 140;
/** La tuile de date chevauche la photo de la moitié de sa hauteur. */
const TUILE_DATE = { largeur: 56, hauteur: 60 } as const;
/** Au-delà de ce remplissage, la carte affiche « Plus que N places ». */
const SEUIL_RARETE = 70;
const ORANGE_RARETE = '#FF5424';

/**
 * Carte d'événement (refonte du 2026-11-19), pensée pour donner envie :
 *  - en haut, la photo de couverture du lieu (sinon un aplat de la couleur
 *    de la catégorie et sa grande icône), avec le statut à gauche et le
 *    style de musique à droite ;
 *  - la date en tuile, à cheval sur la photo ;
 *  - le titre, puis le lieu, l'horaire et « + Suivre » (suivre un lieu, c'est
 *    recevoir ses soirées dans « Pour moi » et une notification à chaque
 *    nouvel événement) ;
 *  - un seul signal : les amis qui y vont, sinon « Plus que N places » quand
 *    la soirée est presque pleine, sinon rien ;
 *  - « Inviter » en icône et une seule action principale, à droite.
 *
 * Prix, réduction, âge minimum, co-organisateurs, note du lieu et clôture
 * des inscriptions vivent dans la fiche de l'événement.
 */
function Carte({ ev, flash, onInviter }: { ev: Evenement; flash: boolean; onInviter: () => void }) {
  const { c } = useTheme();
  const bleu = useRubrique('bleu');
  const { etat, libelleErreur, inscrire, fete } = useInscription(ev);
  const inscrit = etat === 'inscrit' || etat === 'vient';
  // L'horloge ne tourne que pour l'offre flash, ou pour faire passer d'elle-même
  // à « En cours » une soirée à laquelle on est inscrit.
  const debut = ts(ev.date_heure);
  const maintenant = useMaintenant(flash || (inscrit && !ev.en_cours), flash ? undefined : debut * 1000);
  const fin = ts(ev.fin_effective ?? ev.date_fin ?? null);
  const enCours = ev.en_cours || (maintenant >= debut * 1000 && (!fin || maintenant <= fin * 1000));
  const ouvrir = () => router.push({ pathname: '/evenement/[id]', params: { id: String(ev.id) } });

  const teinte = couleursCategorie[ev.type] ?? bleu.accent;
  const couverture = ev.etablissement.couverture ?? null;
  const [photoKo, setPhotoKo] = useState(false);
  // L'heure de début seule : la plage complète est dans la fiche, et le nom
  // du lieu passe avant.
  const plage = heure(ev.date_heure);

  const { quota, inscrits, restantes, complet } = ev.places;
  const pct = quota > 0 ? Math.round((inscrits / quota) * 100) : 0;
  const amis = ev.amis.nb > 0 && ev.amis.prenoms.length > 0;
  const rare = !amis && quota > 0 && !complet && pct >= SEUIL_RARETE && restantes !== null && restantes > 0;

  return (
    <CarteRefonte style={{ padding: 0, gap: 0, borderRadius: rayon.carte, overflow: 'hidden' }}>
      {/* La photo du lieu, ou l'aplat de la catégorie. */}
      <Pressable onPress={ouvrir} accessibilityRole="link" accessibilityLabel={ev.titre} accessibilityHint="Ouvre la fiche de l'événement">
        <View style={{ height: HAUTEUR_MEDIA, backgroundColor: teinte, overflow: 'hidden' }}>
          {couverture && !photoKo ? (
            <Image source={{ uri: couverture }} onError={() => setPhotoKo(true)} resizeMode="cover" style={{ position: 'absolute', top: 0, left: 0, right: 0, bottom: 0 }} />
          ) : (
            <View style={{ position: 'absolute', right: -22, bottom: -34, opacity: 0.22 }}>
              <Icone nom={ICONES_TYPE[ev.type] ?? 'calendrier'} taille={150} couleur="#FFFFFF" trait={1.5} />
            </View>
          )}
          <View style={{ position: 'absolute', top: 12, left: 12, right: 12, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', gap: 8 }}>
            {flash && ev.flash_expiry ? (
              <Pastille libelle={libelleFlash(ts(ev.flash_expiry), maintenant)} icone="eclair" fond="#FFE58A" encre="#633806" style={{ borderRadius: 999, paddingHorizontal: 10, paddingVertical: 5 }} />
            ) : enCours ? <PastilleEnCours petite /> : <PastilleAVenir petite />}
            {ev.style_musique ? (
              <Pastille icone="musique" libelle={libelleStyle(ev.style_musique)} fond="rgba(17,17,22,0.45)" encre="#FFFFFF" filet={fixe.filetMedia} style={{ borderRadius: 999, paddingHorizontal: 10, paddingVertical: 4, flexShrink: 1 }} />
            ) : null}
          </View>
        </View>
      </Pressable>

      {/* La date en tuile, à cheval sur la photo. */}
      <View
        accessible accessibilityLabel={dateFr(ev.date_heure, 'l j F')}
        style={{
          position: 'absolute', left: espace.carte, top: HAUTEUR_MEDIA - TUILE_DATE.hauteur / 2,
          width: TUILE_DATE.largeur, height: TUILE_DATE.hauteur, borderRadius: 14,
          backgroundColor: c.blanc, borderWidth: 1, borderColor: c.line2,
          alignItems: 'center', justifyContent: 'center',
        }}
      >
        <Text style={{ fontFamily: sans(700), fontSize: fs[1], color: bleu.accent, textTransform: 'uppercase', lineHeight: 13 }}>{dateFr(ev.date_heure, 'D')}</Text>
        <Text style={{ fontFamily: sans(700), fontSize: 22, color: c.noir, lineHeight: 25 }}>{dateFr(ev.date_heure, 'j')}</Text>
        <Text style={{ fontFamily: sans(500), fontSize: fs[1], color: c.gris, lineHeight: 13 }}>{dateFr(ev.date_heure, 'M')}</Text>
      </View>

      <View style={{ paddingHorizontal: espace.carte, paddingBottom: espace.carte, paddingTop: TUILE_DATE.hauteur / 2 + 10, gap: 12 }}>
        <View style={{ gap: 6 }}>
          <Pressable onPress={ouvrir}>
            <Text numberOfLines={2} style={{ fontFamily: sans(700), fontSize: TITRE_GRAND, lineHeight: TITRE_GRAND * 1.2, letterSpacing: -0.02 * TITRE_GRAND, color: c.noir }}>{ev.titre}</Text>
          </Pressable>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
            <Pressable onPress={ouvrir} accessibilityLabel={`${ev.etablissement.nom}, ${plage}`} style={{ flex: 1, minWidth: 0, flexDirection: 'row', alignItems: 'center', gap: 5 }}>
              <Icone nom="epingle" taille={14} couleur={c.gris} />
              <T taille={fs[3]} couleur={c.gris} numberOfLines={1} style={{ flexShrink: 1 }}>{ev.etablissement.nom}</T>
              <View style={{ width: 4 }} />
              <Icone nom="horloge" taille={14} couleur={c.gris} />
              <T taille={fs[3]} couleur={c.gris} numberOfLines={1} style={{ flexShrink: 0 }}>{plage}</T>
            </Pressable>
            <BoutonSuivreLieu etabId={ev.etablissement.id} suivi={ev.etablissement.suivi} />
          </View>
        </View>

        {amis ? <QuiYVa prenoms={ev.amis.prenoms} nb={ev.amis.nb} fond={c.blanc} /> : null}

        {rare ? (
          <View style={{ gap: 6 }} accessible accessibilityLabel={`Plus que ${restantes} places`}>
            <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
              <T taille={fs[3]} poids={700} couleur={ORANGE_RARETE}>{`Plus que ${restantes} place${(restantes ?? 0) > 1 ? 's' : ''}`}</T>
              <T taille={fs[3]} couleur={c.gris} style={{ fontVariant: ['tabular-nums'] }}>{`${inscrits}/${quota}`}</T>
            </View>
            <Jauge pourcentage={pct} piste={c.surface2} remplissage={ORANGE_RARETE} />
          </View>
        ) : null}

        {/* Les actions, à la taille de leur libellé, rangées à droite. */}
        <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-end', gap: 8 }}>
          <BoutonContour iconeSeule libelle="Inviter" icone="ajout-personne" onPress={onInviter} />
          <BoutonAction
            fin
            libelle={inscrit ? 'Inscrit' : complet ? 'Complet' : libelleErreur ?? "S'inscrire"}
            accent={bleu.accent}
            fait={inscrit}
            desactive={inscrit || complet}
            chargement={etat === 'attente'}
            onPress={inscrire}
            confettis={fete}
          />
        </View>
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

/** « BDE Info », « BDE Info et Le Comptoir » : les co-organisateurs d'un événement. */
export function nomsCollaborateurs(liste: { nom: string }[]) {
  const noms = liste.map((c) => c.nom);
  return noms.length > 1 ? `${noms.slice(0, -1).join(', ')} et ${noms[noms.length - 1]}` : noms[0] ?? '';
}

/**
 * @param accent   Conservé pour les appelants : toutes les cartes ont la même allure.
 * @param premiere Conservé pour les appelants ; le compte à rebours est dans la fiche.
 */
export function CarteEvenement({ ev, premiere: _premiere, accent: _accent = false, onInviter }: { ev: Evenement; premiere: boolean; accent?: boolean; onInviter: () => void }) {
  const [instant] = useState(() => Date.now() / 1000);
  const flash = ev.is_flash && ts(ev.flash_expiry) > instant;
  return <Carte ev={ev} flash={flash} onInviter={onInviter} />;
}

export { BoutonSuivreLieu };
