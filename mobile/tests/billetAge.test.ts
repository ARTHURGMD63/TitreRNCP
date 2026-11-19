/**
 * Billet numéroté, âge minimum et identité au scan (v44).
 *
 * Lancement : npm test (lanceur intégré à Node, aucune dépendance).
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { conditionsBillet, htmlBillet, numeroAffiche, qrSvg, type PassBillet } from '../src/billetModele.ts';
import { ageMinimum, avertissementEntree, libelleInscription, ligneNaissance, pastilleAgeMinimum, personneRefuseeAge } from '../src/regles.ts';

const CODE = '3fa29c1b' + '0'.repeat(56);
const PASS: PassBillet = {
  genre: 'soiree', id: 123, titre: 'Soirée Mousse', etablissement: 'Le Bateau', lieu: 'Quai des Chartrons',
  date_heure: '2026-11-22 22:30:00', reduction: 0, prix_normal: 10, is_gratuit: false, code: CODE,
  numero_billet: 'OS-000123-3FA2', date_fin: '2026-11-23 04:00:00', age_minimum: 21, adresse: '12 rue Blaise Pascal, Clermont-Ferrand',
};
const html = (pass: Partial<PassBillet> = {}) => htmlBillet({
  pass: { ...PASS, ...pass },
  titulaire: { prenom: 'Léa', nom: 'Martin' },
  qrSvg: qrSvg({ size: 2, data: [1, 0, 0, 1] }),
  genereLe: new Date('2026-11-19T10:00:00Z'),
});

test('le numéro du billet est imprimé sous le QR et en tête', () => {
  const page = html();
  assert.equal(page.split('OS-000123-3FA2').length - 1, 2);
  // Un ancien serveur sans numéro : la référence tirée du code.
  assert.equal(numeroAffiche({ code: CODE, numero_billet: null }), '3FA2 9C1B');
});

test('le billet porte les infos de l’événement et l’âge minimum', () => {
  const page = html();
  assert.match(page, /Lundi 23 novembre 2026 · 04h00/);
  assert.match(page, /12 rue Blaise Pascal/);
  assert.match(page, /21 ans et plus · pièce d'identité exigée/);
  // Sans âge connu : 18.
  assert.match(html({ age_minimum: null }), /18 ans et plus/);
});

test('les conditions du billet électronique dégagent Outsee d’un refus à l’entrée', () => {
  const page = html();
  assert.match(page, /Conditions du billet électronique/);
  const texte = conditionsBillet(21).map((c) => `${c.titre} ${c.texte}`).join(' ');
  assert.match(texte, /Outsee n'en est pas responsable/);
  assert.match(texte, /au moins 21 ans/);
  assert.match(texte, /qu'une fois/);
});

test('âge minimum : 18 par défaut, pastille seulement au-delà', () => {
  assert.equal(ageMinimum({}), 18);
  assert.equal(ageMinimum({ age_minimum: 21 }), 21);
  assert.equal(ageMinimum({ age_minimum: 12 }), 18);
  assert.equal(pastilleAgeMinimum({ age_minimum: 18 }), null);
  assert.equal(pastilleAgeMinimum({ age_minimum: 21 }), '21 ans et plus');
  assert.match(avertissementEntree({ age_minimum: 21 }), /Âge minimum : 21 ans[\s\S]*Outsee n'en est pas responsable/);
});

test('le bouton d’inscription dit pourquoi il est bloqué', () => {
  assert.equal(libelleInscription('libre' as never, false, null, false, false, 21), 'Réservé aux 21 ans et plus');
  // Déjà inscrit : on ne le lui retire pas.
  assert.equal(libelleInscription('libre' as never, true, null, false, false, 21), 'Tu es inscrit·e');
});

test('au scan : date de naissance lisible, refus d’âge reconnu', () => {
  assert.equal(ligneNaissance({ date_naissance: '2005-03-12', age: 21 }), 'Né·e le 12/03/2005 · 21 ans');
  assert.equal(ligneNaissance({ date_naissance: null }), null);
  const personne = { id: 1, prenom: 'Tom', nom: 'Petit' };
  assert.deepEqual(personneRefuseeAge({ code: 'age_insuffisant', donnees: { personne } }), personne);
  assert.equal(personneRefuseeAge({ code: 'deja_scanne', donnees: { personne } }), null);
  assert.equal(personneRefuseeAge(null), null);
});

test('la liste du scanner se coche sans attendre le serveur', async () => {
  const { marquerPresent, heureEntree } = await import('../src/regles.ts');
  const liste = [
    { inscription_id: 1, present: false, checkin_le: null as string | null },
    { inscription_id: 2, present: false, checkin_le: null as string | null },
  ];
  const cochee = marquerPresent(liste, 2, '2026-11-22 22:14:05');
  assert.deepEqual(cochee.map((i) => i.present), [false, true]);
  assert.equal(cochee[1].checkin_le, '2026-11-22 22:14:05');
  assert.equal(liste[1].present, false, 'la liste d’origine n’est pas modifiée');
  assert.deepEqual(marquerPresent(liste, undefined, null), liste);
  assert.equal(heureEntree('2026-11-22 22:14:05'), 'Entré·e à 22h14');
  assert.equal(heureEntree(null), null);
});
