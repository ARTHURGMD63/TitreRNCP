/**
 * Charger des donnees depuis l'API, avec les quatre etats qui vont avec.
 *
 * POURQUOI UN HOOK PLUTOT QUE LA MEME MECANIQUE DANS CHAQUE ECRAN
 *
 * Chaque ecran a besoin exactement des memes choses : un indicateur de
 * chargement, un message d'erreur affichable, un tirer-pour-rafraichir, et
 * l'annulation qui evite qu'une reponse lente n'ecrase une reponse recente.
 * Recopiees ecran par ecran, ces quatre choses finissent par diverger — c'est
 * le meme raisonnement qui a donne pageDebut() et enTetesJson() cote web.
 *
 * SUR LA REGLE set-state-in-effect, LEVEE PLUS BAS
 *
 * react-hooks interdit d'appeler setState depuis un effet, et elle a raison :
 * un setState synchrone y declenche un second rendu avant meme que le premier
 * soit peint.
 *
 * Ici, aucune ecriture ne part du corps de l'effet : toutes suivent une
 * attente reseau, donc s'executent depuis un callback, une fois le premier
 * rendu termine. C'est precisement ce que la regle demande. Mais son analyse
 * traverse l'appel a lancer(), y voit des setState, et ne peut pas distinguer
 * ceux qui sont derriere un `await` de ceux qui ne le sont pas.
 *
 * La regle est donc levee a cet endroit precis, une seule fois, plutot que
 * dans chaque ecran — c'est tout l'interet de concentrer la mecanique ici. Une
 * exception justifiee et relisible, au lieu d'une ligne de suppression
 * recopiee partout, que plus personne n'examine au bout de trois ecrans.
 */

import { useCallback, useEffect, useRef, useState } from 'react';

import { ErreurApi } from './api';

type Etat<T> = {
  donnees: T | null;
  chargement: boolean;
  rafraichit: boolean;
  erreur: string | null;
  /** Relance depuis un geste de l'utilisateur : bouton « Réessayer ». */
  recharger: () => void;
  /** Relance depuis un tirer-pour-rafraichir : pas de voile de chargement. */
  rafraichir: () => void;
};

/**
 * @param recuperer L'appel d'API. DOIT etre memoise par l'appelant avec
 *        useCallback : c'est son identite qui declenche un rechargement, donc
 *        une fonction recreee a chaque rendu boucherait indefiniment. En
 *        contrepartie, il n'y a pas de liste de dependances a tenir a jour
 *        ici — celle du useCallback de l'appelant fait foi, et elle, ESLint
 *        sait la verifier.
 */
export function useChargement<T>(recuperer: () => Promise<T>): Etat<T> {
  const [donnees, setDonnees] = useState<T | null>(null);
  const [chargement, setChargement] = useState(true);
  const [rafraichit, setRafraichit] = useState(false);
  const [erreur, setErreur] = useState<string | null>(null);

  // Compteur de generation : seule la demande la plus recente a le droit
  // d'ecrire. Sans cela, taper vite sur deux filtres laisse deux requetes en
  // vol, et c'est la plus LENTE qui gagne — l'ecran affiche alors le resultat
  // d'un filtre qui n'est plus selectionne.
  const generation = useRef(0);

  const lancer = useCallback(async () => {
    const mienne = ++generation.current;

    try {
      const resultat = await recuperer();
      if (generation.current !== mienne) return;
      setDonnees(resultat);
      setErreur(null);
    } catch (e) {
      if (generation.current !== mienne) return;
      setErreur(e instanceof ErreurApi ? e.message : 'Chargement impossible.');
    } finally {
      if (generation.current === mienne) {
        setChargement(false);
        setRafraichit(false);
      }
    }
  }, [recuperer]);

  useEffect(() => {
    // Voir l'en-tete : les ecritures suivent toutes l'attente reseau, l'analyse
    // statique ne peut simplement pas le demontrer a travers l'appel.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void lancer();
  }, [lancer]);

  const recharger = useCallback(() => {
    setChargement(true);
    void lancer();
  }, [lancer]);

  const rafraichir = useCallback(() => {
    setRafraichit(true);
    void lancer();
  }, [lancer]);

  return { donnees, chargement, rafraichit, erreur, recharger, rafraichir };
}
