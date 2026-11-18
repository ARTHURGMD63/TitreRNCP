/**
 * Le déroulé d'une mise en avant : quand elle a été lancée, quand elle se
 * termine, et un compte à rebours qui tourne.
 *
 * Le temps restant vient du serveur (`restantS`) : les dates de l'API sont des
 * heures de Paris sans fuseau, qu'un téléphone réglé ailleurs lirait de
 * travers. L'application ne fait que décompter à partir de cette valeur, par
 * l'horloge du téléphone (un écart de réglage n'a donc aucun effet).
 */

import React, { useEffect, useState } from 'react';
import { View } from 'react-native';

import { dateFr } from '../format';
import { fs } from '../theme';
import { Jauge } from './Elements';
import { T } from './Texte';

const deux = (n: number) => (n < 10 ? `0${n}` : String(n));

/** « 1 j 23 h 05 min 12 s », « 4 h 12 min 03 s », « 08 min 30 s ». */
export function formaterDuree(secondes: number): string {
  const s = Math.max(0, Math.floor(secondes));
  const j = Math.floor(s / 86400);
  const h = Math.floor((s % 86400) / 3600);
  const m = Math.floor((s % 3600) / 60);
  return `${j > 0 ? `${j} j ` : ''}${j > 0 || h > 0 ? `${h} h ` : ''}${deux(m)} min ${deux(s % 60)} s`;
}

/**
 * Les secondes qui restent, mises à jour chaque seconde. S'arrête à zéro.
 * `restantS` null : pas de décompte (mise en avant sans borne).
 */
export function useSecondesRestantes(restantS: number | null): number | null {
  // `base` rappelle de quelle valeur du serveur part le décompte : quand l'écran
  // se recharge et reçoit un nouveau `restantS`, le temps écoulé repart de zéro
  // sans qu'aucun état ne soit écrit directement depuis l'effet.
  const [suivi, setSuivi] = useState<{ base: number | null; ecoule: number }>({ base: restantS, ecoule: 0 });

  useEffect(() => {
    if (restantS === null) return undefined;
    const debut = Date.now();
    const t = setInterval(() => setSuivi({ base: restantS, ecoule: Math.round((Date.now() - debut) / 1000) }), 1000);
    return () => clearInterval(t);
  }, [restantS]);

  if (restantS === null) return null;
  const ecoule = suivi.base === restantS ? suivi.ecoule : 0;
  return Math.max(0, restantS - ecoule);
}

/** Une ligne : « reste 1 j 23 h 05 min 12 s », à mettre dans une liste. */
export function ResteMiseEnAvant({ restantS, couleur }: { restantS: number | null; couleur?: string }) {
  const reste = useSecondesRestantes(restantS);
  if (reste === null) return null;
  return (
    <T taille={fs[4]} couleur={couleur} poids={600}>
      {reste > 0 ? `Reste ${formaterDuree(reste)}` : 'Terminée'}
    </T>
  );
}

/** Le bloc complet : lancée le…, se termine le…, temps restant, avancement. */
export function ChronoMiseEnAvant({ debut, jusquau, restantS, dureeS, enCours, couleur, piste, remplissage }: {
  debut: string | null;
  jusquau: string | null;
  restantS: number | null;
  dureeS: number | null;
  enCours: boolean;
  couleur?: string;
  piste: string;
  remplissage: string;
}) {
  const reste = useSecondesRestantes(enCours ? restantS : 0);
  const fini = !enCours || (reste !== null && reste <= 0);
  const pct = dureeS && reste !== null ? Math.max(0, Math.min(100, ((dureeS - reste) / dureeS) * 100)) : null;

  return (
    <View style={{ gap: 4 }}>
      {debut ? (
        <T taille={fs[4]} couleur={couleur}>Lancée le {dateFr(debut, 'l j F · H\\hi')}</T>
      ) : null}
      {jusquau ? (
        <T taille={fs[4]} couleur={couleur}>
          {fini ? 'Terminée le ' : 'Se termine le '}{dateFr(jusquau, 'l j F · H\\hi')}
        </T>
      ) : null}
      {!fini && reste !== null ? (
        <T taille={fs[2]} poids={700} couleur={couleur} style={{ marginTop: 2 }}>
          Temps restant : {formaterDuree(reste)}
        </T>
      ) : null}
      {!fini && pct !== null ? <Jauge pourcentage={pct} piste={piste} remplissage={remplissage} hauteur={8} /> : null}
    </View>
  );
}
