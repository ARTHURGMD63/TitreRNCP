<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests des helpers d'authentification (sans toucher la BDD).
 */
final class AuthCheckTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('baseUrl')) {
            require_once __DIR__ . '/../../includes/auth_check.php';
        }
        $_SESSION = [];
    }

    public function testBaseUrlOnLocalhostPrefixesTitreRNCP(): void
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $this->assertSame('/TitreRNCP', baseUrl());
        $this->assertSame('/TitreRNCP/explore.php', baseUrl('/explore.php'));
    }

    public function testBaseUrlOn127IsLocal(): void
    {
        $_SERVER['HTTP_HOST'] = '127.0.0.1:8080';
        $this->assertSame('/TitreRNCP/api/follow.php', baseUrl('/api/follow.php'));
    }

    public function testBaseUrlOnProductionHasNoPrefix(): void
    {
        $_SERVER['HTTP_HOST'] = 'titrerncp-production.up.railway.app';
        $this->assertSame('', baseUrl());
        $this->assertSame('/explore.php', baseUrl('/explore.php'));
    }

    public function testIsLoggedInReturnsFalseWithoutSession(): void
    {
        $_SESSION = [];
        $this->assertFalse(isLoggedIn());
    }

    public function testIsLoggedInReturnsTrueWithUserId(): void
    {
        $_SESSION['user_id'] = 42;
        $this->assertTrue(isLoggedIn());
    }

    public function testCurrentUserReturnsEmptyDefaults(): void
    {
        $_SESSION = [];
        $u = currentUser();
        $this->assertNull($u['id']);
        $this->assertSame('', $u['prenom']);
        $this->assertSame('', $u['type']);
    }

    public function testCurrentUserReadsFromSession(): void
    {
        $_SESSION = [
            'user_id'     => 7,
            'user_prenom' => 'Arthur',
            'user_nom'    => 'Test',
            'user_type'   => 'etudiant',
            'user_ecole'  => 'UCA',
        ];
        $u = currentUser();
        $this->assertSame(7, $u['id']);
        $this->assertSame('Arthur', $u['prenom']);
        $this->assertSame('etudiant', $u['type']);
        $this->assertSame('UCA', $u['ecole']);
    }

    public function testThemeBootScriptIsValidHtml(): void
    {
        $script = themeBootScript();
        $this->assertStringStartsWith('<script>', $script);
        $this->assertStringContainsString('localStorage.getItem', $script);
        $this->assertStringContainsString('data-theme', $script);
        $this->assertStringEndsWith('</script>', $script);
    }
}
