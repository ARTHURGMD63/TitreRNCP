/**
 * Historique de mes sorties — ouvert depuis l'onglet Pass et depuis Moi.
 *
 * Il vivait en bas de l'onglet Pass et l'allongeait à chaque sortie. Il est
 * rangé ici par mois, le plus récent ouvert, les autres repliés. Les données
 * sont celles de wallet.php (historique paginé : « Voir plus » charge la
 * page suivante, cumulée).
 */

import React, { useCallback, useState } from 'react';
import { Pressable, View } from 'react-native';
import { useFocusEffect } from 'expo-router';

import { api, ErreurApi, type Pass } from '../api';
import { Bouton } from '../composants/Bouton';
import { Chargement, Contenu, Ecran, EnTete, Erreur } from '../composants/Ecran';
import { BoutonRetour } from '../composants/Elements';
import { Icone } from '../composants/Icone';
import { LigneHistorique } from '../composants/LignesPass';
import { Display, T } from '../composants/Texte';
import { parMois } from '../regroupement';
import { useJeton } from '../session';
import { fs, lh, rayon } from '../theme';
import { useTheme } from '../useTheme';

export default function Historique() {
  const { c } = useTheme();
  const jeton = useJeton();

  const [page, setPage] = useState(1);
  const [historique, setHistorique] = useState<Pass[] | null>(null);
  const [aSuivre, setASuivre] = useState(false);
  const [erreur, setErreur] = useState<string | null>(null);
  const [suite, setSuite] = useState(false);
  const [ouverts, setOuverts] = useState<Record<string, boolean>>({});

  const charger = useCallback(async (p: number) => {
    try {
      setErreur(null);
      const rep = await api.wallet(jeton, p);
      setHistorique(rep.historique);
      setASuivre(rep.pagination.a_suivre);
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Chargement impossible.');
    } finally {
      setSuite(false);
    }
  }, [jeton]);

  useFocusEffect(useCallback(() => { void charger(page); }, [charger, page]));

  const mois = parMois(historique ?? []);

  return (
    <Ecran avecBarre={false}>
      <EnTete>
        <BoutonRetour style={{ marginBottom: 18 }} />
        <Display taille={fs[8]}>Mes{'\n'}sorties.</Display>
        <T taille={fs[4]} couleur={c.gris} interligne={lh.snug} style={{ marginTop: 10 }}>
          Tes pass passés, mois par mois. Après un check-in, tu peux laisser un avis.
        </T>
      </EnTete>

      <Contenu>
        {erreur ? (
          <Erreur message={erreur} onReessayer={() => charger(page)} />
        ) : historique === null ? (
          <Chargement />
        ) : mois.length === 0 ? (
          <View style={{ backgroundColor: c.blanc, borderRadius: rayon.carte, padding: 18 }}>
            <T taille={fs[4]} poids={600}>Pas encore de sortie passée.</T>
            <T taille={fs[3]} couleur={c.gris} interligne={lh.snug} style={{ marginTop: 6 }}>
              Tes pass apparaîtront ici une fois l&apos;événement passé.
            </T>
          </View>
        ) : (
          <View style={{ gap: 10 }}>
            {mois.map((g, i) => {
              const ouvert = ouverts[g.cle] ?? i === 0;
              const checkins = g.elements.filter((p) => p.statut === 'checkin').length;
              return (
                <View key={g.cle} style={{ backgroundColor: c.blanc, borderRadius: rayon.tuile, overflow: 'hidden' }}>
                  <Pressable
                    onPress={() => setOuverts((o) => ({ ...o, [g.cle]: !ouvert }))}
                    accessibilityRole="button"
                    accessibilityState={{ expanded: ouvert }}
                    accessibilityLabel={`${g.libelle}, ${g.elements.length} sortie${g.elements.length > 1 ? 's' : ''}`}
                    style={{ minHeight: 48, paddingHorizontal: 14, paddingVertical: 12, flexDirection: 'row', alignItems: 'center', gap: 10 }}
                  >
                    <T taille={fs[4]} poids={700} style={{ flex: 1 }}>{g.libelle}</T>
                    <T taille={fs[3]} couleur={c.gris}>
                      {g.elements.length} sortie{g.elements.length > 1 ? 's' : ''}{checkins ? ` · ${checkins} check-in` : ''}
                    </T>
                    <View style={{ transform: [{ rotate: ouvert ? '180deg' : '0deg' }] }}>
                      <Icone nom="chevron-b" taille={18} couleur={c.gris} />
                    </View>
                  </Pressable>
                  {ouvert ? (
                    <View style={{ paddingHorizontal: 14 }}>
                      {/* Chaque ligne, la première comprise, est séparée de ce qui la précède (l'en-tête du mois). */}
                      {g.elements.map((p) => (
                        <LigneHistorique key={`${p.genre ?? 'soiree'}-${p.id}`} p={p} separateur />
                      ))}
                    </View>
                  ) : null}
                </View>
              );
            })}
            {aSuivre ? (
              <Bouton libelle="Voir des sorties plus anciennes" variante="contour" plein chargement={suite}
                onPress={() => { setSuite(true); setPage((p) => p + 1); }} style={{ marginTop: 6 }} />
            ) : null}
          </View>
        )}
      </Contenu>
    </Ecran>
  );
}
