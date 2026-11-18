/**
 * Les listes fixes du site, pour les écrans où l'on n'est pas encore connecté
 * (l'inscription). Recopiées d'auth/register.php et d'interetsDisponibles()
 * (includes/interets.php) : si l'une change côté site, elle change ici.
 * Une fois connecté, l'écran Moi les reçoit du serveur (api/v1/moi.php).
 */

import type { NomIcone } from './composants/Icone';

/**
 * Les écoles de Clermont-Ferrand, la seule ville ouverte pour l'instant.
 * La valeur est enregistrée telle quelle sur le compte (et sert au
 * classement « Mon école ») : ne pas renommer une entrée existante, en
 * ajouter. Même liste que ecolesDisponibles() dans includes/ecoles.php.
 */
export const ECOLES = [
  'UCA',
  'IUT Clermont Auvergne',
  'Polytech Clermont',
  'Clermont Auvergne INP',
  'ISIMA',
  'SIGMA Clermont',
  'INSPÉ Clermont-Auvergne',
  'Clermont School of Business',
  'VetAgro Sup',
  'ENSACF',
  'ESACM',
  'Conservatoire (CRR)',
  'IFSI',
  'Cnam Auvergne',
  'Prépa (CPGE)',
  'BTS',
  'Autre',
];

/**
 * Ce que l'on tape sans connaître le nom exact : la recherche du sélecteur
 * d'école regarde aussi ces mots (sans accents ni majuscules).
 */
export const MOTS_CLES_ECOLES: Record<string, string> = {
  'UCA': 'Université Clermont Auvergne fac faculté droit économie lettres sciences médecine pharmacie dentaire sages-femmes STAPS psychologie',
  'IUT Clermont Auvergne': 'IUT BUT institut universitaire de technologie',
  'Polytech Clermont': 'école d’ingénieurs ingénieur',
  'Clermont Auvergne INP': 'INP école d’ingénieurs ingénieur',
  'ISIMA': 'informatique école d’ingénieurs ingénieur INP',
  'SIGMA Clermont': 'chimie mécanique école d’ingénieurs ingénieur INP',
  'INSPÉ Clermont-Auvergne': 'ESPE enseignement professeur prof MEEF éducation',
  'Clermont School of Business': 'ESC école de commerce business school management',
  'VetAgro Sup': 'vétérinaire véto agronomie agro Lempdes',
  'ENSACF': 'architecture école nationale supérieure d’architecture',
  'ESACM': 'art arts beaux-arts école supérieure d’art',
  'Conservatoire (CRR)': 'musique danse théâtre conservatoire Chabrier',
  'IFSI': 'infirmier infirmière soins santé CHU',
  'Cnam Auvergne': 'CNAM formation continue alternance',
  'Prépa (CPGE)': 'classe préparatoire prépa Blaise-Pascal Lafayette lycée',
  'BTS': 'brevet de technicien supérieur lycée',
};

/**
 * La liste du sélecteur d'école d'un profil : celle du serveur, plus
 * l'école déjà enregistrée si elle n'y figure plus (une ancienne entrée
 * comme « INP Ingénieurs »), placée avant « Autre » pour ne pas la perdre.
 */
export function ecolesProposees(liste: string[], actuelle: string | null | undefined): string[] {
  if (!actuelle || liste.includes(actuelle)) return liste;
  const fin = liste.at(-1) === 'Autre' ? liste.length - 1 : liste.length;
  return [...liste.slice(0, fin), actuelle, ...liste.slice(fin)];
}

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
  { valeur: 'bde', libelle: 'BDE / association' },
  { valeur: 'club', libelle: 'Club sportif' },
];

/** Les libellés des types sur les cartes (explore.php, $typeLabels). */
export const LIBELLES_TYPE: Record<string, string> = { bar: 'Bar', boite: 'Boîte', resto: 'Resto', afterwork: 'Afterwork', club: 'Club', bde: 'BDE', sport: 'Sport' };

/**
 * L'icône de chaque type de lieu : le petit carré coloré des chips du hub,
 * repris sur la carte d'événement pour qu'on relie la soirée au filtre.
 */
export const ICONES_TYPE: Record<string, NomIcone | undefined> = {
  'pour-moi': 'etoile-fine',
  bar: 'verre',
  boite: 'musique',
  resto: 'billet',
  afterwork: 'verre',
  club: 'club',
  bde: 'personnes',
  sport: 'balle',
};

/** L'icône de chaque catégorie Culture (chips de l'espace Culture, cartes). */
export const ICONES_CULTURE: Record<string, NomIcone> = {
  expo: 'image', concert: 'micro', spectacle: 'ticket', patrimoine: 'musee', festival: 'musique',
  cinema: 'appareil', atelier: 'crayon', conference: 'micro', autre: 'etoile-fine',
};
/** Le carré d'une catégorie Culture sans couleur attitrée. */
export const COULEUR_CULTURE_REPLI = '#8B5CF6';

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

/**
 * Espace Culture : libellés des catégories (CULTURE_CATEGORIES,
 * includes/culture.php). Le fil reçoit la liste du serveur pour ses filtres ;
 * celle-ci sert de repli aux cartes et à la fiche.
 */
export const LIBELLES_CULTURE: Record<string, string> = {
  expo: 'Expo', concert: 'Concert', spectacle: 'Spectacle', festival: 'Festival', patrimoine: 'Patrimoine',
  cinema: 'Cinéma', atelier: 'Atelier', conference: 'Conférence', autre: 'Autre',
};
