<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Chaque compte à sa place : sortie (bar, restaurant, boîte, afterwork, BDE),
 * sport (club). La culture n'a pas de compte partenaire.
 */
final class ComptesTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../includes/crm.php';
    }

    public function testLesFamillesSeparentLaSortieEtLeSport(): void
    {
        foreach (['bar', 'resto', 'boite', 'afterwork', 'bde'] as $t) {
            $this->assertSame('sortie', familleEtablissement($t), $t);
        }
        $this->assertSame('sport', familleEtablissement('club'));
    }

    public function testLaCultureNEstPasUnTypeDeCompteEtLesTypesInconnusSontRefuses(): void
    {
        $this->assertFalse(typeEtablissementValide('culture'));
        $this->assertFalse(typeEtablissementValide('admin'));
        $this->assertFalse(typeEtablissementValide(null));
        $this->assertTrue(typeEtablissementValide('bde'));
    }

    public function testUnClubNeVoitQueLaFormuleClubEtLesAutresNeLaVoientPas(): void
    {
        $this->assertSame(['club'], formulesPourType('club'));
        foreach (['bar', 'resto', 'boite', 'afterwork', 'bde'] as $t) {
            $this->assertNotContains('club', formulesPourType($t), $t);
        }
    }

    public function testLaFormuleBdeNEstPourQueLesBde(): void
    {
        $this->assertSame(['bde'], formulesPourType('bde'));
        foreach (['bar', 'resto', 'boite', 'afterwork', 'club'] as $t) {
            $this->assertNotContains('bde', formulesPourType($t), $t);
        }
    }

    public function testLEssentielEstPourLesBarsRestosEtAfterworksPasLesBoites(): void
    {
        $this->assertTrue(formuleCompatible('bar', 'essentiel'));
        $this->assertTrue(formuleCompatible('resto', 'essentiel'));
        $this->assertFalse(formuleCompatible('boite', 'essentiel'));
        $this->assertTrue(formuleCompatible('boite', 'premium'));
    }

    public function testLeTypeDEvenementDecouleDuCompte(): void
    {
        $this->assertSame('sport', typeEvenementPour('club', 'bar'));
        $this->assertSame('resto', typeEvenementPour('resto', 'boite'));
        $this->assertSame('boite', typeEvenementPour('boite', 'bar'));
        // Un BDE, qui n'a pas de lieu, choisit parmi les types de soirée.
        $this->assertSame('afterwork', typeEvenementPour('bde', 'afterwork'));
        $this->assertNull(typeEvenementPour('bde', 'sport'));
        $this->assertNull(typeEvenementPour('inconnu', 'bar'));
    }
}
