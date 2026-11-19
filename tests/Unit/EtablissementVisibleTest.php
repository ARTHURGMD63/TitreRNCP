<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/comptes.php';

/**
 * PAY-09 : un BDE dont la validation est refusée disparaît du fil, de la
 * carte et des inscriptions. Seul ce refus masque : un compte en attente n'a
 * rien pu publier, les autres types ne demandent pas de validation.
 */
final class EtablissementVisibleTest extends TestCase
{
    public function testSeulUnRefusMasque(): void
    {
        $this->assertFalse(etablissementVisible(['type' => 'bde', 'verification' => 'refusee']));
        foreach (['validee', 'en_attente', 'non_requise'] as $etat) {
            $this->assertTrue(etablissementVisible(['type' => 'bde', 'verification' => $etat]), $etat);
        }
    }

    public function testLaConditionSqlEstLaMeme(): void
    {
        $this->assertSame("et.verification <> 'refusee'", sqlEtablissementVisible());
        $this->assertSame("x.verification <> 'refusee'", sqlEtablissementVisible('x'));
    }

    public function testLeFilLaFicheEtLInscriptionAppliquentLaRegle(): void
    {
        $racine = dirname(__DIR__, 2);
        foreach (['includes/hub.php', 'api/inscrire.php'] as $f) {
            $this->assertStringContainsString('sqlEtablissementVisible(', (string) file_get_contents("$racine/$f"), $f);
        }
        $this->assertStringContainsString('etablissementVisible($e)', (string) file_get_contents("$racine/api/v1/evenement.php"));
    }
}
