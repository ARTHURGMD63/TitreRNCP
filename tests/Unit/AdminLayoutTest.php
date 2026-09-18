<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Helpers de rendu du back-office.
 *
 * Trois règles qui se cassent silencieusement : l'accord du pluriel, le
 * format monétaire français, et l'échappement des pastilles de statut.
 */
final class AdminLayoutTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('pluriel')) {
            require_once __DIR__ . '/../../includes/admin_layout.php';
        }
    }

    public function testPluriel(): void
    {
        $this->assertSame('client',  pluriel(0, 'client'));
        $this->assertSame('client',  pluriel(1, 'client'));
        $this->assertSame('clients', pluriel(2, 'client'));
    }

    public function testPlurielAccepteUnAutreSuffixe(): void
    {
        $this->assertSame('journaux', pluriel(3, 'journ', 'aux'));
    }

    public function testMontantEnEurosAuFormatFrancais(): void
    {
        // Séparateur de milliers : espace fine insécable ; avant le symbole :
        // espace insécable, pour que « 1 049 € » ne se coupe pas en fin de ligne.
        $this->assertSame("1\u{202F}049\u{00A0}€", eur(1049.0));
        $this->assertSame("79\u{00A0}€", eur(79.0));
        $this->assertSame("79,50\u{00A0}€", eur(79.5, 2));
    }

    public function testMontantNegatif(): void
    {
        $this->assertSame("-180\u{00A0}€", eur(-180.0));
    }

    public function testLaPastilleEchappeSonTexte(): void
    {
        $html = adminPastille('Bar <script>', 'var(--succes)');
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testLaPastilleRefuseUneCouleurQuiNEstPasUnJeton(): void
    {
        // Sans cette borne, un appel futur passant une chaîne construite
        // pourrait refermer l'attribut style et injecter du balisage.
        $html = adminPastille('Actif', 'red;"><img src=x onerror=alert(1)>');
        $this->assertStringContainsString('color:var(--gris);', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    public function testLaPastilleAccepteUnJetonDeLaCharte(): void
    {
        $this->assertStringContainsString('color:var(--sur-bleu-clair);', adminPastille('Contacté', 'var(--sur-bleu-clair)'));
    }

    public function testToutesLesCouleursDuVocabulaireSontDesJetons(): void
    {
        // Le vocabulaire alimente directement adminPastille() : si un statut
        // arrivait avec autre chose qu'un jeton, il perdrait sa couleur sans
        // que personne ne s'en aperçoive.
        foreach (crmStatuts() as $code => $def) {
            $this->assertMatchesRegularExpression(
                '/^var\(--[a-z0-9-]+\)$/',
                $def['couleur'],
                "La couleur du statut « $code » n'est pas un jeton de la charte."
            );
        }
    }
}
