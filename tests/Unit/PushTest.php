<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/push.php';

/**
 * Les règles des notifications push qui ne demandent ni base ni réseau, et
 * le traitement de la file contre une base SQLite en mémoire.
 */
final class PushTest extends TestCase
{
    public function testSeulUnJetonExpoBienFormeEstAccepte(): void
    {
        $this->assertTrue(pushJetonValide('ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]'));
        $this->assertTrue(pushJetonValide('ExpoPushToken[abcdefghij_0123-45]'));
        $this->assertFalse(pushJetonValide(''));
        $this->assertFalse(pushJetonValide('https://exemple.fr'));
        $this->assertFalse(pushJetonValide('ExponentPushToken[<script>]'));
    }

    public function testUnEtudiantRegleTroisCategoriesEtUnEtablissementAucune(): void
    {
        $this->assertSame(['social', 'rappels', 'lieux'], pushCategoriesPour('etudiant'));
        $this->assertSame([], pushCategoriesPour('partenaire'));
        $this->assertSame([], pushCategoriesPour('admin'));
        foreach (pushCategoriesPour('etudiant') as $code) {
            $this->assertTrue(pushCategories()[$code]['modifiable'], $code);
        }
        $this->assertFalse(pushCategories()['compte']['modifiable']);
    }

    public function testUnMessageDeLaNuitPartLeMatin(): void
    {
        $paris = new DateTimeZone('Europe/Paris');

        $soir = pushHeureEnvoi(new DateTimeImmutable('2026-11-20 23:30', $paris))->setTimezone($paris);
        $this->assertSame('2026-11-21 08:00', $soir->format('Y-m-d H:i'));

        $aube = pushHeureEnvoi(new DateTimeImmutable('2026-11-20 05:10', $paris))->setTimezone($paris);
        $this->assertSame('2026-11-20 08:00', $aube->format('Y-m-d H:i'));

        $jour = pushHeureEnvoi(new DateTimeImmutable('2026-11-20 17:00', $paris))->setTimezone($paris);
        $this->assertSame('2026-11-20 17:00', $jour->format('Y-m-d H:i'));
    }

    public function testLHeureDEnvoiEstEnUtc(): void
    {
        $t = pushHeureEnvoi(new DateTimeImmutable('2026-07-01 12:00', new DateTimeZone('Europe/Paris')));
        $this->assertSame('UTC', $t->getTimezone()->getName());
        $this->assertSame('10:00', $t->format('H:i'));
    }

    public function testLesTextesNeSontJamaisVides(): void
    {
        foreach ([
            pushTexteDemandeSuivi('Léa'), pushTexteDemandeAcceptee('Léa'),
            pushTexteBdeValide(), pushTexteBdeRefuse(),
            pushTexteLieu('Le Bec', 'Soirée'), pushTexteRappel('Soirée', '22h00', 'Le Bec'),
            pushTexteNouvelEvenement('Le Bec', 'Soirée'),
        ] as [$titre, $corps]) {
            $this->assertNotSame('', $titre);
            $this->assertNotSame('', $corps);
        }
    }
}
