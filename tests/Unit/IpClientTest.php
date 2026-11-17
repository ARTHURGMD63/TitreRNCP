<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/security.php';

/**
 * ipClient() décide quelle adresse le limiteur de connexion compte.
 * Derrière un proxy, se tromper revient à compter tous les utilisateurs
 * comme un seul (blocage général) ou à laisser un attaquant choisir son
 * adresse (limiteur contourné).
 */
final class IpClientTest extends TestCase
{
    protected function setUp(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    public function testClientDirectNePeutPasForgerUnEnTeteDeProxy(): void
    {
        $_SERVER['REMOTE_ADDR']          = '203.0.113.9';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.2';

        $this->assertSame('203.0.113.9', ipClient());
    }

    public function testDerriereUnProxyPriveOnLitCloudflare(): void
    {
        $_SERVER['REMOTE_ADDR']           = '10.0.4.2';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.7';

        $this->assertSame('198.51.100.7', ipClient());
    }

    public function testXForwardedForPrendLEntreeDeDroiteNonPrivee(): void
    {
        $_SERVER['REMOTE_ADDR']          = '10.0.4.2';
        // 1.2.3.4 est écrit par le client ; 198.51.100.7 par le proxy de confiance.
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, 198.51.100.7, 10.0.0.9';

        $this->assertSame('198.51.100.7', ipClient());
    }

    public function testEnTeteInvalideRetombeSurLAdresseDirecte(): void
    {
        $_SERVER['REMOTE_ADDR']           = '10.0.4.2';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = 'pas-une-ip';

        $this->assertSame('10.0.4.2', ipClient());
    }

    public function testSansAdresseOnRendUneValeurNeutre(): void
    {
        $this->assertSame('0.0.0.0', ipClient());
    }
}
