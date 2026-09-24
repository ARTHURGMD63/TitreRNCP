/**
 * Abonnés et abonnements — abonnements.php.
 *
 * « ‹ Mon profil », le sélecteur « Abonnements · N / Abonnés · N », le titre
 * (« Ceux que / tu suis. » ou « Ceux qui / te suivent. »), la recherche, les
 * rangées de l'annuaire avec leur bouton Suivre, et « Voir plus ».
 */

import React, { useCallback, useState } from 'react';
import { Pressable, Text, TextInput, View } from 'react-native';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';

import { api, type ReponseAbonnements } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Segments } from '../../composants/Elements';
import { Icone } from '../../composants/Icone';
import { LignePersonne } from '../../composants/Social';
import { Mono, T, TitreEcran } from '../../composants/Texte';
import { useJeton } from '../../session';
import { fs, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

type Vue = 'abonnements' | 'abonnes';

export default function Abonnements() {
  const { c } = useTheme();
  const jeton = useJeton();
  const params = useLocalSearchParams<{ type?: string }>();
  const [vue, setVue] = useState<Vue>(params.type === 'abonnes' ? 'abonnes' : 'abonnements');
  const [saisie, setSaisie] = useState('');
  const [q, setQ] = useState('');
  const [page, setPage] = useState(1);
  const [donnees, setDonnees] = useState<ReponseAbonnements | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);
  const [suite, setSuite] = useState(false);

  // Ouvert depuis l'autre compteur de Moi : l'onglet suit le lien.
  const [typeVu, setTypeVu] = useState(params.type);
  if (params.type !== typeVu) {
    setTypeVu(params.type);
    if (params.type === 'abonnes' || params.type === 'abonnements') { setVue(params.type); setPage(1); }
  }

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      setDonnees(await api.abonnements(jeton, { type: vue, q, p: page }));
    } catch (e) {
      setErreur(e instanceof Error ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
      setSuite(false);
    }
  }, [jeton, vue, q, page]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  const titre = vue === 'abonnes' ? 'Abonnés' : 'Abonnements';
  const lancer = () => { setQ(saisie.trim()); setPage(1); };

  return (
    <Ecran rafraichit={rafraichit} onRafraichir={() => { setRafraichit(true); void charger(); }}>
      <EnTete style={{ paddingBottom: 10 }}>
        <Pressable onPress={() => router.navigate('/moi')} accessibilityRole="link" hitSlop={14} style={{ flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 14, alignSelf: 'flex-start' }}>
          <Icone nom="chevron-g" taille={14} couleur={c.gris} />
          <T taille={fs[4]} poids={600} couleur={c.gris}>Mon profil</T>
        </Pressable>

        <Segments
          style={{ marginBottom: 20 }}
          options={[
            { code: 'abonnements', libelle: `Abonnements · ${donnees?.nb_abonnements ?? '…'}` },
            { code: 'abonnes', libelle: `Abonnés · ${donnees?.nb_abonnes ?? '…'}` },
          ]}
          valeur={vue}
          onChange={(v) => { setVue(v as Vue); setPage(1); setSaisie(''); setQ(''); setDonnees(null); }}
        />

        <TitreEcran lignes={vue === 'abonnes' ? ['Ceux qui', 'te suivent.'] : ['Ceux que', 'tu suis.']} />
      </EnTete>

      <Contenu style={{ paddingTop: 20 }}>
        <View style={{ marginBottom: 16 }}>
          <TextInput
            value={saisie}
            onChangeText={setSaisie}
            onSubmitEditing={lancer}
            placeholder="Chercher un nom ou une école..."
            placeholderTextColor={c.gris}
            accessibilityLabel={`Chercher dans ${titre}`}
            returnKeyType="search"
            style={{ paddingVertical: 14, paddingLeft: 20, paddingRight: 52, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.pill, fontFamily: sans(400), fontSize: fs[4], backgroundColor: c.blanc, color: c.noir }}
          />
          <Pressable onPress={lancer} accessibilityLabel="Chercher" style={{ position: 'absolute', right: 14, top: 0, bottom: 0, justifyContent: 'center' }}>
            <Icone nom="loupe" taille={20} couleur={c.gris} />
          </Pressable>
        </View>

        {q && donnees ? (
          <View style={{ marginBottom: 16, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
            <Mono taille={fs[2]} poids={400} majuscules={false} espacement={0}>
              {donnees.personnes.length}{donnees.pagination.a_suivre ? '+' : ''} résultat{donnees.personnes.length > 1 ? 's' : ''}
            </Mono>
            <Text onPress={() => { setSaisie(''); setQ(''); setPage(1); }} style={{ fontFamily: sans(700), fontSize: fs[3], color: c.surRougeClair }}>Réinitialiser</Text>
          </View>
        ) : null}

        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : donnees === null ? <Chargement /> : (
          <>
            <View style={{ gap: 10 }}>
              {donnees.personnes.map((p) => <LignePersonne key={p.id} p={p} etat={p.etat_suivi} />)}
            </View>

            {donnees.personnes.length === 0 ? (
              <View style={{ paddingVertical: 24, alignItems: 'center' }}>
                {q ? (
                  <T taille={fs[3]} couleur={c.gris} style={{ textAlign: 'center' }}>Personne à ce nom dans tes {titre.toLowerCase()}.</T>
                ) : (
                  <>
                    <T taille={fs[3]} couleur={c.gris} style={{ textAlign: 'center' }}>
                      {vue === 'abonnes' ? 'Personne ne te suit encore.' : "Tu ne suis personne pour l'instant."}
                    </T>
                    <Text onPress={() => router.navigate({ pathname: '/', params: { vue: 'people' } })} style={{ fontFamily: sans(700), fontSize: fs[3], color: c.surRougeClair, textAlign: 'center' }}>
                      {vue === 'abonnes' ? 'Va te faire connaître →' : 'Trouve des étudiants à suivre →'}
                    </Text>
                  </>
                )}
              </View>
            ) : null}

            {donnees.pagination.a_suivre ? (
              <Bouton
                libelle={`Voir plus${q ? '' : ` (${donnees.pagination.restants} restants)`}`}
                variante="contour" plein chargement={suite}
                onPress={() => { setSuite(true); setPage((p) => p + 1); }}
                style={{ marginTop: 14 }}
              />
            ) : null}
          </>
        )}
      </Contenu>
    </Ecran>
  );
}
