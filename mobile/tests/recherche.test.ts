/**
 * La recherche du sélecteur d'école : on tape sans accents, en minuscules,
 * des bouts de mots, ou ce qu'on sait de l'école plutôt que son nom.
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { ECOLES, MOTS_CLES_ECOLES } from '../src/catalogue.ts';
import { filtrerOptions, simplifier } from '../src/recherche.ts';

const options = ECOLES.map((e) => ({ valeur: e, libelle: e }));
const chercher = (q: string) => filtrerOptions(options, q, MOTS_CLES_ECOLES).map((o) => o.valeur);

test('simplifier retire accents, majuscules et ponctuation', () => {
  assert.equal(simplifier('  École d’ingénieurs – INSPÉ  '), 'ecole d ingenieurs inspe');
  assert.equal(simplifier('Œuvre'), 'oeuvre');
});

test('une recherche vide rend toute la liste, dans l’ordre', () => {
  assert.deepEqual(chercher(''), ECOLES);
  assert.deepEqual(chercher('   '), ECOLES);
});

test('un bout du nom suffit, sans accent ni majuscule', () => {
  assert.deepEqual(chercher('inspe'), ['INSPÉ Clermont-Auvergne']);
  assert.ok(chercher('poly').includes('Polytech Clermont'));
});

test('les mots-clés trouvent une école sans son nom exact', () => {
  assert.ok(chercher('ecole de commerce').includes('Clermont School of Business'));
  assert.ok(chercher('veto').includes('VetAgro Sup'));
  assert.ok(chercher('medecine').includes('UCA'));
  assert.ok(chercher('infirmiere').includes('IFSI'));
});

test('chaque mot tapé doit se retrouver', () => {
  assert.deepEqual(chercher('ingenieur informatique'), ['ISIMA']);
  assert.deepEqual(chercher('zzz'), []);
});
