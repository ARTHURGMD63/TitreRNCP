/**
 * Client de l'API Outsee.
 *
 * Point de passage unique vers le serveur : aucun écran n'appelle fetch()
 * directement. Tout ce qui est commun à chaque requête vit ici : l'adresse du
 * serveur, le jeton, le décodage, et la traduction des pannes en une phrase
 * qu'un écran peut afficher.
 *
 * Deux familles de points :
 *  - api/v1/  : les lectures de l'application, et ce qui n'existe qu'en
 *               formulaire sur le site (inscription, profil, avis) ;
 *  - api/     : les écritures partagées avec le site (inscrire, suivre,
 *               inviter…), qui portent la logique métier et reconnaissent le
 *               jeton (protegerEcritureApi()).
 */

import Constants from 'expo-constants';

import { cleLecture, copieAutorisee, memoriser, relire, signaler } from './horsLigne';
import type { PageLegale } from './legal';

/**
 * L'adresse du serveur.
 *
 * En développement, ce n'est PAS « localhost » : sur un téléphone, localhost
 * désigne le téléphone lui-même. Expo connaît l'adresse de la machine — c'est
 * par elle qu'il sert le bundle — et on la lui reprend, ce qui évite une IP
 * écrite en dur, fausse au prochain bail DHCP.
 *
 * En production, EXPO_PUBLIC_API_URL est fixée au moment du build. Sans
 * elle, un build de production appelait localhost en silence : chaque écran
 * échouait sans que rien ne dise pourquoi. Il s'arrête désormais au démarrage
 * avec une erreur explicite (constat MOB-13) ; le repli sur la machine de
 * développement n'existe qu'en __DEV__.
 */
export function adresseServeur(): string {
  const configuree = process.env.EXPO_PUBLIC_API_URL;
  if (configuree) return configuree.replace(/\/+$/, '');

  if (typeof __DEV__ === 'undefined' || !__DEV__) {
    throw new Error('EXPO_PUBLIC_API_URL manquante : ce build ne sait pas où joindre le serveur Outsee.');
  }

  const hote = Constants.expoConfig?.hostUri?.split(':')[0];
  if (hote) return `http://${hote}:8080/TitreRNCP`;

  return 'http://localhost:8080/TitreRNCP';
}

/**
 * La version de l'application (app.json), envoyée sur chaque requête dans
 * l'en-tête X-App-Version : le serveur peut refuser une version trop
 * ancienne (426, code `mise_a_jour_requise`).
 */
export const VERSION_APP: string = Constants.expoConfig?.version ?? '';

export const BASE_API = `${adresseServeur()}/api/v1`;
const BASE_ACTIONS = `${adresseServeur()}/api`;

/** Les codes qui disent « ce jeton ne vaut plus rien », quel que soit le statut. */
const CODES_DECONNEXION = new Set(['jeton_invalide', 'non_authentifie']);

/** Erreur portant le code stable renvoyé par l'API. */
export class ErreurApi extends Error {
  constructor(message: string, readonly code: string, readonly statut: number) {
    super(message);
    this.name = 'ErreurApi';
  }
  /** Le jeton est absent, expiré ou révoqué : il faut se reconnecter. */
  get estDeconnecte(): boolean {
    return this.statut === 401 || CODES_DECONNEXION.has(this.code);
  }
  /** Le serveur refuse cette version de l'application (426). */
  get estMiseAJourRequise(): boolean {
    return this.code === 'mise_a_jour_requise';
  }
}

/**
 * Le rappel de déconnexion, enregistré par la session (session.tsx).
 *
 * Avant, un jeton expiré n'était détecté qu'au lancement : ensuite, chaque
 * écran échouait l'un après l'autre sans jamais renvoyer vers la connexion
 * (constat BUG-03). Désormais, toute réponse qui refuse le jeton envoyé
 * (401, ou code `jeton_invalide` / `non_authentifie`) prévient la session,
 * avec le jeton refusé : elle ne déconnecte que si c'est toujours le sien.
 */
let rappelDeconnexion: ((jetonRefuse: string) => void) | null = null;

/** Enregistre le rappel ; rend la fonction qui le retire. */
export function surDeconnexion(f: (jetonRefuse: string) => void): () => void {
  rappelDeconnexion = f;
  return () => {
    if (rappelDeconnexion === f) rappelDeconnexion = null;
  };
}

/**
 * Le rappel « mise à jour requise », enregistré par la racine (_layout.tsx).
 *
 * Le serveur refuse une version trop ancienne (426) ; sans ce rappel, chaque
 * écran affichait le message dans son coin, l'un après l'autre, et l'étudiant
 * pouvait continuer à naviguer dans une application qui ne marche plus
 * (constat ARC-02). La racine remplace alors toute l'application par un
 * écran bloquant (MiseAJourRequise.tsx).
 */
let rappelMiseAJour: ((message: string) => void) | null = null;

/** Enregistre le rappel ; rend la fonction qui le retire. */
export function surMiseAJourRequise(f: (message: string) => void): () => void {
  rappelMiseAJour = f;
  return () => {
    if (rappelMiseAJour === f) rappelMiseAJour = null;
  };
}

type Options = {
  methode?: 'GET' | 'POST';
  corps?: unknown;
  formulaire?: FormData;
  jeton?: string | null;
  params?: Record<string, string | number | undefined | null>;
};

async function requete<T>(url: string, { methode = 'GET', corps, formulaire, jeton }: Options, messageDefaut: string): Promise<T> {
  const entetes: Record<string, string> = { Accept: 'application/json' };
  if (VERSION_APP) entetes['X-App-Version'] = VERSION_APP;
  if (corps !== undefined) entetes['Content-Type'] = 'application/json';
  if (jeton) entetes.Authorization = `Bearer ${jeton}`;

  // Sans délai maximal, un serveur injoignable laisse l'écran sur son rond qui
  // tourne indéfiniment — la pire des réponses, parce qu'elle n'en est pas une.
  //
  // Un envoi de fichier (formulaire) a droit à plus de temps : sur l'offre
  // gratuite de Render, un serveur endormi met jusqu'à une minute à se
  // réveiller au premier appel, et une photo met plus longtemps à téléverser
  // qu'un JSON. 15 s y coupait la connexion en pleine réveil du serveur —
  // le fichier n'atteignait jamais l'API, sans qu'aucun message ne le dise.
  const abandon = new AbortController();
  const minuteur = setTimeout(() => abandon.abort(), formulaire ? 45000 : 15000);

  let reponse: Response;
  try {
    reponse = await fetch(url, {
      method: methode,
      headers: entetes,
      body: formulaire ?? (corps !== undefined ? JSON.stringify(corps) : undefined),
      signal: abandon.signal,
    });
  } catch (e) {
    clearTimeout(minuteur);
    const interrompu = e instanceof Error && e.name === 'AbortError';
    throw new ErreurApi(interrompu ? 'Le serveur met trop de temps à répondre.' : 'Erreur réseau', interrompu ? 'delai' : 'reseau', 0);
  }
  clearTimeout(minuteur);

  const texte = await reponse.text();
  let donnees: any = null;
  try {
    donnees = texte ? JSON.parse(texte) : null;
  } catch {
    console.warn('Réponse non-JSON de', url, texte.slice(0, 200));
    throw new ErreurApi('Réponse inattendue du serveur.', 'reponse_illisible', reponse.status);
  }

  // Les points d'écriture répondent 200 avec success:false pour un refus
  // métier — « plus de place », « déjà inscrit ». Le message est fait pour
  // être affiché tel quel, c'est celui du site.
  if (!reponse.ok || donnees?.success === false) {
    // 426 : version trop ancienne. Le code est garanti, même si le corps ne
    // le porte pas ; le message du serveur est gardé tel quel.
    const code = reponse.status === 426 ? 'mise_a_jour_requise' : donnees?.code ?? String(reponse.status);
    const erreur = new ErreurApi(donnees?.message ?? messageDefaut, code, reponse.status);
    // Un jeton refusé : la session repart à l'écran de connexion. Sans jeton
    // envoyé (connexion, mot de passe oublié), un 401 n'est qu'un refus.
    if (jeton && erreur.estDeconnecte && rappelDeconnexion) {
      try { rappelDeconnexion(jeton); } catch { /* jamais bloquant */ }
    }
    if (erreur.estMiseAJourRequise && rappelMiseAJour) {
      try { rappelMiseAJour(erreur.message); } catch { /* jamais bloquant */ }
    }
    throw erreur;
  }
  return donnees as T;
}

function urlAvecParams(base: string, params?: Options['params']) {
  const url = new URL(base);
  for (const [cle, valeur] of Object.entries(params ?? {})) {
    if (valeur !== undefined && valeur !== null && valeur !== '') url.searchParams.set(cle, String(valeur));
  }
  return url.toString();
}

/**
 * Une lecture authentifiée (GET avec jeton) de la liste blanche
 * (horsLigneRegles.ts) passe par la copie hors connexion : mise de côté quand
 * le serveur répond, relue quand il ne répond pas — réseau absent ou délai
 * dépassé. Un refus du serveur (401, 404…) n'est jamais masqué par une
 * vieille copie. Les autres lectures ne sont ni écrites ni relues.
 */
export async function appelApi<T>(chemin: string, options: Options = {}): Promise<T> {
  const url = urlAvecParams(`${BASE_API}/${chemin.replace(/^\/+/, '')}`, options.params);
  const lecture = (options.methode ?? 'GET') === 'GET' && !!options.jeton && copieAutorisee(chemin);
  const cle = lecture ? cleLecture(url) : '';
  try {
    const rep = await requete<T>(url, options, 'Une erreur est survenue.');
    if (lecture) {
      signaler(cle, null);
      memoriser(cle, rep);
    }
    return rep;
  } catch (e) {
    if (lecture && e instanceof ErreurApi && (e.code === 'reseau' || e.code === 'delai')) {
      const copie = await relire<T>(cle);
      if (copie) {
        signaler(cle, copie.le);
        return copie.donnees;
      }
    }
    throw e;
  }
}

function action<T>(fichier: string, jeton: string, corps: unknown): Promise<T> {
  return requete<T>(`${BASE_ACTIONS}/${fichier}`, { methode: 'POST', corps, jeton }, 'Erreur');
}

// ─── Formes renvoyées par l'API ──────────────────────────────────────────────

export type EtatSuivi = 'none' | 'pending' | 'accepted';

export type Personne = {
  id: number;
  prenom: string;
  nom: string;
  ecole?: string | null;
  promo?: string | null;
  photo_url: string | null;
};

export type Profil = {
  id: number;
  prenom: string;
  nom: string;
  email: string;
  ecole: string | null;
  promo: string | null;
  ville: string | null;
  type: 'etudiant' | 'partenaire' | 'admin';
  photo_url: string | null;
  interets: string[];
  compte_prive: boolean;
};

/** Un établissement qui co-organise un événement (v39), une fois l'invitation acceptée. */
export type Collaborateur = { id: number; nom: string; type: string };

export type Evenement = {
  id: number;
  titre: string;
  description: string;
  type: string;
  style_musique: string | null;
  /** Événement d'un club (type 'sport') : discipline et format. */
  sport?: string | null;
  format?: string | null;
  date_heure: string;
  date_fin?: string | null;
  /** Fil seulement : la fin retenue par le serveur (date_fin, sinon la fenêtre par défaut). */
  fin_effective?: string;
  /** Fin des inscriptions : la clôture de l'établissement, sinon le début. */
  inscriptions_jusqu_au?: string;
  /** Vrai quand l'établissement a fermé les inscriptions avant le début. */
  cloture_anticipee?: boolean;
  /** Fiche seulement : peut-on encore s'inscrire ? */
  inscriptions_ouvertes?: boolean;
  lieu: string;
  etablissement: { id: number; nom: string; type: string; ville: string; note: number | null; nb_avis: number; suivi: boolean; latitude: number | null; longitude: number | null };
  places: { quota: number; inscrits: number; restantes: number | null; complet: boolean };
  reduction: number | null;
  prix_normal: number | null;
  is_gratuit: boolean;
  is_flash: boolean;
  flash_expiry: string | null;
  sponsorise: boolean;
  deja_inscrit: boolean;
  /** Annulé par l'établissement ou l'équipe (absent d'un serveur d'avant la v41). */
  annule?: boolean;
  annule_motif?: string | null;
  en_cours: boolean;
  amis: { prenoms: string[]; nb: number };
  /** Les établissements qui co-organisent (absent d'un serveur d'avant la v39). */
  collaborateurs?: Collaborateur[];
};

export type EvenementDetail = Omit<Evenement, 'amis' | 'etablissement' | 'places'> & {
  etablissement: Omit<Evenement['etablissement'], 'suivi'> & { adresse: string };
  places: Evenement['places'] & { pourcentage: number | null };
  photos: { url: string; legende: string | null }[];
  amis: Personne[];
};

export type ReponseEvenements = {
  evenements: Evenement[];
  pagination: { page: number; a_suivre: boolean };
  filtres: { type: string; musique: string; styles_musique: { code: string; libelle: string }[] };
};

/** Un événement de l'espace Culture (api/v1/culture.php) : mis en avant par la ville. */
export type EvenementCulture = {
  id: number;
  titre: string;
  description: string;
  categorie: string;
  organisateur: string;
  lieu: string;
  ville: string;
  date_heure: string;
  date_fin: string | null;
  /** quota 0 : entrée libre, sans limite — restantes vaut alors null. */
  places: { quota: number; inscrits: number; restantes: number | null; complet: boolean };
  prix: number;
  is_gratuit: boolean;
  lien: string | null;
  mis_en_avant: boolean;
  en_cours: boolean;
  deja_inscrit: boolean;
  amis: { prenoms: string[]; nb: number };
};

export type EvenementCultureDetail = EvenementCulture & { termine: boolean; amis_detail: Personne[] };

export type CategorieCulture = { code: string; libelle: string };

export type ReponseCulture = {
  evenements: EvenementCulture[];
  pagination: { page: number; a_suivre: boolean };
  filtres: { categorie: string; categories: CategorieCulture[] };
};

export type EvenementCultureAdmin = Omit<EvenementCulture, 'amis' | 'deja_inscrit'> & { termine: boolean };

export type CompteBdeAdmin = {
  id: number;
  nom: string;
  ville: string;
  verification: 'en_attente' | 'validee' | 'refusee' | 'non_requise';
  note: string | null;
  verifie_le: string | null;
  verifie_par: string | null;
  inscrit_le: string;
  contact: { prenom: string; nom: string; email: string };
  formule: string | null;
  evenements: number;
};

export type ReponseCultureAdmin = { a_venir: EvenementCultureAdmin[]; termines: EvenementCultureAdmin[]; categories: CategorieCulture[] };

/** Ce que l'invitation sait viser : une soirée, une squad, un événement culturel. */
export type CibleInvitation = 'event' | 'squad' | 'culture';

/**
 * Ce que représente un pass : une soirée ou un événement de club (avec QR),
 * un événement Culture ou une partie de sport (sans QR). `id` n'est unique
 * qu'à l'intérieur d'un genre ; pour une partie, `evenement_id` est le créneau.
 */
export type GenrePass = 'soiree' | 'sport' | 'culture' | 'partie';

export type Pass = {
  /** Absent d'un serveur antérieur au 2026-11-16 : c'était alors une soirée. */
  genre?: GenrePass;
  id: number;
  evenement_id: number | null;
  titre: string;
  etablissement: string;
  lieu: string;
  date_heure: string | null;
  statut: string;
  reduction: number | null;
  prix_normal: number | null;
  /** Absent des pass Culture et des parties. */
  is_gratuit?: boolean;
  economie: number | null;
  evenement_supprime: boolean;
  etab_type: string | null;
  code_qr: string | null;
  /** Partie de sport : la discipline. */
  sport?: string;
};

export type SquadAgenda = { id: number; titre: string; type: string; niveau: string; date_heure: string; lieu: string; createur_prenom: string };

export type ReponseWallet = {
  economies: { mois: number; annee: number };
  pass_actifs: Pass[];
  historique: Pass[];
  squads: SquadAgenda[];
  pagination: { page: number; a_suivre: boolean };
};

export type Squad = {
  id: number;
  titre: string;
  description: string;
  type: string;
  niveau: string;
  date_heure: string;
  lieu: string;
  createur: { id: number; prenom: string; nom: string; photo_url: string | null };
  places: { quota: number; membres: number; restantes: number | null; complet: boolean };
  deja_membre: boolean;
  est_createur: boolean;
  je_suis_abonne_au_createur: boolean;
  cree_par_association: boolean;
  membres: { id: number; prenom: string; photo_url: string | null }[];
  /** 'abonnes' : visible des seuls abonnés du créateur (v28). */
  visibilite?: 'public' | 'abonnes';
};

// ─── Sport ──────────────────────────────────────────────────────────────────

export type NiveauSport = 'tous' | 'debutant' | 'inter' | 'avance';

export type JoueurCreneau = { id: number; prenom: string; nom: string; photo_url: string | null; invite_par: number | null };

/** Un créneau de terrain : libre, ou partie réservée (ouverte ou non). */
export type Creneau = {
  id: number;
  debut: string;
  fin: string;
  sport: string;
  terrain: { id: number; nom: string };
  club: { id: number; nom: string; ville: string; adresse: string };
  /** Prix du terrain pour le créneau, réglé au club. */
  prix: number;
  statut: 'libre' | 'reserve' | 'annule';
  ouvert: boolean;
  niveau: NiveauSport;
  places: { total: number; prises: number; restantes: number };
  organisateur: { id: number; prenom: string; nom: string; photo_url: string | null } | null;
  joueurs: JoueurCreneau[];
  je_joue: boolean;
  je_organise: boolean;
};

export type CreneauDetail = Creneau & { peut_reserver: boolean; peut_rejoindre: boolean; peut_quitter: boolean };

export type EvenementSport = {
  id: number;
  titre: string;
  sport: string;
  format: string;
  date_heure: string;
  date_fin: string | null;
  lieu: string;
  club: { id: number; nom: string; ville: string };
  places: { quota: number; inscrits: number; restantes: number | null; complet: boolean };
  prix_normal: number;
  reduction: number;
  is_gratuit: boolean;
  deja_inscrit: boolean;
};

export type ReponseSport = {
  sports: { code: string; libelle: string }[];
  parties: Creneau[];
  libres: Creneau[];
  mes_parties: Creneau[];
  evenements: EvenementSport[];
  squads: Squad[];
};

export type TerrainClub = {
  id: number;
  nom: string;
  sport: string;
  joueurs: number;
  duree_min: number;
  prix: number;
  actif: boolean;
  creneaux_libres: number;
  creneaux_reserves: number;
};

export type ReponseTerrainsClub = { terrains: TerrainClub[]; sports_club: string[]; sports: { code: string; libelle: string }[] };

/** Une catégorie de notification push (voir includes/push.php). */
export type CategoriePush = { code: string; libelle: string; detail: string; modifiable: boolean; actif: boolean };

export type Notification = {
  type: 'demande' | 'invitation' | 'ami' | 'lieu';
  ts: string;
  depuis: string;
  traiter: boolean;
  nouvelle: boolean;
  section: string;
  acteur?: Personne;
  invitation?: { id: number; cible_type: CibleInvitation; cible_nom: string | null; de: Personne };
  activite?: { cible_type: 'event' | 'squad'; cible_id: number; cible_nom: string; lieu: string; acteur: Personne };
  evenement?: { id: number; titre: string; lieu: string; date: string; promo?: boolean };
};

export type ProfilAnnuaire = Personne & { etat_suivi: EtatSuivi; interets_communs: string[]; squads_communs: number; score: number };

export type ReponsePersonnes = {
  profils: ProfilAnnuaire[];
  suggestions: ProfilAnnuaire[];
  filtres: {
    mes_interets: string[];
    ecoles: string[];
    catalogue: string[];
    interets_manquants: boolean;
    comme_moi: boolean;
    interet: string;
    ecole: string;
    q: string;
    valeur_comme_moi: string;
  };
  pagination: { page: number; a_suivre: boolean };
};

export type Etudiant = Personne & {
  interets: string[];
  nb_abonnes: number;
  nb_abonnements: number;
  etat_suivi: EtatSuivi;
  je_bloque: boolean;
  activite_visible: boolean;
  squads_communs: { titre: string; type: string }[];
  sorties: { titre: string; etablissement: string; date: string }[];
};

export type Badge = { code: string; nom: string; description: string; icon: string; couleur: string };

export type ReponseMoi = {
  moi: Profil & {
    depuis: string;
    nb_abonnes: number;
    nb_abonnements: number;
    nb_sorties: number;
    nb_squads: number;
    economies: number;
  };
  xp: {
    total: number;
    niveau: number;
    suivant: number;
    progres: number;
    stats: { events: number; squads: number; follows: number; avis: number };
    obtenus: Badge[];
    a_debloquer: Badge[];
  };
  choix: { ecoles: string[]; promos: string[]; interets: string[] };
};

export type LigneClassement = { id: number; prenom: string; nom: string; photo_url: string | null; rang: number; xp: number; moi: boolean };

export type ReponseClassement = {
  portee: 'amis' | 'ecole' | 'ville';
  portees: { code: 'amis' | 'ecole' | 'ville'; libelle: string }[];
  mon_xp: number;
  sans_ecole: boolean;
  sans_amis: boolean;
  lignes: LigneClassement[];
  moi: LigneClassement | null;
  devant: { prenom: string; ecart: number } | null;
};

export type ReponseAbonnements = {
  type: 'abonnes' | 'abonnements';
  nb_abonnes: number;
  nb_abonnements: number;
  personnes: (Personne & { etat_suivi: EtatSuivi })[];
  pagination: { page: number; a_suivre: boolean; restants: number };
};

/** Les personnes que j'ai bloquées (blocages.php), les plus récentes d'abord. */
export type ReponseBlocages = {
  personnes: (Personne & { bloque_le: string })[];
};

export type ReponseAvis = {
  evenement: { id: number; titre: string; etablissement: string; date: string };
  avis: { note: number; commentaire: string } | null;
};

export type ReponseConnexion = { token: string; expire_le: string; utilisateur: Profil };

export type PhotoEvenement = {
  id: number;
  url: string | null;
  legende: string | null;
  created_at: string;
  auteur: { id: number; prenom: string; photo_url: string | null };
  moi: boolean;
};

export type ReponsePhotosEvenement = {
  en_cours: boolean;
  peut_publier: boolean;
  reserve_participants: boolean;
  photos: PhotoEvenement[];
};

// ─── Espace partenaire ────────────────────────────────────────────────────────

export type PersonneCheckin = {
  id: number;
  prenom: string;
  nom: string;
  ecole: string | null;
  promo: string | null;
  photo_url: string | null;
  checkin_le: string | null;
};

/** api/v1/partenaire_checkins.php : les entrées d'UN événement, celui qu'on scanne. */
export type ReponseCheckinsPartenaire = {
  evenement: { id: number; titre: string };
  compteurs: { inscrits: number; checkin: number; total: number; places: number };
  checkins: PersonneCheckin[];
};

export type ReponseDashboardPartenaire = {
  etablissement: { id: number; nom: string; ville: string };
  evenement: { id: number; titre: string; date_heure: string } | null;
  usage: UsageMoisPartenaire;
  offre: { code: string; libelle: string; essai_jusquau: string | null };
  stats: {
    inscrits: number;
    checkin: number;
    total: number;
    places: number;
    /** Part des inscrits déjà scannés ; null tant qu'il n'y a pas d'inscrit. */
    taux_presence: number | null;
    ecoles: { nom: string; pourcentage: number }[];
    graphe: { labels: string[]; valeurs: number[] };
    recentes: { prenom: string; nom: string; ecole: string | null; promo: string | null; photo_url: string | null; heure: string }[];
    checkins: PersonneCheckin[];
  };
};

/** Ce que l'établissement a consommé et payé en plus ce mois-ci. */
export type UsageMoisPartenaire = {
  mois: string;
  evenements: {
    publies: number;
    quota: number;
    restants: number;
    supplementaires: number;
    /** Total HT des événements supplémentaires du mois. */
    a_payer: number;
    prix_supplement: number;
  };
  flash: { utilises: number; quota: number | null; restants: number | null };
  mises_en_avant: { offertes_droit: number; offertes_restantes: number; achetees: number; cout: number };
  /** Supplément d'événements + posts sponsorisés payants, HT. */
  achats_total: number;
};

export type EvenementPartenaire = {
  id: number;
  titre: string;
  type: string;
  sport: string | null;
  format: string | null;
  date_heure: string;
  quota: number;
  inscrits: number;
  checkin: number;
  reduction: number | null;
  is_gratuit: boolean;
  is_flash: boolean;
  is_sponsorise: boolean;
  sponsorise_actif: boolean;
  sponsor_debut: string | null;
  sponsor_jusqu_au: string | null;
  sponsor_restant_s: number | null;
  supplement_tarif: number;
  sponsor_tarif: number;
  statut: 'passe' | 'complet' | 'actif' | 'annule';
  annule_le?: string | null;
  annule_motif?: string | null;
  /** L'établissement invité à co-organiser, et sa réponse. */
  collaboration?: CollaborationEnvoyee | null;
};

export type StatutCollaboration = 'en_attente' | 'acceptee' | 'refusee';
/** L'invitation à co-organiser qu'un hôte a envoyée pour son événement. */
export type CollaborationEnvoyee = Collaborateur & { statut: StatutCollaboration };
/** Un établissement qu'on peut inviter à co-organiser. */
export type EtablissementInvitable = Collaborateur & { ville: string };
/** Une invitation reçue : en attente, ou une collaboration acceptée à venir. */
export type InvitationCollaboration = {
  id: number;
  statut: 'en_attente' | 'acceptee';
  invite_le: string;
  evenement: { id: number; titre: string; date_heure: string; date_fin: string | null; lieu: string };
  hote: Collaborateur;
};

export type FormulePartenaire = { code: string; nom: string; tarif: number; resume: string; heures: number; notifications: number; offrable?: boolean };

export type EvenementFormulairePartenaire = {
  id: number;
  titre: string;
  description: string;
  type: string;
  style_musique: string | null;
  sport: string | null;
  format: string | null;
  date_heure: string;
  date_fin: string | null;
  /** Clôture anticipée des inscriptions (v38) ; null : jusqu'au début. */
  inscriptions_jusqu_au?: string | null;
  quota: number;
  reduction: number;
  prix_normal: number;
  is_flash: boolean;
  flash_expiry: string | null;
  is_gratuit: boolean;
  lieu: string;
  is_sponsorise: boolean;
  sponsor_formule: string | null;
  sponsor_notifications: string[];
  sponsor_notifications_envoyees: string[];
  /** La mise en avant court encore : payée jusqu'à sa fin, elle ne se change ni ne se retire. */
  sponsor_actif: boolean;
  sponsor_termine: boolean;
  sponsor_jusqu_au: string | null;
  sponsor_debut: string | null;
  sponsor_restant_s: number | null;
  supplement_tarif: number;
  /** L'établissement invité à co-organiser (v39), et sa réponse. */
  collaboration?: CollaborationEnvoyee | null;
};

export type ReponseFormulaireEvenementPartenaire = {
  evenement: EvenementFormulairePartenaire | null;
  /** Un club ne publie que des événements sportifs (type 'sport'). */
  est_club: boolean;
  type_compte: string;
  /** Seul un BDE choisit le type de ses événements ; les autres le tiennent de leur compte. */
  type_libre: boolean;
  type_evenement: string | null;
  /** Durée retenue pour un événement sans heure de fin. */
  duree_defaut_heures: number;
  sports: { code: string; libelle: string }[];
  formats: { code: string; libelle: string }[];
  lieu_defaut: string;
  styles_musique: { code: string; libelle: string }[];
  formules_sponsoring: FormulePartenaire[];
  flash_illimite: boolean;
  flash_par_mois: number | null;
  usage: UsageMoisPartenaire;
  /** Le prochain événement publié dépasse-t-il le quota du mois ? */
  supplement: { prochain: boolean; tarif: number };
};

export type FormulePartenaireAbonnement = {
  code: string;
  libelle: string;
  tarif: number;
  note: string;
  places_restantes: number | null;
  mois_essai: number;
  evenements_par_mois: number;
  /** Quota des trois premiers mois (fondateur) ; null sinon. */
  evenements_debut: number | null;
  flash_par_mois: number | null;
  mises_en_avant: number;
};

export type ClientPartenaireAbonnement = {
  offre: string;
  offre_libelle: string;
  mrr: number;
  statut: string;
  essai_jusquau: string | null;
};

export type ReponseAbonnementPartenaire = {
  client: ClientPartenaireAbonnement | null;
  formules: FormulePartenaireAbonnement[];
  est_club: boolean;
  type_compte: string;
  /** Un BDE n'a ni formule ni publication tant que l'administrateur ne l'a pas validé. */
  validation: { requise: boolean; statut: 'non_requise' | 'en_attente' | 'validee' | 'refusee'; valide: boolean; note: string | null };
  /** Une formule se change au plus une fois par mois. */
  changement: { possible: boolean; prochain: string | null };
  inclus: {
    evenements_par_mois: number;
    en_periode_offerte: boolean;
    evenements_apres_offre: number;
    flash_par_mois: number | null;
    mises_en_avant_offertes: number;
  };
  usage: UsageMoisPartenaire;
  achats: { evenement_supplementaire: number; sponsoring: (FormulePartenaire & { heures: number; notifications: number })[] };
};

export type SuiviEvenementPartenaire = {
  evenement: {
    id: number; titre: string; type: string; date_heure: string; date_fin: string | null; lieu: string;
    statut: 'passe' | 'complet' | 'actif'; is_flash: boolean; flash_expiry: string | null; is_gratuit: boolean; publie_le: string;
  };
  chiffres: {
    quota: number; inscrits: number; presents: number; annules: number;
    places_restantes: number | null; taux_remplissage: number | null; taux_presence: number | null;
  };
  courbe: { jour: string; inscriptions: number; cumul: number }[];
  ecoles: { nom: string; nombre: number; pourcentage: number }[];
  /** Les inscrits (hors annulés), sans leur e-mail, pour l'établissement propriétaire. */
  participants: { id: number; prenom: string; nom: string; ecole: string | null; promo: string | null; present: boolean; inscrit_le: string }[];
  mise_en_avant: {
    active: boolean; en_cours: boolean; formule: string | null; libelle: string | null; offerte: boolean; jusqu_au: string | null;
    /** Quand elle a démarré (heure de Paris), null pour une mise en avant d'avant le suivi du début. */
    debut: string | null;
    /** Secondes restantes, calculées par le serveur : l'application décompte à partir de là. */
    restant_s: number | null; duree_s: number | null; ecoule_pct: number | null;
    notifications: { envoyer_le: string; envoyee: boolean }[]; notifications_incluses: number;
  };
  cout: { supplement: number; sponsoring: number; total: number };
};

export type PhotoEtablissement = { id: number; url: string | null; legende: string | null; position: number };

export type ReponsePhotosPartenaire = { max: number; photos: PhotoEtablissement[] };

// ─── Administration ─────────────────────────────────────────────────────────

export type Signalement = {
  id: number;
  motif: string;
  details: string | null;
  created_at: string;
  total_cible: number;
  signale_par: string;
  personne: { id: number; prenom: string; nom: string; photo_url: string | null };
  traite_par: string | null;
  traite_le: string | null;
};

export type ReponseModeration = { nb_nouveaux: number; signalements: Signalement[] };

export type EtudiantAdmin = {
  id: number;
  prenom: string;
  nom: string;
  email: string;
  ecole: string | null;
  promo: string | null;
  photo_url: string | null;
  interets: string[];
  sorties: number;
  presences: number;
  squads: number;
  economies: number;
  derniere_activite: string | null;
  inscrit_le: string;
};

export type ReponseEtudiantsAdmin = {
  repere: { total: number; nouveaux_7j: number; actifs: number };
  filtres: { q: string; ecole: string; etat: string; ecoles_disponibles: string[] };
  pagination: { page: number; par_page: number; total: number; pages: number };
  etudiants: EtudiantAdmin[];
};

export type EvenementAdmin = {
  id: number;
  titre: string;
  type: string;
  date_heure: string;
  passe: boolean;
  etablissement: { nom: string; ville: string };
  quota: number;
  inscrits: number;
  presents: number | null;
  taux_presence: number | null;
  reduction: number | null;
  is_gratuit: boolean;
  is_flash: boolean;
  sponsorise_actif: boolean;
  annule?: boolean;
};

export type ReponseEvenementsAdmin = {
  repere: { total: number; a_venir: number; nouveaux_7j: number; taux_presence_global: number | null };
  filtres: { etablissement: number | null; periode: string; type: string; etablissements: { id: number; nom: string }[] };
  evenements: EvenementAdmin[];
};

export type MouvementFinance = {
  id: number;
  sens: 'recette' | 'depense';
  categorie: string;
  libelle: string;
  montant_ht: number;
  statut: 'regle' | 'prevu';
  date: string;
  client: string | null;
};

/** Un point d'une série quotidienne du suivi (jour 'Y-m-d'). */
export type PointJour = { jour: string; n: number };

/** Le suivi de l'application (api/v1/admin_suivi.php). Un volet nul : indisponible. */
export type ReponseSuiviAdmin = {
  genere_le: string;
  utilisateurs: {
    etudiants: number; etablissements: number; admins: number;
    nouveaux: { jour: number; semaine: number; mois: number; evolution: number | null };
    actifs: { jour: number; semaine: number; mois: number };
    serie_inscriptions: PointJour[];
    record_inscriptions: PointJour | null;
    heures_de_pointe: { heure: number; moyenne: number }[];
    pic_horaire: { heure: string; n: number } | null;
  } | null;
  evenements: {
    total: number; publies_mois: number; a_venir: number; annules_mois: number;
    pass_mois: number; entrees_mois: number; taux_presence_30j: number | null;
    serie_pass: PointJour[];
  } | null;
  abonnements: {
    actifs: number; nouveaux_mois: number; perdus_mois: number;
    mrr: number; mrr_engage: number; clients_payants: number;
    par_offre: { offre: string; libelle: string; nombre: number; essais: number; mrr: number }[];
  } | null;
  options: {
    mois: string; total_mois: number; total_precedent: number;
    detail: { nature: string; libelle: string; nombre: number; montant: number }[];
  } | null;
  revenus: {
    mrr: number; options_mois: number; recettes_mois: number; depenses_mois: number;
    resultat_mois: number; tresorerie: number;
    serie_6_mois: { mois: string; recettes: number; options: number }[];
  } | null;
  sante: {
    erreurs_ouvertes: number; erreurs_serveur: number; plantages_app: number;
    erreurs_vues_24h: number; erreurs_nouvelles_24h: number;
    push_en_echec_24h: number | null; push_en_attente: number | null;
    paiements_en_attente: number | null; signalements: number | null; bde_en_attente: number | null;
  } | null;
};

export type FiltreErreurs = 'ouvertes' | 'serveur' | 'app' | 'resolues';

/** Une erreur du journal (api/v1/admin_erreurs.php), regroupée par empreinte. */
export type ErreurJournal = {
  id: number;
  source: 'serveur' | 'app';
  message: string;
  detail: string | null;
  contexte: Record<string, unknown> | null;
  occurrences: number;
  premiere_le: string;
  derniere_le: string;
  resolu_le: string | null;
};

export type ReponseFinancesAdmin = {
  tresorerie: { solde: number; plancher: number; sous_le_plancher: boolean; autonomie_mois: number | null };
  mrr: { facture: number; engage: number; clients_payants: number };
  mois_courant: { recettes: number; depenses: number; resultat: number; prevu_recettes: number; prevu_depenses: number };
  mouvements: MouvementFinance[];
};

// ─── Lectures ────────────────────────────────────────────────────────────────

export const api = {
  connexion: (email: string, motDePasse: string, appareil?: string, codeTotp?: string) =>
    appelApi<ReponseConnexion>('login.php', { methode: 'POST', corps: { email, password: motDePasse, appareil, code_totp: codeTotp } }),
  inscription: (corps: Record<string, unknown>) =>
    appelApi<ReponseConnexion>('inscription.php', { methode: 'POST', corps }),
  /** Demande un code par e-mail. `code_dev` n'est rempli qu'en local, où rien ne part. */
  motDePasseOublie: (email: string) =>
    appelApi<{ success: true; code_dev: string | null }>('mot_de_passe_oublie.php', { methode: 'POST', corps: { email } }),
  /** Choisit un nouveau mot de passe avec le code reçu. Déconnecte tous les appareils. */
  reinitialiserMotDePasse: (email: string, code: string, motDePasse: string) =>
    appelApi<{ success: true }>('mot_de_passe_reinitialiser.php', { methode: 'POST', corps: { email, code, mot_de_passe: motDePasse } }),
  /**
   * Supprime le compte (étudiant) ou en fait la demande (partenaire).
   * `supprime` : le compte n'existe plus ; `demande` : transmise à l'équipe.
   */
  supprimerCompte: (jeton: string, motDePasse: string) =>
    appelApi<{ success: true; supprime: boolean; demande?: boolean }>('supprimer_compte.php', { methode: 'POST', jeton, corps: { mot_de_passe: motDePasse } }),
  /** Change le mot de passe ; les autres appareils sont déconnectés. */
  changerMotDePasse: (jeton: string, motDePasse: string, nouveau: string) =>
    appelApi<{ success: true }>('compte.php', { methode: 'POST', jeton, corps: { action: 'mot_de_passe', mot_de_passe: motDePasse, nouveau } }),
  /** Premier temps : un code part vers la nouvelle adresse. `code_dev` n'est rempli qu'en local. */
  changerEmail: (jeton: string, motDePasse: string, email: string) =>
    appelApi<{ success: true; envoye: true; email: string; code_dev?: string }>('compte.php', { methode: 'POST', jeton, corps: { action: 'email', mot_de_passe: motDePasse, email } }),
  /** Second temps : le code est juste, l'adresse change et l'ancienne en est avertie. */
  confirmerEmail: (jeton: string, motDePasse: string, code: string) =>
    appelApi<{ success: true; email: string }>('compte.php', { methode: 'POST', jeton, corps: { action: 'email_confirmer', mot_de_passe: motDePasse, code } }),
  /** Un texte légal, sans jeton : on le lit avant de créer un compte. */
  legal: (slug: string) => appelApi<{ success: true; page: PageLegale }>('legal.php', { params: { page: slug } }),
  deconnexion: (jeton: string) => appelApi<{ success: true }>('logout.php', { methode: 'POST', jeton }),
  /** Les catégories de notification de ce compte et ce qui est activé. */
  pushReglages: (jeton: string) => appelApi<{ categories: CategoriePush[] }>('push.php', { jeton }),
  pushEnregistrer: (jeton: string, jetonExpo: string, plateforme: 'ios' | 'android', appareil?: string) =>
    appelApi<{ success: true }>('push.php', { methode: 'POST', jeton, corps: { action: 'enregistrer', jeton: jetonExpo, plateforme, appareil } }),
  pushRetirer: (jeton: string, jetonExpo: string) =>
    appelApi<{ success: true }>('push.php', { methode: 'POST', jeton, corps: { action: 'retirer', jeton: jetonExpo } }),
  pushPreference: (jeton: string, categorie: string, actif: boolean) =>
    appelApi<{ categories: CategoriePush[] }>('push.php', { methode: 'POST', jeton, corps: { action: 'preference', categorie, actif } }),
  moi: (jeton: string) => appelApi<{ utilisateur: Profil }>('me.php', { jeton }),

  evenements: (jeton: string, params?: { type?: string; musique?: string; q?: string; pe?: number }) =>
    appelApi<ReponseEvenements>('evenements.php', { jeton, params }),
  evenement: (jeton: string, id: number) => appelApi<{ evenement: EvenementDetail }>('evenement.php', { jeton, params: { id } }),
  notifications: (jeton: string) => appelApi<{ a_traiter: number; items: Notification[] }>('notifications.php', { jeton }),
  marquerLues: (jeton: string) => appelApi<{ success: boolean }>('notifications.php', { methode: 'POST', jeton }),
  personnes: (jeton: string, params: { q?: string; ecole?: string; interest?: string; p?: number }) =>
    appelApi<ReponsePersonnes>('personnes.php', { jeton, params }),
  etudiant: (jeton: string, id: number) => appelApi<{ etudiant: Etudiant }>('etudiant.php', { jeton, params: { id } }),
  wallet: (jeton: string, page?: number) => appelApi<ReponseWallet>('wallet.php', { jeton, params: { h: page } }),
  squads: (jeton: string) => appelApi<{ squads: Squad[] }>('squads.php', { jeton }),
  squadMembres: (jeton: string, id: number) =>
    appelApi<{ my_id: number; membres: { id: number; prenom: string; nom: string; ecole: string | null }[] }>('squad_membres.php', { jeton, params: { id } }),
  pageMoi: (jeton: string) => appelApi<ReponseMoi>('moi.php', { jeton }),
  enregistrerProfil: (jeton: string, formulaire: FormData) =>
    appelApi<{ message: string | null; erreur_photo: string | null; utilisateur: Profil }>('profil.php', { methode: 'POST', formulaire, jeton }),
  classement: (jeton: string, portee: string) => appelApi<ReponseClassement>('classement.php', { jeton, params: { portee } }),
  abonnements: (jeton: string, params: { type: string; q?: string; p?: number }) =>
    appelApi<ReponseAbonnements>('abonnements.php', { jeton, params }),
  blocages: (jeton: string) => appelApi<ReponseBlocages>('blocages.php', { jeton }),
  avis: (jeton: string, eventId: number) => appelApi<ReponseAvis>('avis.php', { jeton, params: { event_id: eventId } }),
  envoyerAvis: (jeton: string, eventId: number, note: number, commentaire: string) =>
    appelApi<{ message: string }>('avis.php', { methode: 'POST', jeton, corps: { event_id: eventId, note, commentaire } }),
  photosEvenement: (jeton: string, evenementId: number) =>
    appelApi<ReponsePhotosEvenement>('evenement_photos.php', { jeton, params: { id: evenementId } }),
  partenaireDashboard: (jeton: string) => appelApi<{ success: true } & ReponseDashboardPartenaire>('partenaire_dashboard.php', { jeton }),
  partenaireEvenements: (jeton: string) => appelApi<{ evenements: EvenementPartenaire[]; usage: UsageMoisPartenaire }>('partenaire_evenements.php', { jeton }),
  partenaireSuiviEvenement: (jeton: string, id: number) =>
    appelApi<{ success: true } & SuiviEvenementPartenaire>('partenaire_evenement_suivi.php', { jeton, params: { id } }),
  partenaireSupprimerEvenement: (jeton: string, id: number) =>
    appelApi<Ok>('partenaire_evenements.php', { methode: 'POST', jeton, corps: { action: 'supprimer', id } }),
  /** Annule un événement qui a des inscrits : pass annulés, inscrits prévenus. */
  partenaireAnnulerEvenement: (jeton: string, id: number, motif?: string) =>
    appelApi<{ success: true; message: string; pass_annules: number }>('partenaire_evenements.php', { methode: 'POST', jeton, corps: { action: 'annuler', id, motif } }),
  partenaireFormulaireEvenement: (jeton: string, id?: number) =>
    appelApi<{ success: true } & ReponseFormulaireEvenementPartenaire>('partenaire_evenement.php', { jeton, params: { id } }),
  partenaireEnregistrerEvenement: (jeton: string, corps: Record<string, unknown>) =>
    appelApi<Ok & { id: number }>('partenaire_evenement.php', { methode: 'POST', jeton, corps }),
  partenaireRechercherCollaborateurs: (jeton: string, q: string) =>
    appelApi<{ etablissements: EtablissementInvitable[] }>('partenaire_collaborations.php', { jeton, params: { q } }),
  partenaireInvitationsCollaboration: (jeton: string) =>
    appelApi<{ invitations: InvitationCollaboration[] }>('partenaire_collaborations.php', { jeton, params: { invitations: 1 } }),
  partenaireRepondreCollaboration: (jeton: string, id: number, action: 'accepter' | 'refuser' | 'quitter') =>
    appelApi<Ok>('partenaire_collaborations.php', { methode: 'POST', jeton, corps: { action, id } }),
  partenaireScan: (jeton: string, corps: { qr_code?: string; inscription_id?: number; event_id: number }) =>
    appelApi<Ok & { personne: { id: number; prenom: string; nom: string; ecole: string | null; promo: string | null; photo_url: string | null } }>(
      'partenaire_scan.php', { methode: 'POST', jeton, corps }
    ),
  /** Les entrées d'un événement de l'établissement : compteurs et personnes validées. */
  partenaireCheckins: (jeton: string, evenementId: number) =>
    appelApi<{ success: true } & ReponseCheckinsPartenaire>('partenaire_checkins.php', { jeton, params: { evenement_id: evenementId } }),
  partenaireInvites: (jeton: string, evenementId: number, q?: string) =>
    appelApi<{ success: true; invites: { inscription_id: number; prenom: string; nom: string; ecole: string | null; promo: string | null; photo_url: string | null }[] }>(
      'partenaire_invites.php', { jeton, params: { evenement_id: evenementId, q } }
    ),
  adminModeration: (jeton: string, statut: 'nouveau' | 'traite' = 'nouveau') =>
    appelApi<{ success: true } & ReponseModeration>('admin_moderation.php', { jeton, params: { statut } }),
  adminTraiterSignalement: (jeton: string, id: number) =>
    appelApi<Ok>('admin_moderation.php', { methode: 'POST', jeton, corps: { action: 'traiter', id } }),
  adminEtudiants: (jeton: string, params: { q?: string; ecole?: string; etat?: string; p?: number }) =>
    appelApi<{ success: true } & ReponseEtudiantsAdmin>('admin_utilisateurs.php', { jeton, params }),
  adminEvenements: (jeton: string, params: { etablissement?: number; periode?: string; type?: string }) =>
    appelApi<{ success: true } & ReponseEvenementsAdmin>('admin_evenements.php', { jeton, params }),
  adminFinances: (jeton: string) =>
    appelApi<{ success: true } & ReponseFinancesAdmin>('admin_finances.php', { jeton }),
  adminSuivi: (jeton: string) =>
    appelApi<{ success: true } & ReponseSuiviAdmin>('admin_suivi.php', { jeton }),
  adminErreurs: (jeton: string, filtre: FiltreErreurs = 'ouvertes') =>
    appelApi<{ success: true; filtre: FiltreErreurs; erreurs: ErreurJournal[] }>('admin_erreurs.php', { jeton, params: { filtre } }),
  adminErreurAction: (jeton: string, corps: { action: 'resoudre' | 'rouvrir'; id: number } | { action: 'resoudre_tout'; source?: 'serveur' | 'app' }) =>
    appelApi<Ok & { resolues?: number }>('admin_erreurs.php', { methode: 'POST', jeton, corps }),
  partenaireProfil: (jeton: string) =>
    appelApi<{ success: true; compte: { prenom: string; nom: string; email: string }; etablissement: { nom: string; type: string; type_modifiable: boolean; verification: string; ville: string; adresse: string; latitude: number | null; longitude: number | null } }>(
      'partenaire_profil.php', { jeton }
    ),
  partenaireEnregistrerProfil: (jeton: string, corps: { nom: string; type: string; ville: string; adresse: string; latitude?: number; longitude?: number }) =>
    appelApi<Ok>('partenaire_profil.php', { methode: 'POST', jeton, corps }),
  partenaireAbonnement: (jeton: string) =>
    appelApi<{ success: true } & ReponseAbonnementPartenaire>('partenaire_abonnement.php', { jeton }),
  partenairePhotos: (jeton: string) =>
    appelApi<{ success: true } & ReponsePhotosPartenaire>('partenaire_photos.php', { jeton }),
  partenaireAjouterPhoto: (jeton: string, formulaire: FormData) =>
    appelApi<{ success: true; photo: PhotoEtablissement }>('partenaire_photos.php', { methode: 'POST', formulaire, jeton }),
  partenaireSupprimerPhoto: (jeton: string, photoId: number) =>
    appelApi<Ok>('partenaire_photo_supprimer.php', { methode: 'POST', jeton, corps: { photo_id: photoId } }),
  partenaireDefinirCouverture: (jeton: string, photoId: number) =>
    appelApi<Ok>('partenaire_photo_couverture.php', { methode: 'POST', jeton, corps: { photo_id: photoId } }),
  publierPhotoEvenement: (jeton: string, formulaire: FormData) =>
    appelApi<{ photo: PhotoEvenement; remplace: boolean }>('evenement_photos.php', { methode: 'POST', formulaire, jeton }),
  supprimerPhotoEvenement: (jeton: string, photoId: number) =>
    appelApi<Ok>('evenement_photo_supprimer.php', { methode: 'POST', jeton, corps: { photo_id: photoId } }),
  signalerPhotoEvenement: (jeton: string, corps: { photo_id: number; motif: string; details?: string }) =>
    appelApi<Ok>('evenement_photo_signaler.php', { methode: 'POST', jeton, corps }),

  // ─── Sport ────────────────────────────────────────────────────────────────
  /** `autour` : ne garder que les clubs à moins de `rayon` km du point (parties, terrains, événements de club). */
  sport: (jeton: string, sport?: string, autour?: { lat: number; lng: number; rayon: number }) =>
    appelApi<{ success: true } & ReponseSport>('sport.php', { jeton, params: { sport, ...(autour ?? {}) } }),
  creneau: (jeton: string, id: number) => appelApi<{ creneau: CreneauDetail }>('sport_creneau.php', { jeton, params: { id } }),
  reserverCreneau: (jeton: string, id: number, choix: { amis: number[]; ouvert: boolean; niveau: NiveauSport }) =>
    appelApi<Ok & { creneau: CreneauDetail }>('sport_creneau.php', { methode: 'POST', jeton, corps: { action: 'reserver', id, ...choix } }),
  rejoindreCreneau: (jeton: string, id: number) =>
    appelApi<Ok & { creneau: CreneauDetail }>('sport_creneau.php', { methode: 'POST', jeton, corps: { action: 'rejoindre', id } }),
  quitterCreneau: (jeton: string, id: number) =>
    appelApi<Ok & { creneau: CreneauDetail }>('sport_creneau.php', { methode: 'POST', jeton, corps: { action: 'quitter', id } }),
  modifierPartie: (jeton: string, id: number, ouvert: boolean, niveau: NiveauSport) =>
    appelApi<Ok & { creneau: CreneauDetail }>('sport_creneau.php', { methode: 'POST', jeton, corps: { action: 'modifier', id, ouvert, niveau } }),
  /** Événement d'étudiant : gratuit, visible de ses abonnés (public pour une association). */
  creerEvenementSport: (jeton: string, corps: { titre: string; type: string; niveau: string; date_heure: string; quota: number; lieu: string; description: string; cree_par_association: boolean }) =>
    appelApi<Ok & { id: number }>('sport_squad.php', { methode: 'POST', jeton, corps }),

  // ─── Club ─────────────────────────────────────────────────────────────────
  clubTerrains: (jeton: string) => appelApi<{ success: true } & ReponseTerrainsClub>('club_terrains.php', { jeton }),
  clubEnregistrerTerrain: (jeton: string, terrain: { id?: number; nom: string; sport: string; joueurs: number; duree_min: number; prix: number; actif: boolean }) =>
    appelApi<Ok & ReponseTerrainsClub>('club_terrains.php', { methode: 'POST', jeton, corps: { action: 'enregistrer', ...terrain } }),
  clubSupprimerTerrain: (jeton: string, id: number) =>
    appelApi<Ok & ReponseTerrainsClub>('club_terrains.php', { methode: 'POST', jeton, corps: { action: 'supprimer', id } }),
  clubCreneaux: (jeton: string, du: string, jours = 7) =>
    appelApi<{ success: true; du: string; jours: number; creneaux: Creneau[] }>('club_creneaux.php', { jeton, params: { du, jours } }),
  clubPublierCreneaux: (jeton: string, terrainId: number, debuts: string[]) =>
    appelApi<Ok & { crees: number; ignores: number }>('club_creneaux.php', { methode: 'POST', jeton, corps: { action: 'publier', terrain_id: terrainId, debuts } }),
  clubSupprimerCreneau: (jeton: string, id: number) =>
    appelApi<Ok>('club_creneaux.php', { methode: 'POST', jeton, corps: { action: 'supprimer', id } }),
  // ── Espace Culture ──
  culture: (jeton: string, params?: { categorie?: string; pe?: number }) =>
    appelApi<ReponseCulture>('culture.php', { jeton, params }),
  cultureEvenement: (jeton: string, id: number) =>
    appelApi<{ evenement: EvenementCultureDetail }>('culture_evenement.php', { jeton, params: { id } }),
  /** S'inscrire, ou se désinscrire avec annuler = true. Un refus (complet, terminé) arrive en ErreurApi 409. */
  cultureInscription: (jeton: string, evenementId: number, annuler = false) =>
    appelApi<Ok & { inscrit: boolean; inscrits: number }>('culture_inscription.php', {
      methode: 'POST', jeton, corps: { evenement_id: evenementId, action: annuler ? 'annuler' : 'inscrire' },
    }),
  adminBdeListe: (jeton: string) =>
    appelApi<{ success: true; en_attente: number; comptes: CompteBdeAdmin[] }>('admin_bde.php', { jeton }),
  adminBde: (jeton: string, corps: { action: 'valider' | 'refuser' | 'remettre'; id: number; note?: string }) =>
    appelApi<Ok>('admin_bde.php', { methode: 'POST', jeton, corps }),
  adminCulture: (jeton: string) => appelApi<{ success: true } & ReponseCultureAdmin>('admin_culture.php', { jeton }),
  adminEnregistrerCulture: (jeton: string, corps: Record<string, unknown>) =>
    appelApi<Ok & { id: number }>('admin_culture.php', { methode: 'POST', jeton, corps: { ...corps, action: 'enregistrer' } }),
  adminSupprimerCulture: (jeton: string, id: number) =>
    appelApi<Ok>('admin_culture.php', { methode: 'POST', jeton, corps: { action: 'supprimer', id } }),
};

// ─── Écritures partagées avec le site ────────────────────────────────────────

type Ok = { success: true; message?: string };

export const actions = {
  inscrire: (jeton: string, evenementId: number) =>
    action<Ok & { inscrits?: number }>('inscrire.php', jeton, { evenement_id: evenementId }),
  annulerPass: (jeton: string, inscriptionId: number) => action<Ok>('annuler_pass.php', jeton, { inscription_id: inscriptionId }),
  rejoindreSquad: (jeton: string, squadId: number) => action<Ok & { membres?: number }>('rejoindre_squad.php', jeton, { squad_id: squadId }),
  quitterSquad: (jeton: string, squadId: number) => action<Ok>('quitter_squad.php', jeton, { squad_id: squadId }),
  supprimerSquad: (jeton: string, squadId: number) => action<Ok>('delete_squad.php', jeton, { squad_id: squadId }),
  retirerMembre: (jeton: string, squadId: number, membreId: number) =>
    action<Ok>('remove_squad_member.php', jeton, { squad_id: squadId, member_id: membreId }),
  /** follow / unfollow / accept / decline, sur un étudiant ou un établissement. */
  suivi: (jeton: string, act: 'follow' | 'unfollow' | 'accept' | 'decline', type: 'user' | 'etablissement', cible: number) =>
    // etat est null pour accept/decline : l'action porte sur la demande d'un
    // tiers, pas sur « mon » état de suivi envers cible (voir api/follow.php).
    action<Ok & { etat: EtatSuivi | null; count: number }>('follow.php', jeton, { action: act, type, target_id: cible }),
  inviter: (jeton: string, destinataire: number, type: CibleInvitation, cible: number) =>
    action<Ok>('inviter.php', jeton, { action: 'send', to_user_id: destinataire, type, target_id: cible }),
  repondreInvitation: (jeton: string, invitationId: number, accepter: boolean) =>
    action<Ok>('inviter.php', jeton, { action: accepter ? 'accept' : 'decline', invite_id: invitationId }),
  moderation: (jeton: string, corps: { action: 'report' | 'block' | 'unblock'; target_id: number; motif?: string; details?: string }) =>
    action<Ok>('moderation.php', jeton, corps),
  /** Réservé à l'administrateur — voir apiAdmin() côté serveur. */
  adminSupprimerUtilisateur: (jeton: string, userId: number) =>
    action<Ok>('admin_supprimer_utilisateur.php', jeton, { user_id: userId }),
  adminSupprimerEvenement: (jeton: string, evenementId: number) =>
    action<Ok>('admin_supprimer_evenement.php', jeton, { evenement_id: evenementId }),
  adminAnnulerEvenement: (jeton: string, evenementId: number) =>
    action<Ok & { message?: string }>('admin_annuler_evenement.php', jeton, { evenement_id: evenementId }),
};
