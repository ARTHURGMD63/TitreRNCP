/**
 * billetModele.ts : la page HTML du billet PDF d'un pass.
 *
 * Lancement : npm test (lanceur intégré à Node, aucune dépendance).
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { echapper, htmlBillet, nomFichierBillet, qrSvg, referenceBillet, tarifBillet, type PassBillet } from '../src/billetModele.ts';

const CODE = '3fa29c1b' + '0'.repeat(56);
const PASS: PassBillet = {
  genre: 'soiree', id: 42, titre: 'Soirée Mousse', etablissement: 'Le Bateau', lieu: 'Quai des Chartrons',
  date_heure: '2026-11-22 20:30:00', reduction: 20, prix_normal: 10, is_gratuit: false, code: CODE,
};
const QR = qrSvg({ size: 2, data: [1, 0, 0, 1] });
const options = (pass: Partial<PassBillet> = {}, titulaire = {}) => ({
  pass: { ...PASS, ...pass },
  titulaire: { prenom: 'Léa', nom: 'Martin', email: 'lea@example.org', ecole: 'KEDGE', promo: 'M1', ...titulaire },
  qrSvg: QR,
  genereLe: new Date('2026-11-19T10:00:00Z'),
});

test('echapper : le HTML d’un titre ne passe pas', () => {
  assert.equal(echapper('<b>"A" & \'B\'</b>'), '&lt;b&gt;&quot;A&quot; &amp; &#39;B&#39;&lt;/b&gt;');
  assert.equal(echapper(null), '');
});

test('referenceBillet : les huit premiers caractères, en deux groupes', () => {
  assert.equal(referenceBillet(CODE), '3FA2 9C1B');
});

test('tarifBillet : même règle que la carte du Pass', () => {
  assert.equal(tarifBillet(PASS), '8 € au lieu de 10 € (-20 %)');
  assert.equal(tarifBillet({ ...PASS, reduction: 0 }), '10 €');
  assert.equal(tarifBillet({ ...PASS, prix_normal: null, reduction: 0 }), 'Entrée gratuite');
  assert.equal(tarifBillet({ ...PASS, prix_normal: null, reduction: 15 }), '-15 % sur place');
  assert.equal(tarifBillet({ ...PASS, genre: 'culture', prix_normal: 0 }), 'Entrée libre');
  assert.equal(tarifBillet({ ...PASS, genre: 'partie' }), 'Réglé sur place au club');
});

test('nomFichierBillet : sans accent ni espace, avec le jour', () => {
  assert.equal(nomFichierBillet(PASS), 'billet-outsee-soiree-mousse-22-11.pdf');
  assert.equal(nomFichierBillet({ ...PASS, titre: '!!!', date_heure: null }), 'billet-outsee-pass-42.pdf');
});

test('qrSvg : un module sombre par case à 1, deux modules de marge', () => {
  assert.match(QR, /viewBox="0 0 6 6"/);
  assert.equal((QR.match(/h1v1h-1z/g) ?? []).length, 2);
  assert.match(QR, /M2 2h1v1h-1zM3 3h1v1h-1z/);
});

test('htmlBillet : le titulaire, l’événement et le QR', () => {
  const html = htmlBillet(options());
  assert.match(html, /Léa Martin/);
  assert.match(html, /KEDGE/);
  assert.match(html, /lea@example\.org/);
  assert.match(html, /Le Bateau — Soirée Mousse/);
  assert.match(html, /Dimanche 22 novembre 2026 · 20h30/);
  assert.match(html, /Le Bateau · Quai des Chartrons/);
  assert.match(html, /3FA2 9C1B/);
  assert.match(html, /généré le 19 novembre 2026 à 11h00/);
  assert.ok(html.includes(QR));
  // Le bleu Outsee pour une soirée.
  assert.match(html, /#1D55F0/);
});

test('htmlBillet : la couleur suit la rubrique', () => {
  assert.match(htmlBillet(options({ genre: 'sport' })), /Pass sport[\s\S]*/);
  assert.match(htmlBillet(options({ genre: 'sport' })), /#E02D24/);
  assert.match(htmlBillet(options({ genre: 'culture' })), /#7B4DF2/);
});

test('htmlBillet : une ligne vide n’est pas écrite, le texte est échappé', () => {
  const html = htmlBillet(options({ titre: '<script>x</script>' }, { ecole: null, promo: '  ' }));
  assert.doesNotMatch(html, />École</);
  assert.doesNotMatch(html, />Promo</);
  assert.doesNotMatch(html, /<script>/);
});

test('htmlBillet : polices embarquées seulement quand elles sont fournies', () => {
  assert.doesNotMatch(htmlBillet(options()), /@font-face/);
  const avec = htmlBillet({ ...options(), polices: { 700: 'QUJD' } });
  assert.match(avec, /@font-face\{font-family:'Onest';font-weight:700;[^}]*base64,QUJD/);
});
