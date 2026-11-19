/**
 * Un petit graphique en barres, une seule série, pour le suivi admin.
 *
 * Une série : pas de légende, le titre de la carte la nomme. Barres fines,
 * bout arrondi côté valeur, 2 px d'écart entre elles, grille absente.
 * Toucher une barre affiche sa valeur et son libellé au-dessus du graphique
 * (l'équivalent tactile d'une infobulle) ; la plus haute est rappelée en
 * permanence, pour que le chiffre clé se lise sans toucher. Le texte reste
 * dans l'encre du thème, jamais dans la couleur des barres.
 */

import React, { useState } from 'react';
import { Pressable, View } from 'react-native';

import { T } from './Texte';
import { fs } from '../theme';
import { useTheme } from '../useTheme';

export type PointBarre = { cle: string; valeur: number; libelle: string };

export function GraphiqueBarres({ points, couleur, hauteur = 96, unite = '', reperes }: {
  points: PointBarre[];
  couleur: string;
  hauteur?: number;
  unite?: string;
  /** Libellés sous l'axe : premier, milieu, dernier par défaut. */
  reperes?: string[];
}) {
  const { c } = useTheme();
  const [choisi, setChoisi] = useState<number | null>(null);
  const max = Math.max(1, ...points.map((p) => p.valeur));
  const indexMax = points.reduce((m, p, i) => (p.valeur > points[m].valeur ? i : m), 0);
  const actif = choisi ?? indexMax;
  const point = points[actif];
  const sousAxe = reperes ?? (points.length > 0
    ? [points[0].libelle, points[Math.floor(points.length / 2)].libelle, points[points.length - 1].libelle]
    : []);

  if (points.length === 0) {
    return <T taille={fs[3]} couleur={c.gris}>Pas encore de données.</T>;
  }

  return (
    <View>
      <T taille={fs[3]} couleur={c.grisFonce} style={{ marginBottom: 8 }}>
        {choisi === null && points[indexMax].valeur === 0 ? 'Rien sur la période' : null}
        {choisi === null && points[indexMax].valeur === 0 ? null : <>
        {choisi === null ? 'Au plus haut : ' : ''}
        <T taille={fs[3]} poids={700}>{point.valeur.toLocaleString('fr-FR')}{unite}</T>
        {' · '}{point.libelle}
        </>}
      </T>
      <View
        accessibilityRole="image"
        accessibilityLabel={`Graphique, au plus haut ${points[indexMax].valeur}${unite} (${points[indexMax].libelle})`}
        style={{ flexDirection: 'row', alignItems: 'flex-end', height: hauteur, gap: 2 }}
      >
        {points.map((p, i) => (
          <Pressable
            key={p.cle}
            onPress={() => setChoisi(choisi === i ? null : i)}
            accessibilityLabel={`${p.libelle} : ${p.valeur}${unite}`}
            hitSlop={{ top: 8, bottom: 8 }}
            style={{ flex: 1, height: '100%', justifyContent: 'flex-end' }}
          >
            <View
              style={{
                height: Math.max(p.valeur > 0 ? 3 : 1, (p.valeur / max) * hauteur),
                backgroundColor: p.valeur > 0 ? couleur : c.grisClair,
                opacity: i === actif || choisi === null ? 1 : 0.45,
                borderTopLeftRadius: 4,
                borderTopRightRadius: 4,
              }}
            />
          </Pressable>
        ))}
      </View>
      <View style={{ height: 1, backgroundColor: c.grisClair }} />
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginTop: 6 }}>
        {sousAxe.map((r, i) => <T key={`${r}-${i}`} taille={fs[2]} couleur={c.gris}>{r}</T>)}
      </View>
    </View>
  );
}
