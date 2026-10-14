<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Chaque page porte-t-elle sa garde d'accès ?
 *
 * Les points d'API ont leur contrat dans ApiProtectionTest. Les pages HTML
 * n'en avaient pas : chacune appelle sa propre garde (`requireStudent()`,
 * `requirePartner()`…), et oublier cet appel sur une nouvelle page la
 * publie sans qu'aucun test ne bouge. Le test parcourt les dossiers, comme
 * celui des API : il couvre la page de demain, pas seulement celles d'hier.
 */
final class PagesProtegeesTest extends TestCase
{
    /** Pages ouvertes à tous, par choix : vitrine, textes légaux, authentification. */
    private const PUBLIQUES = [
        'cgu.php',
        'confidentialite.php',
        'index.php',
        'mentions-legales.php',
        'auth/forgot.php',
        'auth/login.php',
        'auth/logout.php',
        'auth/register.php',
        'auth/reset.php',
    ];

    /** Points d'API de l'espace partenaire : ils contrôlent la session eux-mêmes (voir ApiProtectionTest). */
    private const API_PARTENAIRE = ['partenaire/api_invites.php', 'partenaire/api_scan.php'];

    private string $racine;

    protected function setUp(): void
    {
        $this->racine = dirname(__DIR__, 2);
    }

    /** @return list<string> chemins relatifs à la racine */
    private function pages(string $motif): array
    {
        $pages = array_map(
            fn (string $p): string => ltrim(substr(strtr($p, '\\', '/'), strlen(strtr($this->racine, '\\', '/'))), '/'),
            glob($this->racine . '/' . $motif) ?: []
        );
        sort($pages);

        return $pages;
    }

    /**
     * Le code de la page, commentaires retirés par le lexeur de PHP : un
     * appel de garde mis en commentaire ne la protège plus, et une simple
     * recherche de texte ne le verrait pas.
     */
    private function contenu(string $page): string
    {
        $code = '';
        foreach (token_get_all((string) file_get_contents($this->racine . '/' . $page)) as $jeton) {
            if (is_array($jeton)) {
                if ($jeton[0] === T_COMMENT || $jeton[0] === T_DOC_COMMENT) {
                    continue;
                }
                $code .= $jeton[1];
            } else {
                $code .= $jeton;
            }
        }

        return $code;
    }

    /** Le fichier tel quel, commentaires compris. */
    private function brut(string $page): string
    {
        return (string) file_get_contents($this->racine . '/' . $page);
    }

    public function testLesPagesDeLAdministrationExigentUnAdmin(): void
    {
        $pages = $this->pages('admin/*.php');
        $this->assertNotEmpty($pages);

        $sansGarde = array_values(array_filter(
            $pages,
            fn (string $p): bool => !str_contains($this->contenu($p), 'requireAdmin(')
        ));

        $this->assertSame([], $sansGarde, 'Pages admin sans requireAdmin() : ' . implode(', ', $sansGarde));
    }

    public function testLesPagesPartenaireExigentUnPartenaire(): void
    {
        $pages = array_diff($this->pages('partenaire/*.php'), self::API_PARTENAIRE);
        $this->assertNotEmpty($pages);

        $sansGarde = array_values(array_filter(
            $pages,
            fn (string $p): bool => !str_contains($this->contenu($p), 'requirePartner(')
        ));

        $this->assertSame([], $sansGarde, 'Pages partenaire sans requirePartner() : ' . implode(', ', $sansGarde));
    }

    public function testLesPagesDeLaRacineSontPubliquesOuGardees(): void
    {
        // __shot.php a sa propre porte, testée plus bas.
        $pages = array_diff($this->pages('*.php'), self::PUBLIQUES, ['__shot.php']);
        $this->assertNotEmpty($pages);

        $sansGarde = array_values(array_filter(
            $pages,
            fn (string $p): bool => !preg_match('/\brequire(?:Student|Partner|Admin|Login)\(/', $this->contenu($p))
        ));

        $this->assertSame(
            [],
            $sansGarde,
            'Pages ni listées comme publiques ni protégées par une garde : ' . implode(', ', $sansGarde)
            . '. Ajouter la garde, ou la page à PUBLIQUES si elle doit vraiment être ouverte.'
        );
    }

    public function testLesPagesPubliquesListeesExistentToujours(): void
    {
        // Une entrée qui ne désigne plus rien est une exception oubliée.
        foreach (self::PUBLIQUES as $page) {
            $this->assertFileExists($this->racine . '/' . $page);
        }
    }

    public function testLOutilDeCapturesNEstOuvertQuEnLocal(): void
    {
        $code = $this->brut('__shot.php');

        foreach (['REMOTE_ADDR', 'APP_ENV', '.shot-enabled'] as $condition) {
            $this->assertStringContainsString(
                $condition,
                $code,
                "__shot.php contourne l'authentification : la condition « $condition » ne doit pas disparaître."
            );
        }
    }
}
