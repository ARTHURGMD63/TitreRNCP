/**
 * Le theme courant, suivant le reglage clair/sombre du telephone.
 *
 * Le site retient le choix dans localStorage parce qu'un navigateur n'expose
 * pas toujours la preference du systeme. Un telephone, si : iOS et Android ont
 * un reglage global, souvent programme sur l'heure. Le suivre est ce que
 * l'utilisateur attend — et c'est pour cela qu'app.json declare
 * « userInterfaceStyle: automatic ».
 */

import { useColorScheme } from 'react-native';

import { couleurs, ombre, type Couleurs, type Mode } from './theme';

export function useMode(): Mode {
  // useColorScheme() rend null tant que le systeme n'a rien dit : on ne bascule
  // pas en sombre sur une incertitude.
  return useColorScheme() === 'dark' ? 'sombre' : 'clair';
}

export function useTheme(): {
  mode: Mode;
  c: Couleurs;
  sombre: boolean;
  ombre: (niveau?: 'sm' | 'base' | 'lg') => ReturnType<typeof ombre>;
} {
  const mode = useMode();

  return {
    mode,
    c: couleurs[mode],
    sombre: mode === 'sombre',
    ombre: (niveau = 'base') => ombre(mode, niveau),
  };
}
