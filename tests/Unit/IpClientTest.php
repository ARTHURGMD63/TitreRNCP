<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/security.php';

/**
 * ipClient() décide quelle adresse le limiteur de connexion compte.
 * Derrière un proxy, se tromper revient à compter tous les utilisateurs
 * comme un seul (blocage général) ou à laisser un attaquant choisir son
 * adresse (limiteur contourné, audit SEC-07).
 */
final class IpClientTest extends TestCase
{
    /** @var array<string,string|false> */
    private array $avant = [];

    protected function setUp(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
        foreach (['IP_ENTETE', 'PROXY_CONFIANCE'] as $variable) {
            $this->avant[$variable] = getenv($variable);
            putenv($variable);
        }
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
        foreach ($this->avant as $variable => $valeur) {
            putenv($valeur === false ? $variable : "$variable=$valeur");
        }
    }

    public function testModeAucunSeuleRemoteAddrCompte(): void
    {
        // L'ancienne règle croyait CF-Connecting-IP dès que REMOTE_ADDR était
        // privée : derrière Render, chaque requête choisissait son adresse.
        putenv('IP_ENTETE=aucun');
        $_SERVER['REMOTE_ADDR']           = '10.0.4.2';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.7';
        $_SERVER['HTTP_X_FORWARDED_FOR']  = '198.51.100.8';

        $this->assertSame('10.0.4.2', ipClient());
    }

    public function testParDefautDerriereUnProxyPriveOnLitLEntreeDeDroite(): void
    {
        // Sans réglage (Render), le client peut écrire à gauche mais pas à droite.
        $_SERVER['REMOTE_ADDR']           = '10.0.4.2';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '6.6.6.6';
        $_SERVER['HTTP_X_FORWARDED_FOR']  = '6.6.6.6, 198.51.100.8';

        $this->assertSame('198.51.100.8', ipClient());
    }

    public function testParDefautSansProxyLEnTeteNEstPasLu(): void
    {
        $_SERVER['REMOTE_ADDR']          = '203.0.113.9';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.8';

        $this->assertSame('203.0.113.9', ipClient());
    }

    public function testClientDirectNePeutPasForgerUnEnTeteDeProxy(): void
    {
        putenv('IP_ENTETE=x-forwarded-for');
        putenv('PROXY_CONFIANCE=prive');
        $_SERVER['REMOTE_ADDR']           = '203.0.113.9';
        $_SERVER['HTTP_X_FORWARDED_FOR']  = '198.51.100.1';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.2';

        $this->assertSame('203.0.113.9', ipClient());
    }

    public function testModeCloudflareDepuisUneAdresseCloudflare(): void
    {
        putenv('IP_ENTETE=cloudflare');
        $_SERVER['REMOTE_ADDR']           = '162.158.1.10';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.7';

        $this->assertSame('198.51.100.7', ipClient());
    }

    public function testModeCloudflareIgnoreLEnTeteHorsDeSesPlages(): void
    {
        putenv('IP_ENTETE=cloudflare');
        $_SERVER['REMOTE_ADDR']           = '10.0.4.2';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.7';

        $this->assertSame('10.0.4.2', ipClient());
    }

    public function testXForwardedForPrendLEntreeDeDroiteNonFiable(): void
    {
        putenv('IP_ENTETE=x-forwarded-for');
        putenv('PROXY_CONFIANCE=10.0.0.0/8');
        $_SERVER['REMOTE_ADDR']          = '10.0.4.2';
        // 1.2.3.4 est écrit par le client ; 198.51.100.7 par le proxy de confiance.
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, 198.51.100.7, 10.0.0.9';

        $this->assertSame('198.51.100.7', ipClient());
    }

    public function testXForwardedForSauteLesProxysCloudflareDeclares(): void
    {
        putenv('IP_ENTETE=x-forwarded-for');
        putenv('PROXY_CONFIANCE=prive, cloudflare');
        $_SERVER['REMOTE_ADDR']          = '10.0.4.2';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '6.6.6.6, 198.51.100.7, 172.70.1.1';

        $this->assertSame('198.51.100.7', ipClient());
    }

    public function testXForwardedForSansProxyDeclareNEstPasLu(): void
    {
        putenv('IP_ENTETE=x-forwarded-for');
        $_SERVER['REMOTE_ADDR']          = '10.0.4.2';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';

        $this->assertSame('10.0.4.2', ipClient());
    }

    public function testChaineAbimeeSArreteAuDernierMaillonSur(): void
    {
        $server = ['REMOTE_ADDR' => '10.0.4.2', 'HTTP_X_FORWARDED_FOR' => 'nimporte, 10.0.0.9'];

        $this->assertSame('10.0.0.9', ipDepuisRequete($server, 'x-forwarded-for', ['10.0.0.0/8']));
    }

    public function testEnTeteInvalideRetombeSurLAdresseDirecte(): void
    {
        putenv('IP_ENTETE=cloudflare');
        putenv('PROXY_CONFIANCE=prive');
        $_SERVER['REMOTE_ADDR']           = '10.0.4.2';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = 'pas-une-ip';

        $this->assertSame('10.0.4.2', ipClient());
    }

    public function testSansAdresseOnRendUneValeurNeutre(): void
    {
        $this->assertSame('0.0.0.0', ipClient());
    }

    public function testIpDansPlage(): void
    {
        $this->assertTrue(ipDansPlage('10.1.2.3', '10.0.0.0/8'));
        $this->assertFalse(ipDansPlage('11.1.2.3', '10.0.0.0/8'));
        $this->assertTrue(ipDansPlage('172.31.255.1', '172.16.0.0/12'));
        $this->assertFalse(ipDansPlage('172.32.0.1', '172.16.0.0/12'));
        $this->assertTrue(ipDansPlage('203.0.113.9', '203.0.113.9'));
        $this->assertTrue(ipDansPlage('2606:4700::1', '2606:4700::/32'));
        $this->assertFalse(ipDansPlage('10.1.2.3', '2606:4700::/32'));
        $this->assertFalse(ipDansPlage('10.1.2.3', '10.0.0.0/99'));
        $this->assertFalse(ipDansPlage('10.1.2.3', 'nimporte'));
    }
}
