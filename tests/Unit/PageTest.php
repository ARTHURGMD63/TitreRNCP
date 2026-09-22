<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * La coquille HTML commune.
 *
 * Ce test ne vérifie pas que les vingt-quatre pages converties sont correctes
 * — cela a été fait en conditions réelles, en comparant le HTML rendu de
 * trente-neuf écrans avant et après la conversion. Il vérifie que la
 * vingt-cinquième ne pourra pas rouvrir sa propre coquille en dur.
 *
 * C'est le seul garde-fou qui tienne : la duplication précédente n'avait
 * produit ni vingt-quatre copies identiques ni une divergence visible, mais
 * treize pages sans icône et sept pages installables sur vingt-quatre. Rien
 * de tout cela ne se voit en relisant un fichier — seulement en les
 * comparant tous, ce que personne ne fait.
 */
final class PageTest extends TestCase
{
    /** @return list<string> Les fichiers PHP du projet, hors dépendances. */
    private function fichiersPhp(): array
    {
        $racine = dirname(__DIR__, 2);
        $out    = [];

        foreach (['*.php', 'auth/*.php', 'partenaire/*.php', 'admin/*.php', 'includes/*.php', 'api/*.php'] as $motif) {
            foreach (glob($racine . '/' . $motif) ?: [] as $chemin) {
                $out[] = $chemin;
            }
        }

        return $out;
    }

    public function testAucunePageNeRouvreSaPropreCoquille(): void
    {
        $racine = dirname(__DIR__, 2);

        $exemptes = array_map('realpath', array_filter([
            // C'est lui qui écrit la coquille.
            $racine . '/includes/page.php',
            // Pages de secours : elles s'affichent quand la base est
            // injoignable ou qu'une erreur fatale est interceptée, c'est-à-dire
            // au moment précis où charger le gabarit — donc auth_check, donc la
            // session — serait le plus mauvais des paris. Elles tiennent en une
            // ligne et ne doivent dépendre de rien.
            $racine . '/includes/db.php',
            $racine . '/includes/log.php',
        ], 'file_exists'));

        $fautives = [];

        foreach ($this->fichiersPhp() as $chemin) {
            if (in_array(realpath($chemin), $exemptes, true)) {
                continue;
            }
            $source = (string) file_get_contents($chemin);
            if (stripos($source, '<!DOCTYPE') !== false) {
                $fautives[] = basename(dirname($chemin)) . '/' . basename($chemin);
            }
        }

        $this->assertSame(
            [],
            $fautives,
            "Ces fichiers réouvrent une coquille HTML en dur au lieu d'appeler "
            . "pageDebut() : " . implode(', ', $fautives) . ". Le <head> se "
            . "modifie à un seul endroit, sinon il diverge."
        );
    }

    public function testLaCoquilleEmetLeMinimumAttendu(): void
    {
        $html = $this->rendre('StudentLink — Test');

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<html lang="fr">', $html);
        $this->assertStringContainsString('<meta charset="UTF-8">', $html);
        $this->assertStringContainsString('<title>StudentLink — Test</title>', $html);
        $this->assertStringContainsString('name="viewport"', $html);
        $this->assertStringContainsString('name="csrf-token"', $html);
        $this->assertStringContainsString('assets/css/style.css', $html);
        $this->assertStringEndsWith("</head>\n<body>\n", $html);
    }

    public function testLIconeEstSurToutesLesPages(): void
    {
        // Elle manquait sur treize pages sur vingt-quatre, dont les quatre
        // pages d'authentification. Sans option pour la retirer : aucune
        // page n'a de raison de ne pas avoir d'icône.
        $this->assertStringContainsString('rel="icon"', $this->rendre('Sans options'));
        $this->assertStringContainsString('rel="icon"', $this->rendre('Avec PWA', ['pwa' => true]));
    }

    public function testLesMetasDInstallationNeSortentQueSurDemande(): void
    {
        $sans = $this->rendre('Espace partenaire');
        $avec = $this->rendre('Espace étudiant', ['pwa' => true]);

        // Le manifeste déclare « start_url: explore.php » : le poser sur une
        // page partenaire enverrait le partenaire sur le hub étudiant.
        $this->assertStringNotContainsString('rel="manifest"', $sans);
        $this->assertStringNotContainsString('apple-mobile-web-app', $sans);

        $this->assertStringContainsString('rel="manifest"', $avec);
        $this->assertStringContainsString('apple-touch-icon', $avec);
        $this->assertStringContainsString('apple-mobile-web-app-title', $avec);
    }

    public function testLeTitreEstEchappe(): void
    {
        // view_event.php et view_profile.php passent un titre venu de la base :
        // le nom d'un événement ou le prénom d'un compte. Les deux sont saisis
        // par un utilisateur.
        $html = $this->rendre('Soirée <script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testLaDescriptionNeSortQueSiElleExiste(): void
    {
        $this->assertStringNotContainsString('name="description"', $this->rendre('Écran connecté'));
        $this->assertStringContainsString(
            'content="Sortir à Clermont"',
            $this->rendre('Accueil', ['description' => 'Sortir à Clermont'])
        );
    }

    public function testLaDescriptionNePeutPasRefermerSonAttribut(): void
    {
        $html = $this->rendre('Accueil', ['description' => 'Fin"><script>alert(1)</script>']);

        // Chercher « "><script> » dans la page entière ne prouverait rien :
        // themeBootScript() produit cette séquence légitimement, en fermant sa
        // méta juste avant son script. C'est la méta description elle-même
        // qu'il faut isoler.
        preg_match('/<meta name="description"[^\n]*>/', $html, $m);

        $this->assertNotEmpty($m, 'la méta description devrait être présente');
        $this->assertStringNotContainsString('<script>', $m[0]);
        $this->assertStringContainsString('&quot;', $m[0]);
    }

    public function testLesScriptsDeTeteSortentDansLOrdreDonne(): void
    {
        // partenaire/dashboard.php charge Chart.js puis html5-qrcode avant le
        // corps : les deux sont utilisés par du script inline plus bas.
        $html = $this->rendre('Tableau de bord', [
            'scripts' => ['/assets/vendor/chart.umd.min.js', '/assets/vendor/html5-qrcode.min.js'],
        ]);

        $chart = strpos($html, 'chart.umd.min.js');
        $qr    = strpos($html, 'html5-qrcode.min.js');

        $this->assertIsInt($chart);
        $this->assertIsInt($qr);
        $this->assertLessThan($qr, $chart);
    }

    /**
     * Rend une coquille en mémoire.
     *
     * @param array<string,mixed> $options
     */
    private function rendre(string $titre, array $options = []): string
    {
        if (!function_exists('pageDebut')) {
            require_once __DIR__ . '/../../includes/page.php';
        }

        ob_start();
        pageDebut($titre, $options);

        return (string) ob_get_clean();
    }
}
