/**
 * Le numéro de mobile de l'inscription : mêmes règles que le serveur
 * (includes/telephone.php, tests/Unit/TelephoneTest.php).
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { afficherTelephone, lienAppel, normaliserTelephone } from '../src/telephone.ts';

test('les écritures courantes d’un mobile français sont acceptées', () => {
  for (const saisie of ['06 12 34 56 78', '0612345678', '06.12.34.56.78', '06-12-34-56-78', '+33 6 12 34 56 78', '+33612345678', '0033612345678', '+33 (0)6 12 34 56 78']) {
    assert.equal(normaliserTelephone(saisie), '+33612345678', saisie);
  }
  assert.equal(normaliserTelephone('07 98 76 54 32'), '+33798765432');
});

test('un fixe, un numéro étranger ou incomplet est refusé', () => {
  for (const saisie of ['', '04 73 00 00 00', '01 23 45 67 89', '+44 7700 900123', '06 12 34 56', '06 12 34 56 78 9', 'abc', '612345678']) {
    assert.equal(normaliserTelephone(saisie), null, saisie);
  }
});

test('le numéro s’affiche à la française et s’appelle au format international', () => {
  assert.equal(afficherTelephone('+33612345678'), '06 12 34 56 78');
  assert.equal(afficherTelephone('inconnu'), 'inconnu');
  assert.equal(lienAppel('+33612345678'), 'tel:+33612345678');
});
