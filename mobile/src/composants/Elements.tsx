/**
 * Les petits composants de la charte, chacun calqué sur sa classe CSS :
 * .pill, .badge, avatarHtml(), .hub-toggle, .progress-bar, .section-divider,
 * .bouton-retour, .card, .encart-ok / .encart-info / .form-error.
 */

import React from 'react';
import { Image, Pressable, Text, View, type StyleProp, type ViewStyle } from 'react-native';
import { router } from 'expo-router';

import { encreSur, fixe, fs, lsEm, mono, police, rayon, sans, toucheMin } from '../theme';
import { useTheme } from '../useTheme';
import { PressableAnime } from './Animations';
import { Icone, type NomIcone } from './Icone';
import { T } from './Texte';

// ─── Chip de filtre ─────────────────────────────────────────────────────────

/**
 * La chip de filtre de la refonte : fond surface sans bordure au repos,
 * encre (texte inversé) une fois choisie, rayon 16, 44 px de haut au moins.
 *
 *  - `carre` : le petit carré coloré (28 px, rayon 10) qui porte l'icône de
 *    la catégorie en blanc ;
 *  - `contour` : la chip « Filtres », bordée d'encre.
 *
 * `accent`, `musique` et `couleurTexte` sont acceptés pour les écrans pas
 * encore redessinés (partenaire, admin), sans effet sur le dessin.
 */
export function Pilule({ libelle, actif, onPress, icone, carre, contour, style, accessibilityLabel }: {
  libelle: string;
  actif: boolean;
  onPress: () => void;
  icone?: NomIcone;
  carre?: string;
  contour?: boolean;
  accent?: string;
  musique?: boolean;
  style?: StyleProp<ViewStyle>;
  couleurTexte?: string;
  accessibilityLabel?: string;
}) {
  const { c } = useTheme();
  const encre = actif ? c.surEncre : c.noir;
  return (
    <PressableAnime
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? libelle}
      accessibilityState={{ selected: actif }}
      style={[
        {
          flexDirection: 'row', alignItems: 'center', gap: 10,
          minHeight: 48, paddingVertical: carre ? 8 : 12, paddingLeft: carre ? 8 : 18, paddingRight: 18,
          borderRadius: rayon.bouton, borderWidth: 1.5,
          borderColor: contour ? c.noir : actif ? c.noir : c.blanc,
          backgroundColor: actif ? c.noir : contour ? 'transparent' : c.blanc,
        },
        style,
      ]}
    >
      {carre && icone ? (
        <View style={{ width: 28, height: 28, borderRadius: rayon.mini, backgroundColor: carre, alignItems: 'center', justifyContent: 'center' }}>
          <Icone nom={icone} taille={16} couleur="#FFFFFF" trait={2} />
        </View>
      ) : icone ? (
        <Icone nom={icone} taille={18} couleur={encre} trait={2} />
      ) : null}
      <Text style={{ fontFamily: sans(500), fontSize: fs[5], color: encre }}>{libelle}</Text>
    </PressableAnime>
  );
}

// ─── .badge ─────────────────────────────────────────────────────────────────

export function Badge({ libelle, fond, encre, filet, icone, espacement = lsEm.label, style }: {
  libelle: string;
  fond: string;
  encre: string;
  filet?: string;
  icone?: NomIcone;
  espacement?: number;
  style?: StyleProp<ViewStyle>;
}) {
  return (
    <View
      style={[
        { flexDirection: 'row', alignItems: 'center', gap: 5, paddingVertical: 4, paddingHorizontal: 10, borderRadius: rayon.pill, backgroundColor: fond },
        filet ? { borderWidth: 1, borderColor: filet } : null,
        style,
      ]}
    >
      {icone ? <Icone nom={icone} taille={16} couleur={encre} /> : null}
      <Text style={{ fontFamily: mono(500), fontSize: fs[1], letterSpacing: espacement * fs[1], textTransform: 'uppercase', color: encre }}>
        {libelle}
      </Text>
    </View>
  );
}

// ─── avatarHtml() ───────────────────────────────────────────────────────────

/**
 * La photo, ou l'initiale sur un rond de couleur — le repli d'avatarHtml() :
 * Unbounded, basalte, corps de 40 % du diamètre (11 px au minimum).
 */
export function Avatar({ photo, prenom, taille = 48, fond, anneau }: {
  photo?: string | null;
  prenom: string;
  taille?: number;
  fond?: string;
  /** box-shadow d'anneau (.avatar dans une pile, podium). */
  anneau?: { couleur: string; epaisseur: number };
}) {
  const { c } = useTheme();
  const cadre = {
    width: taille, height: taille, borderRadius: taille / 2,
    ...(anneau ? { borderWidth: anneau.epaisseur, borderColor: anneau.couleur } : null),
  };
  if (photo) {
    return <Image source={{ uri: photo }} accessibilityLabel={`Photo de ${prenom}`} style={cadre} />;
  }
  const corps = Math.max(11, Math.round(taille * 0.4));
  return (
    <View accessible={false} style={[cadre, { backgroundColor: fond ?? c.bleu, alignItems: 'center', justifyContent: 'center' }]}>
      <Text style={{ fontFamily: police.display, fontSize: corps, lineHeight: corps * 1.2, color: encreSur(fond ?? c.bleu) }}>
        {(prenom || '?').charAt(0).toUpperCase()}
      </Text>
    </View>
  );
}

// ─── .hub-toggle ────────────────────────────────────────────────────────────

export function Segments<K extends string>({ options, valeur, onChange, style }: {
  options: { code: K; libelle: string }[];
  valeur: K;
  onChange: (code: K) => void;
  style?: StyleProp<ViewStyle>;
}) {
  const { c } = useTheme();
  return (
    <View accessibilityRole="tablist" style={[{ flexDirection: 'row', backgroundColor: c.blanc, padding: 5, borderRadius: rayon.pill }, style]}>
      {options.map((o) => {
        const actif = o.code === valeur;
        return (
          <Pressable
            key={o.code}
            onPress={() => onChange(o.code)}
            accessibilityRole="tab"
            accessibilityState={{ selected: actif }}
            style={{ flex: 1, minHeight: toucheMin, paddingVertical: 12, paddingHorizontal: 6, borderRadius: rayon.pill, alignItems: 'center', justifyContent: 'center', backgroundColor: actif ? c.noir : 'transparent' }}
          >
            <Text numberOfLines={1} style={{ fontFamily: sans(600), fontSize: fs[4], color: actif ? c.bg : c.gris }}>{o.libelle}</Text>
          </Pressable>
        );
      })}
    </View>
  );
}

// ─── .progress-bar ──────────────────────────────────────────────────────────

export function Jauge({ pourcentage, piste, remplissage, hauteur = 6, style }: {
  pourcentage: number;
  piste: string;
  remplissage: string;
  hauteur?: number;
  style?: StyleProp<ViewStyle>;
}) {
  const p = Math.max(0, Math.min(100, pourcentage));
  return (
    <View style={[{ height: hauteur, backgroundColor: piste, borderRadius: rayon.pill, overflow: 'hidden' }, style]}>
      <View style={{ width: `${p}%`, height: '100%', backgroundColor: remplissage, borderRadius: rayon.pill }} />
    </View>
  );
}

// ─── .section-divider ───────────────────────────────────────────────────────

export function Separateur({ libelle, style }: { libelle?: string; style?: StyleProp<ViewStyle> }) {
  const { c } = useTheme();
  return (
    <View style={[{ flexDirection: 'row', alignItems: 'center', gap: 12, marginTop: 22, marginBottom: 14 }, style]}>
      <View style={{ flex: 1, height: 1, backgroundColor: c.line2 }} />
      {libelle ? (
        <Text accessibilityRole="header" style={{ fontFamily: mono(500), fontSize: fs[1], letterSpacing: lsEm.label * fs[1], textTransform: 'uppercase', color: c.gris }}>
          {libelle}
        </Text>
      ) : null}
      {libelle ? <View style={{ flex: 1, height: 1, backgroundColor: c.line2 }} /> : null}
    </View>
  );
}

// ─── .bouton-retour ─────────────────────────────────────────────────────────

export function BoutonRetour({ media, vers, style, libelle = 'Retour' }: {
  media?: boolean;
  /** Route explicite ; sinon retour dans l'historique. */
  vers?: string;
  style?: StyleProp<ViewStyle>;
  libelle?: string;
}) {
  const { c } = useTheme();
  return (
    <Pressable
      onPress={() => (vers ? router.navigate(vers as never) : router.canGoBack() ? router.back() : router.navigate('/'))}
      accessibilityRole="button"
      accessibilityLabel={libelle}
      style={[
        // Refonte 2026-11 : carré arrondi de 46 px, comme les boutons d'en-tête.
        { width: 46, height: 46, borderRadius: rayon.bouton, alignItems: 'center', justifyContent: 'center' },
        media ? { backgroundColor: fixe.basalte } : { backgroundColor: c.blanc },
        style,
      ]}
    >
      <Icone nom="chevron-g" taille={22} couleur={media ? fixe.craie : c.noir} />
    </Pressable>
  );
}

// ─── .card ──────────────────────────────────────────────────────────────────

export function Carte({ children, style }: { children: React.ReactNode; style?: StyleProp<ViewStyle> }) {
  const { c, ombre } = useTheme();
  return (
    <View style={[{ backgroundColor: c.blanc, borderRadius: rayon.base, borderWidth: 1, borderColor: c.grisClair, marginBottom: 14, overflow: 'hidden' }, ombre('sm'), style]}>
      {children}
    </View>
  );
}

// ─── .encart-ok, .encart-info, .form-error ──────────────────────────────────

export function Encart({ genre, children, style }: {
  genre: 'ok' | 'info' | 'erreur' | 'alerte' | 'succes';
  children: React.ReactNode;
  style?: StyleProp<ViewStyle>;
}) {
  const { c } = useTheme();
  if (genre === 'ok') {
    return (
      <View accessibilityRole="alert" style={[{ flexDirection: 'row', alignItems: 'flex-start', gap: 12, backgroundColor: c.blanc, borderWidth: 1, borderColor: c.grisClair, borderRadius: rayon.md, paddingVertical: 16, paddingHorizontal: 18, marginBottom: 24 }, style]}>
        <View style={{ width: 9, height: 9, borderRadius: 5, backgroundColor: c.lime, marginTop: 7 }} />
        <View style={{ flex: 1 }}>{typeof children === 'string' ? <T taille={fs[4]} interligne={1.5}>{children}</T> : children}</View>
      </View>
    );
  }
  if (genre === 'info') {
    return (
      <View style={[{ backgroundColor: c.surface2, borderWidth: 1, borderStyle: 'dashed', borderColor: c.line2, borderRadius: rayon.md, padding: 16, marginBottom: 24 }, style]}>
        {children}
      </View>
    );
  }
  const teinte = genre === 'alerte' ? { fond: c.alerteClair, encre: c.alerte } : genre === 'succes' ? { fond: c.succesClair, encre: c.succes } : { fond: c.dangerClair, encre: c.danger };
  return (
    <View accessibilityRole="alert" style={[{ backgroundColor: teinte.fond, borderWidth: 1, borderColor: teinte.encre, borderRadius: rayon.sm, paddingVertical: 13, paddingHorizontal: 16, marginBottom: 16 }, style]}>
      {typeof children === 'string' ? <T taille={fs[4]} couleur={teinte.encre}>{children}</T> : children}
    </View>
  );
}
