/**
 * « Mon mois » de l'établissement : ce que la formule comprend, ce qui en est
 * consommé, et ce qui se paie en plus. Une seule présentation pour le
 * tableau de bord, la liste des événements et l'écran Abonnement, pour que le
 * même chiffre s'écrive de la même façon partout.
 *
 * Trois lignes de jauge (événements, offre flash, mise en avant offerte), puis
 * une ligne « En plus de ta formule » : un compteur, jamais un montant — aucun
 * prix de service numérique ne s'affiche dans l'application (stores, constat
 * MOB-05 de l'audit).
 */

import React from 'react';
import { View } from 'react-native';

import type { UsageMoisPartenaire } from '../api';
import { dateFr } from '../format';
import { fs, rayon } from '../theme';
import { useTheme } from '../useTheme';
import { Pastille, useRubrique } from './Design';
import { Jauge } from './Elements';
import { T } from './Texte';

function Ligne({ titre, detail, pourcentage, teinte, alerte }: {
  titre: string; detail: string; pourcentage: number; teinte: string; alerte?: boolean;
}) {
  const { c } = useTheme();
  return (
    <View style={{ gap: 6 }}>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: 8 }}>
        <T taille={fs[4]} poids={700} style={{ flexShrink: 1 }}>{titre}</T>
        <T taille={fs[4]} poids={alerte ? 700 : 400} couleur={alerte ? c.danger : c.gris}>{detail}</T>
      </View>
      <Jauge pourcentage={Math.min(100, pourcentage)} piste={c.surface2} remplissage={alerte ? c.danger : teinte} hauteur={8} />
    </View>
  );
}

export function UsageMois({ usage, offreLibelle, essaiJusquau, club }: {
  usage: UsageMoisPartenaire;
  offreLibelle?: string;
  essaiJusquau?: string | null;
  /** Un club n'a ni offre flash ni mise en avant : seules les lignes utiles s'affichent. */
  club?: boolean;
}) {
  const { c } = useTheme();
  const bleu = useRubrique('bleu');
  const { evenements: ev, flash, mises_en_avant: mea } = usage;

  const [annee, mois] = usage.mois.split('-').map(Number);
  const nomMois = dateFr(new Date(annee, (mois ?? 1) - 1, 1), 'F');
  const depasse = ev.supplementaires > 0;
  const sponsorises = mea.cout > 0 ? Math.max(1, mea.achetees) : 0;
  const enPlus = ev.supplementaires + sponsorises;

  return (
    <View style={{ backgroundColor: c.blanc, borderRadius: rayon.carte, padding: 16, gap: 16, marginBottom: 16 }}>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
        <View style={{ flexShrink: 1 }}>
          <T taille={17} poids={700}>Mon mois · {nomMois}</T>
          {offreLibelle ? (
            <T taille={fs[4]} couleur={c.gris}>
              Formule {offreLibelle}
              {essaiJusquau ? ` · offerte jusqu'au ${essaiJusquau.split('-').reverse().join('/')}` : ''}
            </T>
          ) : null}
        </View>
        {depasse ? <Pastille libelle="Quota dépassé" fond={c.dangerClair} encre={c.danger} /> : null}
      </View>

      <Ligne
        titre="Événements publiés"
        detail={`${ev.publies} / ${ev.quota}`}
        pourcentage={ev.quota > 0 ? (ev.publies / ev.quota) * 100 : 100}
        teinte={bleu.accent}
        alerte={depasse}
      />
      <T taille={fs[4]} couleur={c.gris} style={{ marginTop: -8 }}>
        {depasse
          ? `${ev.supplementaires} événement${ev.supplementaires > 1 ? 's' : ''} en supplément ce mois-ci.`
          : ev.restants > 0
            ? `Encore ${ev.restants} événement${ev.restants > 1 ? 's' : ''} compris dans ta formule ce mois-ci.`
            : 'Quota du mois atteint.'}
      </T>

      {!club && flash.quota !== 0 ? (
        <Ligne
          titre="Offre flash"
          detail={flash.quota === null ? `${flash.utilises} (illimitées)` : `${flash.utilises} / ${flash.quota}`}
          pourcentage={flash.quota ? (flash.utilises / flash.quota) * 100 : 0}
          teinte={bleu.accent}
        />
      ) : null}

      {!club && mea.offertes_droit > 0 ? (
        <Ligne
          titre="Mise en avant offerte"
          detail={`${mea.offertes_droit - mea.offertes_restantes} / ${mea.offertes_droit}`}
          pourcentage={((mea.offertes_droit - mea.offertes_restantes) / mea.offertes_droit) * 100}
          teinte={bleu.accent}
        />
      ) : null}

      <View style={{ borderTopWidth: 1, borderTopColor: c.grisClair, paddingTop: 12, gap: 4 }}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
          <T taille={fs[4]} poids={700}>En plus de ta formule ce mois-ci</T>
          <T taille={fs[4]} poids={700} couleur={enPlus > 0 ? c.danger : c.succes}>
            {enPlus > 0 ? String(enPlus) : 'Rien'}
          </T>
        </View>
        {enPlus > 0 ? (
          <T taille={fs[4]} couleur={c.gris}>
            {ev.supplementaires > 0 ? `Événements en supplément : ${ev.supplementaires}` : ''}
            {ev.supplementaires > 0 && sponsorises > 0 ? ' · ' : ''}
            {sponsorises > 0 ? `Posts sponsorisés : ${sponsorises}` : ''}
          </T>
        ) : (
          <T taille={fs[4]} couleur={c.gris}>Tout ce que tu as publié est compris dans ta formule.</T>
        )}
      </View>
    </View>
  );
}
