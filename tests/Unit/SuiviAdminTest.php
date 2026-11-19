<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/suivi.php';
require_once __DIR__ . '/../../includes/journal_erreurs.php';

/**
 * Suivi des administrateurs : calculs du tableau de bord, journal des
 * erreurs sans donnée personnelle, et points d'API réservés aux admins.
 */
final class SuiviAdminTest extends TestCase
{
    public function testLaSerieQuotidienneCompleteLesJoursVides(): void
    {
        $maintenant = (int) strtotime('2026-11-19 12:00:00');
        $serie = suiviSerieQuotidienne(['2026-11-18' => 4, '2026-11-19' => 2], 3, $maintenant);

        $this->assertSame([
            ['jour' => '2026-11-17', 'n' => 0],
            ['jour' => '2026-11-18', 'n' => 4],
            ['jour' => '2026-11-19', 'n' => 2],
        ], $serie);
    }

    public function testLesHeuresDePointeSontDesMoyennesSur24Heures(): void
    {
        $heures = suiviHeuresDePointe([21 => 28, 9 => 7], 14);

        $this->assertCount(24, $heures);
        $this->assertSame(2.0, $heures[21]['moyenne']);
        $this->assertSame(0.5, $heures[9]['moyenne']);
        $this->assertSame(0.0, $heures[3]['moyenne']);
    }

    public function testEvolution(): void
    {
        $this->assertSame(50, suiviEvolution(15, 10));
        $this->assertSame(-20, suiviEvolution(8, 10));
        $this->assertNull(suiviEvolution(5, 0), 'sans période précédente, pas de pourcentage');
    }

    public function testUnVoletEnEchecNeFaitPasTomberLesAutres(): void
    {
        $this->assertNull(suiviVolet('test', static fn () => throw new \RuntimeException('table absente')));
        $this->assertSame([1], suiviVolet('test', static fn () => [1]));
    }

    public function testLeJournalMasqueLesAdressesEMail(): void
    {
        $this->assertSame('Envoi refusé pour j***@uca.fr', journalMasquer('Envoi refusé pour jean.dupont@uca.fr'));
    }

    public function testUneMemeErreurAvecDesNombresDifferentsGardeSonEmpreinte(): void
    {
        $a = journalEmpreinte('serveur', 'Événement 42 introuvable', 'x.php:10');
        $b = journalEmpreinte('serveur', 'Événement 1337 introuvable', 'x.php:10');
        $c = journalEmpreinte('app', 'Événement 42 introuvable', 'x.php:10');
        $d = journalEmpreinte('serveur', 'Événement 42 introuvable', 'y.php:3');

        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c, 'la source compte');
        $this->assertNotSame($a, $d, 'l’endroit du code compte');
    }

    public function testLeJournalNeLevePasSansTable(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->assertFalse(journalEnregistrer($pdo, 'serveur', 'Erreur sans table'));
        $this->assertFalse(journalEnregistrer($pdo, 'inconnue', 'Source refusée'));
    }

    public function testLesPointsDuSuiviSontReservesAuxAdmins(): void
    {
        $racine = dirname(__DIR__, 2);
        foreach (['api/v1/admin_suivi.php', 'api/v1/admin_erreurs.php'] as $f) {
            $code = (string) file_get_contents("$racine/$f");
            $this->assertStringContainsString('apiAdmin($pdo)', $code, $f);
            $avantAdmin = strstr($code, 'apiAdmin($pdo)', true);
            $this->assertStringNotContainsString('SELECT', (string) $avantAdmin, "$f lit la base avant de vérifier le rôle");
        }
    }

    public function testLeJournalNeGardeNiCompteNiJetonDansLAdresse(): void
    {
        $code = (string) file_get_contents(dirname(__DIR__, 2) . '/includes/journal_erreurs.php');
        $this->assertStringContainsString("unset(\$contexte['user_id']", $code);
        $this->assertStringContainsString("strtok(\$contexte['uri'], '?')", $code);
    }
}
