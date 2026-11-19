<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * api/v1/inscription.php : règles lues dans le code, faute de base de test
 * pour l'appeler.
 */
final class InscriptionApiTest extends TestCase
{
    private string $code;

    protected function setUp(): void
    {
        $this->code = (string) file_get_contents(__DIR__ . '/../../api/v1/inscription.php');
    }

    public function testUnPartenaireSansEtablissementEstRefuse(): void
    {
        // ETU-15 : le refus précède la transaction, et l'établissement est
        // toujours créé pour un partenaire.
        $refus = strpos($this->code, "\$type === 'partenaire' && \$etablNom === ''");
        $this->assertNotFalse($refus);
        $this->assertLessThan(strpos($this->code, 'beginTransaction()'), $refus);
        $this->assertStringNotContainsString("\$type === 'partenaire' && \$etablNom)", $this->code);
    }
}
