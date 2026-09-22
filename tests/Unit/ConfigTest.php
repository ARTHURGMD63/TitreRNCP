<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Lecture des réglages : environnement, puis config.local.php, puis défaut.
 *
 * Ce fichier décide où l'application se connecte et sous quelle identité elle
 * envoie ses e-mails. Une erreur de priorité ne se voit pas en développement —
 * les trois sources y disent la même chose — mais en production, elle fait
 * taper dans la mauvaise base ou expédier depuis le mauvais domaine.
 *
 * C'est aussi le fichier qui a été extrait de db.php parce que deux lecteurs
 * avaient déjà divergé une fois.
 */
final class ConfigTest extends TestCase
{
    /** @var array<string,string|false> */
    private array $avant = [];

    private const VARIABLES = ['SL_TEST_REGLAGE', 'SL_TEST_BOOLEEN', 'APP_ENV'];

    protected function setUp(): void
    {
        if (!function_exists('reglage')) {
            require_once __DIR__ . '/../../includes/config.php';
        }

        // L'environnement du processus de test est restitué tel quel : un
        // test qui laisse traîner une variable fait échouer le suivant, et
        // l'ordre d'exécution n'est pas garanti.
        foreach (self::VARIABLES as $nom) {
            $this->avant[$nom] = getenv($nom);
            putenv($nom);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->avant as $nom => $valeur) {
            if ($valeur === false) {
                putenv($nom);
            } else {
                putenv($nom . '=' . $valeur);
            }
        }
    }

    public function testLEnvironnementLEmporteSurLeDefaut(): void
    {
        putenv('SL_TEST_REGLAGE=depuis-environnement');

        $this->assertSame(
            'depuis-environnement',
            reglage('SL_TEST_REGLAGE', 'cle_absente', 'defaut')
        );
    }

    public function testLeDefautSertQuandRienNEstDefini(): void
    {
        $this->assertSame('defaut', reglage('SL_TEST_REGLAGE', 'cle_absente', 'defaut'));
    }

    public function testLeDefautEstVideQuandIlNEstPasPrecise(): void
    {
        $this->assertSame('', reglage('SL_TEST_REGLAGE', 'cle_absente'));
    }

    public function testUneVariableVideNeComptePasCommeDefinie(): void
    {
        // Le cas qui compte : un panneau d'hébergeur qui crée la variable sans
        // la remplir. Si la chaîne vide l'emportait, l'application se
        // connecterait avec un mot de passe vide au lieu du défaut, et
        // l'erreur remontée parlerait d'authentification, pas de configuration.
        putenv('SL_TEST_REGLAGE=');

        $this->assertSame('defaut', reglage('SL_TEST_REGLAGE', 'cle_absente', 'defaut'));
    }

    /** @dataProvider ecrituresVraies */
    public function testLesEcrituresDuVraiSontToutesReconnues(string $brut): void
    {
        // Un panneau d'hébergeur, un fichier YAML et un Dockerfile n'écrivent
        // pas « vrai » de la même façon.
        putenv('SL_TEST_BOOLEEN=' . $brut);

        $this->assertTrue(reglageBooleen('SL_TEST_BOOLEEN', 'cle_absente'));
    }

    /** @return array<string,array{string}> */
    public static function ecrituresVraies(): array
    {
        return [
            'un'            => ['1'],
            'true'          => ['true'],
            'TRUE'          => ['TRUE'],
            'on'            => ['on'],
            'yes'           => ['yes'],
            'avec espaces'  => ['  true  '],
        ];
    }

    /** @dataProvider ecrituresFausses */
    public function testToutLeResteEstFaux(string $brut): void
    {
        putenv('SL_TEST_BOOLEEN=' . $brut);

        $this->assertFalse(reglageBooleen('SL_TEST_BOOLEEN', 'cle_absente'));
    }

    /** @return array<string,array{string}> */
    public static function ecrituresFausses(): array
    {
        return [
            'zero'      => ['0'],
            'false'     => ['false'],
            'off'       => ['off'],
            'no'        => ['no'],
            'n importe' => ['peut-être'],
        ];
    }

    public function testUnBooleenAbsentPrendSonDefaut(): void
    {
        $this->assertTrue(reglageBooleen('SL_TEST_BOOLEEN', 'cle_absente', true));
        $this->assertFalse(reglageBooleen('SL_TEST_BOOLEEN', 'cle_absente', false));
    }

    public function testLaProductionNEstReconnueQueSurUnMotExplicite(): void
    {
        foreach (['production', 'prod', 'PRODUCTION', 'Prod'] as $valeur) {
            putenv('APP_ENV=' . $valeur);
            $this->assertTrue(estEnProduction(), "« $valeur » devrait valoir production");
        }

        // En cas de doute, on n'est pas en production : c'est le sens le moins
        // dangereux. Se croire en développement affiche des erreurs détaillées ;
        // se croire en production les masque — la première erreur se corrige,
        // la seconde s'enquête à l'aveugle.
        foreach (['dev', 'staging', 'préprod', ''] as $valeur) {
            putenv('APP_ENV=' . $valeur);
            $this->assertFalse(estEnProduction(), "« $valeur » ne devrait pas valoir production");
        }
    }

    public function testLaConfigurationLocaleEstUnTableau(): void
    {
        // configLocale() doit renvoyer un tableau même sans fichier, sinon
        // chaque appelant doit se protéger — et l'un d'eux oubliera.
        $this->assertIsArray(configLocale());
    }
}
