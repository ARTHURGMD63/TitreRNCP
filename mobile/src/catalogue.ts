/**
 * Les listes fixes du site, pour les écrans où l'on n'est pas encore connecté
 * (l'inscription). Recopiées d'auth/register.php et d'interetsDisponibles()
 * (includes/interets.php) : si l'une change côté site, elle change ici.
 * Une fois connecté, l'écran Moi les reçoit du serveur (api/v1/moi.php).
 */

export const ECOLES = ['UCA', 'SIGMA Clermont', 'INP Ingénieurs', 'IFSI', 'Autre'];

export const PROMOS = ['L1', 'L2', 'L3', 'M1', 'M2', 'BUT1', 'BUT2', 'BUT3'];

export const VILLES = ['Clermont-Ferrand', 'Lyon', 'Paris', 'Toulouse', 'Bordeaux', 'Autre'];

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
  { valeur: 'club', libelle: 'Club sportif' },
];

/** Les libellés des types sur les cartes (explore.php, $typeLabels). */
export const LIBELLES_TYPE: Record<string, string> = { bar: 'Bar', boite: 'Boîte', resto: 'Resto', afterwork: 'Afterwork', club: 'Club', sport: 'Sport' };

/**
 * Les sports, recopiés de catalogueSports() (includes/sports.php) : si l'un
 * change côté serveur, il change ici. Le serveur renvoie aussi la liste avec
 * le fil Sport ; celle-ci sert à afficher un libellé sans l'attendre.
 */
export const SPORTS: Record<string, string> = {
  padel: 'Padel', tennis: 'Tennis', golf: 'Golf', squash: 'Squash', badminton: 'Badminton',
  foot5: 'Foot à 5', basket: 'Basket', escalade: 'Escalade', running: 'Running', velo: 'Vélo',
  muscu: 'Muscu', yoga: 'Yoga', autre: 'Autre',
};

/** Formats d'un événement de club (formatsEvenementSport()). */
export const FORMATS_SPORT: Record<string, string> = {
  tournoi: 'Tournoi', stage: 'Stage', initiation: 'Initiation', cours: 'Cours collectif',
  match: 'Match', soiree: 'Soirée sportive', autre: 'Autre',
};

/** Le libellé d'un type de squad : un sport, ou « culture » d'avant le sport. */
export const TYPES_SQUAD: Record<string, string> = { ...SPORTS, culture: 'Culture' };
export const NIVEAUX_SQUAD: Record<string, string> = { tous: 'Tous niveaux', debutant: 'Débutant', inter: 'Inter.', avance: 'Avancé' };
