<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/evenements_suppression.php';

/**
 * Annulation d'un événement qui a des inscrits (il ne se supprime plus,
 * PAY-10) : les pass valables sont annulés, les entrées scannées restent,
 * l'événement reste en base, et seul son établissement (ou l'admin) peut
 * l'annuler.
 */
final class EvenementAnnulationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $demain = date('Y-m-d H:i:s', time() + 86400);
        $hier   = date('Y-m-d H:i:s', time() - 3 * 86400);
        $this->pdo->exec("
            CREATE TABLE evenements (
                id INTEGER PRIMARY KEY, etablissement_id INTEGER, titre TEXT,
                date_heure TEXT, date_fin TEXT NULL,
                annule_le TEXT NULL, annule_motif TEXT NULL, annule_par INTEGER NULL
            );
            CREATE TABLE inscriptions (id INTEGER PRIMARY KEY, user_id INTEGER, evenement_id INTEGER, statut TEXT);
            INSERT INTO evenements (id, etablissement_id, titre, date_heure) VALUES
                (1, 10, 'Soirée à venir', '$demain'),
                (2, 10, 'Soirée passée', '$hier'),
                (3, 20, 'Soirée d’un autre', '$demain');
            INSERT INTO inscriptions (user_id, evenement_id, statut) VALUES
                (100, 1, 'inscrit'), (101, 1, 'inscrit'), (102, 1, 'annule'), (103, 1, 'checkin'),
                (100, 2, 'checkin');
        ");
    }

    private function statuts(int $evenementId): array
    {
        $stmt = $this->pdo->prepare('SELECT user_id, statut FROM inscriptions WHERE evenement_id = ? ORDER BY user_id');
        $stmt->execute([$evenementId]);

        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function testLesPassValablesSontAnnulesEtLEvenementMarque(): void
    {
        $r = evenementAnnuler($this->pdo, 1, 10, 7, "  Fermeture   exceptionnelle  ");

        $this->assertTrue($r['ok']);
        $this->assertSame(2, $r['pass_annules']);
        $this->assertStringContainsString('2 personnes', $r['message']);
        $this->assertSame([100 => 'annule', 101 => 'annule', 102 => 'annule', 103 => 'checkin'], $this->statuts(1));

        $ev = $this->pdo->query('SELECT annule_le, annule_motif, annule_par FROM evenements WHERE id = 1')->fetch();
        $this->assertNotNull($ev['annule_le']);
        $this->assertSame('Fermeture exceptionnelle', $ev['annule_motif']);
        $this->assertSame(7, (int) $ev['annule_par']);
    }

    public function testOnNAnnulePasDeuxFois(): void
    {
        $this->assertTrue(evenementAnnuler($this->pdo, 1, 10, 7)['ok']);
        $this->assertSame('deja_annule', evenementAnnuler($this->pdo, 1, 10, 7)['code']);
    }

    public function testUnEtablissementNAnnulePasLEvenementDUnAutre(): void
    {
        $this->assertSame('introuvable', evenementAnnuler($this->pdo, 3, 10, 7)['code']);
        $this->assertNull($this->pdo->query('SELECT annule_le FROM evenements WHERE id = 3')->fetchColumn() ?: null);
    }

    public function testUnEvenementTermineNeSAnnulePasCoteEtablissement(): void
    {
        $this->assertSame('termine', evenementAnnuler($this->pdo, 2, 10, 7)['code']);
    }

    public function testLAdministrateurPeutRetirerUnEvenementPasse(): void
    {
        $r = evenementAnnuler($this->pdo, 2, null, 1, 'Contenu signalé');

        $this->assertTrue($r['ok']);
        $this->assertSame(0, $r['pass_annules']);
        $this->assertSame([100 => 'checkin'], $this->statuts(2), 'une entrée scannée a eu lieu : elle reste');
    }

    public function testMotif(): void
    {
        $this->assertNull(evenementMotifAnnulation(null));
        $this->assertNull(evenementMotifAnnulation("   \n "));
        $this->assertSame(EVENEMENT_ANNULATION_MOTIF_MAX, mb_strlen((string) evenementMotifAnnulation(str_repeat('é', 400))));
    }

    public function testLesLecturesEtudiantesExcluentLesEvenementsAnnules(): void
    {
        $racine = dirname(__DIR__, 2);
        foreach (['includes/hub.php', 'api/v1/sport.php', 'api/inscrire.php', 'api/inviter.php'] as $f) {
            $this->assertStringContainsString("sqlEvenementNonAnnule('e')", (string) file_get_contents("$racine/$f"), $f);
        }
    }

    public function testLesPointsDAnnulationPassentParLaRegleCommune(): void
    {
        $racine = dirname(__DIR__, 2);
        foreach (['api/v1/partenaire_evenements.php', 'partenaire/evenements.php', 'api/admin_annuler_evenement.php'] as $f) {
            $code = (string) file_get_contents("$racine/$f");
            $this->assertStringContainsString('evenementAnnuler(', $code, $f);
            $this->assertStringNotContainsString("SET annule_le", $code, $f);
        }
    }
}
