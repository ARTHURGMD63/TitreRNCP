/**
 * Les notifications push : seules les règles sans module natif se testent ici.
 * Lancement : npm test.
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { routeSure } from '../src/pushRegles.ts';

test('une page prévue s’ouvre', () => {
  for (const url of ['/notifications', '/abonnement', '/wallet', '/evenement/12', '/culture/3', '/creneau/7', '/etudiant/42']) {
    assert.equal(routeSure(url), url);
  }
});

test('une adresse externe ou inconnue est refusée', () => {
  for (const url of ['https://exemple.fr', '//exemple.fr', 'javascript:alert(1)', '/admin', '/evenement/abc', '/evenement/12/../x', '/evenement/', '']) {
    assert.equal(routeSure(url), null, url);
  }
});

test('une valeur qui n’est pas du texte est refusée', () => {
  for (const v of [undefined, null, 12, {}, ['/wallet']]) {
    assert.equal(routeSure(v), null);
  }
});
