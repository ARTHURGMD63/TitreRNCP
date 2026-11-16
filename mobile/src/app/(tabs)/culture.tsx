/**
 * L'espace Culture — les événements mis en avant par la ville.
 *
 * Expositions, concerts, patrimoine : ce que la ville propose, à côté des
 * soirées des établissements. Même lecture que le hub — chips de catégorie,
 * cartes (la première en violet), « Voir plus » — et la même invitation
 * d'un ami. Un onglet à part entière, accent violet depuis la refonte.
 */

import React, { useCallback, useEffect, useState } from 'react';
import { ScrollView, View } from 'react-native';
import { router } from 'expo-router';

import { api, type CategorieCulture, type EvenementCulture } from '../../api';
import { Apparition } from '../../composants/Animations';
import { Bouton } from '../../composants/Bouton';
import { EnTeteApp, rangeeChips, useRubrique } from '../../composants/Design';
import { CarteCulture } from '../../composants/CarteCulture';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Pilule } from '../../composants/Elements';
import { Icone, type NomIcone } from '../../composants/Icone';
import { FeuilleInvitation } from '../../composants/Social';
import { Display, T, TitreEcran } from '../../composants/Texte';
import { useJeton } from '../../session';
import { couleursCategorie, fs, lh, rayon } from '../../theme';
import { useTheme } from '../../useTheme';

/** L'icône du petit carré de chaque catégorie. */
const ICONES_CATEGORIE: Record<string, NomIcone> = {
  expo: 'image', concert: 'micro', spectacle: 'ticket', patrimoine: 'musee', festival: 'musique',
  cinema: 'appareil', atelier: 'crayon', conference: 'micro', autre: 'etoile-fine',
};
const COULEUR_REPLI = '#8B5CF6';

export default function EspaceCulture() {
  const { c } = useTheme();
  const violet = useRubrique('culture');
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
        {/* Un onglet à part entière, comme Personnes ou Sport : la marque en
            tête, pas de retour vers Explorer. */}
        <Apparition index={0}>
          <EnTeteApp rubrique="culture" onNotifications={() => router.navigate('/notifications')} />
        </Apparition>
        <Apparition index={1}>
          <TitreEcran avant="La culture" surligne="en ville." rubrique="culture" style={{ marginBottom: 12 }} />
          <T taille={fs[4]} couleur={c.gris} interligne={lh.normal} style={{ marginBottom: 16 }}>
            Expos, concerts, patrimoine : ce que la ville met en avant. Inscris-toi et embarque tes potes.
          </T>
        </Apparition>
      </EnTete>

      <Apparition index={2}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ flexGrow: 0, marginBottom: 16 }} contentContainerStyle={rangeeChips}>
          <Pilule libelle="Tout" actif={categorie === ''} onPress={() => choisir('')} />
          {categories.map((k) => (
            <Pilule key={k.code} libelle={k.libelle} actif={categorie === k.code} onPress={() => choisir(k.code)}
              icone={ICONES_CATEGORIE[k.code] ?? 'musee'} carre={couleursCategorie[k.code] ?? COULEUR_REPLI} />
          ))}
        </ScrollView>
      </Apparition>

      <Contenu>
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : evenements === null ? <Chargement /> : (
          <>
            {evenements.length === 0 ? (
              <View style={{ backgroundColor: c.blanc, borderRadius: rayon.carte, paddingVertical: 36, paddingHorizontal: 26, alignItems: 'center', marginBottom: 16 }}>
                <View style={{ marginBottom: 14 }}><Icone nom="musee" taille={24} couleur={c.gris} /></View>
                <Display taille={fs[5]} interligne={lh.tight} style={{ marginBottom: 6, textAlign: 'center' }}>
                  {categorie ? 'Rien dans cette catégorie' : 'Rien de prévu pour le moment'}
                </Display>
                <T taille={fs[3]} poids={500} couleur={c.grisFonce} interligne={lh.snug} style={{ textAlign: 'center', maxWidth: 290 }}>
                  {categorie
                    ? 'Aucun événement de ce type à venir. Essaie une autre catégorie.'
                    : "La ville n'a pas encore annoncé ses prochains rendez-vous. Reviens d'ici quelques jours."}
                </T>
                {categorie ? <Bouton libelle="Tout voir" accent={violet.accent} onPress={() => choisir('')} style={{ marginTop: 20, alignSelf: 'center' }} /> : null}
              </View>
            ) : null}

            {evenements.map((e, i) => (
              <Apparition key={e.id} index={3 + Math.min(i, 3)}>
                <CarteCulture ev={e} accent={i === 0} onInviter={() => setInvitation({ type: 'culture', id: e.id, nom: e.titre })} />
              </Apparition>
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
