<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * L'espace Culture : critères d'URL, saisie de l'administrateur, mise en forme.
 *
 * Tout ce qui se vérifie sans base. Les requêtes se vérifient sur une base
 * jetable (voir CONTRIBUTING.md).
 */
final class CultureTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('cultureCriteres')) {
            require_once __DIR__ . '/../../includes/culture.php';
        }
    }

    /** @return array<string,mixed> */
    private function saisie(array $change = []): array
    {
        return array_merge([
            'titre'      => 'Nuit des musées',
            'categorie'  => 'patrimoine',
            'lieu'       => 'Musée Roger-Quilliot',
            'date_heure' => '2026-12-05 19:00',
        ], $change);
    }

    public function testUneCategorieInconnueNeFiltreRien(): void
    {
        $this->assertSame('', cultureCriteres(['categorie' => 'rave'])['categorie']);
        $this->assertSame('expo', cultureCriteres(['categorie' => 'expo'])['categorie']);
        $this->assertSame('', cultureCriteres(['categorie' => ['expo']])['categorie']);
    }

    public function testUnePageInvalideRevientALaPremiere(): void
    {
        $this->assertSame(1, cultureCriteres(['pe' => '-4'])['page']);
        $this->assertSame(1, cultureCriteres(['pe' => 'abc'])['page']);
        $this->assertSame(3, cultureCriteres(['pe' => '3'])['page']);
    }

    public function testLeCatalogueSuitLEnumDeLaMigration(): void
    {
        $sql = (string) file_get_contents(__DIR__ . '/../../db_migrations_v29.sql');
        $this->assertMatchesRegularExpression('/categorie ENUM\(([^)]*)\)/', $sql);
        preg_match('/categorie ENUM\(([^)]*)\)/', $sql, $m);
        $enum = array_map(static fn (string $v): string => trim($v, " '"), explode(',', $m[1]));

        $this->assertSame($enum, array_keys(CULTURE_CATEGORIES));
    }

    public function testUneSaisieCompleteEstAcceptee(): void
    {
        [$v, $erreurs] = cultureValiderSaisie($this->saisie(['date_fin' => '2026-12-06T01:00']));

        $this->assertSame([], $erreurs);
        $this->assertSame('2026-12-05 19:00:00', $v['date_heure']);
        $this->assertSame('2026-12-06 01:00:00', $v['date_fin']);
        $this->assertSame('Ville de Clermont-Ferrand', $v['organisateur']);
        $this->assertSame(0, $v['quota']);
        $this->assertNull($v['lien']);
    }

    public function testLesChampsObligatoiresSontSignales(): void
    {
        [, $erreurs] = cultureValiderSaisie(['titre' => '  ', 'categorie' => 'rave', 'lieu' => '', 'date_heure' => '']);

        $this->assertCount(4, $erreurs);
    }

    public function testUneFinAvantLeDebutEstRefusee(): void
    {
        [, $erreurs] = cultureValiderSaisie($this->saisie(['date_fin' => '2026-12-05 18:00']));

        $this->assertSame(['La fin doit venir après le début.'], $erreurs);
    }

    public function testUneDateImpossibleEstRefusee(): void
    {
        [, $erreurs] = cultureValiderSaisie($this->saisie(['date_heure' => '2026-02-31 19:00']));

        $this->assertContains('La date de début est invalide.', $erreurs);
    }

    public function testUnLienNonWebEstRefuse(): void
    {
        [, $erreurs] = cultureValiderSaisie($this->saisie(['lien' => 'javascript:alert(1)']));
        $this->assertContains('Le lien doit commencer par https://.', $erreurs);

        [$v, $erreurs] = cultureValiderSaisie($this->saisie(['lien' => 'https://clermont-ferrand.fr/agenda']));
        $this->assertSame([], $erreurs);
        $this->assertSame('https://clermont-ferrand.fr/agenda', $v['lien']);
    }

    public function testUnQuotaNegatifEstRefuse(): void
    {
        [, $erreurs] = cultureValiderSaisie($this->saisie(['quota' => -1]));

        $this->assertNotSame([], $erreurs);
    }

    public function testUneEntreeLibreNEstJamaisComplete(): void
    {
        $f = cultureFormater([
            'id' => '7', 'titre' => 'Concert', 'description' => null, 'categorie' => 'concert',
            'organisateur' => 'Ville', 'lieu' => 'Kiosque', 'ville' => 'Clermont-Ferrand',
            'date_heure' => '2026-12-05 19:00:00', 'date_fin' => null, 'quota' => '0', 'prix' => '0.00',
            'lien' => null, 'mis_en_avant' => '1', 'en_cours_calc' => '0', 'nb_inscrits' => '412', 'deja_inscrit' => '1',
        ], ['prenoms' => ['Léa'], 'nb' => 1]);

        $this->assertSame(7, $f['id']);
        $this->assertFalse($f['places']['complet']);
        $this->assertNull($f['places']['restantes']);
        $this->assertTrue($f['is_gratuit']);
        $this->assertTrue($f['mis_en_avant']);
        $this->assertTrue($f['deja_inscrit']);
        $this->assertSame(['prenoms' => ['Léa'], 'nb' => 1], $f['amis']);
    }

    public function testUnQuotaAtteintEstComplet(): void
    {
        $f = cultureFormater([
            'id' => 1, 'titre' => 'Atelier', 'description' => '', 'categorie' => 'atelier',
            'organisateur' => 'Ville', 'lieu' => 'Salle', 'ville' => 'Clermont-Ferrand',
            'date_heure' => '2026-12-05 19:00:00', 'date_fin' => null, 'quota' => 16, 'prix' => '5.00',
            'lien' => '', 'mis_en_avant' => 0, 'nb_inscrits' => 16,
        ]);

        $this->assertTrue($f['places']['complet']);
        $this->assertSame(0, $f['places']['restantes']);
        $this->assertFalse($f['is_gratuit']);
        $this->assertNull($f['lien']);
    }
}
