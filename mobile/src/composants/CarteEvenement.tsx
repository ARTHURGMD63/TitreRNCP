/**
 * Les deux cartes d'événement du hub (explore.php).
 *
 *  - Flash : aplat lave, texte basalte, halo lave. « CE SOIR · 20H30 », le
 *    badge FLASH qui décompte, le lieu et sa ville, le compte à rebours sur
 *    la première carte, les amis qui y vont, la réduction en grand, « Je
 *    fonce », et la jauge de remplissage.
 *  - Classique : surface, étiquette « BAR · JEU 12 MARS » teintée par le type,
 *    badges (style, gratuit, sponsorisé), note du lieu, description coupée à
 *    cent caractères, places, « Inviter » et « Rejoindre », taux d'inscription.
 *
 * Suivre le lieu et s'inscrire passent par les mêmes points d'API que le site,
 * avec les mêmes libellés, les mêmes couleurs après le clic et les mêmes
 * toasts.
 */

import React, { useEffect, useState } from 'react';
import { Pressable, Text, View } from 'react-native';
import { router } from 'expo-router';

import { actions, ErreurApi, type Evenement } from '../api';
import { LIBELLES_TYPE } from '../catalogue';
import { dateFr, heure, majuscules, ts } from '../format';
import { useJeton } from '../session';
import { fixe, fs, haloLave, lh, lsEm, mono, rayon, sans } from '../theme';
import { useTheme } from '../useTheme';
import { Bouton } from './Bouton';
import { Badge, Jauge } from './Elements';
import { Icone } from './Icone';
import { Display, Mono, T } from './Texte';
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

/** « FLASH · 12MIN 05S », puis « EXPIRÉ » (app.js, [data-expiry]). */
function libelleFlash(expiry: number, maintenant: number) {
  const diff = Math.max(0, expiry * 1000 - maintenant);
  if (diff <= 0) return 'EXPIRÉ';
  const mins = Math.floor(diff / 60000);
  const secs = Math.floor((diff % 60000) / 1000);
  return `FLASH · ${mins}MIN ${secs < 10 ? '0' : ''}${secs}S`;
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
  const fond = media ? (abonne ? fixe.basalte : 'rgba(17,16,19,0.08)') : abonne ? c.noir : 'transparent';
  const encre = media ? (abonne ? fixe.craie : fixe.surLave) : abonne ? c.blanc : c.noir;
  const filet = media ? fixe.surLave : c.line2;

  return (
    <Pressable
      onPress={basculer}
      accessibilityRole="button"
      accessibilityLabel={abonne ? 'Ne plus suivre ce lieu' : 'Suivre ce lieu'}
      hitSlop={{ top: 8, bottom: 8 }}
      style={{ flexShrink: 0, flexDirection: 'row', alignItems: 'center', gap: 4, borderWidth: 1.5, borderColor: filet, borderRadius: rayon.pill, paddingVertical: 5, paddingHorizontal: 12, backgroundColor: fond }}
    >
      {attente ? (
        <Text style={{ fontFamily: sans(700), fontSize: fs[2], color: encre }}>...</Text>
      ) : (
        <>
          {abonne ? <Icone nom="check" taille={16} couleur={encre} /> : null}
          <Text style={{ fontFamily: sans(700), fontSize: fs[2], letterSpacing: lsEm.wide * fs[2], color: encre }}>
            {abonne ? 'SUIVI' : '+ SUIVRE'}
          </Text>
        </>
      )}
    </Pressable>
  );
}

// ─── S'inscrire (.btn-join-event) ───────────────────────────────────────────

function useInscription(ev: Evenement) {
  const jeton = useJeton();
  const toast = useToast();
  const [etat, setEtat] = useState<'libre' | 'attente' | 'inscrit' | 'vient'>(ev.deja_inscrit ? 'inscrit' : 'libre');
  const [libelleErreur, setLibelleErreur] = useState<string | null>(null);

  async function inscrire() {
    setEtat('attente');
    try {
      await actions.inscrire(jeton, ev.id);
      setEtat('vient');
      toast('Tu es inscrit ! Rendez-vous ce soir.', 'success');
    } catch (e) {
      const msg = e instanceof ErreurApi ? e.message : 'Erreur réseau';
      // Comme le site : le bouton affiche la raison du refus (« Complet »).
      setLibelleErreur(e instanceof ErreurApi && e.statut !== 0 ? msg : '→ je rejoins');
      setEtat('libre');
      toast(msg, 'error');
    }
  }
  return { etat, libelleErreur, inscrire };
}

// ─── Carte flash ────────────────────────────────────────────────────────────

function CarteFlash({ ev, premiere, onInviter: _onInviter }: { ev: Evenement; premiere: boolean; onInviter: () => void }) {
  const { c } = useTheme();
  const maintenant = useMaintenant();
  const { etat, libelleErreur, inscrire } = useInscription(ev);
  const pct = ev.places.quota > 0 ? Math.round((ev.places.inscrits / ev.places.quota) * 100) : 0;
  const amis = ev.amis.nb > 0 ? ev.amis : null;
  const ouvrir = () => router.push({ pathname: '/evenement/[id]', params: { id: String(ev.id) } });
  const reduction = ev.reduction ?? 0;

  return (
    <View style={[{ backgroundColor: c.rouge, padding: 22, borderRadius: rayon.base, marginBottom: 20 }, haloLave]}>
      <View style={{ flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 8, marginBottom: 8 }}>
        <Mono poids={600} couleur={fixe.surLave} style={{ opacity: 0.8 }}>CE SOIR · {heure(ev.date_heure)}</Mono>
        <Badge libelle={ev.flash_expiry ? libelleFlash(ts(ev.flash_expiry), maintenant) : 'FLASH'} fond="rgba(17,16,19,0.12)" encre={fixe.surLave} style={{ paddingVertical: 4 }} />
        {ev.style_musique ? <Badge libelle={libelleStyle(ev.style_musique)} fond="rgba(17,16,19,0.1)" encre={fixe.surLave} filet="rgba(17,16,19,0.3)" espacement={lsEm.wide} style={{ paddingVertical: 2, paddingHorizontal: 9 }} /> : null}
        {ev.sponsorise ? <Badge libelle="Sponsorisé" fond="rgba(17,16,19,0.1)" encre={fixe.surLave} filet="rgba(17,16,19,0.3)" /> : null}
      </View>

      <Pressable onPress={ouvrir} accessibilityRole="link">
        <Display taille={fs[7]} interligne={lh.tight} couleur={fixe.surLave} style={{ marginBottom: 6 }}>{ev.titre}</Display>
      </Pressable>

      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10 }}>
        <Pressable onPress={ouvrir} style={{ flex: 1, minWidth: 0, marginBottom: 12 }}>
          <T numberOfLines={1} taille={fs[3]} couleur={fixe.surLave}>{ev.etablissement.nom} — {ev.etablissement.ville}</T>
        </Pressable>
        <BoutonSuivreLieu etabId={ev.etablissement.id} suivi={ev.etablissement.suivi} media />
      </View>

      {premiere ? (
        <Pressable onPress={ouvrir} style={{ marginTop: 14, paddingVertical: 12, paddingHorizontal: 16, backgroundColor: fixe.basalte, borderRadius: rayon.md, flexDirection: 'row', alignItems: 'center', gap: 10 }}>
          <Icone nom="horloge" taille={16} couleur="rgba(245,241,232,0.6)" />
          <Mono couleur="rgba(245,241,232,0.7)">Commence dans</Mono>
          <Text style={{ marginLeft: 'auto', fontFamily: mono(600), fontSize: fs[6], color: fixe.craie, fontVariant: ['tabular-nums'] }}>
            {libelleDebut(ts(ev.date_heure), maintenant)}
          </Text>
        </Pressable>
      ) : null}

      {amis ? (
        <View style={{ marginTop: 8, flexDirection: 'row', alignItems: 'center', gap: 4 }}>
          <Icone nom="personnes" taille={14} couleur={fixe.surLave} />
          <T taille={fs[3]} poids={600} couleur={fixe.surLave}>
            {amis.prenoms.slice(0, 2).join(', ')}{amis.nb > 2 ? ` +${amis.nb - 2}` : ''} y vont
          </T>
        </View>
      ) : null}

      <View style={{ flexDirection: 'row', alignItems: 'flex-end', justifyContent: 'space-between', marginTop: 16 }}>
        <View>
          <Display taille={fs[8]} couleur={fixe.surLave} style={{ fontVariant: ['tabular-nums'] }}>
            {reduction > 0 ? `-${reduction}%` : ev.is_gratuit ? 'Gratuit' : 'Soirée'}
          </Display>
          <T taille={fs[3]} couleur={fixe.surLave} style={{ opacity: 0.85 }}>
            {reduction > 0 ? (ev.is_gratuit ? 'entrée gratuite' : 'sur conso') : 'sans remise'}
          </T>
        </View>
        <Bouton
          variante="basalte"
          libelle={etat === 'inscrit' || etat === 'vient' ? 'Inscrit' : libelleErreur ?? 'Je fonce'}
          icone={etat === 'inscrit' || etat === 'vient' ? 'check' : undefined}
          desactive={etat === 'inscrit' || etat === 'vient'}
          chargement={etat === 'attente'}
          onPress={inscrire}
        />
      </View>

      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 16, marginBottom: 6 }}>
        <Mono poids={600} couleur={fixe.surLave}>Remplissage</Mono>
        <Mono poids={600} couleur={fixe.surLave}>{pct}%</Mono>
      </View>
      <Jauge pourcentage={pct} piste="rgba(17,16,19,0.2)" remplissage={c.blanc} />
    </View>
  );
}

// ─── Carte classique ────────────────────────────────────────────────────────

function CarteClassique({ ev, onInviter }: { ev: Evenement; onInviter: () => void }) {
  const { c, ombre } = useTheme();
  const { etat, libelleErreur, inscrire } = useInscription(ev);
  const pct = ev.places.quota > 0 ? Math.round((ev.places.inscrits / ev.places.quota) * 100) : 0;
  const accent = ev.type === 'resto' ? c.surOrangeClair : c.surRougeClair;
  const ouvrir = () => router.push({ pathname: '/evenement/[id]', params: { id: String(ev.id) } });
  const inscrit = etat === 'inscrit' || etat === 'vient';

  return (
    <View style={[{ backgroundColor: c.blanc, padding: 20, borderRadius: rayon.base, borderWidth: 1, borderColor: c.grisClair, marginBottom: 20 }, ombre('base')]}>
      <View style={{ flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 8, marginBottom: 8 }}>
        <Mono couleur={accent}>{majuscules(LIBELLES_TYPE[ev.type] ?? ev.type)} · {dateFr(ev.date_heure, 'D j M')}</Mono>
        {ev.style_musique ? <Badge libelle={libelleStyle(ev.style_musique)} icone="musique" fond="transparent" encre={c.grisFonce} filet={c.line2} espacement={lsEm.wide} style={{ paddingVertical: 2, paddingHorizontal: 9, gap: 4 }} /> : null}
        {ev.is_gratuit ? <Badge libelle="GRATUIT" fond={c.noir} encre={c.bg} /> : null}
        {ev.sponsorise ? <Badge libelle="Sponsorisé" fond={c.surface2} encre={c.grisFonce} filet={c.line2} /> : null}
      </View>

      <Pressable onPress={ouvrir} accessibilityRole="link">
        <Display taille={fs[6]} interligne={lh.tight} style={{ marginBottom: 6 }}>{ev.titre}</Display>
      </Pressable>

      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10 }}>
        <Pressable onPress={ouvrir} style={{ flex: 1, minWidth: 0, flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 12 }}>
          <T numberOfLines={1} taille={fs[3]} couleur={c.grisFonce} style={{ flexShrink: 1 }}>{ev.etablissement.nom}</T>
          {ev.etablissement.nb_avis > 0 ? (
            <>
              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4, flexShrink: 0 }}>
                <Icone nom="etoile" taille={16} couleur={c.surOrangeClair} />
                <T taille={fs[3]} poids={700} couleur={c.surOrangeClair}>{formaterNote(ev.etablissement.note)}</T>
              </View>
              <T taille={fs[3]} couleur={c.gris} style={{ flexShrink: 0 }}>({ev.etablissement.nb_avis})</T>
            </>
          ) : null}
        </Pressable>
        <BoutonSuivreLieu etabId={ev.etablissement.id} suivi={ev.etablissement.suivi} />
      </View>

      {ev.amis.nb > 0 ? (
        <View style={{ marginTop: 8, flexDirection: 'row', alignItems: 'center', gap: 4 }}>
          <Icone nom="personnes" taille={14} couleur={c.surRougeClair} />
          <T taille={fs[3]} poids={600} couleur={c.surRougeClair}>{ev.amis.prenoms.slice(0, 2).join(', ')} y vont</T>
        </View>
      ) : null}

      <T taille={fs[3]} couleur={c.grisFonce} interligne={lh.snug} style={{ marginVertical: 12 }}>
        {Array.from(ev.description ?? '').slice(0, 100).join('')}...
      </T>

      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
        <T taille={fs[3]} couleur={c.gris}>{ev.places.inscrits}/{ev.places.quota} places</T>
        <View style={{ flexDirection: 'row', gap: 6 }}>
          <Pressable
            onPress={onInviter}
            accessibilityRole="button"
            style={{ flexDirection: 'row', alignItems: 'center', gap: 5, borderWidth: 1.5, borderColor: c.line2, borderRadius: rayon.pill, paddingVertical: 8, paddingHorizontal: 14, minHeight: 44 }}
          >
            <Icone nom="ajout-personne" taille={13} couleur={c.noir} />
            <T taille={fs[3]} poids={700}>Inviter</T>
          </Pressable>
          <Bouton
            variante={etat === 'vient' ? 'contour' : 'primaire'}
            libelle={inscrit ? 'Inscrit' : libelleErreur ?? 'Rejoindre'}
            icone={inscrit ? 'check' : undefined}
            desactive={inscrit}
            chargement={etat === 'attente'}
            onPress={inscrire}
            taillePolice={fs[3]}
            style={{ paddingVertical: 8, paddingHorizontal: 18 }}
          />
        </View>
      </View>

      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 14, marginBottom: 6 }}>
        <Mono>Taux d&apos;inscription</Mono>
        <Mono>{pct}%</Mono>
      </View>
      <Jauge pourcentage={pct} piste={c.grisClair} remplissage={c.rouge} />
    </View>
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

export function CarteEvenement({ ev, premiere, onInviter }: { ev: Evenement; premiere: boolean; onInviter: () => void }) {
  const [instant] = useState(() => Date.now() / 1000);
  const flash = ev.is_flash && ts(ev.flash_expiry) > instant;
  return flash ? <CarteFlash ev={ev} premiere={premiere} onInviter={onInviter} /> : <CarteClassique ev={ev} onInviter={onInviter} />;
}

export { BoutonSuivreLieu };
