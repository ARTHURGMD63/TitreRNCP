<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Catalogue des écoles.
 *
 * Il vit en deux exemplaires : includes/ecoles.php (le profil, via moi.php)
 * et mobile/src/catalogue.ts (l'inscription, avant toute connexion). Deux
 * listes qui doivent rester identiques finissent par diverger : ce test les
 * compare. Et une école déjà portée par des comptes ne doit pas disparaître,
 * sans quoi leur classement « Mon école » se scinde en deux.
 */
final class EcolesTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('ecolesDisponibles')) {
            require_once __DIR__ . '/../../includes/ecoles.php';
        }
    }

    /** @return list<string> */
    private function ecolesDuMobile(): array
    {
        $source = (string) file_get_contents(__DIR__ . '/../../mobile/src/catalogue.ts');
        self::assertSame(1, preg_match('/export const ECOLES = \[(.*?)\];/s', $source, $m), 'ECOLES introuvable dans catalogue.ts');
        preg_match_all("/'([^']+)'/", $m[1], $noms);
        return $noms[1];
    }

    public function testLeServeurEtLAppProposentLaMemeListe(): void
    {
        self::assertSame(ecolesDisponibles(), $this->ecolesDuMobile());
    }

    public function testLaListeEstSansDoublonEtFinitParAutre(): void
    {
        $ecoles = ecolesDisponibles();
        self::assertSame($ecoles, array_values(array_unique($ecoles)));
        self::assertSame('Autre', end($ecoles));
    }

    public function testLesEcolesDejaPorteesParDesComptesRestent(): void
    {
        foreach (['UCA', 'SIGMA Clermont', 'IFSI'] as $ecole) {
            self::assertContains($ecole, ecolesDisponibles());
        }
    }

    public function testChaqueEcoleTientDansLaColonne(): void
    {
        foreach (ecolesDisponibles() as $ecole) {
            self::assertLessThanOrEqual(150, mb_strlen($ecole), $ecole);
        }
    }
}
