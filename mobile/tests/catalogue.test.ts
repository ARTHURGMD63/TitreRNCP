/**
 * Les listes de choix du mobile (écoles, promos, villes…) alimentent des
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
