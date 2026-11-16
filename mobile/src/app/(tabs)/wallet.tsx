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
 *  - les squads prévus et l'historique, avec « Laisser un avis » après un
 *    passage validé ;
 *  - « Tes économies » en dernier : le mois et l'année, mêmes calculs.
 *
 * « Luminosité au max » et « Ajouter à Apple Wallet » ne sont pas affichés :
 * l'application ne sait ni régler la luminosité ni générer de pass Wallet.
 */

import React, { useCallback, useEffect, useState } from 'react';
import { Animated, Easing, Pressable, ScrollView, Text, View, useWindowDimensions } from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import QRCode from 'react-native-qrcode-svg';

import { actions, api, ErreurApi, type Pass, type ReponseWallet, type SquadAgenda } from '../../api';
import { Apparition, Compteur, PressableAnime, useMouvementReduit } from '../../composants/Animations';
import { Bouton } from '../../composants/Bouton';
import { CarreIcone, EnTeteApp, Pastille, useRubrique } from '../../composants/Design';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Icone } from '../../composants/Icone';
import { T, TitreEcran, TitreSection } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { confirmer } from '../../confirmer';
import { compteARebours, court, dateFr, nombre, ts } from '../../format';
import { useJeton, useSession } from '../../session';
import { fixe, fs, gutter, halo, lh, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

const MOIS = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

/** Arrondi de number_format() : au plus proche, la moitié vers le haut. */
const euros = (n: number) => nombre(Math.round(n));

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

function CartePass({ p, index, total, largeur, nomTitulaire, onAnnule }: {
  p: Pass; index: number; total: number; largeur: number; nomTitulaire: string; onAnnule: () => void;
}) {
  const bleu = useRubrique('bleu');
  const jeton = useJeton();
  const toast = useToast();
  const reduit = useMouvementReduit();
  const maintenant = useMinute();
  const [revele, setRevele] = useState(false);
  const [attente, setAttente] = useState(false);
  const [echelle] = useState(() => new Animated.Value(1));

  async function annuler() {
    if (!(await confirmer('Veux-tu vraiment annuler ce pass ?'))) return;
    setAttente(true);
    try {
      await actions.annulerPass(jeton, p.id);
      toast('Pass annulé !', 'success');
      setTimeout(onAnnule, 1000);
    } catch (e) {
      setAttente(false);
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    }
  }

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
            Pass étudiant · {index + 1}/{total}
          </Text>
          <Text numberOfLines={1} style={{ fontFamily: sans(700), fontSize: 21, letterSpacing: -0.03 * 21, color: encre }}>
            {supprime ? 'Pass invalide' : nomTitulaire}
          </Text>
        </View>
        {supprime ? null : <Pastille libelle="Actif" point={fixe.enCours} fond={voile} encre={encre} style={{ marginTop: 6 }} />}
        <PressableAnime
          onPress={annuler}
          disabled={attente}
          accessibilityRole="button"
          accessibilityLabel={supprime ? 'Supprimer ce pass' : 'Annuler ce pass'}
          style={{ width: 44, height: 44, borderRadius: rayon.bouton, backgroundColor: voile, alignItems: 'center', justifyContent: 'center' }}
        >
          <Icone nom="croix" taille={20} couleur={encre} />
        </PressableAnime>
      </View>

      {supprime ? (
        <View style={{ backgroundColor: 'rgba(17,16,19,0.12)', borderRadius: 20, paddingVertical: 34, paddingHorizontal: 20, minHeight: 188, alignItems: 'center', justifyContent: 'center', gap: 8 }}>
          <Icone nom="interdit" taille={40} couleur="#666666" />
          <T taille={fs[5]} poids={700} couleur="#555555" style={{ textAlign: 'center' }}>{'Événement\nsupprimé'}</T>
        </View>
      ) : (
        // Le QR reste toujours sur fond blanc, dans les deux thèmes : c'est
        // une cible optique.
        <Pressable
          onPress={() => setRevele(true)}
          accessibilityRole="button"
          accessibilityLabel={revele ? 'QR code du pass' : 'Afficher le QR code'}
          style={{ backgroundColor: '#FFFFFF', borderRadius: 20, height: HAUTEUR_QR, alignItems: 'center', justifyContent: 'center', overflow: 'hidden' }}
        >
          {revele && p.code_qr ? (
            <>
              <QRCode value={p.code_qr} size={196} color={fixe.qrSombre} backgroundColor={fixe.qrClair} ecl="H" />
              <LigneScan hauteur={HAUTEUR_QR} onFin={pulser} />
            </>
          ) : (
            <View style={{ alignItems: 'center', gap: 12 }}>
              <Icone nom="qr" taille={44} couleur="#111116" trait={1.75} />
              <T taille={fs[6]} poids={700} couleur="#111116" interligne={lh.tight} style={{ textAlign: 'center' }}>{'Appuyer\npour afficher.'}</T>
            </View>
          )}
        </Pressable>
      )}

      <View style={{ gap: 8 }}>
        {supprime ? (
          <>
            <T taille={fs[5]} poids={700} couleur={encre}>Cet événement n&apos;existe plus</T>
            <T taille={fs[4]} couleur={secondaire}>Tu peux supprimer ce pass</T>
          </>
        ) : (
          <>
            <T taille={20} poids={700} couleur={encre} style={{ letterSpacing: -0.02 * 20 }}>{p.etablissement} — {p.titre}</T>
            <View style={{ flexDirection: 'row', alignItems: 'center', flexWrap: 'wrap', gap: 8 }}>
              <T taille={fs[5]} couleur={secondaire} style={{ marginRight: 'auto' }}>{dateFr(p.date_heure, 'D j M · H\\hi')}</T>
              <Pastille libelle={compteARebours(ts(p.date_heure), maintenant)} icone="horloge" fond={voile} encre={encre} />
              <Pastille libelle={reduction > 0 ? `-${reduction}%` : 'Entrée gratuite'} fond="#FFFFFF" encre="#111116" />
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

  async function quitter() {
    if (!(await confirmer('Veux-tu vraiment quitter ce groupe de sport ?'))) return;
    try {
      await actions.quitterSquad(jeton, sq.id);
      toast('Groupe quitté !', 'success');
      setTimeout(onQuitte, 1000);
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    }
  }

  return (
    <View style={{ width: largeur, backgroundColor: c.blanc, borderRadius: rayon.carte, padding: 18, gap: 8 }}>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', gap: 8 }}>
        <View style={{ flex: 1, gap: 4 }}>
          <Text style={{ fontFamily: sans(600), fontSize: 12, letterSpacing: 0.06 * 12, textTransform: 'uppercase', color: sport.texteDoux }}>Session {sq.type}</Text>
          <T taille={20} poids={700} style={{ letterSpacing: -0.02 * 20 }}>{sq.titre}</T>
        </View>
        <PressableAnime onPress={quitter} accessibilityRole="button" accessibilityLabel="Quitter ce groupe"
          style={{ width: 44, height: 44, borderRadius: rayon.bouton, backgroundColor: c.surface2, alignItems: 'center', justifyContent: 'center' }}>
          <Icone nom="croix" taille={18} couleur={c.noir} />
        </PressableAnime>
      </View>
      <T taille={fs[5]} poids={600}>{dateFr(sq.date_heure, 'D j M · H\\hi')}</T>
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

function economie(p: Pass) {
  if ((p.reduction ?? 0) > 0 && (p.prix_normal ?? 0) > 0) {
    return '-' + nombre(Math.round((p.prix_normal! * p.reduction!) / 100 * 100) / 100, 2) + '€';
  }
  return 'Gratuit';
}

export default function Wallet() {
  const { c } = useTheme();
  const jeton = useJeton();
  const { profil } = useSession();
  const largeurEcran = Math.min(useWindowDimensions().width, 440);
  // Une carte de pass occupe toute la largeur utile ; la suivante, s'il y en
  // a, se devine au bord en faisant défiler.
  const largeurCarte = largeurEcran - gutter * 2;

  const [page, setPage] = useState(1);
  const [donnees, setDonnees] = useState<ReponseWallet | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);
  const [suite, setSuite] = useState(false);

  const charger = useCallback(async (p = page) => {
    try {
      setErreur(null);
      setDonnees(await api.wallet(jeton, p));
    } catch (e) {
      setErreur(e instanceof Error ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
      setSuite(false);
    }
  }, [jeton, page]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  const passes = donnees?.pass_actifs ?? [];
  const squads = donnees?.squads ?? [];
  const nomTitulaire = profil ? court(profil.prenom, profil.nom) : '';
  const couleurType = (t: string | null) => (t === 'resto' ? '#F0508A' : t === 'bar' ? '#FF8A3D' : t === 'boite' ? '#8B5CF6' : t === 'afterwork' ? '#FFB020' : '#1D55F0');

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
                <ScrollView
                  horizontal
                  showsHorizontalScrollIndicator={false}
                  snapToInterval={largeurCarte + 12}
                  decelerationRate="fast"
                  style={{ flexGrow: 0, marginHorizontal: -gutter, overflow: 'visible' }}
                  contentContainerStyle={{ gap: 12, paddingHorizontal: gutter, paddingBottom: 8 }}
                >
                  {passes.map((p, i) => (
                    <CartePass key={p.id} p={p} index={i} total={passes.length} largeur={largeurCarte} nomTitulaire={nomTitulaire} onAnnule={() => charger()} />
                  ))}
                </ScrollView>
              ) : (
                <View style={{ backgroundColor: c.blanc, borderRadius: rayon.carte, padding: 32, alignItems: 'center', marginBottom: 16 }}>
                  <View style={{ marginBottom: 16 }}><Icone nom="ticket" taille={48} couleur={c.gris} trait={1.5} /></View>
                  <T taille={22} poids={700} interligne={lh.tight} style={{ marginBottom: 6, textAlign: 'center', letterSpacing: -0.03 * 22 }}>Aucun pass actif</T>
                  <T taille={fs[5]} couleur={c.gris} style={{ marginBottom: 18, textAlign: 'center' }}>Inscris-toi à un événement pour obtenir ton pass.</T>
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
                  {passes.map((p) => (
                    <View key={p.id} style={{ padding: 14, borderRadius: rayon.tuile, backgroundColor: c.blanc, flexDirection: 'row', alignItems: 'center', gap: 12 }}>
                      <CarreIcone icone="ticket" fond={couleurType(p.etab_type)} encre="#FFFFFF" taille={40} />
                      <View style={{ flex: 1 }}>
                        <T taille={fs[5]} poids={700} numberOfLines={1}>{p.etablissement}</T>
                        <T taille={fs[4]} couleur={c.gris} numberOfLines={1} style={{ marginTop: 2 }}>{p.titre} · {dateFr(p.date_heure, 'D j M · H\\hi')}</T>
                      </View>
                      <T taille={fs[5]} poids={700} couleur={c.succes}>{economie(p)}</T>
                    </View>
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
              <Apparition index={5}>
                <TitreSection style={{ marginBottom: 12 }}>Historique</TitreSection>
                <View style={{ gap: 10, marginBottom: 16 }}>
                  {donnees.historique.map((p) => {
                    const avis = p.statut === 'checkin';
                    return (
                      <View key={p.id} style={{ padding: 14, borderRadius: rayon.tuile, backgroundColor: c.blanc, flexDirection: 'row', alignItems: 'center', gap: 12, opacity: avis ? 1 : 0.6 }}>
                        <CarreIcone icone={avis ? 'check' : 'ticket'} fond={avis ? c.succesClair : c.surface2} encre={avis ? c.succes : c.gris} taille={40} />
                        <View style={{ flex: 1 }}>
                          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
                            <T taille={fs[5]} poids={700}>{p.etablissement}</T>
                            {avis ? <T taille={fs[2]} poids={700} couleur={c.succes}>Check-in</T> : null}
                          </View>
                          <T taille={fs[4]} couleur={c.gris} style={{ marginTop: 2 }}>{p.titre} · {dateFr(p.date_heure, 'D j M')}</T>
                          {avis && p.evenement_id ? (
                            <Text
                              onPress={() => router.push({ pathname: '/avis/[id]', params: { id: String(p.evenement_id) } })}
                              accessibilityRole="link"
                              style={{ marginTop: 4, paddingVertical: 8, fontFamily: sans(700), fontSize: fs[4], color: c.surRougeClair }}
                            >
                              Laisser un avis
                            </Text>
                          ) : null}
                        </View>
                        <T taille={fs[5]} poids={600} couleur={c.gris}>{economie(p)}</T>
                      </View>
                    );
                  })}
                </View>
                {donnees.pagination.a_suivre ? (
                  <Bouton libelle="Voir plus d'historique" variante="contour" plein chargement={suite}
                    onPress={() => { setSuite(true); const p = page + 1; setPage(p); void charger(p); }} style={{ marginBottom: 16 }} />
                ) : null}
              </Apparition>
            ) : null}

            <Apparition index={6}>
              <TitreSection style={{ marginBottom: 12 }}>Tes économies</TitreSection>
              <View style={{ flexDirection: 'row', gap: 10 }}>
                <View style={{ flex: 1, borderRadius: rayon.tuile, padding: 16, backgroundColor: c.blanc, gap: 6 }}>
                  <T taille={fs[4]} couleur={c.gris}>{MOIS[new Date().getMonth()]}</T>
                  <Compteur valeur={donnees.economies.mois} format={(n) => `${euros(n)} €`}
                    style={{ fontFamily: sans(700), fontSize: 23, letterSpacing: -0.03 * 23, color: c.succes, fontVariant: ['tabular-nums'] }} />
                  <T taille={fs[3]} couleur={c.gris}>économisé ce mois</T>
                </View>
                <View style={{ flex: 1, borderRadius: rayon.tuile, padding: 16, backgroundColor: c.blanc, gap: 6 }}>
                  <T taille={fs[4]} couleur={c.gris}>Total année</T>
                  <Compteur valeur={donnees.economies.annee} delai={120} format={(n) => `${euros(n)} €`}
                    style={{ fontFamily: sans(700), fontSize: 23, letterSpacing: -0.03 * 23, color: c.noir, fontVariant: ['tabular-nums'] }} />
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
