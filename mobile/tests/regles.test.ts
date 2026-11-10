/**
 * Les règles des trois écrans critiques : connexion, inscription à une soirée,
 * scan des pass. Les textes sont ceux que voit l'utilisateur — et que les
 * parcours Maestro (.maestro/) attendent : si l'un change ici, il change là.
 *
 * Lancement : npm test (lanceur intégré à Node, aucune dépendance).
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import {
  ajouterCheckin, connexionComplete, delaiResultatScan, libelleApresEchecInscription,
  libelleInscription, messageConnexion, messageEchecScan, retirerInvite,
} from '../src/regles.ts';

// ─── Connexion ──────────────────────────────────────────────────────────────

test('messageConnexion : identifiants faux, sans dire lequel', () => {
  assert.equal(messageConnexion({ code: 'identifiants', message: 'brut' }), 'Email ou mot de passe incorrect.');
});

test('messageConnexion : limitation de tentatives', () => {
  assert.equal(
    messageConnexion({ code: 'trop_de_tentatives', message: 'brut' }),
    'Trop de tentatives. Réessaye dans 15 minutes.',
  );
});

test('messageConnexion : tout autre code garde la phrase du serveur', () => {
  assert.equal(messageConnexion({ code: 'compte_suspendu', message: 'Compte suspendu.' }), 'Compte suspendu.');
});

test('messageConnexion : une panne qui n\'est pas une ErreurApi', () => {
  assert.equal(messageConnexion(null), 'Connexion impossible.');
});

test('connexionComplete : les deux champs sont requis', () => {
  assert.equal(connexionComplete('arthur@uca.fr', 'password'), true);
  assert.equal(connexionComplete('', 'password'), false);
  assert.equal(connexionComplete('arthur@uca.fr', ''), false);
  assert.equal(connexionComplete('   ', 'password'), false);
});

test('connexionComplete : un mot de passe d\'espaces est un mot de passe', () => {
  assert.equal(connexionComplete('arthur@uca.fr', '   '), true);
});

// ─── Inscription à une soirée ───────────────────────────────────────────────

test('libelleInscription : le bouton avant toute action', () => {
  assert.equal(libelleInscription('libre', false, null), "Rejoindre l'événement");
});

test('libelleInscription : déjà inscrit avant d\'ouvrir la fiche', () => {
  assert.equal(libelleInscription('libre', true, null), 'Tu es inscrit·e');
});

test('libelleInscription : à l\'instant où l\'inscription réussit', () => {
  assert.equal(libelleInscription('vient', false, null), 'Inscrit');
  assert.equal(libelleInscription('vient', true, null), 'Inscrit');
});

test('libelleInscription : après un refus, le bouton porte la raison', () => {
  assert.equal(libelleInscription('libre', false, 'Complet'), 'Complet');
});

test('libelleInscription : être inscrit l\'emporte sur une erreur passée', () => {
  assert.equal(libelleInscription('libre', true, 'Complet'), 'Tu es inscrit·e');
});

test('libelleInscription : une soirée complète le dit avant tout essai', () => {
  assert.equal(libelleInscription('libre', false, null, true), 'Complet');
  // Un inscrit garde sa place même quand la soirée se remplit.
  assert.equal(libelleInscription('libre', true, null, true), 'Tu es inscrit·e');
  assert.equal(libelleInscription('vient', false, null, true), 'Inscrit');
});

test('libelleApresEchecInscription : le serveur a répondu, sa phrase reste', () => {
  assert.equal(libelleApresEchecInscription(409, 'Déjà inscrit'), 'Déjà inscrit');
  assert.equal(libelleApresEchecInscription(403, 'Complet'), 'Complet');
});

test('libelleApresEchecInscription : le serveur n\'a pas répondu', () => {
  assert.equal(libelleApresEchecInscription(0, 'Erreur réseau'), '→ je rejoins');
  assert.equal(libelleApresEchecInscription(null, 'Erreur réseau'), '→ je rejoins');
});

// ─── Scan des pass ──────────────────────────────────────────────────────────

const lea = { id: 2, prenom: 'Léa', checkin_le: '2026-10-14T20:00:00Z' };
const lucas = { id: 3, prenom: 'Lucas', checkin_le: '2026-10-14T20:05:00Z' };

test('ajouterCheckin : le dernier arrivé passe en tête', () => {
  assert.deepEqual(ajouterCheckin([lea], lucas).map((p) => p.id), [3, 2]);
});

test('ajouterCheckin : une personne déjà là n\'est pas comptée deux fois', () => {
  const rescan = { ...lea, checkin_le: '2026-10-14T20:30:00Z' };
  const liste = ajouterCheckin([lucas, lea], rescan);
  assert.deepEqual(liste.map((p) => p.id), [2, 3]);
  assert.equal(liste[0].checkin_le, '2026-10-14T20:30:00Z');
});

test('ajouterCheckin : ne modifie pas la liste reçue', () => {
  const avant = [lea];
  ajouterCheckin(avant, lucas);
  assert.equal(avant.length, 1);
});

test('retirerInvite : seul l\'invité validé quitte la liste', () => {
  const invites = [{ inscription_id: 10 }, { inscription_id: 11 }, { inscription_id: 12 }];
  assert.deepEqual(retirerInvite(invites, 11), [{ inscription_id: 10 }, { inscription_id: 12 }]);
});

test('retirerInvite : une liste pas encore chargée devient une liste vide', () => {
  assert.deepEqual(retirerInvite(null, 1), []);
});

test('messageEchecScan : la phrase du serveur, sinon une panne de connexion', () => {
  assert.equal(messageEchecScan({ message: 'Pass déjà utilisé.' }), 'Pass déjà utilisé.');
  assert.equal(messageEchecScan(null), 'Erreur de connexion');
});

test('delaiResultatScan : un succès reste affiché plus longtemps qu\'un échec', () => {
  assert.equal(delaiResultatScan(true), 2200);
  assert.equal(delaiResultatScan(false), 2000);
});
