/**
 * Client de l'API StudentLink.
 *
 * Point de passage unique vers le serveur : aucun ecran n'appelle fetch()
 * directement. C'est la meme raison qui avait fait naitre enTetesJson() cote
 * web — quatorze copies d'un objet d'en-tetes, c'est quatorze occasions
 * d'oublier le jeton sur le prochain appel ajoute.
 *
 * Tout ce qui est commun a chaque requete vit donc ici : l'adresse du serveur,
 * le jeton, le decodage, et surtout la traduction des pannes en quelque chose
 * qu'un ecran peut afficher.
 */

import Constants from 'expo-constants';

/**
 * L'adresse du serveur.
 *
 * En developpement, ce n'est PAS « localhost » : sur un telephone, localhost
 * designe le telephone lui-meme. Il faut l'adresse de la machine sur le
 * reseau. Expo la connait — c'est par elle qu'il sert le bundle — et on la lui
 * reprend, ce qui evite de coder une IP en dur qui changera au prochain bail
 * DHCP.
 *
 * En production, EXPO_PUBLIC_API_URL est fixee au moment du build.
 */
function adresseServeur(): string {
  const configuree = process.env.EXPO_PUBLIC_API_URL;
  if (configuree) return configuree.replace(/\/+$/, '');

  // « 192.168.1.82:8081 » — l'hote qui sert le bundle de developpement.
  const hote = Constants.expoConfig?.hostUri?.split(':')[0];
  if (hote) return `http://${hote}:8080/TitreRNCP`;

  // Dernier repli : le simulateur iOS, qui partage bien le localhost du Mac.
  return 'http://localhost:8080/TitreRNCP';
}

export const BASE_API = `${adresseServeur()}/api/v1`;

/** Erreur portant le code stable renvoye par l'API. */
export class ErreurApi extends Error {
  constructor(
    message: string,
    readonly code: string,
    readonly statut: number,
  ) {
    super(message);
    this.name = 'ErreurApi';
  }

  /** Le jeton est absent, expire ou revoque : il faut se reconnecter. */
  get estDeconnecte(): boolean {
    return this.statut === 401;
  }
}

type Options = {
  methode?: 'GET' | 'POST';
  corps?: unknown;
  jeton?: string | null;
  /** Parametres d'URL, les valeurs vides etant ignorees. */
  params?: Record<string, string | number | undefined | null>;
};

/**
 * Un appel a l'API.
 *
 * Toute panne ressort en ErreurApi, y compris celles qui n'en sont pas une
 * cote serveur : coupure reseau, delai depasse, reponse illisible. Un ecran
 * n'a pas a distinguer « le serveur a repondu 500 » de « le wifi est tombe »,
 * il a besoin d'une phrase a afficher.
 */
export async function appelApi<T>(chemin: string, options: Options = {}): Promise<T> {
  const { methode = 'GET', corps, jeton, params } = options;

  const url = new URL(`${BASE_API}/${chemin.replace(/^\/+/, '')}`);
  for (const [cle, valeur] of Object.entries(params ?? {})) {
    if (valeur !== undefined && valeur !== null && valeur !== '') {
      url.searchParams.set(cle, String(valeur));
    }
  }

  const entetes: Record<string, string> = { Accept: 'application/json' };
  if (corps !== undefined) entetes['Content-Type'] = 'application/json';
  if (jeton) entetes.Authorization = `Bearer ${jeton}`;

  // Sans delai maximal, un serveur injoignable laisse l'ecran sur son rond qui
  // tourne indefiniment — la pire des reponses, parce qu'elle n'en est pas une.
  const abandon = new AbortController();
  const minuteur = setTimeout(() => abandon.abort(), 15000);

  let reponse: Response;
  try {
    reponse = await fetch(url.toString(), {
      method: methode,
      headers: entetes,
      body: corps !== undefined ? JSON.stringify(corps) : undefined,
      signal: abandon.signal,
    });
  } catch (e) {
    clearTimeout(minuteur);
    const interrompu = e instanceof Error && e.name === 'AbortError';
    throw new ErreurApi(
      interrompu ? 'Le serveur met trop de temps à répondre.' : 'Connexion au serveur impossible.',
      interrompu ? 'delai' : 'reseau',
      0,
    );
  }
  clearTimeout(minuteur);

  const texte = await reponse.text();
  let donnees: any = null;
  try {
    donnees = texte ? JSON.parse(texte) : null;
  } catch {
    // Reponse illisible : typiquement une page d'erreur HTML servie a la place
    // du JSON. Le message reste comprehensible pour l'utilisateur ; le detail
    // part dans la console, ou un developpeur le trouvera.
    console.warn('Réponse non-JSON de', url.pathname, texte.slice(0, 200));
    throw new ErreurApi('Réponse inattendue du serveur.', 'reponse_illisible', reponse.status);
  }

  if (!reponse.ok || donnees?.success === false) {
    throw new ErreurApi(
      donnees?.message ?? 'Une erreur est survenue.',
      donnees?.code ?? String(reponse.status),
      reponse.status,
    );
  }

  return donnees as T;
}

// ─── Formes renvoyees par l'API ──────────────────────────────────────────────
//
// Ecrites a la main plutot que deduites : elles servent de contrat. Si le
// serveur change une cle, c'est ici que la compilation doit s'arreter, pas
// dans un ecran qui affichera « undefined » sans rien dire.

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
  etablissement: {
    id: number;
    nom: string;
    type: string;
    ville: string;
    note: number | null;
    nb_avis: number;
  };
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
  code_qr: string | null;
};

export type ReponseConnexion = { token: string; expire_le: string; utilisateur: Profil };
export type ReponseEvenements = {
  evenements: Evenement[];
  pagination: { page: number; a_suivre: boolean; cumulative: boolean };
  filtres: { type: string; musique: string };
};
export type ReponseWallet = {
  economies: { mois: number; annee: number };
  pass_actifs: Pass[];
  historique: Pass[];
  pagination: { page: number; a_suivre: boolean; cumulative: boolean };
};

// ─── Appels ──────────────────────────────────────────────────────────────────

export const api = {
  connexion: (email: string, motDePasse: string, appareil?: string) =>
    appelApi<ReponseConnexion>('login.php', {
      methode: 'POST',
      corps: { email, password: motDePasse, appareil },
    }),

  deconnexion: (jeton: string) =>
    appelApi<{ success: true }>('logout.php', { methode: 'POST', jeton }),

  moi: (jeton: string) =>
    appelApi<{ utilisateur: Profil }>('me.php', { jeton }),

  evenements: (jeton: string, params?: { type?: string; musique?: string; pe?: number }) =>
    appelApi<ReponseEvenements>('evenements.php', { jeton, params }),

  wallet: (jeton: string, page?: number) =>
    appelApi<ReponseWallet>('wallet.php', { jeton, params: { h: page } }),
};
