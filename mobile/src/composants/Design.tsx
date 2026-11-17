/**
 * Les composants partagés de la refonte 2026-11 (« Outsee — mode clair /
 * mode sombre ») : en-tête, pastilles, bouton d'action principale, avatars
 * empilés, tuiles, panneau Filtres.
 *
 * Purement visuels : chacun reçoit ses données et ses actions de l'écran,
 * qui garde sa logique telle quelle.
 */

import React from 'react';
import { Text, View, type StyleProp, type ViewStyle } from 'react-native';

import { fixe, fs, gutter, halo, rayon, sans, teintes, type Rubrique } from '../theme';
import { useTheme } from '../useTheme';
import { Confettis, paletteConfettis, PressableAnime } from './Animations';
import { Avatar } from './Elements';
import { Feuille } from './Feuille';
import { Icone, type NomIcone } from './Icone';
import { Marque } from './Marque';
import { T } from './Texte';

/** La couleur de la rubrique, et ses compagnes, pour le thème courant. */
export function useRubrique(r: Rubrique) {
  const { mode } = useTheme();
  return teintes(r, mode);
}

// ─── En-tête ────────────────────────────────────────────────────────────────

/** Bouton d'icône de l'en-tête : carré arrondi surface de 42 px, rayon 12. */
export function BoutonIcone({ icone, libelle, onPress, pastille, etendu, style }: {
  icone: NomIcone;
  libelle: string;
  onPress: () => void;
  /** Point rouge : une notification non lue. */
  pastille?: boolean;
  etendu?: boolean;
  style?: StyleProp<ViewStyle>;
}) {
  const { c } = useTheme();
  return (
    <PressableAnime
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={pastille ? `${libelle}, non lues` : libelle}
      accessibilityState={etendu === undefined ? undefined : { expanded: etendu }}
      style={[{ width: 42, height: 42, borderRadius: rayon.sm, backgroundColor: c.blanc, alignItems: 'center', justifyContent: 'center' }, style]}
    >
      <Icone nom={icone} taille={19} couleur={c.noir} />
      {pastille ? (
        <View style={{ position: 'absolute', top: 9, right: 10, width: 9, height: 9, borderRadius: 5, backgroundColor: fixe.notif, borderWidth: 1.5, borderColor: c.blanc }} />
      ) : null}
    </PressableAnime>
  );
}

/**
 * L'en-tête d'un écran à onglet : le logo à gauche (couleur de la rubrique),
 * Recherche et Notifications à droite. Chaque bouton n'apparaît que si
 * l'écran sait déjà faire l'action correspondante.
 */
export function EnTeteApp({ rubrique = 'bleu', onRecherche, rechercheOuverte, onNotifications, nonLues, action, style }: {
  rubrique?: Rubrique;
  onRecherche?: () => void;
  rechercheOuverte?: boolean;
  onNotifications?: () => void;
  nonLues?: boolean;
  /** Action principale de l'écran, posée avant la cloche (« + Créer » sur Sport). */
  action?: { libelle: string; accessibilityLabel?: string; icone: NomIcone; onPress: () => void };
  style?: StyleProp<ViewStyle>;
}) {
  const teinte = useRubrique(rubrique);
  return (
    <View style={[{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12, marginBottom: 14 }, style]}>
      <Marque taille={fs[5] + 2} rubrique={rubrique} />
      <View style={{ flexDirection: 'row', gap: 10 }}>
        {action ? (
          <PressableAnime
            onPress={action.onPress}
            accessibilityRole="button"
            accessibilityLabel={action.accessibilityLabel ?? action.libelle}
            style={{ height: 42, flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: 14, borderRadius: rayon.sm, backgroundColor: teinte.accent }}
          >
            <Icone nom={action.icone} taille={18} couleur="#FFFFFF" trait={2.5} />
            <Text style={{ fontFamily: sans(700), fontSize: fs[4], color: '#FFFFFF' }}>{action.libelle}</Text>
          </PressableAnime>
        ) : null}
        {onRecherche ? <BoutonIcone icone="loupe" libelle="Rechercher" onPress={onRecherche} etendu={!!rechercheOuverte} /> : null}
        {onNotifications ? <BoutonIcone icone="cloche" libelle="Notifications" onPress={onNotifications} pastille={nonLues} /> : null}
      </View>
    </View>
  );
}

// ─── Pastilles ──────────────────────────────────────────────────────────────

/**
 * Pastille de statut, de date, de niveau, de prix : rayon 8, 12 px ;
 * `grande` (panneau Filtres) : rayon 12, 13 px.
 * `point` dessine le rond « En cours » à gauche.
 */
export function Pastille({ libelle, fond, encre, point, icone, filet, grande, petite, style }: {
  /** Ancienne option : la pastille est fine par défaut depuis le 2026-11-16. */
  petite?: boolean;
  libelle: string;
  fond: string;
  encre: string;
  point?: string;
  icone?: NomIcone;
  filet?: string;
  grande?: boolean;
  style?: StyleProp<ViewStyle>;
}) {
  return (
    <View
      style={[
        {
          flexDirection: 'row', alignItems: 'center', gap: 6, alignSelf: 'flex-start',
          paddingVertical: grande ? 6 : 3, paddingHorizontal: grande ? 10 : 8, borderRadius: grande ? rayon.sm : 8, backgroundColor: fond,
        },
        filet ? { borderWidth: 1, borderColor: filet } : null,
        style,
      ]}
    >
      {point ? <View style={{ width: 7, height: 7, borderRadius: 4, backgroundColor: point }} /> : null}
      {icone ? <Icone nom={icone} taille={grande ? 14 : 12} couleur={encre} /> : null}
      <Text numberOfLines={1} style={{ fontFamily: sans(500), fontSize: grande ? fs[3] : fs[2], color: encre }}>{libelle}</Text>
    </View>
  );
}

// ─── Bouton d'action principale ─────────────────────────────────────────────

/**
 * L'action principale d'une carte (« S'inscrire », « Compléter ») : un
 * bouton plein (44 px) avec un carré de 34 px qui porte l'icône.
 *
 *  - sur une carte blanche : bouton encre, carré blanc ;
 *  - sur une carte accent : bouton blanc, texte et carré de l'accent ;
 *  - `fait` (« Inscrit ») : fond de l'accent, carré blanc avec la coche.
 *
 * `confettis` : chaque nouvelle valeur (> 0) lance l'explosion depuis le
 * bouton — à n'incrémenter qu'après la réussite de l'action réelle.
 */
export function BoutonAction({ libelle, onPress, icone = 'chevron-d', surAccent, accent, fait, desactive, chargement, iconeADroite, confettis = 0, fin, style, accessibilityLabel, testID }: {
  /**
   * Version fine des cartes (événements, Sport) : 40 px de haut, carré de
   * 30, et largeur ajustée au libellé au lieu de remplir la rangée.
   */
  fin?: boolean;
  /** Repère des tests de bout en bout (Maestro). */
  testID?: string;
  libelle: string;
  onPress?: () => void;
  icone?: NomIcone;
  surAccent?: boolean;
  accent: string;
  fait?: boolean;
  desactive?: boolean;
  chargement?: boolean;
  iconeADroite?: boolean;
  confettis?: number;
  style?: StyleProp<ViewStyle>;
  accessibilityLabel?: string;
}) {
  const { c } = useTheme();
  let fond = c.noir;
  let encre = c.surEncre;
  let carre = c.surEncre;
  let encreCarre = c.noir;
  if (surAccent) { fond = '#FFFFFF'; encre = accent; carre = accent; encreCarre = '#FFFFFF'; }
  if (fait) {
    if (surAccent) { fond = '#FFFFFF'; encre = accent; carre = accent; encreCarre = '#FFFFFF'; }
    else { fond = accent; encre = '#FFFFFF'; carre = '#FFFFFF'; encreCarre = accent; }
  }
  const inactif = desactive || chargement;
  const attenue = desactive && !fait;
  return (
    <View style={[fin ? { flexShrink: 1 } : { flex: 1 }, style]}>
      <PressableAnime
        onPress={onPress}
        disabled={inactif}
        testID={testID}
        accessibilityRole="button"
        accessibilityLabel={accessibilityLabel ?? libelle}
        accessibilityState={{ disabled: !!inactif, busy: !!chargement, checked: fait }}
        style={{
          flexDirection: iconeADroite ? 'row-reverse' : 'row', alignItems: 'center', gap: fin ? 8 : 10,
          minHeight: fin ? 40 : 44, padding: 5, paddingRight: iconeADroite ? 5 : fin ? 16 : 14, paddingLeft: iconeADroite ? 16 : 5,
          borderRadius: fin ? rayon.sm : rayon.md, backgroundColor: attenue ? c.surface2 : fond,
          borderWidth: fait && surAccent ? 0 : 0,
        }}
      >
        <View style={{ width: fin ? 30 : 34, height: fin ? 30 : 34, borderRadius: fin ? 9 : 10, backgroundColor: attenue ? c.grisClair : carre, alignItems: 'center', justifyContent: 'center' }}>
          <Icone nom={fait ? 'check' : icone} taille={fin ? 16 : 18} couleur={attenue ? c.gris : encreCarre} trait={2.5} />
        </View>
        <Text numberOfLines={1} style={{ ...(fin ? { flexShrink: 1 } : { flex: 1 }), fontFamily: sans(700), fontSize: fin ? fs[4] : fs[4] + 1, color: attenue ? c.grisFonce : encre }}>
          {chargement ? '…' : libelle}
        </Text>
      </PressableAnime>
      <Confettis declencheur={confettis} couleurs={paletteConfettis(accent)} />
    </View>
  );
}

/**
 * Le bouton secondaire d'une carte (« Inviter ») : contour, icône à gauche.
 * `iconeSeule` : un carré de 40 px, le libellé ne servant qu'aux lecteurs
 * d'écran (cartes d'événement).
 */
export function BoutonContour({ libelle, icone, onPress, surAccent, iconeSeule, style }: {
  iconeSeule?: boolean;
  libelle: string;
  icone?: NomIcone;
  onPress: () => void;
  surAccent?: boolean;
  style?: StyleProp<ViewStyle>;
}) {
  const { c } = useTheme();
  const encre = surAccent ? '#FFFFFF' : c.noir;
  return (
    <PressableAnime
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={libelle}
      style={[{
        flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8,
        minHeight: iconeSeule ? 40 : 44, width: iconeSeule ? 40 : undefined, paddingHorizontal: iconeSeule ? 0 : 14,
        borderRadius: iconeSeule ? rayon.sm : rayon.md, borderWidth: 1.5, borderColor: surAccent ? 'rgba(255,255,255,0.45)' : c.line2,
      }, style]}
    >
      {icone ? <Icone nom={icone} taille={18} couleur={encre} /> : null}
      {iconeSeule ? null : <Text style={{ fontFamily: sans(600), fontSize: fs[4] + 1, color: encre }}>{libelle}</Text>}
    </PressableAnime>
  );
}

// ─── Avatars empilés ────────────────────────────────────────────────────────

const TEINTES_AVATAR = ['#CDBBF7', '#F9C6D3', '#FFD9A0', '#C7DCFF', '#C9F0D6', '#FFE58A', '#D9D9E0'];

/** Une teinte douce stable pour un prénom : les initiales se lisent en encre. */
export function teinteAvatar(cle: string): string {
  let h = 0;
  for (const ch of cle) h = (h * 31 + ch.charCodeAt(0)) >>> 0;
  return TEINTES_AVATAR[h % TEINTES_AVATAR.length];
}

/**
 * Cercles de 26 à 28 px qui se chevauchent de 8 px, cerclés de la couleur
 * du fond. `libres` ajoute des places vides en pointillés avec un « + ».
 * `mise` colore une place (l'avatar de l'utilisateur qui vient de rejoindre).
 */
export function AvatarsEmpiles({ personnes, libres = 0, taille = 28, fond, maxLibres = 3 }: {
  personnes: { cle: string; prenom: string; photo?: string | null; fond?: string }[];
  libres?: number;
  taille?: number;
  /** La couleur sur laquelle la pile est posée (le contour de 2 px). */
  fond: string;
  maxLibres?: number;
}) {
  const { c } = useTheme();
  const vides = Math.max(0, Math.min(libres, maxLibres));
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center' }} accessible={false}>
      {personnes.map((p, i) => (
        <View key={p.cle} style={{ marginLeft: i === 0 ? 0 : -8, borderRadius: taille, borderWidth: 2, borderColor: fond }}>
          <Avatar photo={p.photo} prenom={p.prenom} taille={taille} fond={p.fond ?? teinteAvatar(p.prenom + p.cle)} />
        </View>
      ))}
      {Array.from({ length: vides }, (_, i) => (
        <View
          key={`libre${i}`}
          style={{
            marginLeft: personnes.length === 0 && i === 0 ? 0 : -8, width: taille + 4, height: taille + 4, borderRadius: taille,
            borderWidth: 1.5, borderStyle: 'dashed', borderColor: c.gris, backgroundColor: fond, alignItems: 'center', justifyContent: 'center',
          }}
        >
          <Text style={{ fontFamily: sans(600), fontSize: 12, color: c.gris }}>+</Text>
        </View>
      ))}
    </View>
  );
}

/** « Nathan et 3 potes y vont », si la donnée existe. */
export function QuiYVa({ prenoms, nb, surAccent, fond }: { prenoms: string[]; nb: number; surAccent?: boolean; fond: string }) {
  const { c } = useTheme();
  if (!nb || !prenoms.length) return null;
  const autres = nb - 1;
  const encre = surAccent ? '#FFFFFF' : c.noir;
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
      <AvatarsEmpiles personnes={prenoms.slice(0, 3).map((p, i) => ({ cle: `${p}${i}`, prenom: p }))} fond={fond} taille={20} />
      <T taille={fs[3]} couleur={encre} style={{ flex: 1 }} numberOfLines={1}>
        <T taille={fs[3]} poids={700} couleur={encre}>{prenoms[0]}</T>
        {autres > 0 ? ` et ${autres} pote${autres > 1 ? 's' : ''} y vont` : ' y va'}
      </T>
    </View>
  );
}

// ─── Cartes et tuiles ───────────────────────────────────────────────────────

/**
 * La carte d'écran : surface blanche (rayon 20, padding 14), ou aplat de
 * l'accent avec son halo pour la 1ʳᵉ carte de la liste.
 */
export function CarteRefonte({ accent, children, style, rayonCarte = rayon.carte }: {
  accent?: string;
  children: React.ReactNode;
  style?: StyleProp<ViewStyle>;
  rayonCarte?: number;
}) {
  const { c } = useTheme();
  return (
    <View
      style={[
        { backgroundColor: accent ?? c.blanc, borderRadius: rayonCarte, padding: 14, gap: 8, marginBottom: 16 },
        accent ? halo(accent) : null,
        style,
      ]}
    >
      {children}
    </View>
  );
}

/**
 * La tuile (stats, raccourcis Sport) : rayon 16, icône et compteur. Active,
 * elle passe à l'encre.
 */
export function Tuile({ icone, libelle, compteur, actif, onPress, accentCompteur, droite, style }: {
  icone: NomIcone;
  libelle: string;
  compteur?: number;
  actif?: boolean;
  onPress?: () => void;
  /** Couleur du compteur de la tuile active (le rouge de Sport). */
  accentCompteur?: string;
  /** Remplace le compteur : un chevron pour une tuile de navigation. */
  droite?: NomIcone;
  style?: StyleProp<ViewStyle>;
}) {
  const { c } = useTheme();
  const encre = actif ? c.surEncre : c.noir;
  return (
    <PressableAnime
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={compteur !== undefined ? `${libelle}, ${compteur}` : libelle}
      accessibilityState={{ selected: !!actif }}
      style={[{ minHeight: 78, padding: 12, borderRadius: rayon.md, backgroundColor: actif ? c.noir : c.blanc, justifyContent: 'space-between', gap: 10 }, style]}
    >
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
        <Icone nom={icone} taille={20} couleur={encre} />
        {compteur !== undefined ? (
          <View style={{ minWidth: 28, height: 24, paddingHorizontal: 7, borderRadius: 12, alignItems: 'center', justifyContent: 'center', backgroundColor: actif ? accentCompteur ?? c.rouge : accentCompteur ? `${accentCompteur}22` : c.surface2 }}>
            <Text style={{ fontFamily: sans(700), fontSize: fs[3], color: actif ? '#FFFFFF' : accentCompteur ?? c.noir }}>{compteur}</Text>
          </View>
        ) : droite ? <Icone nom={droite} taille={20} couleur={encre} /> : null}
      </View>
      <Text numberOfLines={2} style={{ fontFamily: sans(600), fontSize: fs[4], lineHeight: fs[4] * 1.25, color: encre }}>{libelle}</Text>
    </PressableAnime>
  );
}

/** Un carré d'icône coloré doux (tuiles de Moi). */
export function CarreIcone({ icone, fond, encre, taille = 44 }: { icone: NomIcone; fond: string; encre: string; taille?: number }) {
  return (
    <View style={{ width: taille, height: taille, borderRadius: 14, backgroundColor: fond, alignItems: 'center', justifyContent: 'center' }}>
      <Icone nom={icone} taille={taille * 0.48} couleur={encre} />
    </View>
  );
}

// ─── Panneau Filtres ────────────────────────────────────────────────────────

/**
 * Le panneau Filtres : un voile, puis le panneau qui monte du bas, avec sa
 * poignée, « Filtres », la croix, le titre de section, les options en
 * pastilles (l'active à l'encre) et « Appliquer » de la couleur de la
 * rubrique. Le choix reste en brouillon jusqu'à « Appliquer », qui le
 * transmet à l'écran — lequel le filtre avec sa logique de toujours.
 */
export function PanneauFiltres<K extends string>({ visible, onClose, section, options, valeur, onAppliquer, accent }: {
  visible: boolean;
  onClose: () => void;
  section: string;
  options: { code: K; libelle: string }[];
  valeur: K;
  onAppliquer: (code: K) => void;
  accent: string;
}) {
  const { c } = useTheme();
  const [brouillon, setBrouillon] = React.useState(valeur);
  const [vu, setVu] = React.useState(visible);
  // À chaque ouverture, le brouillon repart du filtre en vigueur.
  if (visible !== vu) {
    setVu(visible);
    if (visible) setBrouillon(valeur);
  }
  return (
    <Feuille visible={visible} onClose={onClose}>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 18 }}>
        <T taille={24} poids={700} accessibilityRole="header" style={{ letterSpacing: -0.03 * 24 }}>Filtres</T>
        <PressableAnime onPress={onClose} accessibilityRole="button" accessibilityLabel="Fermer les filtres"
          style={{ width: 44, height: 44, borderRadius: rayon.bouton, backgroundColor: c.surface2, alignItems: 'center', justifyContent: 'center' }}>
          <Icone nom="croix" taille={20} couleur={c.noir} />
        </PressableAnime>
      </View>
      <T taille={fs[2]} poids={600} couleur={c.gris} style={{ textTransform: 'uppercase', letterSpacing: 0.06 * fs[2], marginBottom: 12 }}>{section}</T>
      <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 24 }}>
        {options.map((o) => {
          const actif = brouillon === o.code;
          return (
            <PressableAnime
              key={o.code}
              onPress={() => setBrouillon(o.code)}
              accessibilityRole="radio"
              accessibilityState={{ selected: actif }}
              style={{ minHeight: 44, paddingHorizontal: 16, justifyContent: 'center', borderRadius: rayon.bouton, backgroundColor: actif ? c.noir : c.surface2 }}
            >
              <Text style={{ fontFamily: sans(500), fontSize: fs[5], color: actif ? c.surEncre : c.noir }}>{o.libelle}</Text>
            </PressableAnime>
          );
        })}
      </View>
      <PressableAnime
        onPress={() => { onAppliquer(brouillon); onClose(); }}
        accessibilityRole="button"
        style={{ minHeight: 46, borderRadius: rayon.md, backgroundColor: accent, alignItems: 'center', justifyContent: 'center' }}
      >
        <Text style={{ fontFamily: sans(700), fontSize: fs[5] + 1, color: '#FFFFFF' }}>Appliquer</Text>
      </PressableAnime>
    </Feuille>
  );
}

/** Gouttière horizontale des rangées de chips (la dernière déborde, coupée). */
export const rangeeChips = { gap: 8, paddingHorizontal: gutter, paddingBottom: 4 } as const;
