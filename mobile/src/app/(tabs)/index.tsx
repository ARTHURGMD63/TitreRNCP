/**
 * Le hub étudiant — explore.php.
 *
 * En-tête : « Les bons plans / du moment. », la cloche et sa pastille. Les
 * filtres de type (dont « Pour moi ») et de musique, les cartes flash et
 * classiques, l'état vide, « Voir plus d'événements ».
 *
 * La partie Personnes de l'ancien sélecteur Événements / Personnes vit
 * maintenant dans son propre onglet (personnes.tsx) — plus lisible qu'un
 * bascule cachée en haut d'Explorer, retrouvable directement dans la barre.
 *
 * La cloche ouvre la feuille des notifications, l'invitation sa propre
 * feuille — les deux fenêtres du site.
 */

import React, { useCallback, useEffect, useState } from 'react';
import { Pressable, ScrollView, Text, TextInput, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';

import { api, type Evenement, type Notification } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { CarteEvenement, enregistrerStyles } from '../../composants/CarteEvenement';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Pilule } from '../../composants/Elements';
import { Feuille } from '../../composants/Feuille';
import { Icone } from '../../composants/Icone';
import { Marque } from '../../composants/Marque';
import { ElementNotification, FeuilleInvitation, FermerFeuille, NotificationsVides, SurtitreFeuille } from '../../composants/Social';
import { Display, T, TitreEcran } from '../../composants/Texte';
import { ts } from '../../format';
import { useJeton } from '../../session';
import { fixe, fs, gutter, lh, mono, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

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

  const [rechercheOuverte, setRechercheOuverte] = useState(false);
  const [saisie, setSaisie] = useState('');
  const [q, setQ] = useState('');

  const [type, setType] = useState('all');
  const [musique, setMusique] = useState('');
  const [pageE, setPageE] = useState(1);
  const [evenements, setEvenements] = useState<Evenement[] | null>(null);
  const [styles, setStyles] = useState<{ code: string; libelle: string }[]>([]);
  const [resteE, setResteE] = useState(false);

  const [erreur, setErreur] = useState<string | null>(null);
  const [rafraichit, setRafraichit] = useState(false);
  const [chargeSuite, setChargeSuite] = useState(false);

  // ── Cloche ──
  const [notifs, setNotifs] = useState<{ a_traiter: number; items: Notification[] } | null>(null);
  const [cloche, setCloche] = useState(false);
  const [invitation, setInvitation] = useState<{ type: 'event' | 'squad'; id: number; nom: string } | null>(null);

  const chargerNotifs = useCallback(async () => {
    try { setNotifs(await api.notifications(jeton)); } catch { /* la cloche reste telle quelle */ }
  }, [jeton]);

  const charger = useCallback(async () => {
    try {
      setErreur(null);
      const r = await api.evenements(jeton, { type, musique, q, pe: pageE });
      enregistrerStyles(r.filtres.styles_musique);
      setStyles(r.filtres.styles_musique);
      setEvenements(r.evenements);
      setResteE(r.pagination.a_suivre);
    } catch (e) {
      setErreur(e instanceof Error ? e.message : 'Chargement impossible.');
    } finally {
      setRafraichit(false);
      setChargeSuite(false);
    }
  }, [jeton, type, musique, q, pageE]);

  useEffect(() => {
    // Toutes les écritures d'état de charger() suivent l'attente réseau ;
    // l'analyse statique ne peut pas le démontrer à travers l'appel.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void charger();
  }, [charger]);
  useFocusEffect(useCallback(() => { void chargerNotifs(); }, [chargerNotifs]));

  const filtreActif = type !== 'all' || musique !== '' || q !== '';

  const choisirType = (t: string) => { setType(t); setPageE(1); setEvenements(null); };
  const choisirMusique = (m: string) => { setMusique(m); setPageE(1); setEvenements(null); };

  // La première carte flash porte le compte à rebours ($firstCard).
  const [instant] = useState(() => Date.now() / 1000);
  const premiereFlash = evenements?.find((e) => e.is_flash && ts(e.flash_expiry) > instant)?.id;

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
                <NotificationsVides onLien={() => { setCloche(false); router.navigate('/personnes'); }} />
              ) : null}
            </View>
          </Feuille>
          <FeuilleInvitation cible={invitation} onClose={() => setInvitation(null)} />
        </>
      }
    >
      <EnTete style={{ paddingBottom: 10 }}>
        <View style={{ marginBottom: 14 }}>
          <Marque taille={fs[6]} />
        </View>
        <View style={{ flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12, marginBottom: 16 }}>
          <View style={{ flex: 1 }}>
            <TitreEcran lignes={['Les bons plans', 'du moment.']} />
          </View>
          <View style={{ gap: 8, alignItems: 'flex-end' }}>
            {/* Recherche au-dessus des notifications : « en haut à droite ». */}
            <Pressable
              onPress={() => setRechercheOuverte((o) => !o)}
              accessibilityRole="button"
              accessibilityLabel="Rechercher"
              accessibilityState={{ expanded: rechercheOuverte }}
              style={[{ width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center', backgroundColor: c.blanc, borderWidth: 1, borderColor: c.line2 }, ombre('sm')]}
            >
              <Icone nom="loupe" taille={20} couleur={c.noir} />
            </Pressable>

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
        </View>

        {rechercheOuverte ? (
          <View style={{ marginBottom: 16 }}>
            <TextInput
              autoFocus
              value={saisie}
              onChangeText={setSaisie}
              onSubmitEditing={() => { setQ(saisie.trim()); setPageE(1); setEvenements(null); }}
              placeholder="Chercher une soirée, un lieu..."
              placeholderTextColor={c.gris}
              returnKeyType="search"
              style={{ paddingVertical: 14, paddingLeft: 20, paddingRight: 52, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.pill, fontFamily: sans(400), fontSize: fs[4], backgroundColor: c.blanc, color: c.noir }}
            />
            <Pressable onPress={() => { setQ(saisie.trim()); setPageE(1); setEvenements(null); }} accessibilityLabel="Chercher" style={{ position: 'absolute', right: 14, top: 0, bottom: 0, justifyContent: 'center' }}>
              <Icone nom="loupe" taille={20} couleur={c.gris} />
            </Pressable>
          </View>
        ) : null}
      </EnTete>

      <View style={{ paddingTop: 12 }}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ flexGrow: 0, marginBottom: 20 }} contentContainerStyle={{ gap: 8, paddingHorizontal: gutter, paddingBottom: 16 }}>
          {TYPES.map((t) => {
            const actif = type === t.code;
            const pourMoi = t.code === 'pour-moi';
            return (
              <React.Fragment key={t.code}>
                <Pilule
                  libelle={t.libelle}
                  actif={actif}
                  onPress={() => choisirType(t.code)}
                  icone={pourMoi ? 'etoile-fine' : undefined}
                  style={pourMoi && !actif ? { borderColor: c.surRougeClair } : undefined}
                  couleurTexte={pourMoi && !actif ? c.surRougeClair : undefined}
                />
                {/* Juste après « Pour moi », avant les types de lieu : une
                    action (ouvrir la carte), pas un filtre de plus. */}
                {pourMoi ? <Pilule libelle="Carte" icone="epingle" actif={false} onPress={() => router.push('/carte')} /> : null}
              </React.Fragment>
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
    </Ecran>
  );
}
