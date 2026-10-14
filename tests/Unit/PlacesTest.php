<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/places.php';

/**
 * La règle « complet » existait sous six formes voisines. Ces tests figent
 * les deux comportements qu'elles recouvraient, pour qu'un futur changement
 * soit un choix et non une dérive.
 */
final class PlacesTest extends TestCase
{
    public function testUneSortieEstCompleteQuandLeQuotaEstAtteint(): void
    {
        $this->assertFalse(estComplet(9, 10));
        $this->assertTrue(estComplet(10, 10));
        $this->assertTrue(estComplet(11, 10), 'Un dépassement reste « complet ».');
    }

    public function testUnQuotaNulFermeLInscription(): void
    {
        // Côté écriture : on n'ajoute personne à une sortie sans place.
        $this->assertTrue(estComplet(0, 0));
    }

    public function testLAffichageCompteLesPlacesRestantes(): void
    {
        $this->assertSame(['restantes' => 4, 'complet' => false], remplissage(10, 6));
        $this->assertSame(['restantes' => 0, 'complet' => true], remplissage(10, 10));
        $this->assertSame(['restantes' => 0, 'complet' => true], remplissage(10, 12), 'Jamais de places négatives.');
    }

    public function testUnQuotaNulNEstPasUneSortieComplete(): void
    {
        // Côté affichage : pas de limite, donc ni « complet » ni compteur.
        $this->assertSame(['restantes' => null, 'complet' => false], remplissage(0, 0));
        $this->assertSame(['restantes' => null, 'complet' => false], remplissage(0, 25));
        $this->assertSame(['restantes' => null, 'complet' => false], remplissage(-1, 3));
    }
}
