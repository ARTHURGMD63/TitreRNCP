/**
 * Règles d'affichage de l'espace Culture (src/culture.ts).
 *
 * Lancement : npm test (lanceur intégré à Node, aucune dépendance).
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { libelleAmis, libelleBoutonCulture, libellePlaces, libellePrix, pourcentagePlaces, surPlusieursJours } from '../src/culture.ts';

test('libellePlaces : un quota de 0 est une entrée libre, jamais complète', () => {
  assert.equal(libellePlaces({ quota: 0, inscrits: 900, restantes: null, complet: false }), 'Entrée libre');
});

test('libellePlaces : singulier, pluriel et complet', () => {
  assert.equal(libellePlaces({ quota: 16, inscrits: 15, restantes: 1, complet: false }), '1 place restante');
  assert.equal(libellePlaces({ quota: 16, inscrits: 4, restantes: 12, complet: false }), '12 places restantes');
  assert.equal(libellePlaces({ quota: 16, inscrits: 16, restantes: 0, complet: true }), 'Complet');
});

test('pourcentagePlaces : pas de jauge sans quota, bornée à 100', () => {
  assert.equal(pourcentagePlaces({ quota: 0, inscrits: 3, restantes: null, complet: false }), null);
  assert.equal(pourcentagePlaces({ quota: 300, inscrits: 75, restantes: 225, complet: false }), 25);
  assert.equal(pourcentagePlaces({ quota: 10, inscrits: 12, restantes: 0, complet: true }), 100);
});

test('libellePrix : gratuit, entier, décimales à la française', () => {
  assert.equal(libellePrix(0), 'Gratuit');
  assert.equal(libellePrix(5), '5 €');
  assert.equal(libellePrix(7.5), '7,50 €');
});

test('surPlusieursJours : une exposition oui, un concert non', () => {
  const debut = 1_800_000_000;
  assert.equal(surPlusieursJours(debut, 0), false);
  assert.equal(surPlusieursJours(debut, debut + 6 * 3600), false);
  assert.equal(surPlusieursJours(debut, debut + 20 * 24 * 3600), true);
});

test('libelleBoutonCulture : inscrit l’emporte, puis terminé, puis complet', () => {
  assert.equal(libelleBoutonCulture('inscrit', true, true), 'Inscrit');
  assert.equal(libelleBoutonCulture('libre', true, true), 'Terminé');
  assert.equal(libelleBoutonCulture('libre', true), 'Complet');
  assert.equal(libelleBoutonCulture('libre', false), "Je m'inscris");
});

test('libelleAmis : aucun, un, plusieurs', () => {
  assert.equal(libelleAmis({ prenoms: [], nb: 0 }), null);
  assert.equal(libelleAmis({ prenoms: ['Léa'], nb: 1 }), 'Léa y va');
  assert.equal(libelleAmis({ prenoms: ['Léa', 'Hugo', 'Inès', 'Max'], nb: 4 }), 'Léa, Hugo +2 y vont');
});
