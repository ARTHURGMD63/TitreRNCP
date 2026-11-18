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
  libelleInscription, libelleInscrits, libellesAffluence, messageConnexion, messageEchecScan,
  palierAffluence, retirerInvite,
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

// ─── Carte des soirées ──────────────────────────────────────────────────────

test('palierAffluence : le repère change de couleur à 10, 30 et 60 inscrits', () => {
  assert.deepEqual([0, 9, 10, 29, 30, 59, 60, 250].map(palierAffluence), [0, 0, 1, 1, 2, 2, 3, 3]);
});

test('libellesAffluence : un libellé de légende par palier', () => {
  assert.deepEqual(libellesAffluence(), ['< 10', '10+', '30+', '60+']);
});

test('libelleInscrits : aucun, un, plusieurs', () => {
  assert.equal(libelleInscrits(0), 'Aucun inscrit');
  assert.equal(libelleInscrits(1), '1 inscrit');
  assert.equal(libelleInscrits(42), '42 inscrits');
});

// ─── Sport ──────────────────────────────────────────────────────────────────

import {
  amisMax, debutsCreneaux, formatEuros, joursRecurrents, libellePlaces, libelleVisibilite,
  niveauCompatible, prixParJoueur,
} from '../src/regles.ts';

test('formatEuros : entier sans décimales, sinon deux décimales à virgule', () => {
  assert.equal(formatEuros(12), '12 €');
  assert.equal(formatEuros(12.5), '12,50 €');
  assert.equal(formatEuros(48 / 4), '12 €');
});

test('prixParJoueur : la part de chacun, payée au club', () => {
  assert.equal(prixParJoueur(48, 4), '12 € / joueur sur place');
  assert.equal(prixParJoueur(50, 4), '12,50 € / joueur sur place');
  assert.equal(prixParJoueur(30, 1), '30 € sur place');
  assert.equal(prixParJoueur(0, 4), 'Gratuit');
});

test('libellePlaces : complet, singulier, pluriel', () => {
  assert.equal(libellePlaces(0), 'Complet');
  assert.equal(libellePlaces(1), '1 place');
  assert.equal(libellePlaces(3), '3 places');
});

test('amisMax : le terrain moins soi-même', () => {
  assert.equal(amisMax(4), 3);
  assert.equal(amisMax(1), 0);
});

test('niveauCompatible : « tous » laisse passer, sinon niveau égal', () => {
  assert.equal(niveauCompatible('inter', 'tous'), true);
  assert.equal(niveauCompatible('tous', 'avance'), true);
  assert.equal(niveauCompatible('inter', 'inter'), true);
  assert.equal(niveauCompatible('debutant', 'avance'), false);
});

test('libelleVisibilite : abonnés par défaut', () => {
  assert.equal(libelleVisibilite('public'), 'Public');
  assert.equal(libelleVisibilite('abonnes'), 'Abonnés');
  assert.equal(libelleVisibilite(undefined), 'Abonnés');
});

test('joursRecurrents : même jour de semaine en semaine, passage de mois compris', () => {
  assert.deepEqual(joursRecurrents('2026-11-24', 3), ['2026-11-24', '2026-12-01', '2026-12-08']);
  assert.deepEqual(joursRecurrents('2026-11-24', 0), ['2026-11-24']);
  assert.deepEqual(joursRecurrents('pas une date', 2), []);
});

test('debutsCreneaux : créneaux entiers dans la plage, pour chaque jour', () => {
  assert.deepEqual(debutsCreneaux(['2026-11-15'], '10:00', '14:30', 90), [
    '2026-11-15 10:00', '2026-11-15 11:30', '2026-11-15 13:00',
  ]);
  // Le dernier créneau qui finirait après la fermeture n'est pas publié.
  assert.deepEqual(debutsCreneaux(['2026-11-15'], '10:00', '13:00', 90), ['2026-11-15 10:00', '2026-11-15 11:30']);
  assert.equal(debutsCreneaux(['2026-11-15', '2026-11-22'], '18:00', '21:00', 60).length, 6);
  assert.deepEqual(debutsCreneaux(['2026-11-15'], '14:00', '10:00', 60), []);
  assert.deepEqual(debutsCreneaux(['2026-11-15'], '25:00', '26:00', 60), []);
});
