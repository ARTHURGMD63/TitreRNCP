<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/compte.php';

/**
 * Les parties du cycle de vie d'un compte qui ne demandent pas de base.
 * Le reste (codes, suppression) est joué contre une vraie base : voir
 * tests/Integration/CompteDbTest.php.
 */
final class CompteTest extends TestCase
{
    public function testLeCodeASixChiffresZerosInitiauxCompris(): void
    {
        for ($i = 0; $i < 300; $i++) {
            $this->assertMatchesRegularExpression('/^\d{6}$/', codeReinitialisationGenerer());
        }
    }

    public function testLEmpreinteNeDonnePasLeCodeEtDependDeLAdresse(): void
    {
        $a = codeReinitialisationEmpreinte('ana@uca.fr', '123456');

        $this->assertSame(64, strlen($a));
        $this->assertStringNotContainsString('123456', $a);
        $this->assertNotSame($a, codeReinitialisationEmpreinte('bob@uca.fr', '123456'));
        $this->assertNotSame($a, codeReinitialisationEmpreinte('ana@uca.fr', '123457'));
    }

    public function testLEmpreinteIgnoreLaCasseDeLAdresse(): void
    {
        $this->assertSame(
            codeReinitialisationEmpreinte('Ana@UCA.fr', '000042'),
            codeReinitialisationEmpreinte('ana@uca.fr', '000042')
        );
    }

    public function testLAdresseDUnCompteSupprimeEstUniqueEtInjoignable(): void
    {
        $this->assertSame('supprime-12@linkee.invalid', compteAdresseSupprimee(12));
        $this->assertNotSame(compteAdresseSupprimee(12), compteAdresseSupprimee(13));
        // « .invalid » est réservé par la RFC 2606 : aucun serveur ne peut y répondre.
        $this->assertStringEndsWith('.invalid', compteAdresseSupprimee(1));
    }

    public function testLaBorneDEssaisEtLeDelaiDeRenvoiExistent(): void
    {
        // Ce sont eux qui bornent la devinette d'un code à six chiffres.
        $this->assertLessThanOrEqual(5, CODE_REINIT_ESSAIS_MAX);
        $this->assertGreaterThanOrEqual(30, CODE_REINIT_DELAI_RENVOI_S);
        $this->assertLessThanOrEqual(30, CODE_REINIT_VALIDITE_MIN);
    }
}
