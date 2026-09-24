/**
 * Pass — wallet.php.
 *
 * « Ton pass, / en poche. », les deux montants (ce mois en volt, l'année),
 * le carrousel des pass (aplat lave, halo, « Appuyer pour afficher. » puis le
 * QR code sur fond blanc), les squads prévus, la liste des passes actifs
 * (pastille lave ou moutarde selon le lieu) et l'historique, avec « Laisser
 * un avis → » après un passage validé.
 */

import React, { useCallback, useState } from 'react';
import { Pressable, ScrollView, Text, View, useWindowDimensions } from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import QRCode from 'react-native-qrcode-svg';

import { actions, api, ErreurApi, type Pass, type ReponseWallet, type SquadAgenda } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Separateur } from '../../composants/Elements';
import { Icone } from '../../composants/Icone';
import { Display, Mono, T, TitreEcran } from '../../composants/Texte';
import { useToast } from '../../composants/Toast';
import { confirmer } from '../../confirmer';
import { court, dateFr, majuscules, nombre } from '../../format';
import { useJeton, useSession } from '../../session';
import { fixe, fs, gutter, lh, lsEm, mono, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

const MOIS = ['JANVIER', 'FÉVRIER', 'MARS', 'AVRIL', 'MAI', 'JUIN', 'JUILLET', 'AOÛT', 'SEPTEMBRE', 'OCTOBRE', 'NOVEMBRE', 'DÉCEMBRE'];

/** Arrondi de number_format() : au plus proche, la moitié vers le haut. */
const euros = (n: number) => nombre(Math.round(n));

function CartePass({ p, index, total, largeur, nomTitulaire, onAnnule }: {
  p: Pass; index: number; total: number; largeur: number; nomTitulaire: string; onAnnule: () => void;
}) {
  const jeton = useJeton();
  const toast = useToast();
  const [revele, setRevele] = useState(false);
  const [attente, setAttente] = useState(false);

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

  const supprime = p.evenement_supprime;
  const encre = fixe.surLave;

  return (
    <View
      style={[
        { width: largeur, backgroundColor: supprime ? '#767676' : '#FF5424', borderRadius: rayon.xl, marginBottom: 18, opacity: supprime ? 0.6 : 1 },
      ]}
    >
      <View style={{ paddingTop: 18, paddingHorizontal: 22, paddingBottom: 14, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12 }}>
        <View style={{ flex: 1 }}>
          <Mono couleur={encre} style={{ opacity: 0.8, marginBottom: 4 }}>
            Pass étudiant · {index + 1}/{total}{supprime ? '' : ' · Actif'}
          </Mono>
          <T taille={fs[4]} poids={700} couleur={encre}>{supprime ? 'Pass invalide' : majuscules(nomTitulaire)}</T>
        </View>
        <Pressable
          onPress={annuler}
          disabled={attente}
          accessibilityRole="button"
          accessibilityLabel={supprime ? 'Supprimer ce pass' : 'Annuler ce pass'}
          style={{ width: 32, height: 32, borderRadius: 16, backgroundColor: 'rgba(17,16,19,0.12)', alignItems: 'center', justifyContent: 'center' }}
        >
          <Icone nom="croix" taille={16} couleur={encre} />
        </Pressable>
      </View>

      {supprime ? (
        <View style={{ backgroundColor: 'rgba(17,16,19,0.12)', marginHorizontal: 18, marginBottom: 18, borderRadius: rayon.base, paddingVertical: 34, paddingHorizontal: 20, minHeight: 188, alignItems: 'center', justifyContent: 'center', gap: 8 }}>
          <Icone nom="interdit" taille={40} couleur="#666666" />
          <Display taille={fs[5]} couleur="#555555" style={{ textAlign: 'center' }}>{'Événement\nsupprimé'}</Display>
        </View>
      ) : (
        <Pressable
          onPress={() => setRevele(true)}
          accessibilityRole="button"
          accessibilityLabel={revele ? 'QR code du pass' : 'Afficher le QR code'}
          style={{ backgroundColor: revele ? '#FFFFFF' : 'rgba(17,16,19,0.1)', marginHorizontal: 18, marginBottom: 18, borderRadius: rayon.base, paddingVertical: revele ? 20 : 34, paddingHorizontal: 20, minHeight: 188, alignItems: 'center', justifyContent: 'center' }}
        >
          {revele && p.code_qr ? (
            // Couleurs fixes : un QR code est une cible optique, il ne suit pas le thème.
            <QRCode value={p.code_qr} size={200} color={fixe.qrSombre} backgroundColor={fixe.qrClair} ecl="H" />
          ) : (
            <Display taille={fs[6]} interligne={lh.tight} couleur={encre} style={{ textAlign: 'center' }}>{'Appuyer\npour afficher.'}</Display>
          )}
        </Pressable>
      )}

      <View style={{ paddingHorizontal: 22, paddingBottom: 20 }}>
        {supprime ? (
          <>
            <T taille={fs[4]} poids={700} couleur={encre}>Cet événement n&apos;existe plus</T>
            <T taille={fs[3]} couleur={encre} style={{ opacity: 0.75, marginTop: 2 }}>Tu peux supprimer ce pass</T>
          </>
        ) : (
          <>
            <T taille={fs[5]} poids={700} couleur={encre}>{p.etablissement} — {p.titre}</T>
            <T taille={fs[3]} couleur={encre} style={{ opacity: 0.8, marginTop: 2 }}>
              {dateFr(p.date_heure, 'D j M · H\\hi')} · {(p.reduction ?? 0) > 0 ? `-${p.reduction}%` : 'entrée gratuite'}
            </T>
          </>
        )}
      </View>
    </View>
  );
}

function CarteSquadAgenda({ sq, largeur, onQuitte }: { sq: SquadAgenda; largeur: number; onQuitte: () => void }) {
  const { c } = useTheme();
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
    <View style={{ width: largeur, backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderLeftWidth: 4, borderLeftColor: c.bleu, borderRadius: rayon.md, overflow: 'hidden' }}>
      <View style={{ paddingTop: 16, paddingHorizontal: 16, paddingBottom: 8, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <View style={{ flex: 1 }}>
          <Mono couleur={c.surBleuClair} style={{ marginBottom: 4 }}>Session {sq.type}</Mono>
          <Display taille={fs[6]} interligne={lh.tight}>{sq.titre}</Display>
        </View>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
          <Icone nom="activite" taille={24} couleur={c.surBleuClair} />
          <Pressable onPress={quitter} accessibilityRole="button" accessibilityLabel="Quitter ce groupe" hitSlop={10} style={{ width: 24, height: 24, alignItems: 'center', justifyContent: 'center', marginTop: -2 }}>
            <Icone nom="croix" taille={16} couleur={c.noir} />
          </Pressable>
        </View>
      </View>
      <View style={{ paddingHorizontal: 16, paddingBottom: 16 }}>
        <T taille={fs[4]} poids={700} style={{ marginBottom: 6 }}>{dateFr(sq.date_heure, 'D j M · H\\hi')}</T>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 6 }}>
          <Icone nom="epingle" taille={16} couleur={c.grisFonce} />
          <T taille={fs[3]} couleur={c.grisFonce}>{sq.lieu}</T>
        </View>
        <T taille={fs[3]} poids={500} couleur={c.gris}>Organisé par {sq.createur_prenom} · Niveau : {sq.niveau}</T>
      </View>
    </View>
  );
}

function EnTeteListe({ gauche, droite, style }: { gauche: string; droite: string; style?: object }) {
  const { c } = useTheme();
  const txt = { fontFamily: mono(500), fontSize: fs[1], letterSpacing: lsEm.label * fs[1], textTransform: 'uppercase' as const, color: c.gris };
  return (
    <View style={[{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 14, borderTopWidth: 1, borderTopColor: c.grisClair }, style]}>
      <Text style={txt}>{gauche}</Text>
      <Text style={txt}>{droite}</Text>
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
  const largeurCarte = (largeurEcran - gutter * 2) * 0.88;

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
  const couleurType = (t: string | null) => (t === 'resto' ? c.orange : t === 'bar' || t === 'boite' || t === 'afterwork' ? c.rouge : c.gris);

  return (
    <Ecran rafraichit={rafraichit} onRafraichir={() => { setRafraichit(true); void charger(); }}>
      <EnTete>
        <TitreEcran lignes={['Ton pass,', 'en poche.']} />
      </EnTete>

      <Contenu>
        {erreur ? <Erreur message={erreur} onReessayer={() => charger()} /> : donnees === null ? <Chargement /> : (
          <>
            <View style={{ flexDirection: 'row', gap: 11, marginBottom: 16 }}>
              <View style={{ flex: 1, borderRadius: rayon.base, padding: 18, borderWidth: 1, borderColor: c.grisClair, backgroundColor: c.blanc }}>
                <Mono style={{ marginBottom: 8, opacity: 0.85 }}>{MOIS[new Date().getMonth()]}</Mono>
                <Display taille={fs[8]} couleur={c.surLimeClair} style={{ fontVariant: ['tabular-nums'] }}>{euros(donnees.economies.mois)}€</Display>
                <T taille={fs[2]} couleur={c.gris} style={{ marginTop: 4, opacity: 0.85 }}>économisé ce mois</T>
              </View>
              <View style={{ flex: 1, borderRadius: rayon.base, padding: 18, borderWidth: 1, borderColor: c.line2, backgroundColor: c.blanc }}>
                <Mono style={{ marginBottom: 8, opacity: 0.85 }}>Total année</Mono>
                <Display taille={fs[8]} style={{ fontVariant: ['tabular-nums'] }}>{euros(donnees.economies.annee)}€</Display>
                <T taille={fs[2]} couleur={c.gris} style={{ marginTop: 4, opacity: 0.85 }}>depuis janvier</T>
              </View>
            </View>

            {passes.length ? (
              <ScrollView
                horizontal
                showsHorizontalScrollIndicator={false}
                snapToInterval={largeurCarte + 16}
                decelerationRate="fast"
                style={{ flexGrow: 0 }}
                contentContainerStyle={{ gap: 16, paddingBottom: 16 }}
              >
                {passes.map((p, i) => (
                  <CartePass key={p.id} p={p} index={i} total={passes.length} largeur={largeurCarte} nomTitulaire={nomTitulaire} onAnnule={() => charger()} />
                ))}
              </ScrollView>
            ) : (
              <View style={{ backgroundColor: c.blanc, borderRadius: rayon.base, padding: 32, alignItems: 'center', marginBottom: 12, borderWidth: 1, borderColor: c.grisClair }}>
                <View style={{ marginBottom: 16 }}><Icone nom="ticket" taille={48} couleur={c.gris} trait={1.5} /></View>
                <T taille={fs[5]} poids={600} style={{ marginBottom: 6 }}>Aucun pass actif</T>
                <T taille={fs[3]} couleur={c.gris} style={{ marginBottom: 16, textAlign: 'center' }}>Inscris-toi à un événement pour obtenir ton pass.</T>
                <Bouton libelle="Explorer les événements" onPress={() => router.navigate('/')} style={{ alignSelf: 'center' }} />
              </View>
            )}

            {squads.length ? (
              <>
                <EnTeteListe style={{ marginTop: 24 }} gauche={`${squads.length} Squad${squads.length > 1 ? 's' : ''} prévu${squads.length > 1 ? 's' : ''}`} droite="—— Sport ↓" />
                <ScrollView horizontal showsHorizontalScrollIndicator={false} snapToInterval={largeurCarte + 16} decelerationRate="fast"
                  style={{ flexGrow: 0 }} contentContainerStyle={{ gap: 16, paddingBottom: 16 }}>
                  {squads.map((sq) => <CarteSquadAgenda key={sq.id} sq={sq} largeur={largeurCarte} onQuitte={() => charger()} />)}
                </ScrollView>
              </>
            ) : null}

            <EnTeteListe style={{ marginTop: 16 }} gauche={`${passes.length} passe${passes.length > 1 ? 's' : ''} actif${passes.length > 1 ? 's' : ''}`} droite="—— Agenda ↓" />
            {passes.map((p) => (
              <View key={p.id} style={{ paddingVertical: 13, borderBottomWidth: 1, borderBottomColor: c.grisClair, flexDirection: 'row', alignItems: 'center', gap: 13 }}>
                <View style={{ width: 9, height: 9, borderRadius: 5, backgroundColor: couleurType(p.etab_type) }} />
                <View style={{ flex: 1 }}>
                  <T taille={fs[4]} poids={600}>{p.etablissement}</T>
                  <T taille={fs[2]} couleur={c.gris} style={{ marginTop: 2 }}>{p.titre} · {dateFr(p.date_heure, 'D j M · H\\hi')}</T>
                </View>
                <Text style={{ fontFamily: mono(600), fontSize: fs[4], color: c.surLimeClair }}>{economie(p)}</Text>
              </View>
            ))}

            {donnees.historique.length ? (
              <>
                <Separateur libelle="Historique" />
                {donnees.historique.map((p) => {
                  const avis = p.statut === 'checkin';
                  return (
                    <View key={p.id} style={{ paddingVertical: 13, borderBottomWidth: 1, borderBottomColor: c.grisClair, flexDirection: 'row', alignItems: 'center', gap: 13, opacity: avis ? 1 : 0.5 }}>
                      <View style={{ width: 9, height: 9, borderRadius: 5, backgroundColor: avis ? c.lime : c.gris }} />
                      <View style={{ flex: 1 }}>
                        <T taille={fs[4]} poids={600}>{p.etablissement}</T>
                        <T taille={fs[2]} couleur={c.gris} style={{ marginTop: 2 }}>{p.titre} · {dateFr(p.date_heure, 'D j M')}</T>
                        {avis && p.evenement_id ? (
                          <Text
                            onPress={() => router.push({ pathname: '/avis/[id]', params: { id: String(p.evenement_id) } })}
                            accessibilityRole="link"
                            style={{ marginTop: 6, fontFamily: sans(700), fontSize: fs[3], color: c.surRougeClair }}
                          >
                            Laisser un avis →
                          </Text>
                        ) : null}
                      </View>
                      <Text style={{ fontFamily: mono(600), fontSize: fs[4], color: c.gris }}>{economie(p)}</Text>
                    </View>
                  );
                })}
                {donnees.pagination.a_suivre ? (
                  <Bouton libelle="Voir plus d'historique" variante="contour" plein chargement={suite}
                    onPress={() => { setSuite(true); const p = page + 1; setPage(p); void charger(p); }} style={{ marginTop: 14 }} />
                ) : null}
              </>
            ) : null}
          </>
        )}
      </Contenu>
    </Ecran>
  );
}
