/**
 * Suivi — tout l'état de l'application d'un coup d'œil, pour les fondateurs
 * (api/v1/admin_suivi.php, lecture seule).
 *
 * De haut en bas, ce qui demande d'agir d'abord : la santé (erreurs, file des
 * notifications, signalements), puis les inscrits et leurs heures de pointe,
 * les événements et les pass, les abonnements, les options et ce qu'on gagne.
 * Chaque volet peut manquer (base pas encore migrée, requête en échec) : il
 * est alors signalé comme indisponible, les autres restent lisibles.
 * Rafraîchi à chaque retour sur l'onglet et en tirant l'écran.
 */

import React, { useCallback, useState } from 'react';
import { Pressable, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';

import { api, ErreurApi, type PointJour, type ReponseSuiviAdmin } from '../../api';
import { useRubrique } from '../../composants/Design';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { GraphiqueBarres, type PointBarre } from '../../composants/GraphiqueBarres';
import { Icone, type NomIcone } from '../../composants/Icone';
import { Mono, T, TitreEcran } from '../../composants/Texte';
import { dateFr } from '../../format';
import { formatEuros } from '../../regles';
import { useJeton } from '../../session';
import { fs, rayon } from '../../theme';
import { useTheme } from '../../useTheme';

const eur = (montant: number) => formatEuros(Math.round(montant));
const MOIS_COURTS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

function Carte({ titre, children }: { titre: string; children: React.ReactNode }) {
  const { c, ombre } = useTheme();
  return (
    <View style={[{ backgroundColor: c.blanc, borderRadius: rayon.base, borderWidth: 1, borderColor: c.grisClair, padding: 18, marginBottom: 14 }, ombre('sm')]}>
      <Mono couleur={c.gris} style={{ marginBottom: 12 }}>{titre}</Mono>
      {children}
    </View>
  );
}

function Indisponible() {
  const { c } = useTheme();
  return <T taille={fs[3]} couleur={c.gris}>Indisponible pour le moment.</T>;
}

/** Un chiffre et son libellé, en tuile. */
function Chiffre({ valeur, libelle, detail, alerte }: { valeur: string; libelle: string; detail?: string; alerte?: boolean }) {
  const { c } = useTheme();
  return (
    <View style={{ flex: 1, minWidth: 0 }}>
      <T taille={fs[6]} poids={700} couleur={alerte ? c.danger : undefined}>{valeur}</T>
      <T taille={fs[2]} couleur={c.gris}>{libelle}</T>
      {detail ? <T taille={fs[2]} couleur={c.grisFonce}>{detail}</T> : null}
    </View>
  );
}

function Rangee({ children }: { children: React.ReactNode }) {
  return <View style={{ flexDirection: 'row', gap: 12, marginBottom: 14 }}>{children}</View>;
}

function Ligne({ libelle, valeur, fort }: { libelle: string; valeur: string; fort?: boolean }) {
  const { c } = useTheme();
  return (
    <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: 12, paddingVertical: 6, borderTopWidth: fort ? 1 : 0, borderTopColor: c.grisClair }}>
      <T taille={fs[4]} poids={fort ? 700 : 400} style={{ flexShrink: 1 }}>{libelle}</T>
      <T taille={fs[4]} poids={700}>{valeur}</T>
    </View>
  );
}

/** Une ligne de santé : icône d'état + libellé, jamais la couleur seule. */
function EtatSante({ ok, libelle, onPress }: { ok: boolean; libelle: string; onPress?: () => void }) {
  const { c } = useTheme();
  const icone: NomIcone = ok ? 'valide' : 'echec';
  const contenu = (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 8 }}>
      <Icone nom={icone} taille={18} couleur={ok ? c.succes : c.danger} />
      <T taille={fs[4]} style={{ flex: 1 }}>{libelle}</T>
      {onPress ? <Icone nom="chevron-d" taille={16} couleur={c.gris} /> : null}
    </View>
  );
  return onPress ? <Pressable onPress={onPress} accessibilityRole="link">{contenu}</Pressable> : contenu;
}

const evolution = (v: number | null) => (v === null ? undefined : `${v > 0 ? '+' : ''}${v} % sur 30 jours`);
const parJour = (serie: PointJour[]): PointBarre[] => serie.map((p) => ({ cle: p.jour, valeur: p.n, libelle: dateFr(p.jour, 'D j M') }));
const pluriel = (n: number, mot: string) => `${n} ${mot}${n > 1 ? 's' : ''}`;

export default function SuiviAdmin() {
  const { c } = useTheme();
  const bleu = useRubrique('bleu');
  const jeton = useJeton();
  const [d, setD] = useState<ReponseSuiviAdmin | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      setD(await api.adminSuivi(jeton));
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
    }
  }, [jeton]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  if (!d) {
    return <Ecran>{erreur ? <Erreur message={erreur} onReessayer={charger} /> : <Chargement />}</Ecran>;
  }

  const { utilisateurs: u, evenements: e, abonnements: ab, options: op, revenus: rv, sante: s } = d;

  return (
    <Ecran rafraichit={rafraichit} onRafraichir={() => { setRafraichit(true); void charger(); }}>
      <EnTete>
        <TitreEcran lignes={['Suivi.']} />
        <T taille={fs[3]} couleur={c.gris} style={{ marginTop: 6 }}>Mis à jour à {dateFr(d.genere_le, 'H\\hi')}</T>
      </EnTete>
      <Contenu style={{ paddingTop: 0 }}>
        <Carte titre="Santé">
          {s ? (
            <>
              <EtatSante
                ok={s.erreurs_ouvertes === 0}
                libelle={s.erreurs_ouvertes === 0
                  ? 'Aucune erreur à traiter'
                  : `${pluriel(s.erreurs_ouvertes, 'erreur')} à traiter (${s.erreurs_serveur} serveur, ${s.plantages_app} app)`}
                onPress={() => router.navigate('/erreurs')}
              />
              {s.erreurs_nouvelles_24h > 0 ? (
                <EtatSante ok={false} libelle={`${s.erreurs_nouvelles_24h} nouvelle${s.erreurs_nouvelles_24h > 1 ? 's' : ''} erreur${s.erreurs_nouvelles_24h > 1 ? 's' : ''} en 24 h`} onPress={() => router.navigate('/erreurs')} />
              ) : null}
              {s.push_en_echec_24h !== null ? (
                <EtatSante ok={s.push_en_echec_24h === 0 && (s.push_en_attente ?? 0) === 0}
                  libelle={s.push_en_echec_24h === 0 && (s.push_en_attente ?? 0) === 0
                    ? 'Notifications envoyées normalement'
                    : `Notifications : ${s.push_en_echec_24h} en échec, ${s.push_en_attente ?? 0} en retard`} />
              ) : null}
              {s.paiements_en_attente !== null && s.paiements_en_attente > 0 ? (
                <EtatSante ok={false} libelle={`${pluriel(s.paiements_en_attente, 'paiement')} en attente depuis 24 h`} />
              ) : null}
              <EtatSante ok={(s.signalements ?? 0) === 0}
                libelle={(s.signalements ?? 0) === 0 ? 'Aucun signalement à traiter' : `${pluriel(s.signalements ?? 0, 'signalement')} à traiter`}
                onPress={(s.signalements ?? 0) > 0 ? () => router.navigate('/moderation') : undefined} />
              {(s.bde_en_attente ?? 0) > 0 ? (
                <EtatSante ok={false} libelle={`${pluriel(s.bde_en_attente ?? 0, 'BDE')} à valider`} onPress={() => router.navigate('/bde')} />
              ) : null}
            </>
          ) : <Indisponible />}
        </Carte>

        <Carte titre="Inscrits">
          {u ? (
            <>
              <Rangee>
                <Chiffre valeur={u.etudiants.toLocaleString('fr-FR')} libelle="étudiants" />
                <Chiffre valeur={u.etablissements.toLocaleString('fr-FR')} libelle="établissements" />
              </Rangee>
              <Rangee>
                <Chiffre valeur={`+${u.nouveaux.jour}`} libelle="aujourd'hui" />
                <Chiffre valeur={`+${u.nouveaux.semaine}`} libelle="7 jours" />
                <Chiffre valeur={`+${u.nouveaux.mois}`} libelle="30 jours" detail={evolution(u.nouveaux.evolution)} />
              </Rangee>
              <GraphiqueBarres points={parJour(u.serie_inscriptions)} couleur={bleu.accent} unite=" inscrits" />
              {u.record_inscriptions ? (
                <T taille={fs[3]} couleur={c.grisFonce} style={{ marginTop: 10 }}>
                  Record : <T taille={fs[3]} poids={700}>{u.record_inscriptions.n}</T> inscrits le {dateFr(u.record_inscriptions.jour, 'j M Y')}
                </T>
              ) : null}
            </>
          ) : <Indisponible />}
        </Carte>

        <Carte titre="Utilisation et heures de pointe">
          {u ? (
            <>
              <Rangee>
                <Chiffre valeur={u.actifs.jour.toLocaleString('fr-FR')} libelle="actifs aujourd'hui" />
                <Chiffre valeur={u.actifs.semaine.toLocaleString('fr-FR')} libelle="sur 7 jours" />
                <Chiffre valeur={u.actifs.mois.toLocaleString('fr-FR')} libelle="sur 30 jours" />
              </Rangee>
              <T taille={fs[3]} couleur={c.gris} style={{ marginBottom: 8 }}>Actifs en moyenne par heure, sur 14 jours</T>
              <GraphiqueBarres
                points={u.heures_de_pointe.map((h) => ({ cle: String(h.heure), valeur: h.moyenne, libelle: `${h.heure}h-${h.heure + 1}h` }))}
                couleur={bleu.accent}
                unite=" actifs"
                reperes={['0h', '6h', '12h', '18h', '23h']}
              />
              {u.pic_horaire ? (
                <T taille={fs[3]} couleur={c.grisFonce} style={{ marginTop: 10 }}>
                  Pic : <T taille={fs[3]} poids={700}>{u.pic_horaire.n}</T> personnes le {dateFr(u.pic_horaire.heure, 'D j M à H\\h')}
                </T>
              ) : null}
            </>
          ) : <Indisponible />}
        </Carte>

        <Carte titre="Événements et pass">
          {e ? (
            <>
              <Rangee>
                <Chiffre valeur={String(e.publies_mois)} libelle="publiés ce mois" />
                <Chiffre valeur={String(e.a_venir)} libelle="à venir" />
                <Chiffre valeur={String(e.annules_mois)} libelle="annulés ce mois" alerte={e.annules_mois > 0} />
              </Rangee>
              <Rangee>
                <Chiffre valeur={e.pass_mois.toLocaleString('fr-FR')} libelle="pass pris ce mois" />
                <Chiffre valeur={e.entrees_mois.toLocaleString('fr-FR')} libelle="entrées scannées" />
                <Chiffre valeur={e.taux_presence_30j === null ? '-' : `${Math.round(e.taux_presence_30j)} %`} libelle="présence (30 j)" />
              </Rangee>
              <T taille={fs[3]} couleur={c.gris} style={{ marginBottom: 8 }}>Pass pris par jour</T>
              <GraphiqueBarres points={parJour(e.serie_pass)} couleur={bleu.accent} unite=" pass" />
            </>
          ) : <Indisponible />}
        </Carte>

        <Carte titre="Abonnements">
          {ab ? (
            <>
              <Rangee>
                <Chiffre valeur={String(ab.actifs)} libelle="en cours" />
                <Chiffre valeur={`+${ab.nouveaux_mois}`} libelle="ce mois" />
                <Chiffre valeur={`-${ab.perdus_mois}`} libelle="résiliés ce mois" alerte={ab.perdus_mois > 0} />
              </Rangee>
              {ab.par_offre.length === 0 ? <T taille={fs[3]} couleur={c.gris}>Aucun abonnement en cours.</T> : ab.par_offre.map((o) => (
                <Ligne key={o.offre} libelle={`${o.libelle} · ${o.nombre}${o.essais > 0 ? ` (dont ${o.essais} en essai)` : ''}`} valeur={`${eur(o.mrr)} / mois`} />
              ))}
              <Ligne fort libelle={`MRR facturé · ${pluriel(ab.clients_payants, 'client payant')}`} valeur={eur(ab.mrr)} />
              {ab.mrr_engage !== ab.mrr ? <Ligne libelle="MRR engagé (essais compris)" valeur={eur(ab.mrr_engage)} /> : null}
            </>
          ) : <Indisponible />}
        </Carte>

        <Carte titre="Options payantes ce mois">
          {op ? (
            <>
              {op.detail.length === 0 ? <T taille={fs[3]} couleur={c.gris}>Aucune option vendue ce mois-ci.</T> : op.detail.map((o) => (
                <Ligne key={o.nature} libelle={`${o.libelle} · ${o.nombre}`} valeur={eur(o.montant)} />
              ))}
              <Ligne fort libelle="Total des options" valeur={eur(op.total_mois)} />
              <T taille={fs[2]} couleur={c.gris} style={{ marginTop: 4 }}>Mois dernier : {eur(op.total_precedent)}</T>
            </>
          ) : <Indisponible />}
        </Carte>

        <Carte titre="Ce que vous gagnez">
          {rv ? (
            <>
              <Rangee>
                <Chiffre valeur={eur(rv.mrr + rv.options_mois)} libelle="ce mois (abonnements + options)" />
                <Chiffre valeur={eur(rv.tresorerie)} libelle="trésorerie" alerte={rv.tresorerie < 0} />
              </Rangee>
              <Ligne libelle="Recettes encaissées ce mois" valeur={eur(rv.recettes_mois)} />
              <Ligne libelle="Dépenses réglées ce mois" valeur={eur(rv.depenses_mois)} />
              <Ligne fort libelle="Résultat du mois" valeur={eur(rv.resultat_mois)} />
              <T taille={fs[3]} couleur={c.gris} style={{ marginTop: 12, marginBottom: 8 }}>Recettes et options, 6 derniers mois</T>
              <GraphiqueBarres
                points={rv.serie_6_mois.map((m) => ({
                  cle: m.mois,
                  valeur: Math.round(m.recettes + m.options),
                  libelle: `${MOIS_COURTS[Number(m.mois.slice(5, 7)) - 1]} ${m.mois.slice(0, 4)}`,
                }))}
                couleur={bleu.accent}
                unite=" €"
                reperes={rv.serie_6_mois.map((m) => MOIS_COURTS[Number(m.mois.slice(5, 7)) - 1])}
              />
              <Pressable onPress={() => router.navigate('/finances')} accessibilityRole="link" style={{ marginTop: 14, flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                <T taille={fs[4]} poids={700}>Détail des finances</T>
                <Icone nom="chevron-d" taille={16} couleur={c.noir} />
              </Pressable>
            </>
          ) : <Indisponible />}
        </Carte>
      </Contenu>
    </Ecran>
  );
}
