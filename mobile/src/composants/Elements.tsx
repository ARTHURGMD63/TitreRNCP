/**
 * Les petits composants de la charte, chacun calqué sur sa classe CSS :
 * .pill, .badge, avatarHtml(), .hub-toggle, .progress-bar, .section-divider,
 * .bouton-retour, .card, .encart-ok / .encart-info / .form-error.
 */

import React from 'react';
import { Image, Pressable, Text, View, type StyleProp, type ViewStyle } from 'react-native';
import { router } from 'expo-router';

import { fixe, fs, lsEm, mono, police, rayon, sans, toucheMin } from '../theme';
import { useTheme } from '../useTheme';
import { Icone, type NomIcone } from './Icone';
import { T } from './Texte';

// ─── .pill ──────────────────────────────────────────────────────────────────

export function Pilule({ libelle, actif, onPress, icone, accent, musique, style, couleurTexte }: {
  libelle: string;
  actif: boolean;
  onPress: () => void;
  icone?: NomIcone;
  /** Couleur de la pilule active : lave, ou dôme dans l'univers sport. */
  accent?: string;
  /** .pill-musique : semi-gras même au repos. */
  musique?: boolean;
  style?: StyleProp<ViewStyle>;
  /** « Pour moi » au repos : contour et texte en lave lisible. */
  couleurTexte?: string;
}) {
  const { c } = useTheme();
  const plein = accent ?? c.rouge;
  const encre = actif ? fixe.surLave : couleurTexte ?? c.noir;
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityState={{ selected: actif }}
      hitSlop={{ top: 4, bottom: 4 }}
      style={({ pressed }) => [
        {
          flexDirection: 'row', alignItems: 'center', gap: 6,
          paddingVertical: 10, paddingHorizontal: 17, borderRadius: rayon.pill, borderWidth: 1,
          borderColor: actif ? plein : c.line2, backgroundColor: actif ? plein : 'transparent',
          transform: pressed ? [{ scale: 0.98 }] : [],
        },
        style,
      ]}
    >
      {icone ? <View style={musique ? { marginRight: 4 } : null}><Icone nom={icone} taille={16} couleur={encre} trait={2} /></View> : null}
      <Text style={{ fontFamily: sans(actif || musique ? 600 : 500), fontSize: fs[4], color: encre }}>{libelle}</Text>
    </Pressable>
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
      <Text style={{ fontFamily: police.display, fontSize: corps, lineHeight: corps * 1.2, color: fixe.surLave }}>
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
        { width: toucheMin, height: toucheMin, borderRadius: toucheMin / 2, alignItems: 'center', justifyContent: 'center', borderWidth: 1 },
        media ? { backgroundColor: fixe.basalte, borderColor: fixe.basalte } : { backgroundColor: c.blanc, borderColor: c.grisClair },
        style,
      ]}
    >
      <Icone nom="fleche-g" taille={20} couleur={media ? fixe.craie : c.noir} />
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
