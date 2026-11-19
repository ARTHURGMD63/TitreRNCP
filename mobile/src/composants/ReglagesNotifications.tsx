/**
 * Les notifications push côté écran : la proposition après la connexion,
 * et le réglage de « Mon compte » (constat MOB-02).
 *
 * La permission n'était jamais demandée : aucun téléphone neuf ne recevait
 * de notification. Elle l'est désormais à deux endroits, toujours après une
 * explication et jamais à froid (voir notifications.ts) :
 *
 *  - PropositionNotifications : une feuille, une seule fois par appareil,
 *    juste après la connexion d'un étudiant ou d'un établissement ;
 *  - ReglagesNotifications : le bloc « Notifications » de Mon compte, pour
 *    activer plus tard, ouvrir les réglages du téléphone après un refus
 *    définitif, et choisir les catégories reçues (api/v1/push.php).
 */

import React, { useEffect, useState } from 'react';
import { AppState, Switch, View } from 'react-native';

import { api, ErreurApi, type CategoriePush } from '../api';
import {
  activerNotifications, etatPermission, marquerPropositionFaite, ouvrirReglagesSysteme, propositionAFaire,
} from '../notifications';
import type { EtatPermission } from '../pushRegles';
import { useJeton, useSession } from '../session';
import { fs, lh, rayon } from '../theme';
import { useTheme } from '../useTheme';
import { Bouton } from './Bouton';
import { CarreIcone } from './Design';
import { Encart } from './Elements';
import { Feuille } from './Feuille';
import { FermerFeuille, SurtitreFeuille } from './Social';
import { Display, T, TitreSection } from './Texte';

/**
 * Ce que les notifications apportent, dit de la même façon aux deux
 * endroits. Un établissement ne reçoit que les messages de son compte
 * (includes/push.php, pushCategoriesPour).
 */
function usePourquoi(): string {
  const { profil } = useSession();
  return profil?.type === 'etudiant'
    ? 'Une demande d’abonnement, une invitation, le rappel de ta soirée : on te prévient au bon moment. Tu choisis ce que tu reçois dans Mon compte.'
    : 'Les décisions qui concernent ton établissement et ton compte : on te prévient dès qu’elles tombent.';
}

/** L'état de la permission, relu au retour au premier plan (elle a pu changer dans les réglages). */
function useEtatPermission(): [EtatPermission | null, (e: EtatPermission) => void] {
  const [etat, setEtat] = useState<EtatPermission | null>(null);
  useEffect(() => {
    let actif = true;
    const relire = () => { void etatPermission().then((e) => { if (actif) setEtat(e); }); };
    relire();
    const abonnement = AppState.addEventListener('change', (a) => { if (a === 'active') relire(); });
    return () => { actif = false; abonnement.remove(); };
  }, []);
  return [etat, setEtat];
}

// ─── La proposition, après la connexion ─────────────────────────────────────

/**
 * La feuille « Reste au courant ». Montée par la racine pour un compte
 * connecté ; `pret` attend la fin de l'animation d'arrivée. Elle ne
 * s'ouvre que si la permission n'a jamais été demandée et que la feuille
 * n'a jamais été montrée sur ce téléphone ; quelle que soit la réponse,
 * elle ne revient pas.
 */
export function PropositionNotifications({ pret }: { pret: boolean }) {
  const { c } = useTheme();
  const jeton = useJeton();
  const pourquoi = usePourquoi();
  const [visible, setVisible] = useState(false);
  const [enCours, setEnCours] = useState(false);

  useEffect(() => {
    if (!pret) return;
    let actif = true;
    // Un court délai : la personne voit d'abord son écran d'accueil.
    const minuterie = setTimeout(() => {
      void propositionAFaire().then((oui) => { if (actif && oui) setVisible(true); });
    }, 1200);
    return () => { actif = false; clearTimeout(minuterie); };
  }, [pret]);

  function fermer() {
    setVisible(false);
    void marquerPropositionFaite();
  }

  async function activer() {
    setEnCours(true);
    try {
      // La feuille est marquée AVANT la question du système : même si
      // l'application est fermée pendant la demande, elle ne revient pas.
      await marquerPropositionFaite();
      await activerNotifications(jeton);
    } finally {
      setEnCours(false);
      setVisible(false);
    }
  }

  return (
    <Feuille visible={visible} onClose={fermer}>
      <View style={{ flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12, marginBottom: 16 }}>
        <View style={{ flex: 1 }}>
          <SurtitreFeuille>Notifications</SurtitreFeuille>
          <Display taille={fs[6]}>Reste au courant</Display>
        </View>
        <FermerFeuille onPress={fermer} />
      </View>
      <View style={{ flexDirection: 'row', alignItems: 'flex-start', gap: 14, marginBottom: 24 }}>
        <CarreIcone icone="cloche" fond={c.surface2} encre={c.noir} />
        <T taille={fs[4]} couleur={c.grisFonce} interligne={lh.normal} style={{ flex: 1 }}>{pourquoi}</T>
      </View>
      <Bouton libelle="Activer les notifications" icone="cloche" plein chargement={enCours} onPress={() => void activer()} />
      <Bouton libelle="Plus tard" variante="texte" plein onPress={fermer} style={{ marginTop: 8 }} />
    </Feuille>
  );
}

// ─── Le réglage, dans Mon compte ────────────────────────────────────────────

export function ReglagesNotifications() {
  const { c } = useTheme();
  const jeton = useJeton();
  const pourquoi = usePourquoi();
  const [etat, setEtat] = useEtatPermission();
  const [activation, setActivation] = useState(false);
  const [categories, setCategories] = useState<CategoriePush[] | null>(null);
  const [enregistre, setEnregistre] = useState<string | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);

  // Les catégories (api/v1/push.php, pushReglages) ne servent qu'une fois
  // la permission donnée.
  useEffect(() => {
    if (etat !== 'accordee' || categories !== null) return;
    let actif = true;
    api.pushReglages(jeton)
      .then((r) => { if (actif) setCategories(r.categories); })
      // Sans les catégories, le reste du bloc fonctionne : on n'affiche rien de plus.
      .catch(() => { if (actif) setCategories([]); });
    return () => { actif = false; };
  }, [etat, categories, jeton]);

  async function activer() {
    setActivation(true);
    try {
      setEtat(await activerNotifications(jeton));
    } finally {
      setActivation(false);
    }
  }

  async function basculer(cat: CategoriePush, actif: boolean) {
    setErreur(null);
    setEnregistre(cat.code);
    // L'interrupteur suit le doigt tout de suite ; le serveur a le dernier mot.
    setCategories((liste) => liste?.map((x) => (x.code === cat.code ? { ...x, actif } : x)) ?? null);
    try {
      setCategories((await api.pushPreference(jeton, cat.code, actif)).categories);
    } catch (e) {
      setCategories((liste) => liste?.map((x) => (x.code === cat.code ? { ...x, actif: !actif } : x)) ?? null);
      setErreur(e instanceof ErreurApi ? e.message : 'Réglage non enregistré, réessaie dans un instant.');
    } finally {
      setEnregistre(null);
    }
  }

  return (
    <View style={{ backgroundColor: c.blanc, borderRadius: rayon.carte, padding: 14, marginBottom: 16 }}>
      <TitreSection style={{ marginBottom: 6 }}>Notifications</TitreSection>

      {etat === null ? null : etat === 'indisponible' ? (
        <T taille={fs[4]} couleur={c.gris} interligne={lh.normal}>
          Les notifications ne sont pas disponibles sur cet appareil.
        </T>
      ) : etat === 'a_demander' ? (
        <>
          <T taille={fs[4]} couleur={c.gris} interligne={lh.normal} style={{ marginBottom: 14 }}>{pourquoi}</T>
          <Bouton libelle="Activer les notifications" icone="cloche" plein chargement={activation} onPress={() => void activer()} />
        </>
      ) : etat === 'refusee' ? (
        <>
          <T taille={fs[4]} couleur={c.gris} interligne={lh.normal} style={{ marginBottom: 14 }}>
            Les notifications sont coupées pour Outsee. Tu peux les rétablir dans les réglages de ton téléphone.
          </T>
          <Bouton libelle="Ouvrir les réglages" icone="reglages" variante="contour" plein onPress={ouvrirReglagesSysteme} />
        </>
      ) : (
        <>
          <T taille={fs[4]} couleur={c.gris} interligne={lh.normal} style={{ marginBottom: categories && categories.length > 0 ? 8 : 0 }}>
            Activées sur ce téléphone.{categories && categories.length > 0 ? ' Choisis ce que tu reçois :' : ''}
          </T>
          {erreur ? <Encart genre="erreur" style={{ marginTop: 8 }}>{erreur}</Encart> : null}
          {(categories ?? []).map((cat, i) => (
            <View
              key={cat.code}
              style={{ flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12, borderTopWidth: i === 0 ? 0 : 1, borderTopColor: c.grisClair }}
            >
              <View style={{ flex: 1 }}>
                <T taille={fs[4]} poids={600}>{cat.libelle}</T>
                <T taille={fs[3]} couleur={c.gris} interligne={lh.normal}>{cat.detail}</T>
              </View>
              <Switch
                value={cat.actif}
                disabled={!cat.modifiable || enregistre === cat.code}
                onValueChange={(v) => void basculer(cat, v)}
                trackColor={{ false: c.line2, true: c.rouge }}
                thumbColor="#FFFFFF"
                accessibilityLabel={cat.libelle}
              />
            </View>
          ))}
        </>
      )}
    </View>
  );
}
