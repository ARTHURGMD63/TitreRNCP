/**
 * Une horloge partagée : une seule minuterie pour tous les comptes à rebours.
 *
 * Chaque carte d'événement avait sa propre minuterie d'une seconde, active
 * même application en arrière-plan : vingt cartes, vingt réveils par
 * seconde, pour rien. Ici, une minuterie unique tourne tant qu'au moins un
 * abonné l'écoute, et s'arrête dès qu'il n'y en a plus — ou que l'application
 * passe en arrière-plan (pause / reprendre, voir CarteEvenement.tsx).
 *
 * Sans module natif : se teste avec Node (tests/horloge.test.ts), la
 * minuterie et l'heure sont injectables.
 */

type Rappel = (maintenant: number) => void;

export type Minuterie = {
  demarrer: (f: () => void, periode: number) => unknown;
  arreter: (id: unknown) => void;
};

export type Horloge = {
  /** Reçoit l'heure à chaque battement. Rend la fonction de désabonnement. */
  abonner: (f: Rappel) => () => void;
  /** Application en arrière-plan : plus aucun battement. */
  pause: () => void;
  /** Retour au premier plan : un battement immédiat, puis la cadence normale. */
  reprendre: () => void;
  /** Vrai tant qu'une minuterie système est armée. */
  enMarche: () => boolean;
};

const minuterieSysteme: Minuterie = {
  demarrer: (f, periode) => setInterval(f, periode),
  arreter: (id) => clearInterval(id as ReturnType<typeof setInterval>),
};

export function creerHorloge(periode = 1000, minuterie: Minuterie = minuterieSysteme, maintenant: () => number = Date.now): Horloge {
  const abonnes = new Set<Rappel>();
  let id: unknown = null;
  let enPause = false;

  function battre() {
    const t = maintenant();
    // Une copie : un abonné peut se désabonner pendant le battement.
    [...abonnes].forEach((f) => f(t));
  }

  function ajuster() {
    const doitTourner = abonnes.size > 0 && !enPause;
    if (doitTourner && id === null) id = minuterie.demarrer(battre, periode);
    else if (!doitTourner && id !== null) {
      minuterie.arreter(id);
      id = null;
    }
  }

  return {
    abonner(f) {
      abonnes.add(f);
      ajuster();
      return () => {
        abonnes.delete(f);
        ajuster();
      };
    },
    pause() {
      enPause = true;
      ajuster();
    },
    reprendre() {
      if (!enPause) return;
      enPause = false;
      ajuster();
      if (abonnes.size > 0) battre();
    },
    enMarche: () => id !== null,
  };
}
