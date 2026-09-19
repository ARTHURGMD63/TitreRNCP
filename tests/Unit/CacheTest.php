<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Le cache applicatif.
 *
 * Il se glisse entre les pages et la base : une erreur ici ne provoque pas
 * une panne visible, elle affiche une valeur périmée — ce qui est bien pire,
 * parce que personne ne s'en aperçoit avant qu'un partenaire ne signale que
 * sa note n'a pas bougé depuis trois jours.
 */
final class CacheTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../includes/cache.php';
        cacheVider();
    }

    protected function tearDown(): void
    {
        cacheVider();
    }

    public function testUneValeurEcriteSeRelit(): void
    {
        cacheSet('essai', ['a' => 1, 'b' => 'deux'], 60);
        $this->assertSame(['a' => 1, 'b' => 'deux'], cacheGet('essai'));
    }

    public function testUneCleAbsenteRenvoieLeDefaut(): void
    {
        $this->assertNull(cacheGet('jamais-ecrite'));
        $this->assertSame('replis', cacheGet('jamais-ecrite', 'replis'));
    }

    public function testUnTtlNegatifNEcritRien(): void
    {
        // Le piège : les deux magasins ramenaient un TTL négatif à zéro,
        // c'est-à-dire « sans expiration ». Un calcul d'échéance qui passe
        // sous zéro produisait donc une entrée éternelle là où l'appelant
        // demandait une entrée déjà morte.
        cacheSet('negatif', 'ancien', -10);
        $this->assertNull(cacheGet('negatif'));
    }

    public function testUneValeurPerimeeNEstPasServie(): void
    {
        if (cacheUtiliseApcu()) {
            $this->markTestSkipped('APCu gère son expiration lui-même.');
        }

        cacheSet('perimee', 'ancien', 300);
        $this->assertSame('ancien', cacheGet('perimee'), 'Préalable : la valeur est bien en cache.');

        // On vieillit l'entrée sur place plutôt que d'attendre : le test
        // porte sur la lecture d'une échéance dépassée, pas sur l'horloge.
        $chemin = cacheChemin('perimee');
        $this->assertNotNull($chemin);
        file_put_contents($chemin, serialize(['e' => time() - 1, 'v' => 'ancien']));

        $this->assertNull(cacheGet('perimee'));
        $this->assertFileDoesNotExist($chemin, 'Une entrée périmée lue doit être supprimée au passage.');
    }

    public function testRememberNeCalculeQuUneFois(): void
    {
        $appels = 0;
        $calcul = static function () use (&$appels): string {
            $appels++;

            return 'calcule';
        };

        $this->assertSame('calcule', cacheRemember('memo', 60, $calcul));
        $this->assertSame('calcule', cacheRemember('memo', 60, $calcul));
        $this->assertSame(1, $appels, 'Le second appel devait être servi par le cache.');
    }

    public function testRememberSaitMettreEnCacheUneValeurVide(): void
    {
        // Le piège classique : un tableau vide ou un zéro sont des valeurs
        // légitimes. Un cache qui les confond avec « absent » recalcule à
        // chaque fois, et c'est précisément sur les listes vides que la
        // requête coûte le plus cher — elle ne trouve rien après avoir tout lu.
        $appels = 0;
        $calcul = static function () use (&$appels): array {
            $appels++;

            return [];
        };

        cacheRemember('vide', 60, $calcul);
        cacheRemember('vide', 60, $calcul);
        $this->assertSame(1, $appels);
    }

    public function testOublierSupprimeLaCle(): void
    {
        cacheSet('a-oublier', 'valeur', 60);
        cacheOublier('a-oublier');
        $this->assertNull(cacheGet('a-oublier'));
    }

    public function testLesTypesScalairesSurviventAuTour(): void
    {
        cacheSet('entier', 42, 60);
        cacheSet('flottant', 4.5, 60);
        cacheSet('faux', false, 60);

        $this->assertSame(42, cacheGet('entier'));
        $this->assertSame(4.5, cacheGet('flottant'));
        // false doit se relire comme false, et non être pris pour une absence.
        $this->assertFalse(cacheGet('faux', 'defaut-non-attendu'));
    }
}
