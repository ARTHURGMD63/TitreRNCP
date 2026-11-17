/**
 * Écrire à l'équipe Outsee depuis l'application.
 *
 * Ouvre le client de messagerie du téléphone avec l'adresse et un objet déjà
 * remplis. Si le téléphone n'a aucune application de messagerie configurée,
 * l'adresse s'affiche dans une alerte : elle reste lisible et copiable, au
 * lieu d'un bouton qui ne fait rien.
 */

import { Alert, Linking } from 'react-native';

export const ADRESSE_CONTACT = 'contact@outsee.fr';

export async function ecrireALEquipe(depuis?: string | null): Promise<void> {
  const objet = encodeURIComponent('Outsee — une question');
  const corps = encodeURIComponent(depuis ? `\n\n—\nCompte : ${depuis}` : '');
  const lien = `mailto:${ADRESSE_CONTACT}?subject=${objet}&body=${corps}`;

  try {
    if (await Linking.canOpenURL(lien)) {
      await Linking.openURL(lien);
      return;
    }
  } catch {
    // Retombe sur l'alerte ci-dessous.
  }
  Alert.alert('Écris-nous', `Aucune application de messagerie n'est configurée sur ce téléphone.\n\nÉcris-nous à ${ADRESSE_CONTACT}.`);
}
