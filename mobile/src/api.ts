/**
 * Client de l'API Linkee.
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

/**
 * L'adresse du serveur.
 *
 * En développement, ce n'est PAS « localhost » : sur un téléphone, localhost
 * désigne le téléphone lui-même. Expo connaît l'adresse de la machine — c'est
 * par elle qu'il sert le bundle — et on la lui reprend, ce qui évite une IP
 * écrite en dur, fausse au prochain bail DHCP.
 *
 * En production, EXPO_PUBLIC_API_URL est fixée au moment du build.
 */
export function adresseServeur(): string {
  const configuree = process.env.EXPO_PUBLIC_API_URL;
  if (configuree) return configuree.replace(/\/+$/, '');

  const hote = Constants.expoConfig?.hostUri?.split(':')[0];
  if (hote) return `http://${hote}:8080/TitreRNCP`;

  return 'http://localhost:8080/TitreRNCP';
}

export const BASE_API = `${adresseServeur()}/api/v1`;
const BASE_ACTIONS = `${adresseServeur()}/api`;

/** Erreur portant le code stable renvoyé par l'API. */
export class ErreurApi extends Error {
  constructor(message: string, readonly code: string, readonly statut: number) {
    super(message);
    this.name = 'ErreurApi';
  }
  /** Le jeton est absent, expiré ou révoqué : il faut se reconnecter. */
  get estDeconnecte(): boolean {
    return this.statut === 401;
  }
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
  if (corps !== undefined) entetes['Content-Type'] = 'application/json';
  if (jeton) entetes.Authorization = `Bearer ${jeton}`;

  // Sans délai maximal, un serveur injoignable laisse l'écran sur son rond qui
  // tourne indéfiniment — la pire des réponses, parce qu'elle n'en est pas une.
  const abandon = new AbortController();
  const minuteur = setTimeout(() => abandon.abort(), 15000);

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
    throw new ErreurApi(donnees?.message ?? messageDefaut, donnees?.code ?? String(reponse.status), reponse.status);
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

export function appelApi<T>(chemin: string, options: Options = {}): Promise<T> {
  return requete<T>(urlAvecParams(`${BASE_API}/${chemin.replace(/^\/+/, '')}`, options.params), options, 'Une erreur est survenue.');
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
  type: 'etudiant' | 'partenaire' | 'admin';
  photo_url: string | null;
  interets: string[];
};

export type Evenement = {
  id: number;
  titre: string;
  description: string;
  type: string;
  style_musique: string | null;
  date_heure: string;
  lieu: string;
  etablissement: { id: number; nom: string; type: string; ville: string; note: number | null; nb_avis: number; suivi: boolean };
  places: { quota: number; inscrits: number; restantes: number | null; complet: boolean };
  reduction: number | null;
  prix_normal: number | null;
  is_gratuit: boolean;
  is_flash: boolean;
  flash_expiry: string | null;
  sponsorise: boolean;
  deja_inscrit: boolean;
  amis: { prenoms: string[]; nb: number };
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

export type Pass = {
  id: number;
  evenement_id: number | null;
  titre: string;
  etablissement: string;
  lieu: string;
  date_heure: string | null;
  statut: string;
  reduction: number | null;
  prix_normal: number | null;
  economie: number | null;
  evenement_supprime: boolean;
  etab_type: string | null;
  code_qr: string | null;
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
  membres: { id: number; prenom: string; photo_url: string | null }[];
};

export type Notification = {
  type: 'demande' | 'invitation' | 'ami' | 'lieu';
  ts: string;
  depuis: string;
  traiter: boolean;
  nouvelle: boolean;
  section: string;
  acteur?: Personne;
  invitation?: { id: number; cible_type: 'event' | 'squad'; cible_nom: string | null; de: Personne };
  activite?: { cible_type: 'event' | 'squad'; cible_id: number; cible_nom: string; lieu: string; acteur: Personne };
  evenement?: { id: number; titre: string; lieu: string; date: string };
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

export type ReponseAvis = {
  evenement: { id: number; titre: string; etablissement: string; date: string };
  avis: { note: number; commentaire: string } | null;
};

export type ReponseConnexion = { token: string; expire_le: string; utilisateur: Profil };

// ─── Lectures ────────────────────────────────────────────────────────────────

export const api = {
  connexion: (email: string, motDePasse: string, appareil?: string) =>
    appelApi<ReponseConnexion>('login.php', { methode: 'POST', corps: { email, password: motDePasse, appareil } }),
  inscription: (corps: Record<string, unknown>) =>
    appelApi<ReponseConnexion>('inscription.php', { methode: 'POST', corps }),
  motDePasseOublie: (email: string) =>
    appelApi<{ success: true; lien_dev: string | null }>('mot_de_passe_oublie.php', { methode: 'POST', corps: { email } }),
  deconnexion: (jeton: string) => appelApi<{ success: true }>('logout.php', { methode: 'POST', jeton }),
  moi: (jeton: string) => appelApi<{ utilisateur: Profil }>('me.php', { jeton }),

  evenements: (jeton: string, params?: { type?: string; musique?: string; pe?: number }) =>
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
  avis: (jeton: string, eventId: number) => appelApi<ReponseAvis>('avis.php', { jeton, params: { event_id: eventId } }),
  envoyerAvis: (jeton: string, eventId: number, note: number, commentaire: string) =>
    appelApi<{ message: string }>('avis.php', { methode: 'POST', jeton, corps: { event_id: eventId, note, commentaire } }),
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
  creerSquad: (jeton: string, squad: { titre: string; type: string; niveau: string; date_heure: string; quota: number; lieu: string; description: string }) =>
    action<Ok>('create_squad.php', jeton, squad),
  /** follow / unfollow / accept / decline, sur un étudiant ou un établissement. */
  suivi: (jeton: string, act: 'follow' | 'unfollow' | 'accept' | 'decline', type: 'user' | 'etablissement', cible: number) =>
    action<Ok & { etat: EtatSuivi; count: number }>('follow.php', jeton, { action: act, type, target_id: cible }),
  inviter: (jeton: string, destinataire: number, type: 'event' | 'squad', cible: number) =>
    action<Ok>('inviter.php', jeton, { action: 'send', to_user_id: destinataire, type, target_id: cible }),
  repondreInvitation: (jeton: string, invitationId: number, accepter: boolean) =>
    action<Ok>('inviter.php', jeton, { action: accepter ? 'accept' : 'decline', invite_id: invitationId }),
  moderation: (jeton: string, corps: { action: 'report' | 'block' | 'unblock'; target_id: number; motif?: string; details?: string }) =>
    action<Ok>('moderation.php', jeton, corps),
};
