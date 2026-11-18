/**
 * Le hub étudiant — explore.php.
 *
 * En-tête : le logo, la recherche et la cloche, puis « Les bons plans [du
 * moment.] ». Une rangée de chips : Filtres, Tout, Pour moi, Carte, Bars,
 * Boîtes, Restos. Les styles de musique sont dans le panneau Filtres
 * (refonte 2026-11) ; le filtrage, lui, n'a pas changé. Puis les cartes —
 * la première en couleur accent —, l'état vide, « Voir plus d'événements ».
 *
 * La partie Personnes de l'ancien sélecteur Événements / Personnes vit
 * maintenant dans son propre onglet (personnes.tsx) — plus lisible qu'un
 * bascule cachée en haut d'Explorer, retrouvable directement dans la barre.
 *
 * La cloche ouvre la feuille des notifications, l'invitation sa propre
 * feuille — les deux fenêtres du site.
 */

import React, { useCallback, useRef, useState } from 'react';
import { Text, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';

import { api, type CibleInvitation, type Evenement, type Notification } from '../../api';
import { Bouton } from '../../composants/Bouton';
import { CarteEvenement, enregistrerStyles } from '../../composants/CarteEvenement';
import { Apparition } from '../../composants/Animations';
import { BarreRecherche } from '../../composants/BarreRecherche';
import { EnTeteApp, PanneauFiltres, rangeeChips, Tuile, useRubrique } from '../../composants/Design';
import { Chargement, Contenu, EnTete, Ecran, Erreur } from '../../composants/Ecran';
import { Pilule } from '../../composants/Elements';
import { RangeeDefilante } from '../../composants/RangeeDefilante';
import { Feuille } from '../../composants/Feuille';
import { Icone, type NomIcone } from '../../composants/Icone';
import { ElementNotification, FeuilleInvitation, FermerFeuille, NotificationsVides, SurtitreFeuille } from '../../composants/Social';
import { Display, T, TitreEcran } from '../../composants/Texte';
import { ts } from '../../format';
import { useJeton } from '../../session';
import { couleursCategorie, fs, gutter, lh, rayon, sans } from '../../theme';
import { useTheme } from '../../useTheme';

/** L'icône du petit carré coloré de chaque chip (« Tout » n'en a pas). */
const ICONES_TYPE: Record<string, NomIcone | undefined> = {
  'pour-moi': 'etoile-fine',
  bar: 'verre',
  boite: 'musique',
  resto: 'billet',
};

const TYPES = [
  { code: 'all', libelle: 'Tout' },
  { code: 'pour-moi', libelle: 'Pour moi' },
  { code: 'bar', libelle: 'Bars' },
  { code: 'boite', libelle: 'Boîtes' },
  { code: 'resto', libelle: 'Restos' },
];

export default function Hub() {
  const { c } = useTheme();
  const bleu = useRubrique('bleu');
  const jeton = useJeton();
  const [panneau, setPanneau] = useState(false);

  const [rechercheOuverte, setRechercheOuverte] = useState(false);
  const [saisie, setSaisie] = useState('');
  const [q, setQ] = useState('');
  const [cherche, setCherche] = useState(false);
  // Le numéro de la dernière requête : une réponse plus ancienne, arrivée
  // après une frappe plus récente, est ignorée au lieu d'écraser la liste.
  const derniere = useRef(0);

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
  const [invitation, setInvitation] = useState<{ type: CibleInvitation; id: number; nom: string } | null>(null);

  const chargerNotifs = useCallback(async () => {
    try { setNotifs(await api.notifications(jeton)); } catch { /* la cloche reste telle quelle */ }
  }, [jeton]);

  const charger = useCallback(async () => {
    const numero = ++derniere.current;
    try {
      setErreur(null);
      const r = await api.evenements(jeton, { type, musique, q, pe: pageE });
      if (numero !== derniere.current) return;
      enregistrerStyles(r.filtres.styles_musique);
      setStyles(r.filtres.styles_musique);
      setEvenements(r.evenements);
      setResteE(r.pagination.a_suivre);
    } catch (e) {
      if (numero === derniere.current) setErreur(e instanceof Error ? e.message : 'Chargement impossible.');
    } finally {
      if (numero === derniere.current) {
        setRafraichit(false);
        setChargeSuite(false);
        setCherche(false);
      }
    }
  }, [jeton, type, musique, q, pageE]);

  // Rechargé à chaque retour sur l'onglet, et non plus au seul montage : un
  // pass annulé depuis l'onglet Pass laissait la carte afficher « Inscrit ».
  useFocusEffect(useCallback(() => { void charger(); }, [charger]));
  useFocusEffect(useCallback(() => { void chargerNotifs(); }, [chargerNotifs]));

  const filtreActif = type !== 'all' || musique !== '' || q !== '';

  const choisirType = (t: string) => { setType(t); setPageE(1); setEvenements(null); };
  const choisirMusique = (m: string) => { setMusique(m); setPageE(1); setEvenements(null); };
  // La liste déjà affichée reste à l'écran pendant la recherche : pas de
  // clignotement à chaque lettre, seule la loupe tourne.
  const chercher = (terme: string) => {
    if (terme === q) return;
    setCherche(true);
    setQ(terme);
    setPageE(1);
  };
  // Fermer la recherche la retire : la liste ne reste pas filtrée en cachette.
  const basculerRecherche = () => {
    if (rechercheOuverte) { setSaisie(''); chercher(''); }
    setRechercheOuverte(!rechercheOuverte);
  };
  const toutVoir = () => { setSaisie(''); setQ(''); setRechercheOuverte(false); choisirType('all'); choisirMusique(''); };

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
          <PanneauFiltres
            visible={panneau}
            onClose={() => setPanneau(false)}
            section="Musique"
            options={[{ code: '', libelle: 'Toute musique' }, ...styles]}
            valeur={musique}
            onAppliquer={choisirMusique}
            accent={bleu.accent}
          />
        </>
      }
    >
      <EnTete style={{ paddingBottom: 4 }}>
        <Apparition index={0}>
          <EnTeteApp
            onRecherche={basculerRecherche}
            rechercheOuverte={rechercheOuverte}
            onNotifications={() => { setCloche(true); void chargerNotifs(); }}
            nonLues={!!notifs?.a_traiter}
          />
        </Apparition>
        <Apparition index={1}>
          <TitreEcran avant="Les bons plans" surligne="du moment." style={{ marginBottom: 16 }} />
        </Apparition>

        {rechercheOuverte ? (
          <View style={{ marginBottom: 16 }}>
            <BarreRecherche
              valeur={saisie}
              onChange={setSaisie}
              onChercher={chercher}
              chargement={cherche}
              placeholder="Une soirée, un lieu, un bar..."
              accessibilityLabel="Chercher une soirée ou un lieu"
            />
          </View>
        ) : null}
      </EnTete>

      <View>
        <Apparition index={2}>
          <RangeeDefilante style={{ marginBottom: 20 }} contentContainerStyle={rangeeChips}>
            {/* La musique est passée dans le panneau Filtres ; la chip le
                signale quand un style est choisi. */}
            <Pilule
              libelle={musique ? 'Filtres · 1' : 'Filtres'}
              icone="reglages"
              contour
              actif={false}
              onPress={() => setPanneau(true)}
              accessibilityLabel={musique ? `Filtres, musique : ${styles.find((s) => s.code === musique)?.libelle ?? musique}` : 'Filtres'}
            />
            {TYPES.filter((t) => t.code !== 'pour-moi').map((t) => (
              <Pilule
                key={t.code}
                libelle={t.libelle}
                actif={type === t.code}
                onPress={() => choisirType(t.code)}
                icone={ICONES_TYPE[t.code]}
                carre={couleursCategorie[t.code]}
              />
            ))}
          </RangeeDefilante>
        </Apparition>

        {/* Comme les rubriques de Sport : deux tuiles. « Pour moi » est le
            même filtre qu'avant (le retoucher revient à « Tout ») ; « Carte »
            reste une action qui ouvre la carte. */}
        <Apparition index={3}>
          <View style={{ flexDirection: 'row', gap: 10, paddingHorizontal: gutter, marginBottom: 20 }}>
            <Tuile
              icone="etoile-fine"
              libelle="Pour moi"
              actif={type === 'pour-moi'}
              onPress={() => choisirType(type === 'pour-moi' ? 'all' : 'pour-moi')}
              style={{ flex: 1 }}
            />
            <Tuile icone="epingle" libelle="Carte" droite="chevron-d" onPress={() => router.push('/carte')} style={{ flex: 1 }} />
          </View>
        </Apparition>

        <Contenu>
          {erreur ? <Erreur message={erreur} onReessayer={charger} /> : evenements === null ? <Chargement /> : (
            <>
              {evenements.length === 0 ? (
                <View style={{ backgroundColor: c.blanc, borderRadius: rayon.carte, paddingVertical: 36, paddingHorizontal: 26, alignItems: 'center', marginBottom: 16 }}>
                  <View style={{ marginBottom: 14 }}><Icone nom={filtreActif ? 'loupe' : 'vide'} taille={24} couleur={c.gris} /></View>
                  <Display taille={fs[6]} interligne={lh.tight} style={{ marginBottom: 6, textAlign: 'center', letterSpacing: -0.02 * fs[6] }}>
                    {filtreActif ? 'Aucun résultat' : 'Rien de prévu pour le moment'}
                  </Display>
                  <T taille={fs[3]} poids={500} couleur={c.grisFonce} interligne={lh.snug} style={{ textAlign: 'center', maxWidth: 290 }}>
                    {filtreActif
                      ? (q ? `Rien pour « ${q} ». Vérifie l'orthographe ou essaie un autre mot.` : 'Aucun événement ne correspond à ce filtre. Essaie-en un autre.')
                      : "Les établissements n'ont pas encore publié de soirée près de toi. En attendant, suis des personnes pour voir où elles sortent, ou explore la culture et le sport."}
                  </T>
                  {filtreActif ? (
                    <Bouton libelle="Voir tous les événements" onPress={toutVoir} style={{ marginTop: 20, alignSelf: 'center' }} />
                  ) : (
                    <View style={{ marginTop: 20, gap: 10, alignSelf: 'stretch' }}>
                      <Bouton libelle="Trouver des gens à suivre" icone="personnes" plein onPress={() => router.navigate('/personnes')} />
                      <Bouton libelle="Voir la culture" icone="musee" variante="contour" plein onPress={() => router.navigate('/culture')} />
                    </View>
                  )}
                </View>
              ) : null}

              {evenements.map((e, i) => (
                <Apparition key={e.id} index={4 + Math.min(i, 3)}>
                  <CarteEvenement
                    ev={e}
                    accent={i === 0}
                    premiere={e.id === premiereFlash}
                    onInviter={() => setInvitation({ type: 'event', id: e.id, nom: e.titre })}
                  />
                </Apparition>
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
