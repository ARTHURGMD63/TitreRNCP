<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/textes_legaux.php';

/**
 * Les textes légaux sont servis à l'application et aux pages du site depuis
 * une seule source. Ces tests gardent la forme que les deux savent lire.
 */
final class LegalTest extends TestCase
{
    public function testLesTroisDocumentsExistent(): void
    {
        $this->assertSame(['mentions-legales', 'cgu', 'confidentialite'], array_keys(textesLegaux()));
    }

    public function testChaqueDocumentAUnTitreUneDateEtDesSections(): void
    {
        foreach (textesLegaux() as $slug => $doc) {
            $this->assertNotSame('', $doc['titre'], $slug);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $doc['maj'], "$slug : date AAAA-MM-JJ");
            $this->assertNotEmpty($doc['sections'], $slug);

            foreach ($doc['sections'] as $section) {
                $this->assertNotEmpty($section['blocs'], "$slug : section vide");
                foreach ($section['blocs'] as $bloc) {
                    $this->assertContains($bloc['type'], ['p', 'liste'], "$slug : type de bloc inconnu");
                    if ($bloc['type'] === 'liste') {
                        $this->assertNotEmpty($bloc['items'], "$slug : liste vide");
                    } else {
                        $this->assertNotSame('', trim($bloc['texte']), "$slug : paragraphe vide");
                    }
                }
            }
        }
    }

    /** Ni l'application ni le site n'ont à interpréter du HTML : seul **gras** est balisé. */
    public function testAucunHtmlDansLesTextes(): void
    {
        foreach (textesLegaux() as $slug => $doc) {
            $textes = [$doc['titre'], $doc['pied'] ?? ''];
            foreach ($doc['sections'] as $section) {
                $textes[] = $section['titre'] ?? '';
                foreach ($section['blocs'] as $bloc) {
                    $textes = array_merge($textes, $bloc['type'] === 'liste' ? $bloc['items'] : [$bloc['texte']]);
                }
            }
            foreach ($textes as $texte) {
                $this->assertSame($texte, strip_tags($texte), "$slug contient du HTML : $texte");
                $this->assertSame(0, substr_count($texte, '**') % 2, "$slug : ** non fermé dans « $texte »");
            }
        }
    }

    public function testLeGrasEstLaSeuleBaliseProduite(): void
    {
        $this->assertSame('<strong>Identité :</strong> nom', legalInlineHtml('**Identité :** nom'));
    }

    public function testRienDeCeQuiEstEcritNePeutInjecterDuHtml(): void
    {
        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', legalInlineHtml('<script>alert(1)</script>'));
        // nl2br() garde le saut de ligne après <br> : inoffensif en HTML.
        $this->assertSame("a<br>\nb", legalInlineHtml("a\nb"));
    }

    public function testLaDateSAfficheALaFrancaise(): void
    {
        $this->assertSame('14/10/2026', legalDateFr('2026-10-14'));
    }

    public function testLeCorpsHtmlRendSectionsListesEtDate(): void
    {
        $html = legalCorpsHtml(textesLegaux()['cgu']);

        $this->assertStringContainsString('<h2>Article 3 — Utilisation de la plateforme</h2>', $html);
        $this->assertStringContainsString('<li>Usurper l\'identité d\'une autre personne</li>', str_replace('&#039;', "'", $html));
        $this->assertStringContainsString('Dernière mise à jour : 14/10/2026', $html);
    }

    /**
     * La politique promet la suppression depuis l'application : tant que le
     * texte l'annonce, le chemin décrit doit exister dans l'écran « Moi ».
     */
    public function testLaPolitiqueDecritLaSuppressionDeCompte(): void
    {
        $texte = json_encode(textesLegaux()['confidentialite'], JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('Supprimer mon compte', (string) $texte);
        $this->assertStringContainsString('Supprimer mon compte', (string) file_get_contents(
            dirname(__DIR__, 2) . '/mobile/src/app/(tabs)/moi.tsx'
        ));
    }
}
