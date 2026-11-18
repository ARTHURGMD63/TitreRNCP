/**
 * Complète app.json avec ce qui ne doit pas être versionné.
 *
 * Sur Android, react-native-maps s'appuie sur Google Maps : sans clé, l'écran
 * Carte plante au chargement. La clé vient de l'environnement de build
 * (secret GitHub ou variable EAS), jamais du dépôt.
 *
 * Une clé absente n'empêche pas de construire (développement, iOS, qui
 * utilise Apple Plans), mais un build Android de production sans clé est
 * refusé plus bas : il partirait sur le store avec une carte cassée.
 *
 * OUTSEE_BUILD (le N de l'étiquette mobile-vN, posé par le workflow GitHub)
 * devient le numéro de build : Android versionCode et iOS buildNumber. Les
 * stores refusent deux envois du même numéro ; sans la variable (EAS, qui
 * incrémente lui-même), rien n'est imposé.
 *
 * Notifications push : le jeton Expo exige l'identifiant du projet EAS. Il
 * vient de app.json (extra.eas.projectId) s'il y est, sinon de la variable
 * EXPO_PUBLIC_EAS_PROJECT_ID ; jamais inventé. Sur Android, FCM exige aussi
 * google-services.json : son chemin vient de GOOGLE_SERVICES_JSON (secret
 * fichier EAS ou fichier écrit par le workflow), le fichier n'est pas
 * versionné.
 */
module.exports = ({ config: depart }) => {
  const cleCarte = process.env.GOOGLE_MAPS_ANDROID_API_KEY;
  const build = Number.parseInt(process.env.OUTSEE_BUILD ?? '', 10);
  const idProjet = depart.extra?.eas?.projectId ?? process.env.EXPO_PUBLIC_EAS_PROJECT_ID;
  const servicesGoogle = process.env.GOOGLE_SERVICES_JSON;

  let base = depart;
  if (idProjet) {
    base = { ...base, extra: { ...base.extra, eas: { ...base.extra?.eas, projectId: idProjet } } };
  }
  if (servicesGoogle) {
    base = { ...base, android: { ...base.android, googleServicesFile: servicesGoogle } };
  }

  if (!process.env.EXPO_PUBLIC_API_URL && process.env.EAS_BUILD_PROFILE === 'production') {
    throw new Error("EXPO_PUBLIC_API_URL manquante : l'application ne saurait pas où joindre le serveur.");
  }

  const config = Number.isInteger(build) && build > 0
    ? {
        ...base,
        ios: { ...base.ios, buildNumber: String(build) },
        android: { ...base.android, versionCode: build },
      }
    : base;

  if (!cleCarte && process.env.EAS_BUILD_PROFILE === 'production' && process.env.EAS_BUILD_PLATFORM === 'android') {
    throw new Error('GOOGLE_MAPS_ANDROID_API_KEY manquante : la carte Android ne fonctionnerait pas.');
  }

  if (!cleCarte) return config;

  return {
    ...config,
    android: {
      ...config.android,
      config: {
        ...(config.android?.config ?? {}),
        googleMaps: { apiKey: cleCarte },
      },
    },
  };
};
