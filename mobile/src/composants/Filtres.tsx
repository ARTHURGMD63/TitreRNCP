/**
 * Les rangees de filtres du hub, et le selecteur segmente.
 *
 * ── LE BUG QUE CE FICHIER CORRIGE ──────────────────────────────────────────
 *
 * La premiere version posait le ScrollView horizontal directement dans la
 * colonne de l'ecran, sans contrainte de hauteur. Sur le web il s'affichait
 * correctement ; sur iPhone, la FlatList qui le suit reclamait toute la place
 * et l'ecrasait — les pastilles restaient visibles, mais leur texte etait
 * rogne en hauteur. A l'ecran, cela donnait des traits a la place des mots :
 * on voyait le milieu des lettres, et rien d'autre.
 *
 * `flexGrow: 0` et `flexShrink: 0` disent que cette rangee ne prend ni ne cede
 * de hauteur : elle vaut exactement son contenu. C'est la correction, et c'est
 * aussi pourquoi les filtres vivent desormais dans leur propre composant —
 * une rangee defilante a des contraintes qu'on oublie des qu'elle est melee au
 * reste d'un ecran.
 */

import { ScrollView, StyleSheet, Text, Pressable, View } from 'react-native';

import { useTheme } from '../useTheme';
import { espace, rayon, taille } from '../theme';

// ─── Selecteur segmente ──────────────────────────────────────────────────────

/**
 * ÉVÉNEMENTS / PERSONNES — la pilule claire qui glisse sur un fond neutre,
 * dans le registre des controles segmentes d'iOS. Transposition de .hub-toggle.
 */
export function SelecteurSegmente<T extends string>({
  options,
  valeur,
  surChangement,
}: {
  options: { code: T; libelle: string }[];
  valeur: T;
  surChangement: (code: T) => void;
}) {
  const { c } = useTheme();

  return (
    <View style={[s.segmente, { backgroundColor: c.surface2, borderColor: c.grisClair }]}>
      {options.map((o) => {
        const actif = o.code === valeur;
        return (
          <Pressable
            key={o.code}
            onPress={() => surChangement(o.code)}
            style={[
              s.segment,
              actif && { backgroundColor: c.bg, borderColor: c.grisClair },
            ]}
          >
            <Text
              style={{
                color: actif ? c.noir : c.gris,
                fontSize: taille.base,
                fontWeight: '700',
                letterSpacing: 0.5,
              }}
            >
              {o.libelle}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}

// ─── Rangée de pastilles ─────────────────────────────────────────────────────

export function RangeeFiltres<T extends string>({
  options,
  valeur,
  surChangement,
  variante = 'type',
}: {
  options: { code: T; libelle: string }[];
  valeur: T;
  surChangement: (code: T) => void;
  /** « type » = pastille pleine quand active ; « musique » = contour colore. */
  variante?: 'type' | 'musique';
}) {
  const { c } = useTheme();

  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      // Voir l'en-tete : sans ces deux lignes, la rangee est ecrasee par la
      // liste qui la suit et le texte des pastilles est rogne en hauteur.
      style={s.rangee}
      contentContainerStyle={s.rangeeContenu}
    >
      {options.map((o) => {
        const actif = o.code === valeur;

        const fond = actif
          ? variante === 'musique'
            ? c.noir
            : c.noir
          : c.blanc;
        const bord = actif ? c.noir : variante === 'musique' ? c.grisClair : c.grisClair;
        const texte = actif ? c.bg : c.grisFonce;

        return (
          <Pressable
            key={o.code}
            onPress={() => surChangement(o.code)}
            style={({ pressed }) => [
              s.pastille,
              { backgroundColor: fond, borderColor: bord, opacity: pressed ? 0.75 : 1 },
            ]}
          >
            <Text
              // numberOfLines : un libelle long — « Latino / Reggaeton » — doit
              // etre tronque plutot que de passer a la ligne et de doubler la
              // hauteur de toute la rangee.
              numberOfLines={1}
              style={{ color: texte, fontSize: taille.base, fontWeight: '700' }}
            >
              {o.libelle}
            </Text>
          </Pressable>
        );
      })}
    </ScrollView>
  );
}

const s = StyleSheet.create({
  segmente: {
    flexDirection: 'row',
    borderRadius: rayon.pill,
    borderWidth: 1,
    padding: 3,
    marginHorizontal: espace.lg,
  },
  segment: {
    flex: 1,
    alignItems: 'center',
    paddingVertical: espace.sm,
    borderRadius: rayon.pill,
    borderWidth: 1,
    borderColor: 'transparent',
  },

  // La rangée vaut exactement la hauteur de son contenu : elle ne s'étire pas
  // et ne se laisse pas comprimer.
  rangee: { flexGrow: 0, flexShrink: 0 },
  rangeeContenu: {
    paddingHorizontal: espace.lg,
    gap: espace.sm,
    alignItems: 'center',
  },
  pastille: {
    borderWidth: 1,
    borderRadius: rayon.pill,
    paddingHorizontal: espace.md,
    // Hauteur minimale explicite : une pastille doit rester tapable au doigt,
    // et ne pas dependre de la hauteur que le texte se trouve occuper.
    minHeight: 36,
    justifyContent: 'center',
  },
});
