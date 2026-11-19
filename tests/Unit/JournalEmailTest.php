<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/log.php';

/**
 * Les journaux ne gardent plus d'adresses e-mail en clair (audit DEP-12).
 */
final class JournalEmailTest extends TestCase
{
    public function testLAdresseEstMasqueeSaufLeDomaine(): void
    {
        $this->assertSame('a***@uca.fr', masquerEmail('arthur@uca.fr'));
        $this->assertSame('é***@exemple.fr', masquerEmail('élise.martin@exemple.fr'));
    }

    public function testUneValeurSansArobaseNeFuitPas(): void
    {
        $this->assertSame('***', masquerEmail('pas-une-adresse'));
        $this->assertSame('***', masquerEmail('@exemple.fr'));
    }

    public function testLeCronDeRappelsNeJournaliseQueDesAdressesMasquees(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/cron/rappels.php');

        $this->assertDoesNotMatchRegularExpression("#=>\\s*\\\$r\\['email'\\]#", $source);
        $this->assertDoesNotMatchRegularExpression("#printf\\([^;]*,\\s*\\\$r\\['email'\\]#", $source);
    }
}
