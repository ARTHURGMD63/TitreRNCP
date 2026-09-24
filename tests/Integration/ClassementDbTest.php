<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Le classement recalcule l'XP en SQL pour tous les étudiants d'un coup.
 * Ce test garantit qu'il donne le même nombre que getXp(getUserStats()) —
 * le chiffre affiché sur la page « Moi » — et que rangs et périmètres tiennent.
 */
final class ClassementDbTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec("
            CREATE TABLE users (id INTEGER PRIMARY KEY, prenom TEXT, nom TEXT, photo TEXT, ecole TEXT, promo TEXT, type TEXT);
            CREATE TABLE inscriptions (id INTEGER PRIMARY KEY, user_id INTEGER, statut TEXT);
            CREATE TABLE squad_membres (id INTEGER PRIMARY KEY, user_id INTEGER);
            CREATE TABLE follows_users (id INTEGER PRIMARY KEY, follower_id INTEGER, followed_id INTEGER, statut TEXT NOT NULL DEFAULT 'pending');
            CREATE TABLE avis (id INTEGER PRIMARY KEY, user_id INTEGER, evenement_id INTEGER, note INTEGER);
            CREATE TABLE economies (id INTEGER PRIMARY KEY, user_id INTEGER, montant REAL);
        ");
        $this->pdo->exec("
            INSERT INTO users (id, prenom, nom, ecole, type) VALUES
              (1, 'Arthur', 'G', 'UCA', 'etudiant'),
              (2, 'Léa', 'M', 'UCA', 'etudiant'),
              (3, 'Hugo', 'T', 'SIGMA', 'etudiant'),
              (4, 'Chloé', 'B', 'UCA', 'etudiant'),
              (5, 'Jean', 'P', '', 'partenaire');
            -- Arthur : 2 sorties dont une annulée, 1 squad, 1 abonnement accepté, 1 en attente, 1 avis
            INSERT INTO inscriptions (user_id, statut) VALUES (1, 'inscrit'), (1, 'annule');
            INSERT INTO squad_membres (user_id) VALUES (1);
            INSERT INTO follows_users (follower_id, followed_id, statut) VALUES (1, 2, 'accepted'), (1, 3, 'pending');
            INSERT INTO avis (user_id, evenement_id, note) VALUES (1, 1, 5);
            -- Léa : 3 sorties
            INSERT INTO inscriptions (user_id, statut) VALUES (2, 'inscrit'), (2, 'checkin'), (2, 'inscrit');
            -- Hugo : 2 squads, ex æquo avec Chloé (20 XP)
            INSERT INTO squad_membres (user_id) VALUES (3), (3), (4), (4);
        ");

        if (!function_exists('classementXp')) {
            require_once __DIR__ . '/../../includes/gamification.php';
        }
    }

    public function testLXpDuClassementEgaleCeluiDuProfil(): void
    {
        $parId = array_column(classementXp($this->pdo, []), 'xp', 'id');
        foreach ([1, 2, 3, 4] as $uid) {
            $this->assertSame(getXp(getUserStats($this->pdo, $uid)), $parId[$uid], "étudiant {$uid}");
        }
    }

    public function testOrdreEtRangsDeCompetition(): void
    {
        $lignes = classementXp($this->pdo, []);
        // Léa 45, Arthur 15+10+5+8 = 38, Chloé 20, Hugo 20 (alphabétique à égalité)
        $this->assertSame(['Léa', 'Arthur', 'Chloé', 'Hugo'], array_column($lignes, 'prenom'));
        $this->assertSame([1, 2, 3, 3], array_column($lignes, 'rang'));
    }

    public function testLesPartenairesNeSontPasClasses(): void
    {
        $this->assertNotContains(5, array_column(classementXp($this->pdo, []), 'id'));
    }

    public function testPerimetres(): void
    {
        $ecole = classementXp($this->pdo, ['ecole' => 'UCA']);
        $this->assertSame([2, 1, 4], array_column($ecole, 'id'));

        $amis = classementXp($this->pdo, ['ids' => [1, 2]]);
        $this->assertSame([2, 1], array_column($amis, 'id'));

        $this->assertSame([], classementXp($this->pdo, ['ids' => []]), 'aucun ami : classement vide');

        $sansLea = classementXp($this->pdo, ['exclus' => [2]]);
        $this->assertNotContains(2, array_column($sansLea, 'id'));
    }

    /**
     * La page dérive le classement en mémoire depuis une liste globale mise en
     * cache (classementPourEtudiant). Elle doit donner exactement ce que les
     * requêtes SQL donnent, périmètre par périmètre.
     */
    public function testLeCalculEnMemoireEgaleLeCalculSql(): void
    {
        $global = classementXp($this->pdo, [], 1000);
        $moi = ['id' => 1, 'prenom' => 'Arthur', 'nom' => 'G', 'ecole' => 'UCA'];
        $monXp = getXp(getUserStats($this->pdo, 1));

        foreach ([[], ['ecole' => 'UCA'], ['ids' => [1, 2, 3]], ['exclus' => [2]]] as $criteres) {
            $memoire = classementPourEtudiant($global, $criteres, $moi, $monXp, 50);
            $sql = classementXp($this->pdo, $criteres, 50);

            $this->assertSame(array_column($sql, 'id'), array_column($memoire['lignes'], 'id'), json_encode($criteres));
            $this->assertSame(array_column($sql, 'rang'), array_column($memoire['lignes'], 'rang'));
            // Rang attendu : ma ligne dans la liste SQL complète du périmètre.
            $complet = array_column(classementXp($this->pdo, $criteres, 1000), null, 'id');
            $this->assertSame($complet[1]['rang'] ?? null, $memoire['moi']['rang'] ?? null);
        }
    }

    public function testMonXpEnDirectPrimeSurLaListeEnCache(): void
    {
        // La liste en cache date d'avant ma dernière sortie : 38 XP. En direct,
        // j'en ai 60 — je dois passer devant Léa (45).
        $global = classementXp($this->pdo, [], 1000);
        $r = classementPourEtudiant($global, [], ['id' => 1, 'prenom' => 'Arthur', 'nom' => 'G'], 60, 50);

        $this->assertSame(1, $r['moi']['rang'] ?? null);
        $this->assertSame(60, $r['moi']['xp'] ?? null);
        $this->assertNull($r['devant']);
    }

    public function testRangEtPersonneADepasser(): void
    {
        $global = classementXp($this->pdo, [], 1000);
        $arthur = ['id' => 1, 'prenom' => 'Arthur', 'nom' => 'G'];

        $r = classementPourEtudiant($global, [], $arthur, 38);
        $this->assertSame(2, $r['moi']['rang'] ?? null);
        $this->assertSame(['id' => 2, 'prenom' => 'Léa', 'xp' => 45], $r['devant']);

        $lea = classementPourEtudiant($global, [], ['id' => 2, 'prenom' => 'Léa', 'nom' => 'M'], 45);
        $this->assertSame(1, $lea['moi']['rang'] ?? null);
        $this->assertNull($lea['devant'], 'personne devant la première');

        // Ex æquo : Chloé et Hugo à 20 XP partagent la 3e place.
        $hugo = classementPourEtudiant($global, [], ['id' => 3, 'prenom' => 'Hugo', 'nom' => 'T'], 20);
        $this->assertSame(3, $hugo['moi']['rang'] ?? null);
        $this->assertSame('Arthur', $hugo['devant']['prenom'] ?? null);
    }
}
