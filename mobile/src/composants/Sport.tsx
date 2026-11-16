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
import { fs, rayon, sans, teintes } from '../theme';
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
 */
export function PastillesJoueurs({ joueurs, total, taille = 28, moi, fond }: {
  joueurs: Pick<JoueurCreneau, 'id' | 'prenom' | 'photo_url'>[];
  total: number;
  taille?: number;
  moi?: number;
  fond?: string;
}) {
  const { c, mode } = useTheme();
  const sport = teintes('sport', mode);
  const cerne = fond ?? c.blanc;
  const vides = Math.max(0, total - joueurs.length);
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center' }}>
      {joueurs.map((j, i) => (
        <View key={j.id} style={{ marginLeft: i === 0 ? 0 : -8, borderRadius: taille, borderWidth: 2, borderColor: cerne }}>
          <Avatar photo={j.photo_url} prenom={j.prenom} taille={taille} fond={j.id === moi ? sport.accent : teinteAvatar(j.prenom + j.id)} />
        </View>
      ))}
      {Array.from({ length: Math.min(vides, 8) }, (_, i) => (
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
        { flexDirection: 'row', alignItems: 'center', gap: 6, minHeight: 44, paddingHorizontal: 18, borderRadius: rayon.bouton },
        plein ? { backgroundColor: sport.accent } : { backgroundColor: c.surface2 },
      ]}
    >
      {icone ? <Icone nom={icone} taille={16} couleur={plein ? '#FFFFFF' : c.grisFonce} trait={2.5} /> : null}
      <Text style={{ fontFamily: sans(700), fontSize: fs[5], color: plein ? '#FFFFFF' : c.grisFonce }}>{libelle}</Text>
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
      style={{ borderRadius: rayon.carte, padding: 20, marginBottom: 16, backgroundColor: c.blanc, gap: 10 }}
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
  return <Text style={{ fontFamily: sans(600), fontSize: 12, letterSpacing: 0.06 * 12, textTransform: 'uppercase', color: c.gris, marginTop: 4 }}>{children}</Text>;
}

function Titre({ children }: { children: React.ReactNode }) {
  const { c } = useTheme();
  return <Text style={{ fontFamily: sans(700), fontSize: 24, lineHeight: 24 * 1.15, letterSpacing: -0.03 * 24, color: c.noir }}>{children}</Text>;
}

/** « Mar 17 nov · 19h30 » */
function dateCourte(iso: string) {
  return dateFr(iso, 'D j M · H\\hi');
}

/**
 * Un créneau : partie à compléter, terrain libre, ou l'une de mes parties.
 * `action` est le libellé du bouton (« Compléter », « Réserver », « Voir ») ;
 * il ouvre la fiche du créneau, comme toute la carte.
 */
export function CarteCreneau({ cr, action, onPress }: { cr: Creneau; action: string; onPress: () => void }) {
  const { c, mode } = useTheme();
  const sport = teintes('sport', mode);
  const annule = cr.statut === 'annule';
  const libre = cr.statut === 'libre';
  return (
    <CadreCarte onPress={onPress} libelleAcces={`${libelleSport(cr.sport)} chez ${cr.club.nom}`}>
      <Haut gauche={libre ? undefined : annule ? 'Annulé' : (NIVEAUX_SQUAD[cr.niveau] ?? cr.niveau)} date={dateCourte(cr.debut)} alerte={annule} />
      <View style={{ gap: 4 }}>
        <Surtitre>{libelleSport(cr.sport)} · {cr.terrain.nom}</Surtitre>
        <Titre>{cr.club.nom}</Titre>
        <T taille={fs[5]} couleur={c.gris}>
          {cr.places.total} joueurs · {prixParJoueur(cr.prix, cr.places.total)}
          {cr.organisateur && !libre ? ` · par ${cr.organisateur.prenom}` : ''}
        </T>
      </View>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12, paddingTop: 14, marginTop: 4, borderTopWidth: 1, borderTopColor: c.grisClair }}>
        <View style={{ gap: 6, flexShrink: 1 }}>
          {libre ? (
            <T taille={fs[4]} poids={500} couleur={c.grisFonce}>Terrain libre · {formatEuros(cr.prix)}</T>
          ) : (
            <>
              <PastillesJoueurs joueurs={cr.joueurs} total={cr.places.total} />
              <T taille={fs[4]} couleur={c.gris}>{libellePlaces(cr.places.restantes)}</T>
            </>
          )}
        </View>
        {annule || cr.je_joue ? (
          <ActionCarte libelle={action} onPress={onPress} plein={false} icone={cr.je_joue && !annule ? 'check' : undefined} />
        ) : (
          <BoutonAction libelle={action} icone="fleche-d" iconeADroite accent={sport.accent} onPress={onPress} style={{ flex: 0, minWidth: 172 }} />
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
      <View style={{ gap: 4 }}>
        <Surtitre>{libelleSport(ev.sport)} · {FORMATS_SPORT[ev.format] ?? ev.format}</Surtitre>
        <Titre>{ev.titre}</Titre>
        <T taille={fs[5]} couleur={c.gris}>{ev.club.nom} · {prix}</T>
      </View>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12, paddingTop: 14, marginTop: 4, borderTopWidth: 1, borderTopColor: c.grisClair }}>
        <T taille={fs[4]} couleur={c.gris} style={{ flexShrink: 1 }}>
          {ev.places.quota > 0 ? `${ev.places.inscrits}/${ev.places.quota} inscrits` : `${ev.places.inscrits} inscrits`}
        </T>
        {ev.deja_inscrit
          ? <ActionCarte libelle="Inscrit" plein={false} icone="check" onPress={onPress} />
          : ev.places.complet
            ? <ActionCarte libelle="Complet" plein={false} onPress={onPress} />
            : <BoutonAction libelle="S'inscrire" icone="fleche-d" iconeADroite accent={sport.accent} onPress={onPress} style={{ flex: 0, minWidth: 172 }} />}
      </View>
    </CadreCarte>
  );
}

/** Le texte d'un état vide de section. */
export function VideSport({ titre, texte }: { titre: string; texte?: string }) {
  const { c } = useTheme();
  return (
    <View style={{ alignItems: 'center', paddingVertical: 32, paddingHorizontal: 20, backgroundColor: c.blanc, borderRadius: rayon.carte, marginBottom: 16 }}>
      <View style={{ marginBottom: 12 }}><Icone nom="trophee" taille={32} couleur={c.gris} /></View>
      <T taille={fs[5] + 1} poids={700} style={{ textAlign: 'center' }}>{titre}</T>
      {texte ? <T taille={fs[4]} couleur={c.gris} style={{ marginTop: 6, textAlign: 'center' }}>{texte}</T> : null}
    </View>
  );
}
