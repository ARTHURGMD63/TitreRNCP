/**
 * Pass — wallet.php.
 *
 * Refonte 2026-11 : « Ton pass, [en poche.] », puis dans l'ordre :
 *  - le carrousel des pass : carte bleue, « Pass étudiant · 1/1 », le nom,
 *    « Actif », la croix d'annulation ; au centre le QR, toujours sur fond
 *    blanc, révélé d'un appui comme avant (une ligne de scan le balaie alors
 *    une fois) ; en bas l'événement, la date, le compte à rebours et la
 *    réduction ;
 *  - « N pass actif », puis la liste des passes et leur économie ;
 *  - depuis le 2026-11-16, tout ce à quoi l'étudiant est inscrit : les
 *    soirées gardent la carte pleine couleur et son grand QR ; Culture, clubs
 *    de sport et parties ont un billet clair à part (CarteActivite) : date,
 *    lieu, compte à rebours, « Voir la fiche », et un QR derrière un bouton
 *    pour un club (rien à scanner pour Culture et les parties) ; trois lignes,
 *    chacune son carrousel : Événements, Sport (clubs et parties), Culture ;
 *  - les pass actifs regroupés par jour (2026-11-19), le plus proche ouvert ;
 *  - les squads prévus, puis le lien vers l'historique (écran à part, rangé
 *    par mois, aussi ouvert depuis Moi) ;
 *  - « Tes économies » en dernier : le mois et l'année, mêmes calculs.
 *
 * « Luminosité au max » et « Ajouter à Apple Wallet » ne sont pas affichés :
 * l'application ne sait ni régler la luminosité ni générer de pass Wallet.
 */

import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Animated, Easing, Pressable, ScrollView, Text, View, useWindowDimensions } from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import QRCode from 'react-native-qrcode-svg';

import { actions, api, ErreurApi, type GenrePass, type Pass, type ReponseWallet, type SquadAgenda } from '../../api';
import { SPORTS } from '../../catalogue';
import { Apparition, Compteur, PressableAnime, useMouvementReduit } from '../../composants/Animations';
import { Bouton } from '../../composants/Bouton';
import { CarreIcone, EnTeteApp, Pastille, useRubrique } from '../../composants/Design';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Icone, type NomIcone } from '../../composants/Icone';
import { T, TitreEcran, TitreSection } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { confirmer } from '../../confirmer';
import { compteARebours, court, dateFr, ts } from '../../format';
import { formatEuros, lireCodePass, prixEvenement } from '../../regles';
import { parJour, type Groupe } from '../../regroupement';
import { economiePass } from '../../composants/LignesPass';
import { useJeton, useSession } from '../../session';
import { fixe, fs, gutter, halo, hauteurBouton, lh, rayon, sans, type Rubrique } from '../../theme';
import { useTheme } from '../../useTheme';

/**
 * Le texte à encoder dans le QR d'un pass, contrôlé avant d'être dessiné :
 * « outsee:<64 hexadécimaux> », ou null si le serveur a envoyé autre chose.
 * Un QR que le scanner refuserait n'est jamais affiché : la carte dit
 * plutôt de se faire valider à la main.
 */
function qrDuPass(p: Pick<Pass, 'code_qr'>): string | null {
  const code = lireCodePass(p.code_qr);
  return code ? `outsee:${code}` : null;
}

/** Le surtitre, l'icône et la rubrique de la carte, selon ce que le pass représente. */
const GENRES: Record<GenrePass, { surtitre: string; icone: NomIcone; rubrique: Rubrique; couleur: string }> = {
  soiree: { surtitre: 'Pass étudiant', icone: 'ticket', rubrique: 'bleu', couleur: '#1D55F0' },
  sport: { surtitre: 'Pass sport', icone: 'trophee', rubrique: 'sport', couleur: '#E02D24' },
  culture: { surtitre: 'Pass culture', icone: 'musee', rubrique: 'culture', couleur: '#7B4DF2' },
  partie: { surtitre: 'Partie de sport', icone: 'raquette', rubrique: 'sport', couleur: '#E02D24' },
};

const genreDe = (p: Pass): GenrePass => p.genre ?? 'soiree';
const clePass = (p: Pass) => `${genreDe(p)}-${p.id}`;

/** La fiche d'un pass : la soirée, l'événement Culture ou le créneau. */
function ouvrirFiche(p: Pass) {
  if (p.evenement_id === null) return;
  const id = String(p.evenement_id);
  const g = genreDe(p);
  if (g === 'culture') router.push({ pathname: '/culture/[id]', params: { id } });
  else if (g === 'partie') router.push({ pathname: '/creneau/[id]', params: { id } });
  else router.push({ pathname: '/evenement/[id]', params: { id } });
}

const MOIS = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

/**
 * Le format d'un compteur d'économies : celui de toute l'application
 * (formatEuros, « 12,50 € »). Pendant le défilement, des euros entiers ; la
 * valeur exacte, centimes compris, à l'arrivée.
 */
const formatCompteur = (cible: number) => (n: number) => formatEuros(n === cible ? cible : Math.round(n));

/** Un rappel différé, annulé si le composant disparaît avant. */
function useDiffere() {
  const minuteur = useRef<ReturnType<typeof setTimeout> | null>(null);
  useEffect(() => () => { if (minuteur.current) clearTimeout(minuteur.current); }, []);
  return useCallback((f: () => void, ms: number) => {
    if (minuteur.current) clearTimeout(minuteur.current);
    minuteur.current = setTimeout(f, ms);
  }, []);
}

/** L'heure courante, rafraîchie chaque minute : assez pour « Dans 2 h ». */
function useMinute() {
  const [maintenant, setMaintenant] = useState(() => Date.now());
  useEffect(() => {
    const id = setInterval(() => setMaintenant(Date.now()), 60000);
    return () => clearInterval(id);
  }, []);
  return maintenant;
}

/**
 * Le QR révélé : une fine ligne bleue lumineuse le balaie une fois de haut
 * en bas (1,4 s), puis `onFin` fait battre la carte. Décoratif : le QR reste
 * lisible pendant tout ce temps.
 */
function LigneScan({ hauteur, onFin }: { hauteur: number; onFin: () => void }) {
  const reduit = useMouvementReduit();
  const [progres] = useState(() => new Animated.Value(0));
  const [visible, setVisible] = useState(!reduit);
  useEffect(() => {
    if (reduit) return;
    const anim = Animated.timing(progres, { toValue: 1, duration: 1400, easing: Easing.inOut(Easing.quad), useNativeDriver: true });
    anim.start(({ finished }) => { if (finished) { setVisible(false); onFin(); } });
    return () => anim.stop();
  }, [reduit, progres, onFin]);
  if (!visible) return null;
  return (
    <Animated.View
      pointerEvents="none"
      style={{
        position: 'absolute', left: 14, right: 14, top: 0, height: 3, borderRadius: 2,
        backgroundColor: fixe.logo, shadowColor: fixe.logo, shadowOpacity: 0.9, shadowRadius: 8, shadowOffset: { width: 0, height: 0 },
        transform: [{ translateY: progres.interpolate({ inputRange: [0, 1], outputRange: [8, hauteur - 8] }) }],
      }}
    />
  );
}

/**
 * La désinscription depuis le Pass. Culture se quitte côté Culture, le reste
 * annule le pass. Une partie se quitte depuis sa fiche : l'organisateur qui
 * part libère le créneau pour tous, ce n'est pas une croix qu'on touche par
 * mégarde.
 */
function useAnnulation(p: Pass, onAnnule: () => void) {
  const genre = genreDe(p);
  const jeton = useJeton();
  const toast = useToast();
  const [attente, setAttente] = useState(false);
  const differer = useDiffere();
  const annulable = genre !== 'partie';

  async function annuler() {
    const culture = genre === 'culture';
    if (!(await confirmer(culture ? 'Te désinscrire de cet événement ?' : 'Veux-tu vraiment annuler ce pass ?'))) return;
    setAttente(true);
    try {
      if (culture) {
        if (p.evenement_id !== null) await api.cultureInscription(jeton, p.evenement_id, true);
        toast('Inscription annulée', 'success');
      } else {
        await actions.annulerPass(jeton, p.id);
        toast('Pass annulé !', 'success');
      }
      differer(onAnnule, 1000);
    } catch (e) {
      setAttente(false);
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    }
  }

  return { annuler, attente, annulable };
}

/**
 * Le pass d'une activité — Culture, club de sport, partie : un billet clair,
 * pas la carte pleine couleur d'une soirée. Bandeau teinté de la rubrique,
 * pastille de date, lieu et compte à rebours ; la carte ne présente pas de
 * grand QR : un club garde son QR derrière « Afficher le QR », Culture et
 * parties renvoient à la fiche.
 */
function CarteActivite({ p, index, total, largeur, onAnnule }: {
  p: Pass; index: number; total: number; largeur: number; onAnnule: () => void;
}) {
  const { c } = useTheme();
  const genre = genreDe(p);
  const infos = GENRES[genre];
  const teinte = useRubrique(infos.rubrique);
  const maintenant = useMinute();
  const [qr, setQr] = useState(false);
  const codeQr = qrDuPass(p);
  const { annuler, attente, annulable } = useAnnulation(p, onAnnule);

  return (
    <View style={{ width: largeur, backgroundColor: c.blanc, borderRadius: rayon.carte, overflow: 'hidden', marginBottom: 16 }}>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10, padding: 14, backgroundColor: teinte.doux }}>
        <CarreIcone icone={infos.icone} fond={teinte.accent} encre="#FFFFFF" taille={40} />
        <View style={{ flex: 1 }}>
          <Text style={{ fontFamily: sans(700), fontSize: 12, letterSpacing: 0.06 * 12, textTransform: 'uppercase', color: teinte.texteDoux }}>
            {infos.surtitre} · {index + 1}/{total}
          </Text>
          <T taille={fs[4]} couleur={teinte.texteDoux} numberOfLines={1}>{pastillePrix(p, p.reduction ?? 0)}</T>
        </View>
        {annulable ? (
          <PressableAnime
            onPress={annuler}
            disabled={attente}
            accessibilityRole="button"
            accessibilityLabel={genre === 'culture' ? 'Me désinscrire' : 'Annuler ce pass'}
            accessibilityState={{ disabled: attente, busy: attente }}
            style={{ width: hauteurBouton.normal, height: hauteurBouton.normal, borderRadius: rayon.bouton, backgroundColor: c.blanc, alignItems: 'center', justifyContent: 'center', opacity: attente ? 0.5 : 1 }}
          >
            <Icone nom="croix" taille={18} couleur={c.noir} />
          </PressableAnime>
        ) : null}
      </View>

      <View style={{ padding: 16, gap: 14 }}>
        <View style={{ flexDirection: 'row', gap: 14, alignItems: 'center' }}>
          <View style={{ width: 64, borderRadius: rayon.tuile, backgroundColor: teinte.accent, alignItems: 'center', paddingVertical: 10 }}>
            <Text style={{ fontFamily: sans(700), fontSize: 26, lineHeight: 28, letterSpacing: -0.03 * 26, color: '#FFFFFF', fontVariant: ['tabular-nums'] }}>
              {dateFr(p.date_heure, 'j')}
            </Text>
            <Text style={{ fontFamily: sans(600), fontSize: 12, letterSpacing: 0.06 * 12, textTransform: 'uppercase', color: teinte.surAccent2 }}>
              {dateFr(p.date_heure, 'M')}
            </Text>
          </View>
          <View style={{ flex: 1, gap: 4 }}>
            <T taille={18} poids={700} interligne={lh.tight} style={{ letterSpacing: -0.03 * 18 }} numberOfLines={2}>{p.titre}</T>
            <T taille={fs[4]} couleur={c.gris} numberOfLines={1}>{dateFr(p.date_heure, 'l')} · {dateFr(p.date_heure, 'H\\hi')}</T>
          </View>
        </View>

        <View style={{ flexDirection: 'row', alignItems: 'center', flexWrap: 'wrap', gap: 8 }}>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, flex: 1, minWidth: 140 }}>
            <Icone nom="epingle" taille={16} couleur={c.gris} />
            <T taille={fs[4]} couleur={c.gris} numberOfLines={1} style={{ flexShrink: 1 }}>{p.etablissement}{p.lieu ? ` · ${p.lieu}` : ''}</T>
          </View>
          <Pastille libelle={compteARebours(ts(p.date_heure), maintenant)} icone="horloge" fond={teinte.doux} encre={teinte.texteDoux} />
        </View>

        {genre === 'partie' ? (
          <T taille={fs[4]} couleur={c.gris}>Terrain réservé. Réglé sur place au club.</T>
        ) : null}

        {qr && codeQr ? (
          <View style={{ backgroundColor: '#FFFFFF', borderRadius: 20, paddingVertical: 18, alignItems: 'center' }}>
            <QRCode value={codeQr} size={176} color={fixe.qrSombre} backgroundColor={fixe.qrClair} ecl="H" />
          </View>
        ) : null}

        <View style={{ flexDirection: 'row', gap: 10 }}>
          <PressableAnime
            onPress={() => ouvrirFiche(p)}
            accessibilityRole="button"
            accessibilityLabel="Voir la fiche"
            style={{ flex: 1, minHeight: hauteurBouton.normal, borderRadius: rayon.bouton, backgroundColor: teinte.accent, alignItems: 'center', justifyContent: 'center' }}
          >
            <T taille={fs[4]} poids={700} couleur="#FFFFFF">Voir la fiche</T>
          </PressableAnime>
          {codeQr ? (
            <PressableAnime
              onPress={() => setQr((v) => !v)}
              accessibilityRole="button"
              accessibilityLabel={qr ? 'Masquer le QR code' : 'Afficher le QR code'}
              style={{ flex: 1, minHeight: hauteurBouton.normal, borderRadius: rayon.bouton, backgroundColor: teinte.doux, flexDirection: 'row', gap: 8, alignItems: 'center', justifyContent: 'center' }}
            >
              <Icone nom="qr" taille={18} couleur={teinte.texteDoux} />
              <T taille={fs[4]} poids={700} couleur={teinte.texteDoux}>{qr ? 'Masquer' : 'QR'}</T>
            </PressableAnime>
          ) : null}
        </View>
      </View>
    </View>
  );
}

function CartePass({ p, index, total, largeur, nomTitulaire, onAnnule }: {
  p: Pass; index: number; total: number; largeur: number; nomTitulaire: string; onAnnule: () => void;
}) {
  const genre = genreDe(p);
  const infos = GENRES[genre];
  const bleu = useRubrique(infos.rubrique);
  const reduit = useMouvementReduit();
  const maintenant = useMinute();
  const [revele, setRevele] = useState(false);
  const [echelle] = useState(() => new Animated.Value(1));
  const { annuler, attente, annulable } = useAnnulation(p, onAnnule);

  const pulser = useCallback(() => {
    if (reduit) return;
    Animated.sequence([
      Animated.timing(echelle, { toValue: 1.025, duration: 160, easing: Easing.out(Easing.quad), useNativeDriver: true }),
      Animated.timing(echelle, { toValue: 1, duration: 240, easing: Easing.inOut(Easing.quad), useNativeDriver: true }),
    ]).start();
  }, [reduit, echelle]);

  const supprime = p.evenement_supprime;
  const encre = '#FFFFFF';
  const secondaire = supprime ? 'rgba(255,255,255,0.85)' : bleu.surAccent2;
  const voile = 'rgba(255,255,255,0.18)';
  const reduction = p.reduction ?? 0;
  const HAUTEUR_QR = 240;
  const codeQr = qrDuPass(p);

  return (
    <Animated.View
      style={[
        { width: largeur, backgroundColor: supprime ? '#767676' : bleu.accent, borderRadius: rayon.carte, padding: 18, gap: 14, marginBottom: 16, opacity: supprime ? 0.6 : 1, transform: [{ scale: echelle }] },
        supprime ? null : halo(bleu.accent),
      ]}
    >
      <View style={{ flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 10 }}>
        <View style={{ flex: 1, gap: 4 }}>
          <Text style={{ fontFamily: sans(600), fontSize: 12, letterSpacing: 0.06 * 12, textTransform: 'uppercase', color: secondaire }}>
            {infos.surtitre} · {index + 1}/{total}
          </Text>
          <Text numberOfLines={1} style={{ fontFamily: sans(700), fontSize: 18, letterSpacing: -0.03 * 18, color: encre }}>
            {supprime ? 'Pass invalide' : nomTitulaire}
          </Text>
        </View>
        {supprime ? null : <Pastille libelle="Actif" point={fixe.enCours} fond={voile} encre={encre} style={{ marginTop: 6 }} />}
        {annulable ? (
          <PressableAnime
            onPress={annuler}
            disabled={attente}
            accessibilityRole="button"
            accessibilityLabel={supprime ? 'Supprimer ce pass' : genre === 'culture' ? 'Me désinscrire' : 'Annuler ce pass'}
            accessibilityState={{ disabled: attente, busy: attente }}
            style={{ width: hauteurBouton.normal, height: hauteurBouton.normal, borderRadius: rayon.bouton, backgroundColor: voile, alignItems: 'center', justifyContent: 'center', opacity: attente ? 0.5 : 1 }}
          >
            <Icone nom="croix" taille={20} couleur={encre} />
          </PressableAnime>
        ) : null}
      </View>

      {supprime ? (
        <View style={{ backgroundColor: 'rgba(17,17,22,0.12)', borderRadius: 20, paddingVertical: 34, paddingHorizontal: 16, minHeight: 188, alignItems: 'center', justifyContent: 'center', gap: 8 }}>
          <Icone nom="interdit" taille={40} couleur="#666666" />
          <T taille={fs[4]} poids={700} couleur="#555555" style={{ textAlign: 'center' }}>{'Événement\nsupprimé'}</T>
        </View>
      ) : p.code_qr && !codeQr ? (
        // Un code reçu mais illisible : pas de QR que le scanner refuserait.
        <View style={{ backgroundColor: '#FFFFFF', borderRadius: 20, height: HAUTEUR_QR, alignItems: 'center', justifyContent: 'center', gap: 12, paddingHorizontal: 16 }}>
          <Icone nom="info" taille={44} couleur="#111116" trait={1.75} />
          <T taille={fs[5]} poids={700} couleur="#111116" interligne={lh.tight} style={{ textAlign: 'center' }}>{'QR indisponible.\nDonne ton nom à l’entrée.'}</T>
          <T taille={fs[3]} couleur="#55555C" style={{ textAlign: 'center' }}>Le partenaire peut te valider à la main.</T>
        </View>
      ) : !p.code_qr ? (
        // Culture et parties : rien à scanner. La carte garde la même
        // silhouette et ouvre la fiche, d'où l'on gère son inscription.
        <Pressable
          onPress={() => ouvrirFiche(p)}
          accessibilityRole="button"
          accessibilityLabel="Voir la fiche"
          style={{ backgroundColor: '#FFFFFF', borderRadius: 20, height: HAUTEUR_QR, alignItems: 'center', justifyContent: 'center', gap: 12, paddingHorizontal: 16 }}
        >
          <Icone nom={infos.icone} taille={44} couleur="#111116" trait={1.75} />
          <T taille={fs[5]} poids={700} couleur="#111116" interligne={lh.tight} style={{ textAlign: 'center' }}>
            {genre === 'partie' ? 'Terrain réservé.\nRéglé sur place au club.' : 'Tu es inscrit.\nPas de QR à présenter.'}
          </T>
          <T taille={fs[3]} couleur="#55555C">Voir la fiche</T>
        </Pressable>
      ) : (
        // Le QR reste toujours sur fond blanc, dans les deux thèmes : c'est
        // une cible optique.
        <Pressable
          onPress={() => setRevele(true)}
          accessibilityRole="button"
          accessibilityLabel={revele ? 'QR code du pass' : 'Afficher le QR code'}
          style={{ backgroundColor: '#FFFFFF', borderRadius: 20, height: HAUTEUR_QR, alignItems: 'center', justifyContent: 'center', overflow: 'hidden' }}
        >
          {revele && codeQr ? (
            <>
              <QRCode value={codeQr} size={196} color={fixe.qrSombre} backgroundColor={fixe.qrClair} ecl="H" />
              <LigneScan hauteur={HAUTEUR_QR} onFin={pulser} />
            </>
          ) : (
            <View style={{ alignItems: 'center', gap: 12 }}>
              <Icone nom="qr" taille={44} couleur="#111116" trait={1.75} />
              <T taille={fs[5]} poids={700} couleur="#111116" interligne={lh.tight} style={{ textAlign: 'center' }}>{'Appuyer\npour afficher.'}</T>
            </View>
          )}
        </Pressable>
      )}

      <View style={{ gap: 8 }}>
        {supprime ? (
          <>
            <T taille={fs[4]} poids={700} couleur={encre}>Cet événement n&apos;existe plus</T>
            <T taille={fs[4]} couleur={secondaire}>Tu peux supprimer ce pass</T>
          </>
        ) : (
          <>
            <T taille={17} poids={700} couleur={encre} style={{ letterSpacing: -0.02 * 17 }}>{p.etablissement} — {p.titre}</T>
            <View style={{ flexDirection: 'row', alignItems: 'center', flexWrap: 'wrap', gap: 8 }}>
              <T taille={fs[4]} couleur={secondaire} style={{ marginRight: 'auto' }}>{dateFr(p.date_heure, 'D j M · H\\hi')}</T>
              <Pastille libelle={compteARebours(ts(p.date_heure), maintenant)} icone="horloge" fond={voile} encre={encre} />
              <Pastille libelle={pastillePrix(p, reduction)} fond="#FFFFFF" encre="#111116" />
            </View>
          </>
        )}
      </View>
    </Animated.View>
  );
}

function CarteSquadAgenda({ sq, largeur, onQuitte }: { sq: SquadAgenda; largeur: number; onQuitte: () => void }) {
  const { c } = useTheme();
  const sport = useRubrique('sport');
  const jeton = useJeton();
  const toast = useToast();
  const differer = useDiffere();

  async function quitter() {
    if (!(await confirmer('Veux-tu vraiment quitter ce groupe de sport ?'))) return;
    try {
      await actions.quitterSquad(jeton, sq.id);
      toast('Groupe quitté !', 'success');
      differer(onQuitte, 1000);
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    }
  }

  return (
    <View style={{ width: largeur, backgroundColor: c.blanc, borderRadius: rayon.carte, padding: 18, gap: 8 }}>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', gap: 8 }}>
        <View style={{ flex: 1, gap: 4 }}>
          <Text style={{ fontFamily: sans(600), fontSize: 12, letterSpacing: 0.06 * 12, textTransform: 'uppercase', color: sport.texteDoux }}>Session {sq.type}</Text>
          <T taille={17} poids={700} style={{ letterSpacing: -0.02 * 17 }}>{sq.titre}</T>
        </View>
        <PressableAnime onPress={quitter} accessibilityRole="button" accessibilityLabel="Quitter ce groupe"
          style={{ width: 44, height: 44, borderRadius: rayon.bouton, backgroundColor: c.surface2, alignItems: 'center', justifyContent: 'center' }}>
          <Icone nom="croix" taille={18} couleur={c.noir} />
        </PressableAnime>
      </View>
      <T taille={fs[4]} poids={600}>{dateFr(sq.date_heure, 'D j M · H\\hi')}</T>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
        <Icone nom="epingle" taille={16} couleur={c.gris} />
        <T taille={fs[4]} couleur={c.gris}>{sq.lieu}</T>
      </View>
      <T taille={fs[4]} couleur={c.gris}>Organisé par {sq.createur_prenom} · Niveau : {sq.niveau}</T>
    </View>
  );
}

/** « 1 pass actif » et la pastille « Agenda ». */
function EnTeteListe({ titre, droite, style }: { titre: string; droite: string; style?: object }) {
  const { c } = useTheme();
  return (
    <View style={[{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 }, style]}>
      <TitreSection>{titre}</TitreSection>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, minHeight: 40, paddingHorizontal: 14, borderRadius: rayon.bouton, backgroundColor: c.blanc }}>
        <T taille={fs[4]} poids={500}>{droite}</T>
        <Icone nom="fleche-b" taille={16} couleur={c.noir} />
      </View>
    </View>
  );
}

/** La pastille blanche du bas de la carte. */
function pastillePrix(p: Pass, reduction: number) {
  const g = genreDe(p);
  if (g === 'partie') return (p.sport && SPORTS[p.sport]) || 'Sport';
  if (g === 'culture') return (p.prix_normal ?? 0) > 0 ? formatEuros(p.prix_normal!) : 'Entrée libre';
  // Même règle que la fiche de l'événement : un prix saisi s'affiche, réduit
  // le cas échéant ; « gratuite » seulement quand il n'y en a vraiment pas.
  const prix = prixEvenement({ prix_normal: p.prix_normal, reduction, is_gratuit: p.is_gratuit ?? false });
  if (prix) return reduction > 0 ? `${prix.final} · -${reduction}%` : prix.final;
  return reduction > 0 ? `-${reduction}%` : 'Entrée gratuite';
}

/**
 * Les pass d'un même jour, dans un seul bloc : « Demain · 2 pass ». Le jour
 * le plus proche est ouvert ; les autres se déplient d'un appui, pour que
 * plusieurs pass ne fassent pas un écran interminable.
 */
function GroupeJour({ groupe, ouvert, onBascule, couleurType }: {
  groupe: Groupe<Pass>; ouvert: boolean; onBascule: () => void; couleurType: (p: Pass) => string;
}) {
  const { c } = useTheme();
  const n = groupe.elements.length;
  return (
    <View style={{ backgroundColor: c.blanc, borderRadius: rayon.tuile, overflow: 'hidden' }}>
      <Pressable
        onPress={onBascule}
        accessibilityRole="button"
        accessibilityState={{ expanded: ouvert }}
        accessibilityLabel={`${groupe.libelle}, ${n} pass`}
        style={{ minHeight: 48, paddingHorizontal: 14, paddingVertical: 12, flexDirection: 'row', alignItems: 'center', gap: 10 }}
      >
        <Icone nom="calendrier" taille={18} couleur={c.noir} />
        <T taille={fs[4]} poids={700} style={{ flex: 1 }}>{groupe.libelle}</T>
        <T taille={fs[3]} couleur={c.gris}>{n} pass</T>
        <View style={{ transform: [{ rotate: ouvert ? '180deg' : '0deg' }] }}>
          <Icone nom="chevron-b" taille={18} couleur={c.gris} />
        </View>
      </Pressable>
      {ouvert ? groupe.elements.map((p) => (
        <PressableAnime key={clePass(p)} onPress={() => ouvrirFiche(p)} accessibilityRole="button" accessibilityLabel={`${p.titre}, voir la fiche`}
          style={{ paddingHorizontal: 14, paddingVertical: 12, flexDirection: 'row', alignItems: 'center', gap: 12, borderTopWidth: 1, borderTopColor: c.grisClair }}>
          <CarreIcone icone={GENRES[genreDe(p)].icone} fond={couleurType(p)} encre="#FFFFFF" taille={36} />
          <View style={{ flex: 1 }}>
            <T taille={fs[4]} poids={700} numberOfLines={1}>{p.etablissement}</T>
            <T taille={fs[3]} couleur={c.gris} numberOfLines={1} style={{ marginTop: 2 }}>{dateFr(p.date_heure, 'H\\hi')} · {p.titre}</T>
          </View>
          <T taille={fs[4]} poids={700} couleur={c.succes}>{economiePass(p)}</T>
        </PressableAnime>
      )) : null}
    </View>
  );
}

export default function Wallet() {
  const { c } = useTheme();
  const jeton = useJeton();
  const { profil } = useSession();
  const largeurEcran = Math.min(useWindowDimensions().width, 440);
  // Une carte de pass occupe toute la largeur utile ; la suivante, s'il y en
  // a, se devine au bord en faisant défiler.
  const largeurCarte = largeurEcran - gutter * 2;

  const [donnees, setDonnees] = useState<ReponseWallet | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);
  // Les jours dépliés ou repliés à la main ; sinon, seul le premier est ouvert.
  const [ouverts, setOuverts] = useState<Record<string, boolean>>({});
  const maintenant = useMinute();

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      setDonnees(await api.wallet(jeton));
    } catch (e) {
      setErreur(e instanceof Error ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
    }
  }, [jeton]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  const passes = donnees?.pass_actifs ?? [];
  const jours = parJour(passes, new Date(maintenant));
  const squads = donnees?.squads ?? [];
  const toutesSections: { cle: 'soiree' | 'sport' | 'culture'; titre: string; icone: NomIcone; couleur: string; passes: Pass[] }[] = [
    { cle: 'soiree', titre: 'Événements', icone: 'ticket', couleur: GENRES.soiree.couleur, passes: passes.filter((p) => genreDe(p) === 'soiree') },
    { cle: 'sport', titre: 'Sport', icone: 'trophee', couleur: GENRES.sport.couleur, passes: passes.filter((p) => genreDe(p) === 'sport' || genreDe(p) === 'partie') },
    { cle: 'culture', titre: 'Culture', icone: 'musee', couleur: GENRES.culture.couleur, passes: passes.filter((p) => genreDe(p) === 'culture') },
  ];
  const sections = toutesSections.filter((s) => s.passes.length > 0);
  const nomTitulaire = profil ? court(profil.prenom, profil.nom) : '';
  const couleurType = (p: Pass) => {
    const g = genreDe(p);
    if (g !== 'soiree') return GENRES[g].couleur;
    const t = p.etab_type;
    return t === 'resto' ? '#F0508A' : t === 'bar' ? '#FF8A3D' : t === 'boite' ? '#8B5CF6' : t === 'afterwork' ? '#FFB020' : '#1D55F0';
  };

  return (
    <Ecran rafraichit={rafraichit} onRafraichir={() => { setRafraichit(true); void charger(); }}>
      <EnTete style={{ paddingBottom: 4 }}>
        <Apparition index={0}>
          <EnTeteApp onNotifications={() => router.navigate('/notifications')} />
        </Apparition>
        <Apparition index={1}>
          <TitreEcran avant="Ton pass," surligne="en poche." style={{ marginBottom: 16 }} />
        </Apparition>
      </EnTete>

      <Contenu>
        {erreur ? <Erreur message={erreur} onReessayer={() => charger()} /> : donnees === null ? <Chargement /> : (
          <>
            <Apparition index={2}>
              {passes.length ? (
                // Trois lignes, chacune son carrousel : les événements, le
                // sport (clubs et parties) et la culture. Une ligne sans pass
                // n'est pas affichée.
                sections.map((s) => (
                  <View key={s.cle} style={{ marginBottom: 8 }}>
                    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 10 }}>
                      <CarreIcone icone={s.icone} fond={s.couleur} encre="#FFFFFF" taille={28} />
                      <TitreSection>{s.titre}</TitreSection>
                      <T taille={fs[4]} couleur={c.gris}>{s.passes.length}</T>
                    </View>
                    <ScrollView
                      horizontal
                      showsHorizontalScrollIndicator={false}
                      snapToInterval={largeurCarte + 12}
                      decelerationRate="fast"
                      style={{ flexGrow: 0, marginHorizontal: -gutter, overflow: 'visible' }}
                      contentContainerStyle={{ gap: 12, paddingHorizontal: gutter, paddingBottom: 8 }}
                    >
                      {s.passes.map((p, i) => s.cle === 'soiree' ? (
                        <CartePass key={clePass(p)} p={p} index={i} total={s.passes.length} largeur={largeurCarte} nomTitulaire={nomTitulaire} onAnnule={() => charger()} />
                      ) : (
                        <CarteActivite key={clePass(p)} p={p} index={i} total={s.passes.length} largeur={largeurCarte} onAnnule={() => charger()} />
                      ))}
                    </ScrollView>
                  </View>
                ))
              ) : (
                <View style={{ backgroundColor: c.blanc, borderRadius: rayon.carte, padding: 32, alignItems: 'center', marginBottom: 16 }}>
                  <View style={{ marginBottom: 16 }}><Icone nom="ticket" taille={48} couleur={c.gris} trait={1.5} /></View>
                  <T taille={19} poids={700} interligne={lh.tight} style={{ marginBottom: 6, textAlign: 'center', letterSpacing: -0.03 * 19 }}>Aucun pass actif</T>
                  <T taille={fs[4]} couleur={c.gris} style={{ marginBottom: 18, textAlign: 'center' }}>Inscris-toi à un événement pour obtenir ton pass.</T>
                  <Bouton libelle="Explorer les événements" onPress={() => router.navigate('/')} style={{ alignSelf: 'center' }} />
                </View>
              )}
            </Apparition>

            {/* TODO design : « Ajouter à Apple Wallet » (bouton noir pleine
                largeur) attend une génération de pass Wallet, qui n'existe
                pas. « Luminosité au max » sous le QR attend un réglage de la
                luminosité (expo-brightness, non installé). */}

            {passes.length ? (
              <Apparition index={3} style={{ marginTop: 8 }}>
                <EnTeteListe titre={`${passes.length} pass actif${passes.length > 1 ? 's' : ''}`} droite="Agenda" />
                <View style={{ gap: 10, marginBottom: 16 }}>
                  {jours.map((g, i) => (
                    <GroupeJour key={g.cle} groupe={g} couleurType={couleurType}
                      ouvert={ouverts[g.cle] ?? i === 0}
                      onBascule={() => setOuverts((o) => ({ ...o, [g.cle]: !(o[g.cle] ?? i === 0) }))} />
                  ))}
                </View>
              </Apparition>
            ) : null}

            {squads.length ? (
              <Apparition index={4}>
                <EnTeteListe titre={`${squads.length} squad${squads.length > 1 ? 's' : ''} prévu${squads.length > 1 ? 's' : ''}`} droite="Sport" />
                <ScrollView horizontal showsHorizontalScrollIndicator={false} snapToInterval={largeurCarte * 0.88 + 12} decelerationRate="fast"
                  style={{ flexGrow: 0, marginHorizontal: -gutter, marginBottom: 16 }} contentContainerStyle={{ gap: 12, paddingHorizontal: gutter }}>
                  {squads.map((sq) => <CarteSquadAgenda key={sq.id} sq={sq} largeur={largeurCarte * 0.88} onQuitte={() => charger()} />)}
                </ScrollView>
              </Apparition>
            ) : null}

            {donnees.historique.length ? (
              // L'historique a son écran, rangé par mois (aussi dans Moi) :
              // en entier ici, il allongeait l'onglet à chaque sortie.
              <Apparition index={5}>
                <PressableAnime onPress={() => router.push('/historique' as never)} accessibilityRole="button" testID="ouvrir-historique"
                  style={{ minHeight: 56, paddingHorizontal: 14, paddingVertical: 12, borderRadius: rayon.tuile, backgroundColor: c.blanc, flexDirection: 'row', alignItems: 'center', gap: 12, marginBottom: 16 }}>
                  <CarreIcone icone="horloge" fond={c.surface2} encre={c.noir} taille={36} />
                  <View style={{ flex: 1 }}>
                    <T taille={fs[4]} poids={700}>Historique de mes sorties</T>
                    <T taille={fs[3]} couleur={c.gris} style={{ marginTop: 2 }}>Rangé par mois, avec les avis à laisser</T>
                  </View>
                  <Icone nom="chevron-d" taille={18} couleur={c.gris} />
                </PressableAnime>
              </Apparition>
            ) : null}

            <Apparition index={6}>
              <TitreSection style={{ marginBottom: 12 }}>Tes économies</TitreSection>
              <View style={{ flexDirection: 'row', gap: 10 }}>
                <View style={{ flex: 1, borderRadius: rayon.tuile, padding: 16, backgroundColor: c.blanc, gap: 6 }}>
                  <T taille={fs[4]} couleur={c.gris}>{MOIS[new Date().getMonth()]}</T>
                  <Compteur valeur={donnees.economies.mois} format={formatCompteur(donnees.economies.mois)}
                    style={{ fontFamily: sans(700), fontSize: 20, letterSpacing: -0.03 * 20, color: c.succes, fontVariant: ['tabular-nums'] }} />
                  <T taille={fs[3]} couleur={c.gris}>économisé ce mois</T>
                </View>
                <View style={{ flex: 1, borderRadius: rayon.tuile, padding: 16, backgroundColor: c.blanc, gap: 6 }}>
                  <T taille={fs[4]} couleur={c.gris}>Total année</T>
                  <Compteur valeur={donnees.economies.annee} delai={120} format={formatCompteur(donnees.economies.annee)}
                    style={{ fontFamily: sans(700), fontSize: 20, letterSpacing: -0.03 * 20, color: c.noir, fontVariant: ['tabular-nums'] }} />
                  <T taille={fs[3]} couleur={c.gris}>depuis janvier</T>
                </View>
              </View>
            </Apparition>
          </>
        )}
      </Contenu>
    </Ecran>
  );
}
