<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Redirection de retour et contrôle d'origine.
 *
 * Deux gardes qui n'existaient pas :
 *
 *  • csrfVerify() renvoyait l'utilisateur vers `$_SERVER['HTTP_REFERER']`
 *    tel quel. Cet en-tête est posé par le navigateur d'après la page
 *    précédente, qui peut appartenir à n'importe qui : une page hostile
 *    pointant vers un formulaire de l'application avec un jeton
 *    volontairement faux récupérait le visiteur sur son propre domaine,
 *    avec l'application comme caution.
 *
 *  • Rien ne regardait l'origine de la requête. Le cookie en SameSite=Lax
 *    suffisait, mais c'était la seule couche.
 */
final class RedirectionTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('urlInterneOuDefaut')) {
            require_once __DIR__ . '/../../includes/security.php';
        }
        $_SERVER['HTTP_HOST'] = 'studentlink.example';
        unset($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_REFERER']);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']);
    }

    // ── urlInterneOuDefaut ──────────────────────────────────────────────────

    public function testUnCheminInterneEstConserve(): void
    {
        $this->assertSame('/explore.php', urlInterneOuDefaut('/explore.php'));
    }

    public function testLaRequeteEtLeFragmentSuivent(): void
    {
        // Sans cela, revenir d'un formulaire en erreur perdrait le filtre en
        // cours, et le message de récupération arriverait sur la mauvaise page.
        $this->assertSame(
            '/explore.php?view=people&p=2#liste',
            urlInterneOuDefaut('/explore.php?view=people&p=2#liste')
        );
    }

    public function testUneUrlAbsolueDuMemeHoteEstAcceptee(): void
    {
        $this->assertSame(
            '/profil.php',
            urlInterneOuDefaut('https://studentlink.example/profil.php')
        );
    }

    public function testUnHoteEtrangerEstRefuse(): void
    {
        $this->assertSame('/', urlInterneOuDefaut('https://evil.example/piege'));
    }

    public function testUnHoteQuiCommencePareilEstRefuse(): void
    {
        // « studentlink.example.evil.tld » contient le nom du site : une
        // comparaison par préfixe ou par str_contains() l'aurait laissé passer.
        $this->assertSame('/', urlInterneOuDefaut('https://studentlink.example.evil.tld/x'));
    }

    public function testUnPortDifferentEstRefuse(): void
    {
        $this->assertSame('/', urlInterneOuDefaut('https://studentlink.example:8443/x'));
    }

    public function testUneUrlProtocoleRelatifEstRefusee(): void
    {
        // « //evil.example/x » n'a pas de schéma mais change bien de domaine.
        $this->assertSame('/', urlInterneOuDefaut('//evil.example/x'));
        $this->assertSame('/', urlInterneOuDefaut('/\\evil.example/x'));
    }

    public function testUnCheminRelatifEstRefuse(): void
    {
        // Il s'interpréterait depuis l'URL courante, pas depuis celle qu'on
        // croit reconstruire : on ne devine pas.
        $this->assertSame('/', urlInterneOuDefaut('explore.php'));
    }

    public function testLAbsenceDeRefererRetombeSurLeDefaut(): void
    {
        $this->assertSame('/TitreRNCP/', urlInterneOuDefaut(null, '/TitreRNCP/'));
        $this->assertSame('/TitreRNCP/', urlInterneOuDefaut('', '/TitreRNCP/'));
    }

    // ── origineFiable ───────────────────────────────────────────────────────

    public function testUneOrigineIdentiqueEstAcceptee(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://studentlink.example';
        $this->assertTrue(origineFiable());
    }

    public function testUneOrigineEtrangereEstRefusee(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.example';
        $this->assertFalse(origineFiable());
    }

    public function testLOrigineLEmporteSurLeReferer(): void
    {
        // Origin est le signal fiable ; Referer ne sert que de repli.
        $_SERVER['HTTP_ORIGIN']  = 'https://evil.example';
        $_SERVER['HTTP_REFERER'] = 'https://studentlink.example/explore.php';
        $this->assertFalse(origineFiable());
    }

    public function testLeRefererSertDeRepliQuandOriginManque(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://studentlink.example/explore.php';
        $this->assertTrue(origineFiable());

        $_SERVER['HTTP_REFERER'] = 'https://evil.example/piege';
        $this->assertFalse(origineFiable());
    }

    public function testSansAucunEnTeteOnAccepte(): void
    {
        // Quelques proxys d'entreprise retirent les deux. Refuser rendrait
        // l'application inutilisable derrière eux pour un gain nul : le jeton
        // reste exigé dans tous les cas, et lui ne peut pas être deviné.
        $this->assertTrue(origineFiable());
    }

    public function testUneOrigineIllisibleEstRefusee(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'null';
        $this->assertFalse(origineFiable());
    }
}
