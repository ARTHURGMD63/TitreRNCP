/**
 * confirm() du site : la même question, « Annuler » ou « OK ».
 */

import { Alert } from 'react-native';

export function confirmer(message: string): Promise<boolean> {
  return new Promise((resolve) => {
    Alert.alert('', message, [
      { text: 'Annuler', style: 'cancel', onPress: () => resolve(false) },
      { text: 'OK', style: 'destructive', onPress: () => resolve(true) },
    ], { cancelable: true, onDismiss: () => resolve(false) });
  });
}
