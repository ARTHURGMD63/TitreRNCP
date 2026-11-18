<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * La double authentification des administrateurs.
 *
 * Les valeurs attendues viennent des vecteurs de test de la RFC 6238
 * (secret « 12345678901234567890 », ici en base32), tronquées à six chiffres :
 * si elles passent, n'importe quelle application d'authentification lira le
 * même code que le serveur.
 */
final class TotpTest extends TestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../includes/totp.php';
    }

    /** @return array<string, array{int, string}> */
    public static function vecteursRfc(): array
    {
        return [
            't=59'         => [59, '287082'],
            't=1111111109' => [1111111109, '081804'],
            't=1111111111' => [1111111111, '050471'],
            't=1234567890' => [1234567890, '005924'],
            't=2000000000' => [2000000000, '279037'],
        ];
    }

    /** @dataProvider vecteursRfc */
    public function testLeCodeCorrespondAuxVecteursDeLaRfc(int $instant, string $attendu): void
    {
        $this->assertSame($attendu, totpCode(self::SECRET, intdiv($instant, TOTP_PERIODE)));
    }

    public function testUnCodeJusteEstAccepteEtRenvoieSonPas(): void
    {
        $this->assertSame(intdiv(59, 30), totpVerifier(self::SECRET, '287082', 59));
    }

    public function testUnCodeFauxEstRefuse(): void
    {
        $this->assertNull(totpVerifier(self::SECRET, '000000', 59));
    }

    public function testLesEspacesEtLesSaisiesInvalidesSontGeres(): void
    {
        $this->assertNotNull(totpVerifier(self::SECRET, '287 082', 59));
        $this->assertNull(totpVerifier(self::SECRET, '28708', 59), 'cinq chiffres');
        $this->assertNull(totpVerifier(self::SECRET, 'abcdef', 59));
        $this->assertNull(totpVerifier(self::SECRET, '', 59));
    }

    public function testUneHorlogeEnRetardOuEnAvanceDeTrenteSecondesPasse(): void
    {
        // Le code du pas 1111111109/30 est lu 30 s plus tard, au pas suivant.
        $this->assertNotNull(totpVerifier(self::SECRET, '081804', 1111111109 + 30));
        $this->assertNotNull(totpVerifier(self::SECRET, '081804', 1111111109 - 30));
        $this->assertNull(totpVerifier(self::SECRET, '081804', 1111111109 + 120), 'trop loin');
    }

    public function testUnCodeDejaUtiliseNeServiraPasDeuxFois(): void
    {
        $pas = totpVerifier(self::SECRET, '287082', 59);
        $this->assertNotNull($pas);
        $this->assertNull(totpVerifier(self::SECRET, '287082', 59, $pas), 'rejeu refusé');
    }

    public function testUnSecretGenereEstEnBase32DeLaBonneLongueur(): void
    {
        $secret = totpGenererSecret();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $this->assertNotSame($secret, totpGenererSecret());
        $this->assertSame(20, strlen(totpDecoderBase32($secret)));
    }

    public function testUnSecretInvalideNeDonneAucunCode(): void
    {
        $this->assertSame('', totpCode('pas de base32 !', 1));
    }

    public function testLUriDEnrolementContientLEssentiel(): void
    {
        $uri = totpUriEnrolement(self::SECRET, 'admin@outsee.fr');
        $this->assertStringStartsWith('otpauth://totp/Outsee%3Aadmin%40outsee.fr?', $uri);
        $this->assertStringContainsString('secret=' . self::SECRET, $uri);
        $this->assertStringContainsString('issuer=Outsee', $uri);
    }

    public function testLesCodesDeSecoursSontDistinctsEtAuBonFormat(): void
    {
        $codes = totpGenererCodesSecours();
        $this->assertCount(10, $codes);
        $this->assertCount(10, array_unique($codes));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{4}-[0-9a-f]{4}$/', $codes[0]);
        $this->assertSame(totpEmpreinteSecours('ABCD-1234'), totpEmpreinteSecours(' abcd-1234 '), 'casse et espaces ignorés');
    }
}
