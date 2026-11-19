<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/comptes.php';

/**
 * PAY-18 : un BDE validé qui change de nom repasse en attente.
 */
final class VerificationBdeTest extends TestCase
{
    public function testUnBdeValideQuiChangeDeNomRepasseEnAttente(): void
    {
        $this->assertSame('en_attente', verificationApresModification('bde', 'bde', 'BDE Sciences', 'BDE Médecine', 'validee'));
    }

    public function testLaCasseEtLesEspacesNeComptentPas(): void
    {
        $this->assertSame('validee', verificationApresModification('bde', 'bde', 'BDE Sciences', '  bde   SCIENCES ', 'validee'));
    }

    public function testUnRefusResteUnRefus(): void
    {
        $this->assertSame('refusee', verificationApresModification('bde', 'bde', 'BDE A', 'BDE B', 'refusee'));
    }

    public function testUnChangementDeTypeRepartDeLEtatInitial(): void
    {
        $this->assertSame('en_attente', verificationApresModification('bar', 'bde', 'Le Bar', 'Le Bar', 'non_requise'));
        $this->assertSame('non_requise', verificationApresModification('bde', 'bar', 'BDE', 'Le Bar', 'validee'));
    }

    public function testUnBarPeutChangerDeNomLibrement(): void
    {
        $this->assertSame('non_requise', verificationApresModification('bar', 'bar', 'Le Bar', 'Le Comptoir', 'non_requise'));
    }
}
