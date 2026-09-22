<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Le hub étudiant : lecture des critères d'URL et liens de filtre.
 *
 * Ce que ces tests couvrent est exactement ce qui n'était pas testable avant
 * le découpage : explore.php lisait $_GET et construisait ses requêtes dans le
 * même fichier que son HTML, donc le charger affichait une page. Les cas
 * tordus — page négative, style de musique inventé, vue inconnue — ne se
 * vérifiaient qu'à la main, dans un navigateur.
 */
final class HubTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('hubCriteres')) {
            require_once __DIR__ . '/../../includes/hub.php';
        }
    }

    public function testUneUrlVideDonneLeFilDesSoirees(): void
    {
        $c = hubCriteres([]);

        $this->assertSame('events', $c['vue']);
        $this->assertSame('all', $c['type']);
        $this->assertSame('', $c['musique']);
        $this->assertSame(1, $c['page_profils']);
        $this->assertSame(1, $c['page_evenements']);
    }

    public function testUneVueInconnueRetombeSurLesSoirees(): void
    {
        // Avant, « ?view=bidon » rendait une page sans aucune des deux listes :
        // ni annuaire ni soirées, juste la coquille. Un lien périmé donnait
        // donc un écran vide qu'on prenait pour une panne.
        $this->assertSame('events', hubCriteres(['view' => 'bidon'])['vue']);
        $this->assertSame('people', hubCriteres(['view' => 'people'])['vue']);
    }

    public function testUnStyleDeMusiqueInventeNeFiltreRien(): void
    {
        // Filtrer sur un style inexistant rendrait « aucune soirée ce soir »,
        // ce qui se lit comme une information alors que c'est une erreur d'URL.
        $this->assertSame('', hubCriteres(['musique' => 'nawak'])['musique']);
    }

    public function testUnStyleValideEstConserve(): void
    {
        require_once __DIR__ . '/../../includes/musique.php';
        $styles = array_keys(stylesMusique());
        $this->assertNotEmpty($styles, 'Le catalogue de styles est vide.');

        $this->assertSame($styles[0], hubCriteres(['musique' => $styles[0]])['musique']);
    }

    public function testUnePageNegativeOuAbsurdeRevientAUn(): void
    {
        // Un LIMIT négatif est une erreur SQL, pas une page vide.
        $this->assertSame(1, hubCriteres(['p' => '-5'])['page_profils']);
        $this->assertSame(1, hubCriteres(['p' => '0'])['page_profils']);
        $this->assertSame(1, hubCriteres(['p' => 'abc'])['page_profils']);
        $this->assertSame(1, hubCriteres(['pe' => '-1'])['page_evenements']);
        $this->assertSame(3, hubCriteres(['p' => '3'])['page_profils']);
    }

    public function testLesChampsTexteSontDebarrassesDeLeursBlancs(): void
    {
        $c = hubCriteres(['q' => '  lea  ', 'ecole' => " UCA\t"]);

        $this->assertSame('lea', $c['q']);
        $this->assertSame('UCA', $c['ecole']);
    }

    public function testUnParametreNonTextuelNeCassePas(): void
    {
        // « ?q[]=x » fait arriver un tableau là où le code attend une chaîne.
        $c = hubCriteres(['q' => ['x'], 'ecole' => ['y'], 'musique' => ['z'], 'type' => ['w']]);

        $this->assertSame('', $c['q']);
        $this->assertSame('', $c['ecole']);
        $this->assertSame('', $c['musique']);
        $this->assertSame('all', $c['type']);
    }

    public function testLeFiltreCommeMoiEstReconnu(): void
    {
        $c = hubCriteres(['interest' => FILTRE_MES_INTERETS]);

        $this->assertTrue($c['comme_moi']);
        // La valeur réservée est conservée : « un filtre est actif » est ce qui
        // masque les suggestions d'abonnement, et « comme moi » en est un.
        $this->assertSame(FILTRE_MES_INTERETS, $c['interet']);
    }

    public function testUnInteretOrdinaireNestPasCommeMoi(): void
    {
        $c = hubCriteres(['interest' => 'techno']);

        $this->assertFalse($c['comme_moi']);
        $this->assertSame('techno', $c['interet']);
    }

    // ── Liens de filtre ─────────────────────────────────────────────────────

    public function testChangerDeStyleGardeLeTypeDeLieu(): void
    {
        // Les pilules écrivaient leur URL en dur : choisir un style effaçait le
        // type, et inversement. « Les boîtes techno » était impossible à
        // demander en deux clics, le second annulant le premier.
        $lien = hubLienFiltre('boite', '', ['musique' => 'techno']);

        $this->assertStringContainsString('type=boite', $lien);
        $this->assertStringContainsString('musique=techno', $lien);
    }

    public function testChangerDeTypeGardeLeStyle(): void
    {
        $lien = hubLienFiltre('bar', 'techno', ['type' => 'boite']);

        $this->assertStringContainsString('type=boite', $lien);
        $this->assertStringContainsString('musique=techno', $lien);
    }

    public function testUnFiltreVideDisparaitDeLUrl(): void
    {
        $lien = hubLienFiltre('bar', '', []);

        $this->assertStringNotContainsString('musique=', $lien);
        $this->assertStringContainsString('view=events', $lien);
    }

    public function testLeLienEstEchappePourLHtml(): void
    {
        // Le lien part directement dans un attribut href.
        $lien = hubLienFiltre('bar', '', ['q' => 'a&b"c']);

        $this->assertStringNotContainsString('"', $lien);
        $this->assertStringContainsString('&amp;', $lien);
    }
}
