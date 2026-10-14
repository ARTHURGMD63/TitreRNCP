/**
 * format.ts transpose dateFr() et number_format() du site. Ces tests figent
 * les sorties que le site produit, pour que le mobile ne s'en écarte pas.
 *
 * Lancement : npm test (lanceur intégré à Node, aucune dépendance).
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { court, dateFr, heure, lireDate, majuscules, nombre, ts } from '../src/format.ts';

// 23 septembre 2026 est un mercredi.
const SOIREE = '2026-09-23 20:30:00';

test('dateFr : format par défaut, jour court, jour du mois, mois court', () => {
  assert.equal(dateFr(SOIREE), 'Mer 23 Sept');
});

test('dateFr : noms longs et année', () => {
  assert.equal(dateFr(SOIREE, 'l j F Y'), 'Mercredi 23 Septembre 2026');
});

test('dateFr : abréviations françaises propres au site', () => {
  assert.equal(dateFr('2026-02-03', 'M'), 'Févr');
  assert.equal(dateFr('2026-12-03', 'M'), 'Déc');
  assert.equal(dateFr('2026-08-03', 'M'), 'Août');
  assert.equal(dateFr('2026-03-03', 'M'), 'Mars');
});

test('dateFr : le caractère échappé reste littéral', () => {
  assert.equal(dateFr(SOIREE, 'H\hi'), '20h30');
});

test('heure : « 20h30 », avec zéros', () => {
  assert.equal(heure(SOIREE), '20h30');
  assert.equal(heure('2026-09-23 08:05:00'), '08h05');
});

test('dateFr : une valeur absente ou illisible donne une chaîne vide', () => {
  assert.equal(dateFr(null), '');
  assert.equal(dateFr(undefined), '');
  assert.equal(dateFr(''), '');
  assert.equal(dateFr('pas une date'), '');
});

test('lireDate : accepte l’espace comme le T, et une date seule', () => {
  assert.equal(lireDate('2026-09-23T20:30')?.getHours(), 20);
  assert.equal(lireDate('2026-09-23')?.getHours(), 0);
  assert.equal(lireDate('23/09/2026'), null);
});

test('lireDate : lue champ par champ, indépendante du fuseau', () => {
  const d = lireDate(SOIREE);
  assert.deepEqual([d?.getFullYear(), d?.getMonth(), d?.getDate(), d?.getHours(), d?.getMinutes()], [2026, 8, 23, 20, 30]);
});

test('ts : secondes, 0 si la date est illisible', () => {
  assert.equal(ts('nope'), 0);
  assert.equal(ts('2026-09-23 20:31:00') - ts(SOIREE), 60);
});

test('nombre : espace pour les milliers, virgule pour les décimales', () => {
  assert.equal(nombre(1234567), '1 234 567');
  assert.equal(nombre(1234567.8, 1), '1 234 567,8');
  assert.equal(nombre(999), '999');
  assert.equal(nombre(0, 2), '0,00');
});

test('nombre : le signe moins précède le nombre groupé', () => {
  assert.equal(nombre(-1500), '-1 500');
  assert.equal(nombre(-0.5, 1), '-0,5');
});

test('court : prénom et initiale du nom', () => {
  assert.equal(court('Arthur', 'Gramond'), 'Arthur G.');
  assert.equal(court('Arthur', '  '), 'Arthur');
  assert.equal(court('Arthur', null), 'Arthur');
  assert.equal(court('Arthur'), 'Arthur');
});

test('majuscules : accents conservés', () => {
  assert.equal(majuscules('Févr déjà'), 'FÉVR DÉJÀ');
});
