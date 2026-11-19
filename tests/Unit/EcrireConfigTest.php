<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * .github/scripts/ecrire_config.php fabrique la configuration du site
 * mutualisé. Sans app_env, le site se croyait hors production et les
 * comptes de démonstration au mot de passe public s'ouvraient (audit
 * SEC-01) : la variable est désormais obligatoire.
 */
final class EcrireConfigTest extends TestCase
{
    private string $sortie = '';

    protected function setUp(): void
    {
        $this->sortie = sys_get_temp_dir() . '/outsee_config_' . bin2hex(random_bytes(6)) . '.php';
    }

    protected function tearDown(): void
    {
        @unlink($this->sortie);
    }

    /**
     * @param array<string,string> $env
     * @return array{0:int,1:string}
     */
    private function lancer(array $env): array
    {
        $env += ['DB_HOST' => 'h', 'DB_NAME' => 'n', 'DB_USER' => 'u', 'DB_PASSWORD' => 'p'];
        $env += ['PATH' => (string) getenv('PATH'), 'SystemRoot' => (string) getenv('SystemRoot')];
        $script = dirname(__DIR__, 2) . '/.github/scripts/ecrire_config.php';
        $proc = proc_open(
            [PHP_BINARY, '-n', $script, $this->sortie],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $tubes,
            null,
            $env
        );
        $this->assertIsResource($proc);
        stream_get_contents($tubes[1]);
        $erreurs = (string) stream_get_contents($tubes[2]);
        fclose($tubes[1]);
        fclose($tubes[2]);

        return [proc_close($proc), $erreurs];
    }

    public function testSansAppEnvLeDeploiementSArrete(): void
    {
        [$code, $erreurs] = $this->lancer([]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('APP_ENV', $erreurs);
        $this->assertFileDoesNotExist($this->sortie);
    }

    public function testUnEnvironnementDeDeveloppementEstRefuse(): void
    {
        [$code] = $this->lancer(['APP_ENV' => 'dev']);

        $this->assertSame(1, $code);
        $this->assertFileDoesNotExist($this->sortie);
    }

    public function testEnProductionLaConfigurationEstEcrite(): void
    {
        [$code] = $this->lancer(['APP_ENV' => 'production', 'URL_PUBLIQUE' => 'https://exemple.fr']);

        $this->assertSame(0, $code);
        $config = require $this->sortie;
        $this->assertSame('production', $config['app_env']);
        $this->assertSame('https://exemple.fr', $config['url_publique']);
    }
}
