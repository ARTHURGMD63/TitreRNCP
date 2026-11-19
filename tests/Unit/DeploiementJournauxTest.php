<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * root n'écrit jamais dans /var/log/outsee, dossier de www-data : le compte
 * web pourrait y remplacer un journal par un lien symbolique et faire écrire
 * root où il veut (audit DEP-02).
 */
final class DeploiementJournauxTest extends TestCase
{
    private string $deploy;

    protected function setUp(): void
    {
        $this->deploy = dirname(__DIR__, 2) . '/deploy';
    }

    public function testLesTachesRootNEcriventPasDansLeDossierDeWwwData(): void
    {
        $lignes = file($this->deploy . '/cron/outsee', FILE_IGNORE_NEW_LINES) ?: [];
        $root   = array_filter($lignes, static fn (string $l): bool => (bool) preg_match('/^[^#\s]\S*\s+\S+\s+\S+\s+\S+\s+\S+\s+root\s/', $l));

        $this->assertNotEmpty($root, 'La sauvegarde nocturne tourne en root.');
        foreach ($root as $ligne) {
            $this->assertStringNotContainsString('/var/log/outsee/', $ligne);
            $this->assertStringContainsString('/var/log/outsee-sauvegarde/', $ligne);
        }
    }

    public function testLeDossierDuJournalRootEstCreeEtTourne(): void
    {
        foreach (['appliquer_config.sh', 'installer_serveur.sh'] as $script) {
            $this->assertStringContainsString(
                'install -d -o root -g root -m 750 /var/log/outsee-sauvegarde',
                (string) file_get_contents($this->deploy . '/' . $script)
            );
        }
        $this->assertStringContainsString('/var/log/outsee-sauvegarde/*.log', (string) file_get_contents($this->deploy . '/logrotate/outsee'));
    }
}
