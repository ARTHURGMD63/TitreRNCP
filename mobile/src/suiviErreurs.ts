/**
 * Suivi des plantages de l'application.
 *
 * Quand une erreur JavaScript n'est pas rattrapée, le message et le début de
 * la pile d'appels sont envoyés au serveur (api/v1/erreur_app.php), qui les
 * range dans son journal d'erreurs. Sans cela, un plantage sur un téléphone
 * ne laisse aucune trace : on l'apprend par un avis à une étoile.
 *
 * Anonyme : aucun jeton, aucun identifiant de compte, aucune donnée
 * personnelle — seulement le message, la pile, la plateforme et la version.
 * Rien n'est envoyé en développement. L'envoi ne doit jamais, lui-même,
 * provoquer ou aggraver un plantage : toute erreur est avalée, et le
 * comportement d'origine de l'application (écran d'erreur, fermeture) est
 * conservé tel quel.
 */

import { Platform } from 'react-native';
import Constants from 'expo-constants';

import { BASE_API, VERSION_APP } from './api';

type Gestionnaire = (erreur: unknown, fatale?: boolean) => void;
type ErrorUtilsGlobal = { getGlobalHandler?: () => Gestionnaire; setGlobalHandler?: (g: Gestionnaire) => void };

let installe = false;
/** Les erreurs déjà signalées : une même erreur ne part qu'une fois. */
const dejaSignalees = new WeakSet<object>();

function signaler(erreur: unknown, fatale?: boolean): void {
  const e = erreur instanceof Error ? erreur : new Error(String(erreur));
  if (dejaSignalees.has(e)) return;
  dejaSignalees.add(e);
  void fetch(`${BASE_API}/erreur_app.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-App-Version': VERSION_APP },
    body: JSON.stringify({
      message: e.message,
      pile: e.stack ?? '',
      fatale: Boolean(fatale),
      plateforme: Platform.OS,
      version: Constants.expoConfig?.version ?? '',
    }),
  }).catch(() => {});
}

/**
 * Une erreur rattrapée par l'ErrorBoundary de la racine (_layout.tsx) : elle
 * n'atteint jamais le gestionnaire global, on la signale ici. Une seule fois
 * par erreur, jamais en développement, et sans rien relancer si l'envoi
 * échoue — pas de boucle possible.
 */
export function signalerErreurRattrapee(erreur: unknown): void {
  if (__DEV__) return;
  try {
    signaler(erreur, false);
  } catch {
    // Le signalement ne doit jamais aggraver la panne.
  }
}

export function installerSuiviErreurs(): void {
  if (installe || __DEV__) return;
  installe = true;

  const utilitaires = (globalThis as { ErrorUtils?: ErrorUtilsGlobal }).ErrorUtils;
  if (!utilitaires?.getGlobalHandler || !utilitaires.setGlobalHandler) return;

  const precedent = utilitaires.getGlobalHandler();
  utilitaires.setGlobalHandler((erreur, fatale) => {
    try {
      signaler(erreur, fatale);
    } catch {
      // Le signalement ne doit jamais empêcher le comportement d'origine.
    }
    precedent(erreur, fatale);
  });
}
