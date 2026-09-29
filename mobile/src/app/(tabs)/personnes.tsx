/**
 * Personnes — explore.php?view=people, désormais son propre onglet plutôt
 * qu'un sélecteur Événements / Personnes au sommet d'Explorer : un aller-
 * retour de plus dans la barre du bas, mais deux écrans qu'on ne confond
 * plus et qu'on retrouve chacun directement.
 *
 * Recherche, filtres école et intérêt (dont « Comme moi »), suggestions,
 * annuaire, « Voir plus de profils ». Un lien « Trouve des étudiants » ou
 * une étiquette #intérêt du profil ouvre directement cet onglet avec le
 * bon filtre, comme avant avec ?view=people&interest=…
 */

import React, { useCallback, useState } from 'react';
import { Pressable, ScrollView, TextInput, View } from 'react-native';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';

import { api, type ReponsePersonnes } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Separateur } from '../../composants/Elements';
import { Selecteur } from '../../composants/Formulaire';
import { Icone } from '../../composants/Icone';
import { Marque } from '../../composants/Marque';
import { CarteSuggestion, LignePersonne } from '../../composants/Social';
import { Mono, T, TitreEcran } from '../../composants/Texte';
import { useJeton } from '../../session';
import { fs, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

export default function Personnes() {
  const { c, ombre } = useTheme();
  const jeton = useJeton();
  const params = useLocalSearchParams<{ interest?: string }>();

  const [rechercheOuverte, setRechercheOuverte] = useState(false);
  const [saisie, setSaisie] = useState('');
  const [q, setQ] = useState('');

  const [ecole, setEcole] = useState('');
  const [interet, setInteret] = useState(params.interest ?? '');
  const [page, setPage] = useState(1);
  const [annuaire, setAnnuaire] = useState<ReponsePersonnes | null>(null);

  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);
  const [chargeSuite, setChargeSuite] = useState(false);

  // Une étiquette #intérêt tapée depuis un autre écran change de cible sans
  // remonter le composant (même onglet, juste un nouveau paramètre) : sans
  // ce suivi, le second tap sur une étiquette différente n'avait aucun effet.
  const [interetVu, setInteretVu] = useState(params.interest);
  if (params.interest !== interetVu) {
    setInteretVu(params.interest);
    if (params.interest !== undefined) { setInteret(params.interest); setPage(1); }
  }

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      setAnnuaire(await api.personnes(jeton, { q, ecole, interest: interet, p: page }));
    } catch (e) {
      setErreur(e instanceof Error ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
      setChargeSuite(false);
    }
  }, [jeton, q, ecole, interet, page]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  const f = annuaire?.filtres;
  const filtreInteretActif = interet !== '';

  return (
    <Ecran rafraichit={rafraichit} onRafraichir={() => { setRafraichit(true); void charger(); }}>
      <EnTete style={{ paddingBottom: 10 }}>
        <View style={{ marginBottom: 14 }}>
          <Marque taille={fs[6]} />
        </View>
        <View style={{ flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12, marginBottom: 16 }}>
          <View style={{ flex: 1 }}>
            <TitreEcran lignes={['Trouve tes', 'futurs potes.']} />
          </View>
          <Pressable
            onPress={() => setRechercheOuverte((o) => !o)}
            accessibilityRole="button"
            accessibilityLabel="Rechercher"
            accessibilityState={{ expanded: rechercheOuverte }}
            style={[{ width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center', backgroundColor: c.blanc, borderWidth: 1, borderColor: c.line2, marginTop: -6 }, ombre('sm')]}
          >
            <Icone nom="loupe" taille={20} couleur={c.noir} />
          </Pressable>
        </View>

        {rechercheOuverte ? (
          <View style={{ marginBottom: 16 }}>
            <TextInput
              autoFocus
              value={saisie}
              onChangeText={setSaisie}
              onSubmitEditing={() => { setQ(saisie.trim()); setPage(1); }}
              placeholder="Chercher un nom ou une passion..."
              placeholderTextColor={c.gris}
              returnKeyType="search"
              style={{ paddingVertical: 14, paddingLeft: 20, paddingRight: 52, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.pill, fontFamily: sans(400), fontSize: fs[4], backgroundColor: c.blanc, color: c.noir }}
            />
            <Pressable onPress={() => { setQ(saisie.trim()); setPage(1); }} accessibilityLabel="Chercher" style={{ position: 'absolute', right: 14, top: 0, bottom: 0, justifyContent: 'center' }}>
              <Icone nom="loupe" taille={20} couleur={c.gris} />
            </Pressable>
          </View>
        ) : null}
      </EnTete>

      <Contenu style={{ paddingTop: 12 }}>
        <View style={{ flexDirection: 'row', gap: 8, marginBottom: 20 }}>
          <Selecteur
            apparence="filtre"
            style={{ flex: 1 }}
            titre="École"
            valeur={ecole}
            onChange={(v) => { setEcole(v); setPage(1); }}
            options={[{ valeur: '', libelle: 'Toutes les écoles' }, ...(f?.ecoles ?? []).map((e) => ({ valeur: e, libelle: e }))]}
          />
          {f && f.catalogue.length ? (
            <Selecteur
              apparence="filtre"
              style={{ flex: 1 }}
              titre="Intérêts"
              valeur={f.comme_moi ? f.valeur_comme_moi : interet}
              onChange={(v) => { setInteret(v); setPage(1); }}
              options={[
                { valeur: '', libelle: 'Tous les intérêts' },
                ...(f.mes_interets.length ? [{ valeur: f.valeur_comme_moi, libelle: 'Comme moi' }] : []),
                ...f.catalogue.map((i) => ({ valeur: i, libelle: '#' + i })),
              ]}
            />
          ) : null}
        </View>

        {f?.interets_manquants ? (
          <View style={{ backgroundColor: c.alerteClair, borderRadius: rayon.md, paddingVertical: 12, paddingHorizontal: 16, marginBottom: 16 }}>
            <T taille={fs[3]} poids={600} couleur={c.alerte}>
              Tu n&apos;as pas encore de centres d&apos;intérêt.{' '}
              <T taille={fs[3]} poids={700} couleur={c.alerte} onPress={() => router.navigate('/moi')} style={{ textDecorationLine: 'underline' }}>Ajoute-les depuis ton profil →</T>
            </T>
          </View>
        ) : null}

        {(ecole || filtreInteretActif || q) && annuaire ? (
          <View style={{ marginBottom: 16, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
            <Mono taille={fs[2]} poids={400} majuscules={false} espacement={0}>
              {annuaire.profils.length} résultat{annuaire.profils.length > 1 ? 's' : ''}
            </Mono>
            <T
              taille={fs[3]}
              poids={700}
              couleur={c.surRougeClair}
              onPress={() => { setSaisie(''); setQ(''); setEcole(''); setInteret(''); setPage(1); }}
            >
              Réinitialiser
            </T>
          </View>
        ) : null}

        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : annuaire === null ? <Chargement /> : (
          <>
            {annuaire.suggestions.length ? (
              <View style={{ marginBottom: 22 }}>
                <Separateur libelle="À suivre · d'après tes goûts" style={{ marginTop: 4 }} />
                <ScrollView horizontal showsHorizontalScrollIndicator={false} snapToInterval={146} decelerationRate="fast" contentContainerStyle={{ gap: 10, paddingBottom: 4 }}>
                  {annuaire.suggestions.map((s) => (
                    <CarteSuggestion
                      key={s.id}
                      p={s}
                      motif={s.interets_communs.length ? '#' + s.interets_communs.slice(0, 2).join(' #') : s.squads_communs > 0 ? 'Squad en commun' : 'Même école'}
                    />
                  ))}
                </ScrollView>
              </View>
            ) : null}

            <View style={{ gap: 10 }}>
              {annuaire.profils.map((p) => (
                <LignePersonne key={p.id} p={p} etat={p.etat_suivi} interetsCommuns={p.interets_communs} squadsCommuns={p.squads_communs} score={p.score} />
              ))}
              {annuaire.profils.length === 0 ? (
                <T taille={fs[3]} couleur={c.gris} style={{ textAlign: 'center', paddingVertical: 24 }}>Aucun autre profil à afficher pour le moment.</T>
              ) : null}
            </View>

            {annuaire.pagination.a_suivre ? (
              <Bouton libelle="Voir plus de profils" variante="contour" plein chargement={chargeSuite} onPress={() => { setChargeSuite(true); setPage((p) => p + 1); }} style={{ marginTop: 14 }} />
            ) : null}
          </>
        )}
      </Contenu>
    </Ecran>
  );
}
