/**
 * Un texte légal — mentions légales, CGU, politique de confidentialité.
 *
 * Servi par l'API (api/v1/legal.php) et affiché ici, sans quitter
 * l'application : les boutiques exigent que ces textes soient lisibles depuis
 * l'app, et l'écran sert aussi bien avant la connexion (case des CGU à
 * l'inscription) qu'après (« Moi »).
 *
 * Les pages déjà lues restent en mémoire le temps de la session : rouvrir un
 * texte n'attend pas le réseau.
 */

import React, { useCallback, useState } from 'react';
import { View } from 'react-native';
import { useFocusEffect, useLocalSearchParams } from 'expo-router';

import { api } from '../../api';
import { Chargement, Contenu, Ecran, EnTete, Erreur } from '../../composants/Ecran';
import { BoutonRetour, Separateur } from '../../composants/Elements';
import { Display, Mono, T } from '../../composants/Texte';
import { fragments, type BlocLegal, type PageLegale } from '../../legal';
import { fs, lh } from '../../theme';
import { useTheme } from '../../useTheme';

const memoire = new Map<string, PageLegale>();

/** Un texte avec ses passages en gras. */
function Texte({ texte, couleur, gras }: { texte: string; couleur: string; gras: string }) {
  return (
    <T taille={fs[4]} interligne={lh.relaxed} couleur={couleur}>
      {fragments(texte).map((f, i) => (f.gras
        ? <T key={i} taille={fs[4]} poids={700} couleur={gras}>{f.texte}</T>
        : f.texte))}
    </T>
  );
}

function Bloc({ bloc }: { bloc: BlocLegal }) {
  const { c } = useTheme();

  if (bloc.type === 'liste') {
    return (
      <View style={{ marginBottom: 14, gap: 8 }}>
        {bloc.items.map((item, i) => (
          <View key={i} style={{ flexDirection: 'row', gap: 10, paddingRight: 8 }}>
            <T taille={fs[4]} couleur={c.gris}>•</T>
            <View style={{ flex: 1 }}>
              <Texte texte={item} couleur={c.grisFonce} gras={c.noir} />
            </View>
          </View>
        ))}
      </View>
    );
  }

  return (
    <View style={{ marginBottom: 14 }}>
      <Texte texte={bloc.texte} couleur={c.grisFonce} gras={c.noir} />
    </View>
  );
}

export default function PageLegaleEcran() {
  const { c } = useTheme();
  const { page } = useLocalSearchParams<{ page: string }>();
  const [doc, setDoc] = useState<PageLegale | null>(memoire.get(page) ?? null);
  const [erreur, setErreur] = useState<string | null>(null);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      const rep = await api.legal(page);
      memoire.set(page, rep.page);
      setDoc(rep.page);
    } catch (e) {
      setErreur(e instanceof Error ? e.message : 'Chargement impossible.');
    }
  }, [page]);

  useFocusEffect(useCallback(() => {
    if (!memoire.has(page)) void charger();
  }, [page, charger]));

  if (!doc) {
    return (
      <Ecran avecBarre={false}>
        <EnTete><BoutonRetour /></EnTete>
        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : <Chargement />}
      </Ecran>
    );
  }

  return (
    <Ecran avecBarre={false}>
      <EnTete>
        <BoutonRetour style={{ marginBottom: 18 }} />
        <Display taille={fs[6]}>{doc.titre}</Display>
      </EnTete>

      <Contenu>
        {doc.sections.map((section, i) => (
          <View key={i} style={{ marginTop: section.titre ? 14 : 0 }}>
            {section.titre ? (
              <T taille={fs[5]} poids={700} style={{ marginBottom: 8 }}>{section.titre}</T>
            ) : null}
            {section.blocs.map((bloc, j) => <Bloc key={j} bloc={bloc} />)}
          </View>
        ))}

        <Separateur style={{ marginTop: 24, marginBottom: 14 }} />
        {doc.pied ? <T taille={fs[2]} couleur={c.gris} interligne={lh.normal}>{doc.pied}</T> : null}
        <Mono couleur={c.gris} style={{ marginTop: 6 }}>Dernière mise à jour : {doc.maj}</Mono>
      </Contenu>
    </Ecran>
  );
}
