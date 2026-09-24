/**
 * Le hub étudiant — explore.php.
 *
 * En-tête : « Salut Arthur », le titre en deux lignes (« Les bons plans /
 * du moment. » ou « Trouve tes / futurs potes. »), la cloche et sa pastille,
 * puis le sélecteur Événements / Personnes.
 *
 * Événements : les filtres de type (dont « Pour moi ») et de musique, les
 * cartes flash et classiques, l'état vide, « Voir plus d'événements ».
 * Personnes : recherche, filtres école et intérêt (dont « Comme moi »),
 * suggestions, annuaire, « Voir plus de profils ».
 *
 * La cloche ouvre la feuille des notifications, l'invitation sa propre
 * feuille — les deux fenêtres du site.
 */

import React, { useCallback, useEffect, useState } from 'react';
import { Pressable, ScrollView, Text, TextInput, View } from 'react-native';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';

import { api, type Evenement, type Notification, type ReponsePersonnes } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { CarteEvenement, enregistrerStyles } from '../../composants/CarteEvenement';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Pilule, Segments, Separateur } from '../../composants/Elements';
import { Feuille } from '../../composants/Feuille';
import { Selecteur } from '../../composants/Formulaire';
import { Icone } from '../../composants/Icone';
import {
  CarteSuggestion, ElementNotification, FeuilleInvitation, FermerFeuille, LignePersonne, NotificationsVides, SurtitreFeuille,
} from '../../composants/Social';
import { Display, Mono, T, TitreEcran } from '../../composants/Texte';
import { ts } from '../../format';
import { useJeton, useSession } from '../../session';
import { fixe, fs, gutter, lh, mono, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

type Vue = 'events' | 'people';

const TYPES = [
  { code: 'all', libelle: 'Tout' },
  { code: 'pour-moi', libelle: 'Pour moi' },
  { code: 'bar', libelle: 'Bars' },
  { code: 'boite', libelle: 'Boîtes' },
  { code: 'resto', libelle: 'Restos' },
];

export default function Hub() {
  const { c, ombre } = useTheme();
  const jeton = useJeton();
  const { profil } = useSession();
  const params = useLocalSearchParams<{ vue?: string; interest?: string }>();

  const [vue, setVue] = useState<Vue>(params.vue === 'people' ? 'people' : 'events');

  // ── Événements ──
  const [type, setType] = useState('all');
  const [musique, setMusique] = useState('');
  const [pageE, setPageE] = useState(1);
  const [evenements, setEvenements] = useState<Evenement[] | null>(null);
  const [styles, setStyles] = useState<{ code: string; libelle: string }[]>([]);
  const [resteE, setResteE] = useState(false);

  // ── Personnes ──
  const [saisie, setSaisie] = useState('');
  const [q, setQ] = useState('');
  const [ecole, setEcole] = useState('');
  const [interet, setInteret] = useState(params.interest ?? '');
  const [pageP, setPageP] = useState(1);
  const [annuaire, setAnnuaire] = useState<ReponsePersonnes | null>(null);

  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);
  const [chargeSuite, setChargeSuite] = useState(false);

  // ── Cloche ──
  const [notifs, setNotifs] = useState<{ a_traiter: number; items: Notification[] } | null>(null);
  const [cloche, setCloche] = useState(false);
  const [invitation, setInvitation] = useState<{ type: 'event' | 'squad'; id: number; nom: string } | null>(null);

  // Un lien « Trouve des étudiants » ou une étiquette #intérêt du profil
  // ouvre directement l'annuaire, comme ?view=people&interest=… sur le site.
  const [paramsVus, setParamsVus] = useState(`${params.vue}|${params.interest}`);
  if (`${params.vue}|${params.interest}` !== paramsVus) {
    setParamsVus(`${params.vue}|${params.interest}`);
    if (params.vue === 'people') setVue('people');
    if (params.interest !== undefined) { setInteret(params.interest); setPageP(1); }
  }

  const chargerEvenements = useCallback(async () => {
    const r = await api.evenements(jeton, { type, musique, pe: pageE });
    enregistrerStyles(r.filtres.styles_musique);
    setStyles(r.filtres.styles_musique);
    setEvenements(r.evenements);
    setResteE(r.pagination.a_suivre);
  }, [jeton, type, musique, pageE]);

  const chargerAnnuaire = useCallback(async () => {
    setAnnuaire(await api.personnes(jeton, { q, ecole, interest: interet, p: pageP }));
  }, [jeton, q, ecole, interet, pageP]);

  const chargerNotifs = useCallback(async () => {
    try { setNotifs(await api.notifications(jeton)); } catch { /* la cloche reste telle quelle */ }
  }, [jeton]);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      await (vue === 'events' ? chargerEvenements() : chargerAnnuaire());
    } catch (e) {
      setErreur(e instanceof Error ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
      setChargeSuite(false);
    }
  }, [vue, chargerEvenements, chargerAnnuaire]);

  useEffect(() => {
    // Toutes les écritures d'état de charger() suivent l'attente réseau ;
    // l'analyse statique ne peut pas le démontrer à travers l'appel.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void charger();
  }, [charger]);
  useFocusEffect(useCallback(() => { void chargerNotifs(); }, [chargerNotifs]));

  const filtreActif = type !== 'all' || musique !== '';

  const choisirType = (t: string) => { setType(t); setPageE(1); setEvenements(null); };
  const choisirMusique = (m: string) => { setMusique(m); setPageE(1); setEvenements(null); };

  // La première carte flash porte le compte à rebours ($firstCard).
  const [instant] = useState(() => Date.now() / 1000);
  const premiereFlash = evenements?.find((e) => e.is_flash && ts(e.flash_expiry) > instant)?.id;

  const f = annuaire?.filtres;
  const filtreInteretActif = interet !== '';

  return (
    <Ecran
      rafraichit={rafraichit}
      onRafraichir={() => { setRafraichit(true); void charger(); void chargerNotifs(); }}
      horsDefilement={
        <>
          <Feuille visible={cloche} onClose={() => setCloche(false)}>
            <View style={{ flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12, marginBottom: 18 }}>
              <View style={{ flex: 1 }}>
                <SurtitreFeuille>Notifications</SurtitreFeuille>
                <Display taille={fs[6]}>{notifs?.a_traiter ? `${notifs.a_traiter} à traiter` : 'Quoi de neuf'}</Display>
                <Text
                  onPress={() => { setCloche(false); router.navigate('/notifications'); }}
                  accessibilityRole="link"
                  style={{ marginTop: 2, minHeight: 44, paddingVertical: 12, fontFamily: sans(700), fontSize: fs[3], color: c.surRougeClair }}
                >
                  Tout voir →
                </Text>
              </View>
              <FermerFeuille onPress={() => setCloche(false)} />
            </View>
            <View style={{ gap: 8 }}>
              {(notifs?.items ?? []).map((n, i) => (
                <ElementNotification
                  key={`${n.type}-${n.ts}-${i}`}
                  n={n}
                  onTraitee={() => {
                    setNotifs((s) => s && { a_traiter: Math.max(0, s.a_traiter - 1), items: s.items.filter((x) => x !== n) });
                    void chargerNotifs();
                  }}
                />
              ))}
              {notifs && notifs.items.length === 0 ? (
                <NotificationsVides onLien={() => { setCloche(false); setVue('people'); }} />
              ) : null}
            </View>
          </Feuille>
          <FeuilleInvitation cible={invitation} onClose={() => setInvitation(null)} />
        </>
      }
    >
      <EnTete style={{ paddingBottom: 10 }}>
        <View style={{ flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12, marginBottom: 16 }}>
          <View style={{ flex: 1 }}>
            <T taille={fs[4]} couleur={c.gris} style={{ marginBottom: 4 }}>Salut {profil?.prenom}</T>
            <TitreEcran lignes={vue === 'events' ? ['Les bons plans', 'du moment.'] : ['Trouve tes', 'futurs potes.']} />
          </View>
          <Pressable
            onPress={() => { setCloche(true); void chargerNotifs(); }}
            accessibilityRole="button"
            accessibilityLabel="Notifications"
            style={[{ width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center', backgroundColor: c.blanc, borderWidth: 1, borderColor: c.line2 }, ombre('sm')]}
          >
            <Icone nom="cloche" taille={22} couleur={c.noir} />
            {notifs?.a_traiter ? (
              <View style={{ position: 'absolute', top: -3, right: -3, minWidth: 19, height: 19, paddingHorizontal: 5, borderRadius: rayon.pill, backgroundColor: c.rouge, borderWidth: 2, borderColor: c.bg, alignItems: 'center', justifyContent: 'center' }}>
                <Text style={{ fontFamily: mono(600), fontSize: fs[1], lineHeight: fs[1] + 1, color: fixe.surLave }}>{notifs.a_traiter}</Text>
              </View>
            ) : null}
          </Pressable>
        </View>

        <Segments
          options={[{ code: 'events', libelle: 'Événements' }, { code: 'people', libelle: 'Personnes' }]}
          valeur={vue}
          onChange={(v) => setVue(v as Vue)}
        />
      </EnTete>

      {vue === 'events' ? (
        <View style={{ paddingTop: 12 }}>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ flexGrow: 0, marginBottom: 20 }} contentContainerStyle={{ gap: 8, paddingHorizontal: gutter, paddingBottom: 16 }}>
            {TYPES.map((t) => {
              const actif = type === t.code;
              const pourMoi = t.code === 'pour-moi';
              return (
                <Pilule
                  key={t.code}
                  libelle={t.libelle}
                  actif={actif}
                  onPress={() => choisirType(t.code)}
                  icone={pourMoi ? 'etoile-fine' : undefined}
                  style={pourMoi && !actif ? { borderColor: c.surRougeClair } : undefined}
                  couleurTexte={pourMoi && !actif ? c.surRougeClair : undefined}
                />
              );
            })}
          </ScrollView>

          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ flexGrow: 0, marginTop: -12, marginBottom: 24 }} contentContainerStyle={{ gap: 8, paddingHorizontal: gutter, paddingBottom: 16 }}>
            <Pilule libelle="Toute musique" icone="musique" musique actif={musique === ''} onPress={() => choisirMusique('')} />
            {styles.map((s) => (
              <Pilule key={s.code} libelle={s.libelle} musique actif={musique === s.code} onPress={() => choisirMusique(s.code)} />
            ))}
          </ScrollView>

          <Contenu>
            {erreur ? <Erreur message={erreur} onReessayer={charger} /> : evenements === null ? <Chargement /> : (
              <>
                {evenements.length === 0 ? (
                  <View style={[{ backgroundColor: c.blanc, borderRadius: rayon.base, borderWidth: 1, borderColor: c.grisClair, paddingVertical: 36, paddingHorizontal: 26, alignItems: 'center', marginBottom: 14 }, ombre('sm')]}>
                    <View style={{ marginBottom: 14 }}><Icone nom={filtreActif ? 'loupe' : 'vide'} taille={24} couleur={c.gris} /></View>
                    <Display taille={fs[6]} interligne={lh.tight} style={{ marginBottom: 6, textAlign: 'center', letterSpacing: -0.02 * fs[6] }}>
                      {filtreActif ? 'Aucun résultat' : 'Rien de prévu pour le moment'}
                    </Display>
                    <T taille={fs[3]} poids={500} couleur={c.grisFonce} interligne={lh.snug} style={{ textAlign: 'center', maxWidth: 290 }}>
                      {filtreActif
                        ? 'Aucun événement ne correspond à cette recherche. Essaie un autre filtre.'
                        : "Les établissements n'ont pas encore publié de soirée. Reviens d'ici quelques jours."}
                    </T>
                    {filtreActif ? (
                      <Bouton libelle="Voir tous les événements" onPress={() => { choisirType('all'); choisirMusique(''); }} style={{ marginTop: 20, alignSelf: 'center' }} />
                    ) : null}
                  </View>
                ) : null}

                {evenements.map((e) => (
                  <CarteEvenement
                    key={e.id}
                    ev={e}
                    premiere={e.id === premiereFlash}
                    onInviter={() => setInvitation({ type: 'event', id: e.id, nom: e.titre })}
                  />
                ))}

                {resteE ? (
                  <Bouton libelle="Voir plus d'événements" variante="contour" plein chargement={chargeSuite} onPress={() => { setChargeSuite(true); setPageE((p) => p + 1); }} style={{ marginTop: 4 }} />
                ) : null}
              </>
            )}
          </Contenu>
        </View>
      ) : (
        <Contenu style={{ paddingTop: 12 }}>
          {/* Recherche */}
          <View style={{ marginBottom: 14 }}>
            <TextInput
              value={saisie}
              onChangeText={setSaisie}
              onSubmitEditing={() => { setQ(saisie.trim()); setPageP(1); }}
              placeholder="Chercher un nom ou une passion..."
              placeholderTextColor={c.gris}
              returnKeyType="search"
              style={{ paddingVertical: 14, paddingLeft: 20, paddingRight: 52, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.pill, fontFamily: sans(400), fontSize: fs[4], backgroundColor: c.blanc, color: c.noir }}
            />
            <Pressable onPress={() => { setQ(saisie.trim()); setPageP(1); }} accessibilityLabel="Chercher" style={{ position: 'absolute', right: 14, top: 0, bottom: 0, justifyContent: 'center' }}>
              <Icone nom="loupe" taille={20} couleur={c.gris} />
            </Pressable>
          </View>

          {/* Filtres */}
          <View style={{ flexDirection: 'row', gap: 8, marginBottom: 20 }}>
            <Selecteur
              apparence="filtre"
              style={{ flex: 1 }}
              titre="École"
              valeur={ecole}
              onChange={(v) => { setEcole(v); setPageP(1); }}
              options={[{ valeur: '', libelle: 'Toutes les écoles' }, ...(f?.ecoles ?? []).map((e) => ({ valeur: e, libelle: e }))]}
            />
            {f && f.catalogue.length ? (
              <Selecteur
                apparence="filtre"
                style={{ flex: 1 }}
                titre="Intérêts"
                valeur={f.comme_moi ? f.valeur_comme_moi : interet}
                onChange={(v) => { setInteret(v); setPageP(1); }}
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
                <Text onPress={() => router.navigate('/moi')} style={{ fontFamily: sans(700), textDecorationLine: 'underline' }}>Ajoute-les depuis ton profil →</Text>
              </T>
            </View>
          ) : null}

          {(ecole || filtreInteretActif || q) && annuaire ? (
            <View style={{ marginBottom: 16, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
              <Mono taille={fs[2]} poids={400} majuscules={false} espacement={0}>
                {annuaire.profils.length} résultat{annuaire.profils.length > 1 ? 's' : ''}
              </Mono>
              <Text
                onPress={() => { setSaisie(''); setQ(''); setEcole(''); setInteret(''); setPageP(1); }}
                style={{ fontFamily: sans(700), fontSize: fs[3], color: c.surRougeClair }}
              >
                Réinitialiser
              </Text>
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
                <Bouton libelle="Voir plus de profils" variante="contour" plein chargement={chargeSuite} onPress={() => { setChargeSuite(true); setPageP((p) => p + 1); }} style={{ marginTop: 14 }} />
              ) : null}
            </>
          )}
        </Contenu>
      )}
    </Ecran>
  );
}
