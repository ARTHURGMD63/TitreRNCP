/**
 * La copie hors connexion : ce qui est gardé, sous quel nom, combien de
 * temps. Le stockage (expo-file-system) ne se teste pas ici.
 * Lancement : npm test.
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import {
  cleLecture, copieAutorisee, copiesASupprimer, DUREE_COPIE_MS, lireCopie, nomCopie, PLAFOND_COPIES, VERSION_COPIES,
} from '../src/horsLigneRegles.ts';

test('liste blanche : pass, profil et fiches ouvertes depuis un pass', () => {
  for (const chemin of ['wallet.php', 'me.php', 'moi.php', 'partenaire_profil.php', 'evenement.php', 'culture_evenement.php', 'sport_creneau.php', '/wallet.php?h=2', 'evenement.php?id=12']) {
    assert.equal(copieAutorisee(chemin), true, chemin);
  }
});

test('liste blanche : jamais participants, administration, finances, suivi, annuaire', () => {
  for (const chemin of [
    'partenaire_evenement_suivi.php?id=3', 'partenaire_invites.php', 'admin_utilisateurs.php', 'admin_finances.php',
    'admin_moderation.php', 'admin_evenements.php', 'admin_bde.php', 'admin_culture.php', 'personnes.php', 'squad_membres.php',
    'partenaire_dashboard.php', 'notifications.php', '', 'wallet.php.bak', 'xwallet.php',
  ]) {
    assert.equal(copieAutorisee(chemin), false, chemin);
  }
});

test('la clé porte la version du format et oublie le serveur', () => {
  assert.equal(cleLecture('https://outsee.fr/api/v1/wallet.php?h=1'), `v${VERSION_COPIES}:/api/v1/wallet.php?h=1`);
  assert.equal(cleLecture('http://192.168.1.2:8080/x/me.php'), cleLecture('https://outsee.fr/x/me.php'));
});

test('nomCopie : stable, différent pour deux clés', () => {
  assert.equal(nomCopie('v1:/a'), nomCopie('v1:/a'));
  assert.notEqual(nomCopie('v1:/a'), nomCopie('v2:/a'));
  assert.match(nomCopie('v1:/a'), /^[0-9a-z]+-\d+\.json$/);
});

test('lireCopie : une copie intacte et récente est rendue', () => {
  const maintenant = 1_800_000_000_000;
  const copie = lireCopie<{ a: number }>(JSON.stringify({ le: maintenant - 1000, donnees: { a: 1 } }), maintenant);
  assert.deepEqual(copie, { le: maintenant - 1000, donnees: { a: 1 } });
});

test('lireCopie : corrompue, incomplète ou périmée, elle est ignorée sans erreur', () => {
  const maintenant = 1_800_000_000_000;
  for (const texte of [
    '', '{', 'null', '42', '"texte"', '[]',
    JSON.stringify({ donnees: { a: 1 } }),
    JSON.stringify({ le: 'hier', donnees: { a: 1 } }),
    JSON.stringify({ le: maintenant }),
    JSON.stringify({ le: maintenant, donnees: null }),
    JSON.stringify({ le: maintenant - DUREE_COPIE_MS - 1, donnees: { a: 1 } }),
    JSON.stringify({ le: maintenant + 3600_000, donnees: { a: 1 } }),
  ]) {
    assert.equal(lireCopie(texte, maintenant), null, texte);
  }
});

test('copiesASupprimer : les périmées, puis les plus anciennes au-delà du plafond', () => {
  const maintenant = 1_800_000_000_000;
  const fichiers = Array.from({ length: PLAFOND_COPIES + 3 }, (_, i) => ({ nom: `f${i}`, modifie: maintenant - i * 1000 }));
  fichiers.push({ nom: 'vieux', modifie: maintenant - DUREE_COPIE_MS - 1 });
  const partent = copiesASupprimer(fichiers, maintenant);
  assert.deepEqual(partent.sort(), ['f50', 'f51', 'f52', 'vieux'].sort());
});

test('copiesASupprimer : sous le plafond et récentes, rien ne part', () => {
  const maintenant = 1_800_000_000_000;
  assert.deepEqual(copiesASupprimer([{ nom: 'a', modifie: maintenant }, { nom: 'b', modifie: 0 }], maintenant), []);
});
