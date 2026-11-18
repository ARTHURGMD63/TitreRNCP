/**
 * Les éléments sociaux partagés par le hub, les abonnements, le classement,
 * les notifications et les profils.
 *
 *  - BoutonSuivre : les trois états d'app.js (peindreBoutonSuivi) — « + Suivre »
 *    en lave, « En attente » et « Suivi » en surface neutre. Depuis « en
 *    attente » comme depuis « abonné », toucher retire le lien.
 *  - LignePersonne : .ligne-personne (annuaire, abonnements).
 *  - CarteSuggestion : .carte-suggestion (« À suivre · d'après tes goûts »).
 *  - ElementNotification : notificationHtml() (cloche et page Notifications).
 *  - FeuilleInvitation : la fenêtre « Inviter un ami » du hub.
 */

import React, { useCallback, useEffect, useState } from 'react';
import { Pressable, Text, View } from 'react-native';
import { router } from 'expo-router';

import { actions, api, ErreurApi, type CibleInvitation, type EtatSuivi, type Notification, type Personne } from '../api';
import { court } from '../format';
import { useJeton } from '../session';
import { fixe, fs, lh, police, rayon, sans, teintes } from '../theme';
import { useTheme } from '../useTheme';
import { PressableAnime, Pulsation } from './Animations';
import { teinteAvatar } from './Design';
import { Avatar } from './Elements';
import { Feuille } from './Feuille';
import { Icone } from './Icone';
import { Display, Mono, T } from './Texte';
import { useToast } from './Toast';

// ─── Bouton Suivre ──────────────────────────────────────────────────────────

export function useSuivi(cible: number, initial: EtatSuivi) {
  const jeton = useJeton();
  const toast = useToast();
  const [etat, setEtat] = useState<EtatSuivi>(initial);
  const [initialVu, setInitialVu] = useState(initial);
  const [attente, setAttente] = useState(false);
  // Nouvelle valeur venue du serveur (rechargement) : elle l'emporte.
  if (initial !== initialVu) {
    setInitialVu(initial);
    setEtat(initial);
  }

  const basculer = useCallback(async () => {
    if (attente) return;
    setAttente(true);
    try {
      const rep = await actions.suivi(jeton, etat === 'none' ? 'follow' : 'unfollow', 'user', cible);
      setEtat(rep.etat ?? 'none');
      toast(rep.message || (rep.etat === 'none' ? 'Abonnement retiré' : 'Demande envoyée'));
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setAttente(false);
    }
  }, [attente, cible, etat, jeton, toast]);

  return { etat, attente, basculer };
}

/**
 * Refonte 2026-11 : « ✓ Suivi » à l'encre, « En attente » en surface neutre
 * avec l'horloge, « + Suivre » plein de l'accent (`accent`) ou en contour.
 */
export function couleursSuivi(etat: EtatSuivi, c: ReturnType<typeof useTheme>['c'], accent?: string) {
  if (etat === 'accepted') return { fond: c.noir, encre: c.surEncre, filet: c.noir };
  if (etat === 'pending') return { fond: c.surface2, encre: c.grisFonce, filet: c.surface2 };
  return accent ? { fond: accent, encre: fixe.surAccent, filet: accent } : { fond: 'transparent', encre: c.noir, filet: c.line2 };
}

export function BoutonSuivre({ cible, etatInitial, largeur, accent }: { cible: number; etatInitial: EtatSuivi; largeur?: 'pleine'; accent?: string }) {
  const { c } = useTheme();
  const { etat, attente, basculer } = useSuivi(cible, etatInitial);
  const { fond, encre, filet } = couleursSuivi(etat, c, accent);
  const libelle = etat === 'accepted' ? 'Suivi' : etat === 'pending' ? 'En attente' : '+ Suivre';
  return (
    <PressableAnime
      onPress={basculer}
      accessibilityRole="button"
      accessibilityLabel={libelle}
      accessibilityState={{ busy: attente }}
      hitSlop={6}
      style={[
        { flexShrink: 0, minHeight: 32, paddingHorizontal: 12, borderRadius: rayon.xs + 2, borderWidth: 1, borderColor: filet, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6, backgroundColor: fond },
        largeur === 'pleine' ? { alignSelf: 'stretch' } : null,
      ]}
    >
      {attente ? (
        <Text style={{ fontFamily: sans(600), fontSize: fs[3], color: encre }}>…</Text>
      ) : (
        <>
          {etat === 'accepted' ? <Icone nom="check" taille={13} couleur={encre} trait={2.5} /> : null}
          {etat === 'pending' ? <Icone nom="horloge" taille={13} couleur={encre} /> : null}
          <Text style={{ fontFamily: sans(etat === 'pending' ? 500 : 600), fontSize: fs[3], color: encre }}>{libelle}</Text>
        </>
      )}
    </PressableAnime>
  );
}

// ─── .ligne-personne ────────────────────────────────────────────────────────

export function LignePersonne({ p, etat, interetsCommuns = [], squadsCommuns = 0, score = 0 }: {
  p: Personne;
  etat: EtatSuivi;
  interetsCommuns?: string[];
  squadsCommuns?: number;
  score?: number;
}) {
  const { c } = useTheme();
  const affinites = (score > 0 || squadsCommuns > 0) && (squadsCommuns > 0 || interetsCommuns.length > 0);
  return (
    <View style={{ backgroundColor: c.blanc, borderRadius: 18, paddingVertical: 12, paddingHorizontal: 14, gap: 8 }}>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
        <Pressable
          onPress={() => router.push({ pathname: '/etudiant/[id]', params: { id: String(p.id) } })}
          accessibilityRole="link"
          accessibilityLabel={`Profil de ${court(p.prenom, p.nom)}`}
          style={{ flex: 1, minWidth: 0, flexDirection: 'row', alignItems: 'center', gap: 10 }}
        >
          <Avatar photo={p.photo_url} prenom={p.prenom} taille={40} fond={teinteAvatar(p.prenom + p.id)} />
          <View style={{ flex: 1, minWidth: 0 }}>
            <Text numberOfLines={1} style={{ fontFamily: sans(600), fontSize: 15, letterSpacing: -0.01 * 15, color: c.noir }}>{court(p.prenom, p.nom)}</Text>
            <T numberOfLines={1} taille={fs[3]} couleur={c.gris}>{p.ecole ?? ''}{p.promo ? ' · ' + p.promo : ''}</T>
          </View>
        </Pressable>
        <BoutonSuivre cible={p.id} etatInitial={etat} />
      </View>
      {affinites ? (
        // Les étiquettes passent à la ligne plutôt que d'être coupées.
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6, paddingLeft: 50 }}>
          {squadsCommuns > 0 ? (
            <Pulsation>
              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: fixe.squadCommun, paddingVertical: 3, paddingHorizontal: 8, borderRadius: 8 }}>
                <Icone nom="eclair" taille={12} couleur="#111116" />
                <T taille={fs[2]} poids={600} couleur="#111116">Squad commun</T>
              </View>
            </Pulsation>
          ) : null}
          {interetsCommuns.map((i) => (
            <View key={i} style={{ backgroundColor: c.surface2, paddingVertical: 3, paddingHorizontal: 8, borderRadius: 8 }}>
              <T taille={fs[2]} poids={500} couleur={c.grisFonce}>#{i}</T>
            </View>
          ))}
        </View>
      ) : null}
    </View>
  );
}

// ─── .carte-suggestion ──────────────────────────────────────────────────────

/** Largeur d'une carte « À suivre » ; le carrousel s'accroche sur ce pas. */
export const LARGEUR_SUGGESTION = 124;

/** Carte du carrousel « À suivre » : avatar de 48 px, nom, hashtags en bleu, « + Suivre » bleu. */
export function CarteSuggestion({ p, motif }: { p: Personne; motif: string }) {
  const { c, mode } = useTheme();
  const bleu = teintes('bleu', mode);
  return (
    <View style={{ width: LARGEUR_SUGGESTION, alignItems: 'center', gap: 8, backgroundColor: c.blanc, borderRadius: 18, paddingVertical: 12, paddingHorizontal: 10 }}>
      <Pressable
        onPress={() => router.push({ pathname: '/etudiant/[id]', params: { id: String(p.id) } })}
        accessibilityRole="link"
        accessibilityLabel={`Profil de ${court(p.prenom, p.nom)}`}
        style={{ alignItems: 'center', gap: 2, width: '100%' }}
      >
        <Avatar photo={p.photo_url} prenom={p.prenom} taille={48} fond={teinteAvatar(p.prenom + p.id)} />
        <Text numberOfLines={1} style={{ fontFamily: sans(600), fontSize: 14, color: c.noir, maxWidth: '100%', marginTop: 4 }}>{court(p.prenom, p.nom)}</Text>
        <T numberOfLines={1} taille={fs[2]} poids={500} couleur={bleu.texteDoux} style={{ maxWidth: '100%' }}>{motif}</T>
      </Pressable>
      <BoutonSuivre cible={p.id} etatInitial="none" largeur="pleine" accent={bleu.accent} />
    </View>
  );
}

// ─── notificationHtml() ─────────────────────────────────────────────────────

function Texte({ children }: { children: React.ReactNode }) {
  const { c } = useTheme();
  return <Text style={{ fontFamily: sans(400), fontSize: fs[3], lineHeight: fs[3] * lh.snug, color: c.noir }}>{children}</Text>;
}
function Gras({ children }: { children: React.ReactNode }) {
  return <Text style={{ fontFamily: sans(700) }}>{children}</Text>;
}
function Italique({ children }: { children: React.ReactNode }) {
  return <Text style={{ fontFamily: police.sans600Italique }}>{children}</Text>;
}
function Date_({ children }: { children: React.ReactNode }) {
  const { c } = useTheme();
  return <T numberOfLines={1} taille={fs[2]} couleur={c.gris}>{children}</T>;
}

/**
 * Une notification. `onTraitee` retire l'élément de la liste après une
 * réponse (Accepter / Refuser), comme le site retire la carte.
 */
export function ElementNotification({ n, onTraitee }: { n: Notification; onTraitee: () => void }) {
  const { c } = useTheme();
  const jeton = useJeton();
  const toast = useToast();
  const [attente, setAttente] = useState<'oui' | 'non' | null>(null);

  const carte = { gap: 10, padding: 14, borderRadius: rayon.md, borderWidth: 1, borderColor: c.grisClair, backgroundColor: c.blanc } as const;
  const ligne = { flexDirection: 'row' as const, alignItems: 'center' as const, gap: 12, minWidth: 0 };

  async function repondreDemande(accepter: boolean) {
    setAttente(accepter ? 'oui' : 'non');
    try {
      const rep = await actions.suivi(jeton, accepter ? 'accept' : 'decline', 'user', n.acteur!.id);
      toast(rep.message ?? '', accepter ? 'success' : '');
      onTraitee();
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setAttente(null);
    }
  }

  async function repondreInvitation(accepter: boolean) {
    setAttente(accepter ? 'oui' : 'non');
    try {
      await actions.repondreInvitation(jeton, n.invitation!.id, accepter);
      toast(accepter ? 'Invitation acceptée ! Tu es inscrit.' : 'Invitation refusée.', accepter ? 'success' : '');
      onTraitee();
    } catch (e) {
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    } finally {
      setAttente(null);
    }
  }

  const actionsOuiNon = (onOui: () => void, onNon: () => void) => (
    <View style={{ flexDirection: 'row', gap: 8 }}>
      <Pressable onPress={onOui} disabled={!!attente} accessibilityRole="button" style={{ flex: 1, minHeight: 40, paddingHorizontal: 12, borderRadius: rayon.pill, backgroundColor: c.rouge, borderWidth: 1, borderColor: c.rouge, alignItems: 'center', justifyContent: 'center' }}>
        <Text style={{ fontFamily: sans(700), fontSize: fs[3], color: fixe.surAccent }}>{attente === 'oui' ? '…' : 'Accepter'}</Text>
      </Pressable>
      <Pressable onPress={onNon} disabled={!!attente} accessibilityRole="button" style={{ flex: 1, minHeight: 40, paddingHorizontal: 12, borderRadius: rayon.pill, borderWidth: 1, borderColor: c.line2, alignItems: 'center', justifyContent: 'center' }}>
        <Text style={{ fontFamily: sans(700), fontSize: fs[3], color: c.noir }}>{attente === 'non' ? '…' : 'Refuser'}</Text>
      </Pressable>
    </View>
  );

  if (n.type === 'demande' && n.acteur) {
    const d = n.acteur;
    return (
      <View style={carte}>
        <Pressable onPress={() => router.push({ pathname: '/etudiant/[id]', params: { id: String(d.id) } })} style={ligne}>
          <Avatar photo={d.photo_url} prenom={d.prenom} taille={38} />
          <View style={{ flex: 1, gap: 2 }}>
            <Texte><Gras>{court(d.prenom, d.nom)}</Gras> demande à te suivre</Texte>
            <Date_>{n.depuis}</Date_>
          </View>
        </Pressable>
        {actionsOuiNon(() => repondreDemande(true), () => repondreDemande(false))}
      </View>
    );
  }

  if (n.type === 'invitation' && n.invitation) {
    const inv = n.invitation;
    return (
      <View style={carte}>
        <View style={ligne}>
          <Avatar photo={inv.de.photo_url} prenom={inv.de.prenom} taille={38} fond={inv.cible_type === 'squad' ? c.bleu : c.rouge} />
          <View style={{ flex: 1, gap: 2 }}>
            <Texte><Gras>{court(inv.de.prenom, inv.de.nom)}</Gras> t&apos;invite à <Italique>{inv.cible_nom ?? 'une sortie'}</Italique></Texte>
            <Date_>{n.depuis}</Date_>
          </View>
        </View>
        {actionsOuiNon(() => repondreInvitation(true), () => repondreInvitation(false))}
      </View>
    );
  }

  if (n.type === 'ami' && n.activite) {
    const a = n.activite;
    return (
      <Pressable
        onPress={() => (a.cible_type === 'event' ? router.push({ pathname: '/evenement/[id]', params: { id: String(a.cible_id) } }) : router.navigate('/squads'))}
        accessibilityRole="link"
        style={[carte, ligne]}
      >
        <Avatar photo={a.acteur.photo_url} prenom={a.acteur.prenom} taille={38} fond={a.cible_type === 'event' ? c.rouge : c.lime} />
        <View style={{ flex: 1, gap: 2 }}>
          <Texte><Gras>{a.acteur.prenom}</Gras> {a.cible_type === 'event' ? 'va à' : 'rejoint'} <Italique>{a.cible_nom}</Italique></Texte>
          <Date_>{a.lieu} · {n.depuis}</Date_>
        </View>
      </Pressable>
    );
  }

  if (n.type === 'lieu' && n.evenement) {
    const e = n.evenement;
    return (
      <Pressable onPress={() => router.push({ pathname: '/evenement/[id]', params: { id: String(e.id) } })} accessibilityRole="link" style={[carte, ligne]}>
        <View style={{ width: 38, height: 38, borderRadius: 19, alignItems: 'center', justifyContent: 'center', backgroundColor: c.surface2 }}>
          <Icone nom="calendrier" taille={16} couleur={c.grisFonce} />
        </View>
        <View style={{ flex: 1, gap: 2 }}>
          <Texte>{e.promo ? 'À ne pas rater chez' : 'Nouveau chez'} <Gras>{e.lieu}</Gras> : <Italique>{e.titre}</Italique></Texte>
          <Date_>{e.date} · {n.depuis}{e.promo ? ' · Sponsorisé' : ''}</Date_>
        </View>
      </Pressable>
    );
  }
  return null;
}

/** L'état vide de la cloche (.notif-vide). */
export function NotificationsVides({ onLien }: { onLien: () => void }) {
  const { c } = useTheme();
  return (
    <View style={{ paddingVertical: 32, paddingHorizontal: 16, alignItems: 'center' }}>
      <T taille={fs[5]} couleur={c.grisFonce} interligne={lh.snug} style={{ textAlign: 'center' }}>Rien de neuf pour l&apos;instant.</T>
      <Text onPress={onLien} accessibilityRole="link" style={{ marginTop: 12, fontFamily: sans(700), fontSize: fs[4], color: c.surRougeClair }}>
        Suis des étudiants et des lieux →
      </Text>
    </View>
  );
}

// ─── Surtitre et bouton de fermeture des feuilles du hub ────────────────────

export function SurtitreFeuille({ children }: { children: React.ReactNode }) {
  const { c } = useTheme();
  return <Mono couleur={c.surRougeClair} style={{ marginBottom: 6 }}>{children}</Mono>;
}

export function FermerFeuille({ onPress }: { onPress: () => void }) {
  const { c } = useTheme();
  return (
    <Pressable onPress={onPress} accessibilityRole="button" accessibilityLabel="Fermer" style={{ width: 36, height: 36, borderRadius: 18, borderWidth: 1, borderColor: c.line2, alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
      <Icone nom="croix" taille={16} couleur={c.noir} />
    </Pressable>
  );
}

// ─── « Inviter un ami » ─────────────────────────────────────────────────────

export function FeuilleInvitation({ cible, onClose }: { cible: { type: CibleInvitation; id: number; nom: string } | null; onClose: () => void }) {
  const { c } = useTheme();
  const jeton = useJeton();
  const toast = useToast();
  const [amis, setAmis] = useState<Personne[] | null>(null);
  const [envoyes, setEnvoyes] = useState<Record<number, 'attente' | 'ok'>>({});
  const [cibleVue, setCibleVue] = useState(cible);

  // Chaque ouverture repart d'une ardoise propre, comme sur le site.
  if (cible !== cibleVue) {
    setCibleVue(cible);
    setEnvoyes({});
  }

  useEffect(() => {
    if (!cible) return;
    api.abonnements(jeton, { type: 'abonnements', p: 10 })
      .then((r) => setAmis(r.personnes))
      .catch(() => setAmis([]));
  }, [cible, jeton]);

  async function inviter(ami: Personne) {
    if (!cible) return;
    setEnvoyes((e) => ({ ...e, [ami.id]: 'attente' }));
    try {
      const rep = await actions.inviter(jeton, ami.id, cible.type, cible.id);
      setEnvoyes((e) => ({ ...e, [ami.id]: 'ok' }));
      toast(rep.message || 'Invitation envoyée !', 'success');
    } catch (e) {
      setEnvoyes((x) => { const y = { ...x }; delete y[ami.id]; return y; });
      toast(e instanceof ErreurApi ? e.message : 'Erreur réseau', 'error');
    }
  }

  return (
    <Feuille visible={cible !== null} onClose={onClose}>
      <View style={{ flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12, marginBottom: 20 }}>
        <View style={{ flex: 1 }}>
          <SurtitreFeuille>Inviter un ami</SurtitreFeuille>
          <Display taille={fs[6]}>{cible?.nom ?? ''}</Display>
        </View>
        <FermerFeuille onPress={onClose} />
      </View>
      <View style={{ gap: 10 }}>
        {(amis ?? []).map((f) => {
          const etat = envoyes[f.id];
          return (
            <View key={f.id} style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12, paddingVertical: 12, paddingHorizontal: 14, backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.md }}>
              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12, flex: 1, minWidth: 0 }}>
                <Avatar photo={f.photo_url} prenom={f.prenom} taille={40} />
                <T numberOfLines={1} taille={fs[4]} poids={700} style={{ flexShrink: 1 }}>{court(f.prenom, f.nom)}</T>
              </View>
              <Pressable
                onPress={() => inviter(f)}
                disabled={!!etat}
                accessibilityRole="button"
                style={{ flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: etat === 'ok' ? c.lime : c.noir, borderRadius: rayon.pill, paddingVertical: 9, paddingHorizontal: 18 }}
              >
                {etat === 'ok' ? <Icone nom="check" taille={16} couleur={fixe.surLave} /> : null}
                <Text style={{ fontFamily: sans(700), fontSize: fs[3], color: etat === 'ok' ? fixe.surLave : c.blanc }}>
                  {etat === 'ok' ? 'Envoyé' : etat === 'attente' ? '…' : 'Inviter'}
                </Text>
              </Pressable>
            </View>
          );
        })}
        {amis && amis.length === 0 ? (
          <View style={{ alignItems: 'center', padding: 24 }}>
            <T taille={fs[4]} couleur={c.gris} style={{ textAlign: 'center' }}>Tu ne suis personne encore.</T>
            <Text
              onPress={() => { onClose(); router.navigate('/personnes'); }}
              style={{ fontFamily: sans(700), fontSize: fs[4], color: c.surRougeClair, textAlign: 'center' }}
            >
              Trouve des étudiants à suivre →
            </Text>
          </View>
        ) : null}
      </View>
    </Feuille>
  );
}
