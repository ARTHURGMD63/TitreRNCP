/**
 * Les champs de formulaire de la charte (.form-group, select, .case,
 * .filtre-annuaire, le sélecteur de centres d'intérêt).
 *
 *  - L'étiquette : Onest 500 à 14 (fs[4]), en gris, sans capitales.
 *  - Le champ : surface, filet fin, rayon 12, corps 16 (anti-zoom iOS sur le
 *    site, confort de lecture ici). Au focus, filet d'accent et halo clair
 *    de 3 px, dessiné autour sans déplacer la mise en page.
 *  - Un <select> ouvre une feuille listant les choix : le sélecteur natif
 *    d'un téléphone n'a ni la police ni les couleurs de la charte.
 */

import React, { useRef, useState } from 'react';
import { Platform, Pressable, Text, TextInput, View, type StyleProp, type TextInputProps, type ViewStyle } from 'react-native';
import DateTimePicker, { DateTimePickerAndroid } from '@react-native-community/datetimepicker';

import { fixe, fs, hauteurBouton, lh, rayon, sans } from '../theme';
import { useTheme } from '../useTheme';
import { Feuille } from './Feuille';
import { Icone } from './Icone';
import { Display, T } from './Texte';

// ─── Étiquette ──────────────────────────────────────────────────────────────

export function Etiquette({ children, style }: { children: React.ReactNode; style?: object }) {
  const { c } = useTheme();
  return (
    <Text style={[{ fontFamily: sans(500), fontSize: fs[4], color: c.gris, marginBottom: 8 }, style]}>
      {children}
    </Text>
  );
}

export function Aide({ children, style }: { children: React.ReactNode; style?: object }) {
  const { c } = useTheme();
  return <T taille={fs[2]} couleur={c.gris} interligne={lh.normal} style={[{ marginTop: 7 }, style]}>{children}</T>;
}

// ─── Cadre commun d'un champ, avec l'anneau de focus ────────────────────────

function Cadre({ focus, children, style, arrondi = rayon.bouton }: { focus: boolean; children: React.ReactNode; style?: StyleProp<ViewStyle>; arrondi?: number }) {
  const { c } = useTheme();
  return (
    <View style={style}>
      {focus ? (
        <View pointerEvents="none" style={{ position: 'absolute', top: -3, left: -3, right: -3, bottom: -3, borderRadius: arrondi + 3, borderWidth: 3, borderColor: c.rougeClair }} />
      ) : null}
      {children}
    </View>
  );
}

// ─── .form-group input / textarea ───────────────────────────────────────────

type ChampProps = TextInputProps & {
  etiquette?: string;
  aide?: string;
  multiligne?: boolean;
  style?: StyleProp<ViewStyle>;
  marge?: number;
};

export function Champ({ etiquette, aide, multiligne, style, marge = 16, ...input }: ChampProps) {
  const { c } = useTheme();
  const [focus, setFocus] = useState(false);
  return (
    <View style={[{ marginBottom: marge }, style]}>
      {etiquette ? <Etiquette>{etiquette}</Etiquette> : null}
      <Cadre focus={focus}>
        <TextInput
          placeholderTextColor={c.gris}
          {...input}
          multiline={multiligne}
          textAlignVertical={multiligne ? 'top' : 'center'}
          onFocus={(e) => { setFocus(true); input.onFocus?.(e); }}
          onBlur={(e) => { setFocus(false); input.onBlur?.(e); }}
          style={{
            // Refonte 2026-11 : même champ que les menus déroulants — fond
            // gris clair, fin contour (visible sur une carte comme sur le
            // fond de page), rayon 16, 52 px de haut.
            paddingVertical: 11, paddingHorizontal: 14, borderWidth: 1, borderRadius: rayon.sm,
            borderColor: focus ? c.rouge : c.line2, backgroundColor: c.surface2, color: c.noir,
            fontFamily: sans(400), fontSize: fs[4] + 1, minHeight: multiligne ? 88 : 46,
          }}
        />
      </Cadre>
      {aide ? <Aide>{aide}</Aide> : null}
    </View>
  );
}

// ─── <select> ───────────────────────────────────────────────────────────────

export type Option = { valeur: string; libelle: string };

export function Selecteur({ etiquette, valeur, options, onChange, titre, style, apparence = 'champ', marge = 16 }: {
  etiquette?: string;
  valeur: string;
  options: Option[];
  onChange: (v: string) => void;
  /** Titre de la feuille ; l'étiquette par défaut. */
  titre?: string;
  style?: StyleProp<ViewStyle>;
  /** « filtre » : la pilule des filtres de l'annuaire (.filtre-annuaire). */
  apparence?: 'champ' | 'filtre';
  marge?: number;
}) {
  const { c } = useTheme();
  const [ouvert, setOuvert] = useState(false);
  const choisi = options.find((o) => o.valeur === valeur) ?? options[0];

  const filtreActif = apparence === 'filtre' && valeur !== '';
  // Refonte 2026-11 : menus surface sans bordure, rayon 16, 52 px de haut ;
  // un filtre choisi passe à l'encre. Le champ de formulaire est en
  // surface secondaire.
  const bouton = apparence === 'filtre'
    ? {
        conteneur: { minHeight: 42, paddingVertical: 9, paddingLeft: 14, paddingRight: 32, borderRadius: rayon.sm, backgroundColor: filtreActif ? c.noir : c.blanc },
        texte: { fontFamily: sans(500), fontSize: fs[4], color: filtreActif ? c.surEncre : c.noir },
        chevron: filtreActif ? c.surEncre : c.noir,
      }
    : {
        conteneur: { minHeight: 46, paddingVertical: 11, paddingLeft: 14, paddingRight: 36, borderRadius: rayon.sm, borderWidth: 1, borderColor: c.line2, backgroundColor: c.surface2 },
        texte: { fontFamily: sans(500), fontSize: fs[4] + 1, color: c.noir },
        chevron: c.noir,
      };

  return (
    <View style={[apparence === 'champ' ? { marginBottom: marge } : null, style]}>
      {etiquette ? <Etiquette>{etiquette}</Etiquette> : null}
      <Pressable
        onPress={() => setOuvert(true)}
        accessibilityRole="combobox"
        accessibilityLabel={etiquette ?? titre}
        accessibilityValue={{ text: choisi?.libelle }}
        style={[bouton.conteneur, { justifyContent: 'center' }]}
      >
        <Text numberOfLines={1} style={bouton.texte}>{choisi?.libelle ?? ''}</Text>
        <View pointerEvents="none" style={{ position: 'absolute', right: 14, top: 0, bottom: 0, justifyContent: 'center' }}>
          <Icone nom="chevron-b" taille={16} couleur={bouton.chevron} trait={2} />
        </View>
      </Pressable>

      <Feuille visible={ouvert} onClose={() => setOuvert(false)}>
        <Display taille={fs[5] + 2} style={{ marginBottom: 14 }}>{titre ?? etiquette ?? ''}</Display>
        <View style={{ gap: 8 }}>
          {options.map((o) => {
            const actif = o.valeur === valeur;
            return (
              <Pressable
                key={o.valeur}
                onPress={() => { onChange(o.valeur); setOuvert(false); }}
                accessibilityRole="button"
                accessibilityState={{ selected: actif }}
                style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', minHeight: 44, paddingVertical: 10, paddingHorizontal: 14, borderRadius: rayon.sm, backgroundColor: actif ? c.noir : c.surface2 }}
              >
                <T taille={fs[4] + 1} poids={actif ? 600 : 500} couleur={actif ? c.surEncre : c.noir}>{o.libelle}</T>
                {actif ? <Icone nom="check" taille={16} couleur={c.surEncre} /> : null}
              </Pressable>
            );
          })}
        </View>
      </Feuille>
    </View>
  );
}

// ─── .case (case à cocher) ──────────────────────────────────────────────────

export function Case({ coche, onChange, children, style }: {
  coche: boolean;
  onChange: (v: boolean) => void;
  children: React.ReactNode;
  style?: StyleProp<ViewStyle>;
}) {
  const { c } = useTheme();
  return (
    <Pressable
      onPress={() => onChange(!coche)}
      accessibilityRole="checkbox"
      accessibilityState={{ checked: coche }}
      style={[{ flexDirection: 'row', alignItems: 'center', gap: 9, minHeight: 44 }, style]}
    >
      <View style={{ width: 19, height: 19, borderRadius: 6, borderWidth: 1.5, borderColor: coche ? c.rouge : c.line2, backgroundColor: coche ? c.rouge : c.blanc, alignItems: 'center', justifyContent: 'center' }}>
        {coche ? <Icone nom="check" taille={13} couleur={fixe.surAccent} trait={3.2} /> : null}
      </View>
      <View style={{ flex: 1 }}>
        {typeof children === 'string' ? <T taille={fs[4]} couleur={c.grisFonce}>{children}</T> : children}
      </View>
    </Pressable>
  );
}

// ─── Sélecteur de centres d'intérêt ─────────────────────────────────────────

/**
 * Le comportement de selecteurInteretsHtml() et d'app.js : ce qu'on vient de
 * cocher remonte en tête, et au-delà de quatre choix le surplus se replie
 * derrière un « +N ». Replier n'est pas décocher.
 */
export function SelecteurInterets({ catalogue, choisis, onChange, max = 4 }: {
  catalogue: string[];
  choisis: string[];
  onChange: (l: string[]) => void;
  max?: number;
}) {
  const { c } = useTheme();
  const [deplie, setDeplie] = useState(false);

  // Coché d'abord, dans l'ordre des choix ; le reste dans l'ordre du catalogue.
  const nonCoches = catalogue.filter((i) => !choisis.includes(i));
  const surplus = choisis.length - max;
  const visiblesCoches = surplus > 0 && !deplie ? choisis.slice(0, max) : choisis;

  const basculer = (i: string) => {
    if (choisis.includes(i)) {
      const reste = choisis.filter((x) => x !== i);
      onChange(reste);
      if (reste.length <= max) setDeplie(false);
    } else {
      onChange([...choisis, i]);
    }
  };

  const etiquette = (i: string, coche: boolean) => (
    <Pressable
      key={i}
      onPress={() => basculer(i)}
      accessibilityRole="checkbox"
      accessibilityState={{ checked: coche }}
      style={{ minHeight: 44, justifyContent: 'center', paddingHorizontal: 16, borderRadius: rayon.bouton, backgroundColor: coche ? c.noir : c.surface2 }}
    >
      <Text style={{ fontFamily: sans(500), fontSize: fs[3], color: coche ? c.surEncre : c.noir }}>{i}</Text>
    </Pressable>
  );

  return (
    <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8, alignItems: 'center' }}>
      {visiblesCoches.map((i) => etiquette(i, true))}
      {surplus > 0 ? (
        <Pressable
          onPress={() => setDeplie(!deplie)}
          accessibilityRole="button"
          accessibilityLabel={deplie ? "Replier les centres d'intérêt" : `Afficher ${surplus} centre${surplus > 1 ? 's' : ''} d'intérêt de plus`}
          style={{ minWidth: 46, minHeight: 44, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 15, borderRadius: rayon.bouton, borderWidth: 1.5, borderStyle: 'dashed', borderColor: c.line2 }}
        >
          <Text style={{ fontFamily: sans(600), fontSize: fs[3], color: c.grisFonce }}>{deplie ? '−' : '+' + surplus}</Text>
        </Pressable>
      ) : null}
      {nonCoches.map((i) => etiquette(i, false))}
    </View>
  );
}

// ─── Date (date de naissance, date et heure d'un squad) ─────────────────────

function deuxChiffres(n: number) {
  return n < 10 ? '0' + n : String(n);
}
/** « 2000-01-31 » */
export function isoDate(d: Date) {
  return `${d.getFullYear()}-${deuxChiffres(d.getMonth() + 1)}-${deuxChiffres(d.getDate())}`;
}
/** « 2026-09-23T20:30 », le format de <input type="datetime-local">. */
export function isoDateHeure(d: Date) {
  return `${isoDate(d)}T${deuxChiffres(d.getHours())}:${deuxChiffres(d.getMinutes())}`;
}

export function ChampDate({ etiquette, valeur, onChange, mode = 'date', min, max, aide, marge = 16, placeholder }: {
  etiquette?: string;
  valeur: Date | null;
  onChange: (d: Date) => void;
  mode?: 'date' | 'datetime';
  min?: Date;
  max?: Date;
  aide?: string;
  marge?: number;
  placeholder?: string;
}) {
  const { c, sombre } = useTheme();
  const [ouvert, setOuvert] = useState(false);
  const [brouillon, setBrouillon] = useState<Date>(valeur ?? max ?? new Date());
  // La date validée, en attente que la feuille soit partie (voir onFerme).
  const aAppliquer = useRef<Date | null>(null);

  const texte = valeur
    ? mode === 'date'
      ? `${deuxChiffres(valeur.getDate())}/${deuxChiffres(valeur.getMonth() + 1)}/${valeur.getFullYear()}`
      : `${deuxChiffres(valeur.getDate())}/${deuxChiffres(valeur.getMonth() + 1)}/${valeur.getFullYear()} ${deuxChiffres(valeur.getHours())}:${deuxChiffres(valeur.getMinutes())}`
    : placeholder ?? (mode === 'date' ? 'jj/mm/aaaa' : 'jj/mm/aaaa --:--');

  const ouvrir = () => {
    if (Platform.OS === 'android') {
      // Android : deux boîtes système successives (date, puis heure).
      DateTimePickerAndroid.open({
        value: valeur ?? brouillon, mode: 'date', minimumDate: min, maximumDate: max,
        onChange: (ev, d) => {
          if (ev.type !== 'set' || !d) return;
          if (mode === 'date') { onChange(d); return; }
          DateTimePickerAndroid.open({
            value: d, mode: 'time', is24Hour: true,
            onChange: (ev2, h) => { if (ev2.type === 'set' && h) onChange(h); },
          });
        },
      });
      return;
    }
    setBrouillon(valeur ?? max ?? new Date());
    setOuvert(true);
  };

  return (
    <View style={{ marginBottom: marge }}>
      {etiquette ? <Etiquette>{etiquette}</Etiquette> : null}
      <Pressable
        onPress={ouvrir}
        accessibilityRole="button"
        accessibilityLabel={etiquette}
        style={{ minHeight: 46, justifyContent: 'center', paddingVertical: 11, paddingHorizontal: 14, borderWidth: 1, borderRadius: rayon.sm, borderColor: c.line2, backgroundColor: c.surface2 }}
      >
        <Text style={{ fontFamily: sans(400), fontSize: fs[4] + 1, color: valeur ? c.noir : c.gris }}>{texte}</Text>
      </Pressable>
      {aide ? <Aide>{aide}</Aide> : null}

      {/* La date validée n'est remise au formulaire qu'une fois la feuille
          partie (onFerme). Avant, elle l'était tout de suite : à l'inscription,
          une date entre 18 et 25 ans fait apparaître École et Promo, dont les
          feuilles naissaient pendant que celle-ci était encore présentée ; sur
          iOS, elles ne s'ouvraient alors plus jamais. */}
      <Feuille visible={ouvert} onClose={() => { aAppliquer.current = null; setOuvert(false); }}
        onFerme={() => { const d = aAppliquer.current; aAppliquer.current = null; if (d) onChange(d); }}>
        {etiquette ? <Display taille={fs[5] + 2} style={{ marginBottom: 8 }}>{etiquette}</Display> : null}
        <DateTimePicker
          value={brouillon}
          mode={mode === 'date' ? 'date' : 'datetime'}
          display="spinner"
          locale="fr-FR"
          minimumDate={min}
          maximumDate={max}
          themeVariant={sombre ? 'dark' : 'light'}
          textColor={c.noir}
          onChange={(_, d) => d && setBrouillon(d)}
        />
        <Pressable
          onPress={() => { aAppliquer.current = brouillon; setOuvert(false); }}
          accessibilityRole="button"
          style={{ marginTop: 12, minHeight: hauteurBouton.plein, borderRadius: rayon.md, backgroundColor: c.rouge, alignItems: 'center', justifyContent: 'center' }}
        >
          <Text style={{ fontFamily: sans(700), fontSize: fs[4] + 1, color: fixe.surAccent }}>Valider</Text>
        </Pressable>
      </Feuille>
    </View>
  );
}
