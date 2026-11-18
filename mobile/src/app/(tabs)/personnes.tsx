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

import React, { useCallback, useRef, useState } from 'react';
import { ScrollView, View } from 'react-native';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';

import { api, type ReponsePersonnes } from '../../api';
import { Apparition } from '../../composants/Animations';
import { BarreRecherche } from '../../composants/BarreRecherche';
import { Bouton } from '../../composants/Bouton';
import { EnTeteApp, useRubrique } from '../../composants/Design';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Selecteur } from '../../composants/Formulaire';
import { Icone } from '../../composants/Icone';
import { CarteSuggestion, LARGEUR_SUGGESTION, LignePersonne } from '../../composants/Social';
import { T, TitreEcran, TitreSection } from '../../composants/Texte';
import { useJeton } from '../../session';
import { fs, gutter, lh, rayon } from '../../theme';
import { useTheme } from '../../useTheme';

export default function Personnes() {
  const { c } = useTheme();
  const jeton = useJeton();
  const params = useLocalSearchParams<{ interest?: string }>();

  const [rechercheOuverte, setRechercheOuverte] = useState(false);
  const [saisie, setSaisie] = useState('');
  const [q, setQ] = useState('');
  const [cherche, setCherche] = useState(false);
  // Une réponse plus ancienne qu'une frappe récente est ignorée.
  const derniere = useRef(0);

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
    const numero = ++derniere.current;
    try {
      setErreur(null);
      const r = await api.personnes(jeton, { q, ecole, interest: interet, p: page });
      if (numero === derniere.current) setAnnuaire(r);
    } catch (e) {
      if (numero === derniere.current) setErreur(e instanceof Error ? e.message : 'Chargement impossible.');
    } finally {
      if (numero === derniere.current) {
        setRafraichit(false);
        setChargeSuite(false);
        setCherche(false);
      }
    }
  }, [jeton, q, ecole, interet, page]);

  useFocusEffect(useCallback(() => { void charger(); }, [charger]));

  const f = annuaire?.filtres;

  const chercher = (terme: string) => {
    if (terme === q) return;
    setCherche(true);
    setQ(terme);
    setPage(1);
  };
  // Fermer la recherche la retire : l'annuaire ne reste pas filtré en cachette.
  const basculerRecherche = () => {
    if (rechercheOuverte) { setSaisie(''); chercher(''); }
    setRechercheOuverte(!rechercheOuverte);
  };
  const filtreInteretActif = interet !== '';

  return (
    <Ecran rafraichit={rafraichit} onRafraichir={() => { setRafraichit(true); void charger(); }}>
      <EnTete style={{ paddingBottom: 4 }}>
        <Apparition index={0}>
          <EnTeteApp
            onRecherche={basculerRecherche}
            rechercheOuverte={rechercheOuverte}
            onNotifications={() => router.navigate('/notifications')}
          />
        </Apparition>
        <Apparition index={1}>
          <TitreEcran avant="Trouve tes" surligne="futurs potes." style={{ marginBottom: 16 }} />
        </Apparition>

        {rechercheOuverte ? (
          <View style={{ marginBottom: 16 }}>
            <BarreRecherche
              valeur={saisie}
              onChange={setSaisie}
              onChercher={chercher}
              chargement={cherche}
              placeholder="Un prénom, un nom, une passion..."
              accessibilityLabel="Chercher un nom ou une passion"
            />
          </View>
        ) : null}
      </EnTete>

      <Contenu style={{ paddingTop: 0 }}>
        <Apparition index={2}>
        <View style={{ flexDirection: 'row', gap: 8, marginBottom: 14 }}>
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
        </Apparition>

        {/* Le formulaire du profil a quitté Moi pour « Modifier le profil » :
            le lien y mène directement. */}
        {f?.interets_manquants && annuaire && annuaire.profils.length > 0 ? (
          <View style={{ backgroundColor: c.alerteClair, borderRadius: rayon.bouton, paddingVertical: 12, paddingHorizontal: 16, marginBottom: 16 }}>
            <T taille={fs[4]} poids={600} couleur={c.alerte}>
              Tu n&apos;as pas encore de centres d&apos;intérêt.{' '}
              <T taille={fs[4]} poids={700} couleur={c.alerte} onPress={() => router.push('/profil/modifier' as never)} accessibilityRole="link" style={{ textDecorationLine: 'underline' }}>Ajoute-les depuis ton profil</T>
            </T>
          </View>
        ) : null}

        {(ecole || filtreInteretActif || q) && annuaire ? (
          <View style={{ marginBottom: 16, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
            <T taille={fs[4]} couleur={c.gris}>
              {annuaire.profils.length} résultat{annuaire.profils.length > 1 ? 's' : ''}
            </T>
            <T
              accessibilityRole="button"
              style={{ minHeight: 44, paddingVertical: 12 }}
              taille={fs[4]}
              poids={700}
              couleur={c.surRougeClair}
              onPress={() => { setSaisie(''); setQ(''); setRechercheOuverte(false); setEcole(''); setInteret(''); setPage(1); }}
            >
              Réinitialiser
            </T>
          </View>
        ) : null}

        {erreur ? <Erreur message={erreur} onReessayer={charger} /> : annuaire === null ? <Chargement /> : (
          <>
            {annuaire.suggestions.length ? (
              <Apparition index={3} style={{ marginBottom: 16 }}>
                <View style={{ flexDirection: 'row', alignItems: 'baseline', justifyContent: 'space-between', marginBottom: 10 }}>
                  <TitreSection>À suivre</TitreSection>
                  <T taille={fs[3]} couleur={c.gris}>D&apos;après tes goûts</T>
                </View>
                {/* Le carrousel déborde jusqu'au bord de l'écran : la dernière
                    carte coupée dit qu'il y en a d'autres. */}
                <ScrollView horizontal showsHorizontalScrollIndicator={false} snapToInterval={LARGEUR_SUGGESTION + 8} decelerationRate="fast"
                  style={{ marginHorizontal: -gutter }} contentContainerStyle={{ gap: 8, paddingHorizontal: gutter, paddingBottom: 4 }}>
                  {annuaire.suggestions.map((s) => (
                    <CarteSuggestion
                      key={s.id}
                      p={s}
                      motif={s.interets_communs.length ? '#' + s.interets_communs.slice(0, 2).join(' #') : s.squads_communs > 0 ? 'Squad en commun' : 'Même école'}
                    />
                  ))}
                </ScrollView>
              </Apparition>
            ) : null}

            <View style={{ gap: 8 }}>
              {annuaire.profils.map((p, i) => (
                <Apparition key={p.id} index={4 + Math.min(i, 4)}>
                  <LignePersonne p={p} etat={p.etat_suivi} interetsCommuns={p.interets_communs} squadsCommuns={p.squads_communs} score={p.score} />
                </Apparition>
              ))}
              {annuaire.profils.length === 0 ? (
                <Apparition index={3}>
                  <EtatVidePersonnes filtre={!!(ecole || filtreInteretActif || q)} />
                </Apparition>
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

/**
 * L'état vide de l'annuaire (refonte 2026-11) : des cercles d'avatars et une
 * loupe, « Personne à suivre pour l'instant… », et « Compléter mes intérêts »
 * qui ouvre Modifier le profil.
 */
function EtatVidePersonnes({ filtre }: { filtre: boolean }) {
  const { c } = useTheme();
  const bleu = useRubrique('bleu');
  return (
    <View style={{ backgroundColor: c.blanc, borderRadius: rayon.carte, paddingVertical: 28, paddingHorizontal: 22, alignItems: 'center' }}>
      <View accessible={false} style={{ width: 170, height: 110, marginBottom: 18 }}>
        <View style={{ position: 'absolute', left: 0, top: 22, width: 64, height: 64, borderRadius: 32, backgroundColor: '#CDBBF7' }} />
        <View style={{ position: 'absolute', right: 0, top: 22, width: 64, height: 64, borderRadius: 32, backgroundColor: '#F9C6D3' }} />
        <View style={{ position: 'absolute', left: 43, top: 0, width: 84, height: 84, borderRadius: 42, backgroundColor: '#C7DCFF', borderWidth: 3, borderColor: c.blanc }} />
        <View style={{ position: 'absolute', left: 63, top: 60, width: 44, height: 44, borderRadius: 14, backgroundColor: bleu.accent, borderWidth: 3, borderColor: c.blanc, alignItems: 'center', justifyContent: 'center' }}>
          <Icone nom="loupe" taille={20} couleur="#FFFFFF" trait={2.5} />
        </View>
      </View>
      <T taille={22} poids={700} interligne={lh.tight} style={{ textAlign: 'center', letterSpacing: -0.03 * 22, marginBottom: 8 }}>
        Personne à suivre pour l&apos;instant…
      </T>
      <T taille={fs[5]} couleur={c.gris} interligne={lh.normal} style={{ textAlign: 'center', marginBottom: 20 }}>
        {filtre
          ? 'Aucun profil ne correspond à ces filtres. Essaie-en un autre, ou complète tes centres d\'intérêt.'
          : 'Ajoute quelques centres d\'intérêt et on te trouve des potes qui sortent comme toi.'}
      </T>
      <Bouton libelle="Compléter mes intérêts" plein accent={bleu.accent} onPress={() => router.push('/profil/modifier' as never)} />
      {/* TODO design : « Inviter des potes » (bouton contour sous le
          précédent) attend une invitation à rejoindre l'application ; seule
          l'invitation à un événement ou à un squad existe aujourd'hui. */}
    </View>
  );
}
