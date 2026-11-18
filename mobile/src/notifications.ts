/**
 * Notifications push : permission, jeton de l'appareil, réaction au toucher.
 *
 * Trois règles :
 *
 *  1. LA PERMISSION SE DEMANDE UNE FOIS, POUR UNE RAISON QUE LA PERSONNE
 *     COMPREND — jamais au lancement. iOS ne repose pas la question après un
 *     refus : une demande à froid, refusée par réflexe, est perdue pour de bon.
 *     Elle part donc d'un bouton explicite (Moi, ou la page Notifications).
 *
 *  2. LE JETON SUIT LE COMPTE. Il est rattaché au serveur à chaque ouverture
 *     de session (il peut changer), et retiré à la déconnexion : le prochain
 *     à se connecter sur ce téléphone ne reçoit pas les messages du
 *     précédent.
 *
 *  3. L'APPLICATION NE NAVIGUE QUE VERS CE QU'ELLE CONNAÎT. `routeSure()`
 *     (pushRegles.ts) filtre l'adresse portée par la notification.
 *
 * Ce qui part, à qui et quand : tout est décidé côté serveur
 * (includes/push.php) — préférences, nuit, plafonds.
 */

import { Platform } from 'react-native';
import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import * as SecureStore from 'expo-secure-store';

import { api } from './api';
import { routeSure, type EtatPermission } from './pushRegles';

const CLE_JETON_PUSH = 'outsee.jetonPush';

/** Les canaux Android : un par catégorie, pour que chacune se règle dans le système. */
const CANAUX: { id: string; nom: string; importance: Notifications.AndroidImportance }[] = [
  { id: 'social', nom: 'Abonnements et invitations', importance: Notifications.AndroidImportance.HIGH },
  { id: 'rappels', nom: 'Rappels de soirée', importance: Notifications.AndroidImportance.HIGH },
  { id: 'lieux', nom: 'Offres des lieux suivis', importance: Notifications.AndroidImportance.DEFAULT },
  { id: 'compte', nom: 'Mon compte', importance: Notifications.AndroidImportance.HIGH },
];

/**
 * À appeler une fois, au chargement de l'application : comportement d'une
 * notification reçue pendant qu'elle est ouverte (bandeau, sans son), et
 * canaux Android. Sans gestionnaire, une notification reçue au premier plan
 * n'apparaît pas.
 */
export function configurerNotifications(): void {
  // Pas de notifications distantes sur le web : rien à configurer.
  if (Platform.OS === 'web') return;
  Notifications.setNotificationHandler({
    handleNotification: async () => ({
      shouldShowBanner: true,
      shouldShowList: true,
      shouldPlaySound: false,
      shouldSetBadge: false,
    }),
  });

  if (Platform.OS === 'android') {
    // Les canaux doivent exister avant la demande de permission (Android 13+).
    for (const canal of CANAUX) {
      void Notifications.setNotificationChannelAsync(canal.id, { name: canal.nom, importance: canal.importance });
    }
  }
}

export async function etatPermission(): Promise<EtatPermission> {
  // Un simulateur ne reçoit aucune notification distante.
  if (Platform.OS === 'web' || !Device.isDevice) return 'indisponible';
  try {
    const p = await Notifications.getPermissionsAsync();
    if (p.granted) return 'accordee';
    return p.canAskAgain ? 'a_demander' : 'refusee';
  } catch {
    return 'indisponible';
  }
}

/** Demande la permission au système, puis rattache l'appareil si elle est donnée. */
export async function activerNotifications(jetonSession: string): Promise<EtatPermission> {
  const avant = await etatPermission();
  if (avant === 'indisponible' || avant === 'refusee') return avant;

  if (avant === 'a_demander') {
    try {
      const rep = await Notifications.requestPermissionsAsync({ ios: { allowAlert: true, allowBadge: false, allowSound: true } });
      if (!rep.granted) return rep.canAskAgain ? 'a_demander' : 'refusee';
    } catch {
      return 'indisponible';
    }
  }

  await synchroniserAppareil(jetonSession);
  return 'accordee';
}

/**
 * Le toucher d'une notification : ouvre la page qu'elle désigne, si elle
 * figure dans la liste de routeSure() — sinon rien. Couvre aussi
 * l'application lancée par ce toucher (dernière réponse, relue une fois).
 * Rend la fonction qui retire l'écoute.
 */
export function ecouterOuvertures(naviguer: (route: string) => void): () => void {
  if (Platform.OS === 'web') return () => {};
  const ouvrir = (reponse: Notifications.NotificationResponse | null) => {
    const route = routeSure(reponse?.notification.request.content.data?.url);
    if (route) naviguer(route);
  };
  Notifications.getLastNotificationResponseAsync()
    .then((reponse) => {
      ouvrir(reponse);
      if (reponse) return Notifications.clearLastNotificationResponseAsync();
    })
    .catch(() => {});
  const abonnement = Notifications.addNotificationResponseReceivedListener(ouvrir);
  return () => abonnement.remove();
}

/** Le projet Expo : sans lui, aucun jeton ne peut être obtenu (voir app.config.js). */
function idProjet(): string | undefined {
  const extra = Constants.expoConfig?.extra as { eas?: { projectId?: string } } | undefined;
  return extra?.eas?.projectId ?? Constants.easConfig?.projectId;
}

/**
 * Rattache ce téléphone au compte connecté — seulement si la permission est
 * DÉJÀ donnée : au lancement, on ne demande rien.
 */
export async function synchroniserAppareil(jetonSession: string): Promise<void> {
  try {
    if ((await etatPermission()) !== 'accordee') return;
    const projectId = idProjet();
    if (!projectId) {
      console.warn('Notifications : aucun identifiant de projet Expo (extra.eas.projectId).');
      return;
    }

    const { data } = await Notifications.getExpoPushTokenAsync({ projectId });
    await api.pushEnregistrer(jetonSession, data, Platform.OS === 'ios' ? 'ios' : 'android', Device.modelName ?? undefined);
    await SecureStore.setItemAsync(CLE_JETON_PUSH, data);
  } catch (e) {
    // Hors ligne, Expo injoignable : ce n'est pas bloquant, on réessaiera à la
    // prochaine ouverture.
    console.warn('Notifications : appareil non enregistré', e);
  }
}

/** À la déconnexion : ce téléphone ne reçoit plus rien pour ce compte. */
export async function retirerAppareil(jetonSession: string): Promise<void> {
  try {
    const jeton = await SecureStore.getItemAsync(CLE_JETON_PUSH);
    if (jeton) {
      await api.pushRetirer(jetonSession, jeton);
      await SecureStore.deleteItemAsync(CLE_JETON_PUSH);
    }
  } catch {
    // Le serveur désactive de lui-même un jeton que plus personne n'utilise.
  }
}
