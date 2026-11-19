/**
 * Ce que l'onglet Pass et l'écran Historique partagent : l'économie d'un pass
 * et la ligne d'une sortie passée (check-in, « Laisser un avis »).
 */

import React from 'react';
import { Text, View } from 'react-native';
import { router } from 'expo-router';

import type { Pass } from '../api';
import { dateFr } from '../format';
import { formatEuros } from '../regles';
import { fs, sans } from '../theme';
import { useTheme } from '../useTheme';
import { CarreIcone } from './Design';
import { T } from './Texte';

/** « -4,50 € », « Gratuit », ou rien : ce que le pass a fait économiser. */
export function economiePass(p: Pass): string {
  const g = p.genre ?? 'soiree';
  if (g === 'culture' || g === 'partie') return '';
  if ((p.reduction ?? 0) > 0 && (p.prix_normal ?? 0) > 0) {
    return '-' + formatEuros((p.prix_normal! * p.reduction!) / 100);
  }
  // Payant sans réduction : rien d'économisé, mais ce n'est pas gratuit.
  if (!p.is_gratuit && (p.prix_normal ?? 0) > 0) return '';
  return 'Gratuit';
}

/**
 * Une sortie passée, sans fond propre : elle se pose dans le bloc de son mois.
 * Un passage validé (check-in) est mis en avant et propose « Laisser un avis ».
 */
export function LigneHistorique({ p, separateur }: { p: Pass; separateur: boolean }) {
  const { c } = useTheme();
  const avis = p.statut === 'checkin';
  return (
    <View style={{ paddingVertical: 12, flexDirection: 'row', alignItems: 'center', gap: 12, borderTopWidth: separateur ? 1 : 0, borderTopColor: c.grisClair }}>
      <View style={{ opacity: avis ? 1 : 0.6 }}>
        <CarreIcone icone={avis ? 'check' : 'ticket'} fond={avis ? c.succesClair : c.surface2} encre={avis ? c.succes : c.gris} taille={36} />
      </View>
      <View style={{ flex: 1, opacity: avis ? 1 : 0.6 }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
          <T taille={fs[4]} poids={700} numberOfLines={1} style={{ flexShrink: 1 }}>{p.etablissement}</T>
          {avis ? <T taille={fs[2]} poids={700} couleur={c.succes}>Check-in</T> : null}
        </View>
        <T taille={fs[3]} couleur={c.gris} numberOfLines={1} style={{ marginTop: 2 }}>{dateFr(p.date_heure, 'D j')} · {p.titre}</T>
        {avis && p.evenement_id ? (
          <Text
            onPress={() => router.push({ pathname: '/avis/[id]', params: { id: String(p.evenement_id) } })}
            accessibilityRole="link"
            style={{ marginTop: 2, paddingVertical: 6, fontFamily: sans(700), fontSize: fs[3], color: c.surRougeClair }}
          >
            Laisser un avis
          </Text>
        ) : null}
      </View>
      <T taille={fs[3]} poids={600} couleur={c.gris}>{economiePass(p)}</T>
    </View>
  );
}
