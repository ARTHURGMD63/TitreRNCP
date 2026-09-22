/**
 * L'etat vide, partage par tous les ecrans.
 *
 * Il existe pour une raison precise : sur le site, « Rien de neuf pour
 * l'instant. » etait ecrit en 13 px et en gris tertiaire, comme une legende
 * posee a cote d'autre chose. Or quand une liste est vide, cette phrase EST le
 * contenu — elle occupe seule un ecran de telephone. Un composant unique evite
 * que chaque ecran ne refasse ce reglage a sa facon.
 */

import Feather from '@expo/vector-icons/Feather';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { useTheme } from '../useTheme';
import { espace, rayon, taille } from '../theme';

type Props = {
  icone: React.ComponentProps<typeof Feather>['name'];
  titre: string;
  texte?: string;
  action?: { libelle: string; onPress: () => void };
};

export function Vide({ icone, titre, texte, action }: Props) {
  const { c } = useTheme();

  return (
    <View style={s.bloc}>
      <View style={[s.rond, { backgroundColor: c.surface2, borderColor: c.grisClair }]}>
        <Feather name={icone} size={26} color={c.gris} />
      </View>

      <Text style={[s.titre, { color: c.noir }]}>{titre}</Text>

      {texte !== undefined && (
        <Text style={[s.texte, { color: c.grisFonce }]}>{texte}</Text>
      )}

      {action !== undefined && (
        <Pressable
          onPress={action.onPress}
          style={({ pressed }) => [
            s.bouton,
            { backgroundColor: c.noir, opacity: pressed ? 0.85 : 1 },
          ]}
        >
          <Text style={[s.boutonTexte, { color: c.bg }]}>{action.libelle}</Text>
        </Pressable>
      )}
    </View>
  );
}

const s = StyleSheet.create({
  bloc: { alignItems: 'center', paddingVertical: espace.xxl, paddingHorizontal: espace.lg },
  rond: {
    width: 64,
    height: 64,
    borderRadius: 32,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: espace.md,
  },
  titre: { fontSize: taille.titre, fontWeight: '900', textAlign: 'center' },
  // 16 pt, pas 13 : c'est le contenu de l'ecran, pas une note de bas de page.
  texte: {
    fontSize: taille.corps,
    textAlign: 'center',
    marginTop: espace.sm,
    lineHeight: 22,
  },
  bouton: {
    marginTop: espace.lg,
    borderRadius: rayon.bouton,
    paddingHorizontal: espace.lg,
    paddingVertical: espace.base,
  },
  boutonTexte: { fontSize: taille.texte, fontWeight: '700' },
});
