/**
 * Les listes de choix du mobile (écoles, promos, intérêts…) alimentent des
 * formulaires envoyés au serveur : un doublon ou une entrée vide y devient
 * une option fantôme.
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import * as catalogue from '../src/catalogue.ts';

const listes = Object.entries(catalogue).filter(([, v]) => Array.isArray(v)) as [string, unknown[]][];

test('le catalogue expose bien des listes', () => {
  assert.ok(listes.length >= 4, 'ECOLES, PROMOS, VILLES, INTERETS au moins');
});

for (const [nom, valeurs] of listes) {
  test(`${nom} : non vide, sans doublon ni entrée vide`, () => {
    assert.ok(valeurs.length > 0);
    const textes = valeurs.map((v) => (typeof v === 'string' ? v : JSON.stringify(v)));
    assert.equal(new Set(textes).size, textes.length, 'doublon');
    assert.ok(textes.every((t) => t.trim() !== ''), 'entrée vide');
  });
}

test('le choix « Autre » ferme les listes d’écoles et de villes', () => {
  assert.equal(catalogue.ECOLES.at(-1), 'Autre');
  assert.equal(catalogue.VILLES.at(-1), 'Autre');
});

test('les écoles déjà portées par des comptes restent dans la liste', () => {
  for (const e of ['UCA', 'SIGMA Clermont', 'IFSI']) assert.ok(catalogue.ECOLES.includes(e), e);
});

test('chaque mot-clé désigne une école de la liste', () => {
  for (const e of Object.keys(catalogue.MOTS_CLES_ECOLES)) assert.ok(catalogue.ECOLES.includes(e), e);
});

test('une école retirée de la liste reste proposée au profil, avant « Autre »', () => {
  const liste = ['UCA', 'IFSI', 'Autre'];
  assert.deepEqual(catalogue.ecolesProposees(liste, 'INP Ingénieurs'), ['UCA', 'IFSI', 'INP Ingénieurs', 'Autre']);
  assert.deepEqual(catalogue.ecolesProposees(liste, 'IFSI'), liste);
  assert.deepEqual(catalogue.ecolesProposees(liste, null), liste);
});
