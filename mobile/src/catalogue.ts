/**
 * Les listes fixes du site, pour les écrans où l'on n'est pas encore connecté
 * (l'inscription). Recopiées d'auth/register.php et d'interetsDisponibles()
 * (includes/interets.php) : si l'une change côté site, elle change ici.
 * Une fois connecté, l'écran Moi les reçoit du serveur (api/v1/moi.php).
 */

export const ECOLES = ['UCA', 'SIGMA Clermont', 'INP Ingénieurs', 'IFSI', 'Autre'];

export const PROMOS = ['L1', 'L2', 'L3', 'M1', 'M2', 'BUT1', 'BUT2', 'BUT3'];

export const INTERETS = [
  'Sorties', 'Soirées', 'Bars', 'Boîtes', 'Techno', 'Musique', 'Mixologie',
  'Running', 'Muscu', 'Vélo', 'Foot', 'Tennis', 'Yoga',
  'Cuisine', 'Voyage', 'Cinéma', 'Lecture', 'Art', 'Photo',
  'Gaming', 'Code', 'Échecs', 'Animaux', 'Bénévolat',
];

export const TYPES_ETABLISSEMENT = [
  { valeur: 'bar', libelle: 'Bar' },
  { valeur: 'boite', libelle: 'Boîte' },
  { valeur: 'resto', libelle: 'Restaurant' },
  { valeur: 'afterwork', libelle: 'Afterwork' },
];

/** Les libellés des types sur les cartes (explore.php, $typeLabels). */
export const LIBELLES_TYPE: Record<string, string> = { bar: 'Bar', boite: 'Boîte', resto: 'Resto', afterwork: 'Afterwork' };

/** squads.php */
export const TYPES_SQUAD: Record<string, string> = { running: 'Running', velo: 'Vélo', muscu: 'Muscu', autre: 'Autre' };
export const NIVEAUX_SQUAD: Record<string, string> = { tous: 'Tous niveaux', debutant: 'Débutant', inter: 'Inter.', avance: 'Avancé' };
