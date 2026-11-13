/**
 * L'espace Culture — les événements mis en avant par la ville.
 *
 * Expositions, concerts, patrimoine : ce que la ville propose, à côté des
 * soirées des établissements. Même lecture que le hub — filtres en pilules,
 * cartes, « Voir plus » — et la même invitation d'un ami. Ouvert depuis la
 * pilule « Culture » d'Explorer ; la barre allume Explorer (BarreOnglets,
 * PARENT), comme pour la carte.
 */

import React, { useCallback, useEffect, useState } from 'react';
import { ScrollView, View } from 'react-native';

import { api, type CategorieCulture, type EvenementCulture } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { CarteCulture } from '../../composants/CarteCulture';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { BoutonRetour, Pilule } from '../../composants/Elements';
import { Icone } from '../../composants/Icone';
import { FeuilleInvitation } from '../../composants/Social';
import { Display, T, TitreEcran } from '../../composants/Texte';
import { useJeton } from '../../session';
import { fs, gutter, lh, rayon } from '../../theme';
import { useTheme } from '../../useTheme';

export default function EspaceCulture() {
  const { c, ombre } = useTheme();
  const jeton = useJeton();

  const [categorie, setCategorie] = useState('');
  const [page, setPage] = useState(1);
  const [evenements, setEvenements] = useState<EvenementCulture[] | null>(null);
  const [categories, setCategories] = useState<CategorieCulture[]>([]);
  const [reste, setReste] = useState(false);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);
  const [chargeSuite, setChargeSuite] = useState(false);
  const [invitation, setInvitation] = useState<{ type: 'culture'; id: number; nom: string } | null>(null);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      const r = await api.culture(jeton, { categorie, pe: page });
      setCategories(r.filtres.categories);
      // La pagination est cumulative : la réponse remplace la liste.
      setEvenements(r.evenements);
      setReste(r.pagination.a_suivre);
    } catch (e) {
      setErreur(e instanceof Error ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
      setChargeSuite(false);
    }
  }, [jeton, categorie, page]);

  useEffect(() => {
    // Les écritures d'état de charger() suivent l'attente réseau.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void charger();
  }, [charger]);

  const choisir = (code: string) => { setCategorie(code); setPage(1); setEvenements(null); };

  return (
    <Ecran
      rafraichit={rafraichit}
      onRafraichir={() => { setRafraichit(true); void charger(); }}
      horsDefilement={<FeuilleInvitation cible={invitation} onClose={() => setInvitation(null)} />}
    >
      <EnTete style={{ paddingBottom: 4 }}>
        <View style={{ flexDirection: 'row', alignItems: 'flex-start', gap: 14, marginBottom: 12 }}>
          <BoutonRetour vers="/" libelle="Retour à Explorer" />
          <View style={{ flex: 1 }}>
            <TitreEcran lignes={['La culture', 'en ville.']} />
          </View>
        </View>
        <T taille={fs[4]} couleur={c.grisFonce} interligne={lh.snug} style={{ marginBottom: 16 }}>
          Expos, concerts, patrimoine : ce que la ville met en avant. Inscris-toi et embarque tes potes.
        </T>
      </EnTete>

      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ flexGrow: 0, marginBottom: 8 }} contentContainerStyle={{ gap: 8, paddingHorizontal: gutter, paddingBottom: 16 }}>
        <Pilule libelle="Tout" icone="musee" actif={categorie === ''} onPress={() => choisir('')} />
        {categories.map((k) => (
          <Pilule key={k.code} libelle={k.libelle} actif={categorie === k.code} onPress={() => choisir(k.code)} />
        ))}
      </ScrollView>

      <Contenu>
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : evenements === null ? <Chargement /> : (
          <>
            {evenements.length === 0 ? (
              <View style={[{ backgroundColor: c.blanc, borderRadius: rayon.base, borderWidth: 1, borderColor: c.grisClair, paddingVertical: 36, paddingHorizontal: 26, alignItems: 'center', marginBottom: 14 }, ombre('sm')]}>
                <View style={{ marginBottom: 14 }}><Icone nom="musee" taille={24} couleur={c.gris} /></View>
                <Display taille={fs[6]} interligne={lh.tight} style={{ marginBottom: 6, textAlign: 'center' }}>
                  {categorie ? 'Rien dans cette catégorie' : 'Rien de prévu pour le moment'}
                </Display>
                <T taille={fs[3]} poids={500} couleur={c.grisFonce} interligne={lh.snug} style={{ textAlign: 'center', maxWidth: 290 }}>
                  {categorie
                    ? 'Aucun événement de ce type à venir. Essaie une autre catégorie.'
                    : "La ville n'a pas encore annoncé ses prochains rendez-vous. Reviens d'ici quelques jours."}
                </T>
                {categorie ? <Bouton libelle="Tout voir" onPress={() => choisir('')} style={{ marginTop: 20, alignSelf: 'center' }} /> : null}
              </View>
            ) : null}

            {evenements.map((e) => (
              <CarteCulture key={e.id} ev={e} onInviter={() => setInvitation({ type: 'culture', id: e.id, nom: e.titre })} />
            ))}

            {reste ? (
              <Bouton libelle="Voir plus d'événements" variante="contour" plein chargement={chargeSuite} onPress={() => { setChargeSuite(true); setPage((p) => p + 1); }} style={{ marginTop: 4 }} />
            ) : null}
          </>
        )}
      </Contenu>
    </Ecran>
  );
}
