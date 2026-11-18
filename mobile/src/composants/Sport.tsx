/**
 * Les éléments de l'univers sport partagés par l'onglet Sport, la fiche d'un
 * créneau et l'espace club : la ligne d'étiquette « PADEL · SAM 14H00 ·
 * TERRAIN 2 », les pastilles des joueurs (avec les places vides en
 * pointillé), et les cartes de créneau et d'événement de club.
 *
 * Refonte 2026-11 : l'accent du sport est rouge (#E02D24). Une carte porte en
 * haut le niveau (pastille rouge douce) et la date (encre) ; puis le
 * surtitre, le club, la ligne « joueurs · prix · organisateur » ; sous un
 * filet, les avatars des inscrits et les places libres en pointillés, et le
 * bouton d'action à flèche.
 */

import React from 'react';
import { Pressable, Text, View } from 'react-native';

import type { Creneau, EvenementSport, JoueurCreneau } from '../api';
import { FORMATS_SPORT, NIVEAUX_SQUAD, SPORTS } from '../catalogue';
import { dateFr, majuscules } from '../format';
import { formatEuros, libellePlaces, prixParJoueur } from '../regles';
import { espace, fs, hauteurBouton, rayon, sans, taille, teintes } from '../theme';
import { useTheme } from '../useTheme';
import { PressableAnime } from './Animations';
import { BoutonAction, Pastille, teinteAvatar } from './Design';
import { Avatar } from './Elements';
import { Icone, type NomIcone } from './Icone';
import { T } from './Texte';

export function libelleSport(code: string): string {
  return SPORTS[code] ?? code;
}

/** « PADEL · SAM 14H00 · TERRAIN 2 » */
export function etiquetteCreneau(c: Pick<Creneau, 'sport' | 'debut' | 'terrain'>): string {
  return `${majuscules(libelleSport(c.sport))} · ${dateFr(c.debut, 'D j M H\\hi')} · ${majuscules(c.terrain.nom)}`;
}

/**
 * Les joueurs d'une partie (cercles de 28 px qui se chevauchent de 8 px,
 * cerclés de la couleur du fond), puis une place en pointillé par place vide.
 * `moi` : l'identifiant de l'utilisateur, dont l'avatar passe au rouge.
 * `maxJoueurs` / `maxVides` : au-delà, les joueurs se résument en « +N »
 * et les places libres ne sont plus dessinées (le texte « N places » le
 * dit) — sur une carte, la pile ne doit jamais passer sous le bouton.
 */
export function PastillesJoueurs({ joueurs, total, taille = 28, moi, fond, maxJoueurs = 99, maxVides = 8 }: {
  joueurs: Pick<JoueurCreneau, 'id' | 'prenom' | 'photo_url'>[];
  total: number;
  taille?: number;
  moi?: number;
  fond?: string;
  maxJoueurs?: number;
  maxVides?: number;
}) {
  const { c, mode } = useTheme();
  const sport = teintes('sport', mode);
  const cerne = fond ?? c.blanc;
  const vides = Math.max(0, total - joueurs.length);
  const visibles = joueurs.length > maxJoueurs ? joueurs.slice(0, maxJoueurs - 1) : joueurs;
  const caches = joueurs.length - visibles.length;
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center' }}>
      {visibles.map((j, i) => (
        <View key={j.id} style={{ marginLeft: i === 0 ? 0 : -8, borderRadius: taille, borderWidth: 2, borderColor: cerne }}>
          <Avatar photo={j.photo_url} prenom={j.prenom} taille={taille} fond={j.id === moi ? sport.accent : teinteAvatar(j.prenom + j.id)} />
        </View>
      ))}
      {caches > 0 ? (
        <View
          accessibilityLabel={`et ${caches} autres joueurs`}
          style={{ marginLeft: -8, minWidth: taille + 4, height: taille + 4, paddingHorizontal: 4, borderRadius: taille, borderWidth: 2, borderColor: cerne, backgroundColor: c.surface2, alignItems: 'center', justifyContent: 'center' }}
        >
          <Text style={{ fontFamily: sans(700), fontSize: 11, color: c.grisFonce }}>+{caches}</Text>
        </View>
      ) : null}
      {Array.from({ length: Math.min(vides, maxVides) }, (_, i) => (
        <View
          key={`vide-${i}`}
          accessibilityLabel="Place libre"
          style={{ marginLeft: joueurs.length === 0 && i === 0 ? 0 : -8, width: taille + 4, height: taille + 4, borderRadius: taille, borderWidth: 1.5, borderStyle: 'dashed', borderColor: c.gris, backgroundColor: cerne, alignItems: 'center', justifyContent: 'center' }}
        >
          <Text style={{ fontFamily: sans(600), fontSize: 12, color: c.gris }}>+</Text>
        </View>
      ))}
    </View>
  );
}

/** L'action en pied de carte : pleine (rouge) ou neutre. */
export function ActionCarte({ libelle, onPress, plein = true, icone }: { libelle: string; onPress?: () => void; plein?: boolean; icone?: NomIcone }) {
  const { c, mode } = useTheme();
  const sport = teintes('sport', mode);
  return (
    <PressableAnime
      onPress={onPress}
      disabled={!onPress}
      accessibilityRole="button"
      style={[
        { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6, minHeight: hauteurBouton.fin, paddingHorizontal: 14, borderRadius: rayon.sm },
        plein ? { backgroundColor: sport.accent } : { backgroundColor: c.surface2 },
      ]}
    >
      {icone ? <Icone nom={icone} taille={14} couleur={plein ? '#FFFFFF' : c.grisFonce} trait={2.5} /> : null}
      <Text style={{ fontFamily: sans(600), fontSize: fs[4], color: plein ? '#FFFFFF' : c.grisFonce }}>{libelle}</Text>
    </PressableAnime>
  );
}

function CadreCarte({ children, onPress, libelleAcces }: { children: React.ReactNode; onPress?: () => void; libelleAcces?: string }) {
  const { c } = useTheme();
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole={onPress ? 'link' : undefined}
      accessibilityLabel={libelleAcces}
      style={{ borderRadius: rayon.carte, padding: espace.carte, marginBottom: espace.entreCartes, backgroundColor: c.blanc, gap: espace.ecart }}
    >
      {children}
    </Pressable>
  );
}

/** Ligne du haut d'une carte : pastille de niveau à gauche, date à droite. */
function Haut({ gauche, date, alerte }: { gauche?: string; date: string; alerte?: boolean }) {
  const { c, mode } = useTheme();
  const sport = teintes('sport', mode);
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
      {gauche ? (
        <Pastille libelle={gauche} fond={alerte ? c.dangerClair : sport.doux} encre={alerte ? c.danger : sport.texteDoux} />
      ) : <View />}
      <Pastille libelle={date} fond={c.noir} encre={c.surEncre} style={{ flexShrink: 1 }} />
    </View>
  );
}

function Surtitre({ children }: { children: React.ReactNode }) {
  const { c } = useTheme();
  return <Text style={{ fontFamily: sans(600), fontSize: 10, letterSpacing: 0.06 * 10, textTransform: 'uppercase', color: c.gris, marginTop: 2 }}>{children}</Text>;
}

function Titre({ children }: { children: React.ReactNode }) {
  const { c } = useTheme();
  return <Text numberOfLines={2} style={{ fontFamily: sans(700), fontSize: taille.titreCarte, lineHeight: taille.titreCarte * 1.2, letterSpacing: -0.02 * taille.titreCarte, color: c.noir }}>{children}</Text>;
}

/** « Mar 17 nov · 19h30 » */
function dateCourte(iso: string) {
  return dateFr(iso, 'D j M · H\\hi');
}

/**
 * Le bouton d'une carte de créneau, décidé par mon rôle dans la partie :
 *   - j'organise : « Gérer la partie » (ouvrir, refermer, annuler) ;
 *   - j'y joue déjà : « Inscrit », coché ;
 *   - partie d'un autre avec des places : « Rejoindre la partie » ;
 *   - terrain libre : « Réserver ».
 */
export function actionCreneau(cr: Pick<Creneau, 'statut' | 'je_joue' | 'je_organise'>): { libelle: string; mode: 'plein' | 'neutre' | 'fait'; icone?: NomIcone } {
  if (cr.statut === 'annule') return { libelle: 'Annulée', mode: 'neutre' };
  if (cr.je_organise) return { libelle: 'Gérer la partie', mode: 'plein', icone: 'reglages' };
  if (cr.je_joue) return { libelle: 'Inscrit', mode: 'fait', icone: 'check' };
  if (cr.statut === 'libre') return { libelle: 'Réserver', mode: 'plein', icone: 'fleche-d' };
  return { libelle: 'Rejoindre la partie', mode: 'plein', icone: 'fleche-d' };
}

/**
 * Un créneau : partie à compléter, terrain libre, ou l'une de mes parties.
 * Le bouton suit mon rôle (actionCreneau) ; il ouvre la fiche du créneau,
 * comme toute la carte.
 */
export function CarteCreneau({ cr, onPress }: { cr: Creneau; onPress: () => void }) {
  const { c, mode } = useTheme();
  const sport = teintes('sport', mode);
  const annule = cr.statut === 'annule';
  const libre = cr.statut === 'libre';
  const action = actionCreneau(cr);
  return (
    <CadreCarte onPress={onPress} libelleAcces={`${libelleSport(cr.sport)} chez ${cr.club.nom}`}>
      <Haut gauche={libre ? undefined : annule ? 'Annulé' : (NIVEAUX_SQUAD[cr.niveau] ?? cr.niveau)} date={dateCourte(cr.debut)} alerte={annule} />
      <View style={{ gap: 1 }}>
        <Surtitre>{libelleSport(cr.sport)} · {cr.terrain.nom}</Surtitre>
        <Titre>{cr.club.nom}</Titre>
        <T taille={fs[3]} couleur={c.gris} numberOfLines={1} style={{ marginTop: 2 }}>
          {cr.places.total} joueurs · {prixParJoueur(cr.prix, cr.places.total)}
          {cr.je_organise ? ' · tu organises' : cr.organisateur && !libre ? ` · par ${cr.organisateur.prenom}` : ''}
        </T>
      </View>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, paddingTop: 10, marginTop: 2, borderTopWidth: 1, borderTopColor: c.grisClair }}>
        <View style={{ gap: 4, flex: 1, minWidth: 0, overflow: 'hidden' }}>
          {libre ? (
            <T taille={fs[3]} poids={500} couleur={c.grisFonce}>Terrain libre · {formatEuros(cr.prix)}</T>
          ) : (
            <>
              <PastillesJoueurs joueurs={cr.joueurs} total={cr.places.total} maxJoueurs={4} maxVides={2} />
              <T taille={fs[2]} couleur={c.gris}>{libellePlaces(cr.places.restantes)}</T>
            </>
          )}
        </View>
        {action.mode === 'plein' ? (
          <BoutonAction fin libelle={action.libelle} icone={action.icone} iconeADroite accent={sport.accent} onPress={onPress} style={{ flex: 0, minWidth: 136 }} />
        ) : (
          <ActionCarte libelle={action.libelle} onPress={onPress} plein={false} icone={action.icone} />
        )}
      </View>
    </CadreCarte>
  );
}

/** Un événement de club : tournoi, stage… Il s'ouvre dans la fiche événement. */
export function CarteEvenementSport({ ev, onPress }: { ev: EvenementSport; onPress: () => void }) {
  const { c, mode } = useTheme();
  const sport = teintes('sport', mode);
  const prix = ev.is_gratuit || ev.prix_normal <= 0 ? 'Gratuit' : formatEuros(ev.prix_normal);
  return (
    <CadreCarte onPress={onPress} libelleAcces={ev.titre}>
      <Haut gauche="Club" date={dateCourte(ev.date_heure)} />
      <View style={{ gap: 1 }}>
        <Surtitre>{libelleSport(ev.sport)} · {FORMATS_SPORT[ev.format] ?? ev.format}</Surtitre>
        <Titre>{ev.titre}</Titre>
        <T taille={fs[3]} couleur={c.gris} numberOfLines={1} style={{ marginTop: 2 }}>{ev.club.nom} · {prix}</T>
      </View>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, paddingTop: 10, marginTop: 2, borderTopWidth: 1, borderTopColor: c.grisClair }}>
        <T taille={fs[3]} couleur={c.gris} style={{ flexShrink: 1 }}>
          {ev.places.quota > 0 ? `${ev.places.inscrits}/${ev.places.quota} inscrits` : `${ev.places.inscrits} inscrits`}
        </T>
        {ev.deja_inscrit
          ? <ActionCarte libelle="Inscrit" plein={false} icone="check" onPress={onPress} />
          : ev.places.complet
            ? <ActionCarte libelle="Complet" plein={false} onPress={onPress} />
            : <BoutonAction fin libelle="S'inscrire" icone="fleche-d" iconeADroite accent={sport.accent} onPress={onPress} style={{ flex: 0, minWidth: 136 }} />}
      </View>
    </CadreCarte>
  );
}

/** Le texte d'un état vide de section. */
export function VideSport({ titre, texte }: { titre: string; texte?: string }) {
  const { c } = useTheme();
  return (
    <View style={{ alignItems: 'center', ...espace.vide, backgroundColor: c.blanc, borderRadius: rayon.carte, marginBottom: espace.entreCartes }}>
      <View style={{ marginBottom: 12 }}><Icone nom="trophee" taille={32} couleur={c.gris} /></View>
      <T taille={fs[5] + 1} poids={700} style={{ textAlign: 'center' }}>{titre}</T>
      {texte ? <T taille={fs[4]} couleur={c.gris} style={{ marginTop: 6, textAlign: 'center' }}>{texte}</T> : null}
    </View>
  );
}
