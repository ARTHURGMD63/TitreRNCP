/**
 * Les textes légaux arrivent de l'API avec des passages **en gras** ; l'écran
 * ne sait afficher que ce que fragments() lui découpe.
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { fragments, PAGES_LEGALES } from '../src/legal.ts';

test('fragments : un texte sans balisage reste entier et non gras', () => {
  assert.deepEqual(fragments('Bonjour tout le monde'), [{ texte: 'Bonjour tout le monde', gras: false }]);
});

test('fragments : le gras alterne avec le texte courant', () => {
  assert.deepEqual(fragments('**Identité :** nom, prénom, email'), [
    { texte: 'Identité :', gras: true },
    { texte: ' nom, prénom, email', gras: false },
  ]);
  assert.deepEqual(fragments('a **b** c **d**'), [
    { texte: 'a ', gras: false },
    { texte: 'b', gras: true },
    { texte: ' c ', gras: false },
    { texte: 'd', gras: true },
  ]);
});

test('fragments : un balisage resté ouvert met la fin en gras, sans afficher les astérisques', () => {
  const f = fragments('début **fin');
  assert.deepEqual(f, [{ texte: 'début ', gras: false }, { texte: 'fin', gras: true }]);
  assert.ok(!f.some((x) => x.texte.includes('*')));
});

test('fragments : un texte vide ne produit aucun fragment', () => {
  assert.deepEqual(fragments(''), []);
});

test('fragments : recoller les morceaux redonne le texte sans les astérisques', () => {
  const source = 'Le **gras** et le **reste** du texte';
  assert.equal(fragments(source).map((f) => f.texte).join(''), source.replaceAll('**', ''));
});

test('les trois pages légales sont proposées, avec les slugs que sert le serveur', () => {
  assert.deepEqual(PAGES_LEGALES.map((p) => p.slug), ['mentions-legales', 'cgu', 'confidentialite']);
  assert.ok(PAGES_LEGALES.every((p) => p.libelle.trim() !== ''));
});
