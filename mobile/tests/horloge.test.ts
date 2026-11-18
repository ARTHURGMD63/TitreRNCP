/**
 * L'horloge partagée des comptes à rebours : une seule minuterie, arrêtée
 * sans abonné ou en arrière-plan. Lancement : npm test.
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { creerHorloge, type Minuterie } from '../src/horloge.ts';

/** Une minuterie factice : on compte les armements et on déclenche à la main. */
function fausseMinuterie() {
  const actives = new Map<number, () => void>();
  let suivant = 1;
  let armements = 0;
  const minuterie: Minuterie = {
    demarrer: (f) => { armements++; actives.set(suivant, f); return suivant++; },
    arreter: (id) => { actives.delete(id as number); },
  };
  return {
    minuterie,
    actives: () => actives.size,
    armements: () => armements,
    battre: () => [...actives.values()].forEach((f) => f()),
  };
}

test('une seule minuterie pour plusieurs abonnés', () => {
  const m = fausseMinuterie();
  let t = 1000;
  const h = creerHorloge(1000, m.minuterie, () => t);
  const vus: number[] = [];
  const a = h.abonner((x) => vus.push(x));
  const b = h.abonner((x) => vus.push(x));
  assert.equal(m.actives(), 1);
  assert.equal(m.armements(), 1);
  t = 2000;
  m.battre();
  assert.deepEqual(vus, [2000, 2000]);
  a();
  assert.equal(h.enMarche(), true);
  b();
  assert.equal(h.enMarche(), false);
  assert.equal(m.actives(), 0);
});

test('en arrière-plan, plus de minuterie ; au retour, un battement immédiat', () => {
  const m = fausseMinuterie();
  let t = 0;
  const h = creerHorloge(1000, m.minuterie, () => t);
  const vus: number[] = [];
  h.abonner((x) => vus.push(x));
  h.pause();
  assert.equal(m.actives(), 0);
  t = 5000;
  h.reprendre();
  assert.equal(m.actives(), 1);
  assert.deepEqual(vus, [5000]);
});

test('un abonné peut se désabonner pendant le battement', () => {
  const m = fausseMinuterie();
  const h = creerHorloge(1000, m.minuterie, () => 1);
  let appels = 0;
  const arret: { f?: () => void } = {};
  arret.f = h.abonner(() => { appels++; arret.f?.(); });
  let autres = 0;
  h.abonner(() => { autres++; });
  m.battre();
  m.battre();
  assert.equal(appels, 1);
  assert.equal(autres, 2);
});

test('reprendre sans pause préalable ne déclenche rien', () => {
  const m = fausseMinuterie();
  const h = creerHorloge(1000, m.minuterie, () => 1);
  let appels = 0;
  h.abonner(() => { appels++; });
  h.reprendre();
  assert.equal(appels, 0);
  assert.equal(m.armements(), 1);
});
