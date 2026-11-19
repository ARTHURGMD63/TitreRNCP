/**
 * format.ts transpose dateFr() et number_format() du site. Ces tests figent
 * les sorties que le site produit, pour que le mobile ne s'en écarte pas.
 *
 * Lancement : npm test (lanceur intégré à Node, aucune dépendance).
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import {
  compteARebours, court, dateFr, decalageParis, dureeCourte, heure, lireDate, lireDateFormulaire, majuscules, murDeParis, nombre, ts,
} from '../src/format.ts';

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
  assert.equal(lireDate('2026-09-23T20:30')?.toISOString(), '2026-09-23T18:30:00.000Z');
  assert.equal(lireDate('2026-09-23')?.toISOString(), '2026-09-22T22:00:00.000Z');
  assert.equal(lireDate('23/09/2026'), null);
});

// ─── Fuseau : l'heure de Paris, quel que soit le téléphone (BUG-08) ─────────

/** Exécute `f` avec le téléphone réglé sur `fuseau`, puis remet le fuseau d'origine. */
function avecFuseau<T>(fuseau: string, f: () => T): T {
  const avant = process.env.TZ;
  process.env.TZ = fuseau;
  try {
    return f();
  } finally {
    if (avant === undefined) delete process.env.TZ;
    else process.env.TZ = avant;
  }
}

const FUSEAUX = ['Europe/Paris', 'UTC', 'America/Los_Angeles', 'Asia/Tokyo', 'Pacific/Auckland'];

test('decalageParis : +1 h l’hiver, +2 h l’été, bascule le dernier dimanche à 1 h UTC', () => {
  assert.equal(decalageParis(Date.UTC(2026, 0, 15, 12)), 60);
  assert.equal(decalageParis(Date.UTC(2026, 6, 15, 12)), 120);
  // 2026 : 29 mars et 25 octobre.
  assert.equal(decalageParis(Date.UTC(2026, 2, 29, 0, 59)), 60);
  assert.equal(decalageParis(Date.UTC(2026, 2, 29, 1, 0)), 120);
  assert.equal(decalageParis(Date.UTC(2026, 9, 25, 0, 59)), 120);
  assert.equal(decalageParis(Date.UTC(2026, 9, 25, 1, 0)), 60);
});

test('lireDate : une date sans décalage est l’heure de Paris, été comme hiver', () => {
  for (const fuseau of FUSEAUX) {
    avecFuseau(fuseau, () => {
      assert.equal(lireDate(SOIREE)?.toISOString(), '2026-09-23T18:30:00.000Z', fuseau);
      assert.equal(lireDate('2026-12-31 23:30:00')?.toISOString(), '2026-12-31T22:30:00.000Z', fuseau);
    });
  }
});

test('lireDate : une date ISO avec décalage garde le sien', () => {
  assert.equal(lireDate('2026-09-23T20:30:00+02:00')?.toISOString(), '2026-09-23T18:30:00.000Z');
  assert.equal(lireDate('2026-09-23T18:30:00Z')?.toISOString(), '2026-09-23T18:30:00.000Z');
  assert.equal(lireDate('2026-09-23T18:30:00.250Z')?.toISOString(), '2026-09-23T18:30:00.000Z');
  assert.equal(lireDate('2026-09-23T13:30:00-0500')?.toISOString(), '2026-09-23T18:30:00.000Z');
  // DATE_ATOM du PHP, à l'heure d'hiver.
  assert.equal(lireDate('2026-01-10T21:00:00+01:00')?.toISOString(), '2026-01-10T20:00:00.000Z');
});

test('lireDate : les heures du changement d’heure', () => {
  // 29 mars 2026, 2h30 n'existe pas à Paris : on avance à 3h30 (01:30 UTC).
  assert.equal(lireDate('2026-03-29 02:30:00')?.toISOString(), '2026-03-29T01:30:00.000Z');
  // 25 octobre 2026, 2h30 existe deux fois : prise à l'heure d'hiver.
  assert.equal(lireDate('2026-10-25 02:30:00')?.toISOString(), '2026-10-25T01:30:00.000Z');
  assert.equal(lireDate('2026-10-25 01:30:00')?.toISOString(), '2026-10-24T23:30:00.000Z');
});

test('dateFr et heure : l’heure de Paris, où que soit le téléphone', () => {
  for (const fuseau of FUSEAUX) {
    avecFuseau(fuseau, () => {
      assert.equal(heure(SOIREE), '20h30', fuseau);
      assert.equal(dateFr(SOIREE, 'l j F Y'), 'Mercredi 23 Septembre 2026', fuseau);
      // Un instant exact (une date ISO, un Date) s'écrit aussi à Paris.
      assert.equal(heure('2026-09-23T18:30:00Z'), '20h30', fuseau);
      assert.equal(dateFr(new Date(Date.UTC(2026, 11, 31, 23, 30)), 'D j M H\hi'), 'Ven 1 Janv 00h30', fuseau);
    });
  }
});

test('ts et compteARebours : le même instant sur tous les téléphones', () => {
  const attendu = Date.UTC(2026, 8, 23, 18, 30) / 1000;
  for (const fuseau of FUSEAUX) {
    avecFuseau(fuseau, () => {
      assert.equal(ts(SOIREE), attendu, fuseau);
      // Deux heures avant la soirée, en temps réel : « Dans 2 h » partout.
      assert.equal(compteARebours(ts(SOIREE), attendu * 1000 - 2 * 3600_000), 'Dans 2 h', fuseau);
    });
  }
});

test('lireDateFormulaire : les mêmes chiffres dans le fuseau du téléphone', () => {
  for (const fuseau of FUSEAUX) {
    avecFuseau(fuseau, () => {
      const d = lireDateFormulaire(SOIREE);
      assert.deepEqual([d?.getFullYear(), d?.getMonth(), d?.getDate(), d?.getHours(), d?.getMinutes()], [2026, 8, 23, 20, 30], fuseau);
    });
  }
});

test('murDeParis : les chiffres de l’heure de Paris, pour comparer des jours', () => {
  for (const fuseau of FUSEAUX) {
    avecFuseau(fuseau, () => {
      // 23 h 30 UTC le 31 décembre : déjà le 1er janvier à Paris.
      const d = murDeParis(Date.UTC(2026, 11, 31, 23, 30));
      assert.deepEqual([d.getFullYear(), d.getMonth(), d.getDate(), d.getHours(), d.getMinutes()], [2027, 0, 1, 0, 30], fuseau);
    });
  }
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

test('compteARebours : minutes, heures, puis le jour au-delà de 24 h', () => {
  const debut = ts(SOIREE);
  const avant = (minutes: number) => debut * 1000 - minutes * 60000;
  assert.equal(compteARebours(debut, avant(25)), 'Dans 25 min');
  assert.equal(compteARebours(debut, avant(130)), 'Dans 2 h');
  assert.equal(compteARebours(debut, avant(23 * 60 + 59)), 'Dans 23 h');
  assert.equal(compteARebours(debut, avant(3 * 24 * 60)), 'Mercredi');
  assert.equal(compteARebours(debut, debut * 1000 + 1000), 'Maintenant');
});

test('dureeCourte : jours et heures au-delà de 24 h, jamais « 35h11 »', () => {
  const H = 3600_000;
  assert.equal(dureeCourte(35 * H + 11 * 60_000), '1j11h');
  assert.equal(dureeCourte(72 * H), '3j');
  assert.equal(dureeCourte(2 * H + 5 * 60_000), '2h05');
  assert.equal(dureeCourte(12 * 60_000 + 4_000), '12min04s');
  assert.equal(dureeCourte(-5), '0min00s');
});
