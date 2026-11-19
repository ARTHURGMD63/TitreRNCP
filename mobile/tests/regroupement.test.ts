/**
 * regroupement.ts : les pass actifs par jour, l'historique par mois.
 *
 * Lancement : npm test (lanceur intégré à Node, aucune dépendance).
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { cleJour, libelleJour, libelleMois, parJour, parMois, SANS_DATE } from '../src/regroupement.ts';

// Jeudi 19 novembre 2026, 10 h.
const MAINTENANT = new Date(2026, 10, 19, 10, 0, 0);
const p = (id: number, date_heure: string | null) => ({ id, date_heure });

test('cleJour : la date seule, ou « sans date »', () => {
  assert.equal(cleJour('2026-11-22 23:30:00'), '2026-11-22');
  assert.equal(cleJour(null), SANS_DATE);
  assert.equal(cleJour('n’importe quoi'), SANS_DATE);
});

test('libelleJour : aujourd’hui, demain, hier, puis le jour en toutes lettres', () => {
  assert.equal(libelleJour('2026-11-19', MAINTENANT), 'Aujourd’hui');
  assert.equal(libelleJour('2026-11-20', MAINTENANT), 'Demain');
  assert.equal(libelleJour('2026-11-18', MAINTENANT), 'Hier');
  assert.equal(libelleJour('2026-11-22', MAINTENANT), 'Dimanche 22 novembre');
  assert.equal(libelleJour('2027-01-02', MAINTENANT), 'Samedi 2 janvier 2027');
});

test('libelleJour : « demain » franchit la fin du mois', () => {
  assert.equal(libelleJour('2026-12-01', new Date(2026, 10, 30, 22, 0)), 'Demain');
});

test('libelleMois : le mois en toutes lettres et l’année', () => {
  assert.equal(libelleMois('2026-11'), 'Novembre 2026');
  assert.equal(libelleMois('2027-08'), 'Août 2027');
});

test('parJour : un groupe par jour, du plus proche au plus lointain, par heure', () => {
  const groupes = parJour([
    p(1, '2026-11-22 23:30:00'),
    p(2, '2026-11-19 21:00:00'),
    p(3, '2026-11-22 19:00:00'),
    p(4, '2026-11-20 18:30:00'),
  ], MAINTENANT);
  assert.deepEqual(groupes.map((g) => g.libelle), ['Aujourd’hui', 'Demain', 'Dimanche 22 novembre']);
  assert.deepEqual(groupes[2].elements.map((e) => e.id), [3, 1]);
});

test('parJour : les éléments sans date ferment la marche', () => {
  const groupes = parJour([p(1, null), p(2, '2026-11-25 20:00:00')], MAINTENANT);
  assert.deepEqual(groupes.map((g) => g.cle), ['2026-11-25', SANS_DATE]);
});

test('parMois : du mois le plus récent au plus ancien, et du plus récent au plus ancien dans le mois', () => {
  const groupes = parMois([
    p(1, '2026-10-03 22:00:00'),
    p(2, '2026-11-14 23:00:00'),
    p(3, '2026-11-02 20:00:00'),
    p(4, '2026-09-28 21:00:00'),
  ]);
  assert.deepEqual(groupes.map((g) => g.libelle), ['Novembre 2026', 'Octobre 2026', 'Septembre 2026']);
  assert.deepEqual(groupes[0].elements.map((e) => e.id), [2, 3]);
});

test('parJour et parMois : une liste vide donne zéro groupe', () => {
  assert.deepEqual(parJour([], MAINTENANT), []);
  assert.deepEqual(parMois([]), []);
});
