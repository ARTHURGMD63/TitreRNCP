/**
 * Les éléments de l'univers sport partagés par l'onglet Sport, la fiche d'un
 * créneau et l'espace club : la ligne d'étiquette « PADEL · SAM 14H00 ·
 * TERRAIN 2 », les pastilles des joueurs (avec les places vides en
 * pointillé), et les cartes de créneau et d'événement de club.
 *
 * Même gabarit que les cartes de squad : dôme pour l'accent, niveau en haut à
 * droite, pied séparé par un filet.
 */

import React from 'react';
import { Pressable, Text, View } from 'react-native';

import type { Creneau, EvenementSport, JoueurCreneau } from '../api';
import { FORMATS_SPORT, NIVEAUX_SQUAD, SPORTS } from '../catalogue';
import { dateFr, majuscules } from '../format';
import { formatEuros, libellePlaces, prixParJoueur } from '../regles';
import { fixe, fs, lh, police, rayon, sans } from '../theme';
import { useTheme } from '../useTheme';
import { Avatar } from './Elements';
import { Icone, type NomIcone } from './Icone';
import { Display, Mono, T } from './Texte';

export function libelleSport(code: string): string {
  return SPORTS[code] ?? code;
}

/** « PADEL · SAM 14H00 · TERRAIN 2 » */
export function etiquetteCreneau(c: Pick<Creneau, 'sport' | 'debut' | 'terrain'>): string {
  return `${majuscules(libelleSport(c.sport))} · ${dateFr(c.debut, 'D j M H\\hi')} · ${majuscules(c.terrain.nom)}`;
}

/** Les joueurs d'une partie, puis une pastille en pointillé par place vide. */
export function PastillesJoueurs({ joueurs, total, taille = 30 }: { joueurs: Pick<JoueurCreneau, 'id' | 'prenom' | 'photo_url'>[]; total: number; taille?: number }) {
  const { c } = useTheme();
  const vides = Math.max(0, total - joueurs.length);
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center' }}>
      {joueurs.map((j, i) => (
        <View key={j.id} style={{ marginLeft: i === 0 ? 0 : -8, borderRadius: taille, borderWidth: 2, borderColor: c.blanc }}>
          <Avatar photo={j.photo_url} prenom={j.prenom} taille={taille} fond={c.bleu} />
        </View>
      ))}
      {Array.from({ length: Math.min(vides, 8) }, (_, i) => (
        <View
          key={`vide-${i}`}
          accessibilityLabel="Place libre"
          style={{ marginLeft: joueurs.length === 0 && i === 0 ? 0 : -4, width: taille + 4, height: taille + 4, borderRadius: taille, borderWidth: 1.5, borderStyle: 'dashed', borderColor: c.gris, backgroundColor: c.blanc, alignItems: 'center', justifyContent: 'center' }}
        >
          <Icone nom="plus" taille={12} couleur={c.gris} />
        </View>
      ))}
    </View>
  );
}

/** L'action en pied de carte : pilule pleine (dôme) ou contour. */
export function ActionCarte({ libelle, onPress, plein = true, icone }: { libelle: string; onPress?: () => void; plein?: boolean; icone?: NomIcone }) {
  const { c } = useTheme();
  return (
    <Pressable
      onPress={onPress}
      disabled={!onPress}
      accessibilityRole="button"
      style={({ pressed }) => [
        { flexDirection: 'row', alignItems: 'center', gap: 4, paddingVertical: 11, paddingHorizontal: 18, borderRadius: rayon.pill, borderWidth: 1.5 },
        plein ? { backgroundColor: c.bleu, borderColor: c.bleu } : { backgroundColor: c.surface2, borderColor: c.line2 },
        pressed && onPress ? { transform: [{ scale: 0.98 }] } : null,
      ]}
    >
      {icone ? <Icone nom={icone} taille={14} couleur={plein ? fixe.surLave : c.grisFonce} /> : null}
      <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: plein ? fixe.surLave : c.grisFonce }}>{libelle}</Text>
    </Pressable>
  );
}

function Pastille({ libelle, accent }: { libelle: string; accent?: string }) {
  const { c } = useTheme();
  return (
    <View style={{ borderWidth: 1, borderColor: accent ?? c.line2, borderRadius: rayon.pill, paddingVertical: 4, paddingHorizontal: 10 }}>
      <T taille={fs[2]} poids={500} couleur={accent ?? c.grisFonce}>{libelle}</T>
    </View>
  );
}

function CadreCarte({ children, onPress, libelleAcces }: { children: React.ReactNode; onPress?: () => void; libelleAcces?: string }) {
  const { c, ombre } = useTheme();
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole={onPress ? 'link' : undefined}
      accessibilityLabel={libelleAcces}
      style={[{ borderRadius: rayon.base, padding: 20, marginBottom: 14, borderWidth: 1, borderColor: c.grisClair, backgroundColor: c.blanc, overflow: 'hidden' }, ombre('base')]}
    >
      {children}
    </Pressable>
  );
}

/**
 * Un créneau : partie à compléter, terrain libre, ou l'une de mes parties.
 * `action` est le libellé du bouton (« Compléter », « Réserver », « Voir »).
 */
export function CarteCreneau({ cr, action, onPress }: { cr: Creneau; action: string; onPress: () => void }) {
  const { c } = useTheme();
  const annule = cr.statut === 'annule';
  const libre = cr.statut === 'libre';
  return (
    <CadreCarte onPress={onPress} libelleAcces={`${libelleSport(cr.sport)} chez ${cr.club.nom}`}>
      {!libre ? (
        <View style={{ position: 'absolute', top: 16, right: 16 }}>
          <Pastille libelle={annule ? 'Annulé' : (NIVEAUX_SQUAD[cr.niveau] ?? cr.niveau)} accent={annule ? c.danger : undefined} />
        </View>
      ) : null}
      <Mono couleur={c.surBleuClair} style={{ marginBottom: 6, paddingRight: libre ? 0 : 96 }}>{etiquetteCreneau(cr)}</Mono>
      <Display taille={fs[6]} interligne={lh.tight} style={{ marginBottom: 4, paddingRight: libre ? 0 : 70 }}>{cr.club.nom}</Display>
      <T taille={fs[3]} couleur={c.grisFonce} style={{ marginBottom: 12 }}>
        {cr.places.total} joueurs · {prixParJoueur(cr.prix, cr.places.total)}
        {cr.organisateur && !libre ? ` · par ${cr.organisateur.prenom}` : ''}
      </T>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, paddingTop: 15, borderTopWidth: 1, borderTopColor: c.grisClair }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, flexShrink: 1 }}>
          {libre ? (
            <T taille={fs[3]} poids={500} couleur={c.grisFonce}>Terrain libre · {formatEuros(cr.prix)}</T>
          ) : (
            <>
              <PastillesJoueurs joueurs={cr.joueurs} total={cr.places.total} />
              <T taille={fs[3]} poids={500} couleur={c.grisFonce}>{libellePlaces(cr.places.restantes)}</T>
            </>
          )}
        </View>
        <ActionCarte libelle={action} onPress={onPress} plein={!annule && !cr.je_joue} icone={cr.je_joue && !annule ? 'check' : undefined} />
      </View>
    </CadreCarte>
  );
}

/** Un événement de club : tournoi, stage… Il s'ouvre dans la fiche événement. */
export function CarteEvenementSport({ ev, onPress }: { ev: EvenementSport; onPress: () => void }) {
  const { c } = useTheme();
  const prix = ev.is_gratuit || ev.prix_normal <= 0 ? 'Gratuit' : formatEuros(ev.prix_normal);
  return (
    <CadreCarte onPress={onPress} libelleAcces={ev.titre}>
      <View style={{ position: 'absolute', top: 16, right: 16 }}>
        <Pastille libelle="Club" />
      </View>
      <Mono couleur={c.surBleuClair} style={{ marginBottom: 6, paddingRight: 70 }}>
        {majuscules(libelleSport(ev.sport))} · {majuscules(FORMATS_SPORT[ev.format] ?? ev.format)} · {dateFr(ev.date_heure, 'D j M H\\hi')}
      </Mono>
      <Display taille={fs[6]} interligne={lh.tight} style={{ marginBottom: 4, paddingRight: 50 }}>{ev.titre}</Display>
      <T taille={fs[3]} couleur={c.grisFonce} style={{ marginBottom: 12 }}>{ev.club.nom} · {prix}</T>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, paddingTop: 15, borderTopWidth: 1, borderTopColor: c.grisClair }}>
        <T taille={fs[3]} poids={500} couleur={c.grisFonce}>
          {ev.places.quota > 0 ? `${ev.places.inscrits}/${ev.places.quota} inscrits` : `${ev.places.inscrits} inscrits`}
        </T>
        {ev.deja_inscrit
          ? <ActionCarte libelle="Inscrit" plein={false} icone="check" onPress={onPress} />
          : ev.places.complet
            ? <ActionCarte libelle="Complet" plein={false} onPress={onPress} />
            : <ActionCarte libelle="S'inscrire" onPress={onPress} />}
      </View>
    </CadreCarte>
  );
}

/** Le texte d'un état vide de section. */
export function VideSport({ titre, texte }: { titre: string; texte?: string }) {
  const { c } = useTheme();
  return (
    <View style={{ alignItems: 'center', paddingVertical: 36 }}>
      <View style={{ marginBottom: 12 }}><Icone nom="trophee" taille={36} couleur={c.gris} /></View>
      <Text style={{ fontFamily: police.display, fontSize: fs[5], color: c.gris, textAlign: 'center' }}>{titre}</Text>
      {texte ? <T taille={fs[3]} couleur={c.gris} style={{ marginTop: 6, textAlign: 'center' }}>{texte}</T> : null}
    </View>
  );
}
