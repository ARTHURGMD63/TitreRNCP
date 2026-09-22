/**
 * Squads — en attente de son point d'API.
 *
 * L'ecran existe pour que l'onglet ne mene pas dans le vide, et il dit
 * exactement ou en est le portage. Un onglet qui ouvre une page blanche se lit
 * comme une panne ; celui-ci annonce un chantier.
 */

import { View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useTheme } from '../../useTheme';
import { Vide } from '../../composants/Vide';

export default function Squads() {
  const { c } = useTheme();
  const marges = useSafeAreaInsets();

  return (
    <View style={{ flex: 1, backgroundColor: c.bg, paddingTop: marges.top }}>
      <Vide
        icone="users"
        titre="Squads"
        texte="Le portage de cet écran arrive : il attend son point d'API. Les squads restent accessibles depuis le site."
      />
    </View>
  );
}
