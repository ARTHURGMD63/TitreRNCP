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
 */
module.exports = ({ config: base }) => {
  const cleCarte = process.env.GOOGLE_MAPS_ANDROID_API_KEY;
  const build = Number.parseInt(process.env.OUTSEE_BUILD ?? '', 10);
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
