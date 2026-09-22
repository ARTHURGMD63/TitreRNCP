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

    /**
     * Le nom d'hôte ne décide plus du préfixe.
     *
     * Trois tests figeaient ici le comportement inverse : « localhost » ou
     * « 127.0.0.1 » donnaient « /TitreRNCP », tout autre hôte donnait la
     * racine. Ils décrivaient fidèlement un défaut — ouvrir le site à un
     * téléphone du même réseau, donc par une adresse IP, faisait tomber le
     * préfixe et arriver la page sans style ni lien valide.
     *
     * Le préfixe se déduit désormais du chemin d'installation.
     * `PrefixeTest` couvre ce calcul cas par cas ; ici, on vérifie seulement
     * que baseUrl() n'écoute plus l'hôte.
     */
    public function testBaseUrlNeDependPlusDeLHote(): void
    {
        $reference = baseUrl('/explore.php');

        foreach (['localhost', '127.0.0.1:8080', '192.168.1.82:8080', 'studentlink.example.com'] as $hote) {
            $_SERVER['HTTP_HOST'] = $hote;
            $this->assertSame($reference, baseUrl('/explore.php'), "l'hôte « $hote » a changé le préfixe");
        }
    }

    public function testBaseUrlConcateneLeCheminSansDoubleBarre(): void
    {
        $this->assertStringEndsWith('/explore.php', baseUrl('/explore.php'));
        $this->assertStringNotContainsString('//', baseUrl('/api/follow.php'));
    }

    public function testBaseUrlSansCheminNeRendJamaisUneBarreSeule(): void
    {
        // baseUrl() sert de base à toutes les concaténations : une barre
        // esseulée produirait « //explore.php », que le navigateur lit comme
        // un hôte et non comme un chemin.
        $this->assertNotSame('/', baseUrl());
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
        $this->assertStringStartsWith('<meta name="theme-color"', $script);
        $this->assertStringContainsString('<script>', $script);
        $this->assertStringContainsString('localStorage.getItem', $script);
        $this->assertStringContainsString('data-theme', $script);
        $this->assertStringEndsWith('</script>', $script);
    }

    /**
     * La barre d'état doit suivre le thème : claire par défaut, sombre quand
     * l'utilisateur a choisi le thème sombre.
     */
    public function testThemeBootScriptColorsTheStatusBar(): void
    {
        $script = themeBootScript();
        $this->assertStringContainsString('content="#F3EEE3"', $script, 'valeur claire par défaut');
        $this->assertStringContainsString('#16130F', $script, 'valeur sombre appliquée au besoin');
        $this->assertStringContainsString('meta[name=theme-color]', $script);
    }

    /**
     * La majorite conditionne l'acces a toute l'application : le calcul
     * doit tomber juste au jour pres, et refuser ce qui n'est pas une date.
     */
    public function testAgeEnAnneesComptelesAnneesRevolues(): void
    {
        $hier    = (new \DateTimeImmutable('today'))->modify('-18 years -1 day');
        $demain  = (new \DateTimeImmutable('today'))->modify('-18 years +1 day');
        $pileAuj = (new \DateTimeImmutable('today'))->modify('-18 years');

        // La veille des 18 ans : encore mineur.
        self::assertSame(17, ageEnAnnees($demain->format('Y-m-d')));
        // Le jour des 18 ans : majeur.
        self::assertSame(18, ageEnAnnees($pileAuj->format('Y-m-d')));
        self::assertSame(18, ageEnAnnees($hier->format('Y-m-d')));
    }

    public function testAgeEnAnneesRejetteLesDatesImpossibles(): void
    {
        self::assertNull(ageEnAnnees(null));
        self::assertNull(ageEnAnnees(''));
        self::assertNull(ageEnAnnees('pas-une-date'));
        self::assertNull(ageEnAnnees('2005-02-31'), 'le 31 fevrier n existe pas');
        self::assertNull(ageEnAnnees('15/09/1998'), 'format francais non accepte');

        $futur = (new \DateTimeImmutable('today'))->modify('+1 day')->format('Y-m-d');
        self::assertNull(ageEnAnnees($futur), 'une naissance a venir n a pas d age');
    }
}
