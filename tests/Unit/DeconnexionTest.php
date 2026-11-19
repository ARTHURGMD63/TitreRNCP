<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * La déconnexion se fait en POST avec le jeton CSRF (audit SEC-10) : en GET,
 * une simple image sur un site tiers déconnectait un établissement.
 */
final class DeconnexionTest extends TestCase
{
    private string $racine;

    protected function setUp(): void
    {
        $this->racine = dirname(__DIR__, 2);
        if (!function_exists('formulaireDeconnexion')) {
            require_once $this->racine . '/includes/auth_check.php';
        }
    }

    public function testLogoutExigePostEtJetonCsrf(): void
    {
        $source = (string) file_get_contents($this->racine . '/auth/logout.php');

        $this->assertStringContainsString("\$_SERVER['REQUEST_METHOD'] !== 'POST'", $source);
        $this->assertStringContainsString('csrfVerify();', $source);
        $this->assertLessThan(
            strpos($source, 'session_destroy()'),
            strpos($source, 'csrfVerify();'),
            'Le jeton doit être vérifié avant de fermer la session.'
        );
    }

    public function testLeBoutonEstUnFormulairePostAvecJeton(): void
    {
        $_SESSION = [];
        $html = formulaireDeconnexion('Se déconnecter', 'bouton');

        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringContainsString('/auth/logout.php', $html);
        $this->assertStringContainsString('name="csrf_token"', $html);
        $this->assertStringContainsString('<button type="submit" class="bouton">', $html);
    }

    public function testPlusAucunLienDeDeconnexionEnGet(): void
    {
        $fichiers = array_merge(
            glob($this->racine . '/*.php') ?: [],
            glob($this->racine . '/includes/*.php') ?: [],
            glob($this->racine . '/partenaire/*.php') ?: [],
            glob($this->racine . '/admin/*.php') ?: [],
            glob($this->racine . '/auth/*.php') ?: [],
        );
        foreach ($fichiers as $fichier) {
            $source = (string) file_get_contents($fichier);
            $this->assertDoesNotMatchRegularExpression(
                '#href=[^>]*logout\.php#',
                $source,
                basename($fichier) . ' contient encore un lien de déconnexion en GET.'
            );
        }
    }
}
