<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Le chemin sous lequel l'application est servie.
 *
 * Tout lien, toute feuille de style, tout appel d'API en dépend : s'il est
 * faux, la page arrive nue et plus rien ne fonctionne. C'est la fonction la
 * plus transversale du projet, et elle n'avait aucun test.
 *
 * Elle déduisait le préfixe du nom d'hôte — « localhost » voulait dire
 * sous-dossier, tout le reste racine. La règle tenait tant que le poste de
 * développement ne se visitait que depuis lui-même, et tombait dès qu'on
 * ouvrait le site à un téléphone du même réseau.
 */
final class PrefixeTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('calculerPrefixe')) {
            require_once __DIR__ . '/../../includes/auth_check.php';
        }
    }

    public function testInstallationDansUnSousDossier(): void
    {
        $this->assertSame('/TitreRNCP', calculerPrefixe(
            '/TitreRNCP/explore.php',
            'C:/wamp64/www/TitreRNCP/explore.php',
            'C:/wamp64/www/TitreRNCP'
        ));
    }

    public function testDepuisUnePageEnSousDossier(): void
    {
        // Le cas qui piège une implémentation naïve à base de dirname() : la
        // page est deux niveaux plus bas, le préfixe reste le même.
        $this->assertSame('/TitreRNCP', calculerPrefixe(
            '/TitreRNCP/partenaire/dashboard.php',
            'C:/wamp64/www/TitreRNCP/partenaire/dashboard.php',
            'C:/wamp64/www/TitreRNCP'
        ));
    }

    public function testDepuisUnPointDApi(): void
    {
        $this->assertSame('/TitreRNCP', calculerPrefixe(
            '/TitreRNCP/api/live.php',
            'C:/wamp64/www/TitreRNCP/api/live.php',
            'C:/wamp64/www/TitreRNCP'
        ));
    }

    public function testInstallationALaRacine(): void
    {
        // Railway, Docker, et tout mutualisé où le projet EST la racine web.
        $this->assertSame('', calculerPrefixe(
            '/explore.php',
            '/var/www/html/explore.php',
            '/var/www/html'
        ));
    }

    public function testInstallationALaRacineDepuisUnSousDossier(): void
    {
        $this->assertSame('', calculerPrefixe(
            '/partenaire/dashboard.php',
            '/var/www/html/partenaire/dashboard.php',
            '/var/www/html'
        ));
    }

    public function testSousDossierImbrique(): void
    {
        // Un mutualisé où le projet est posé deux niveaux sous la racine.
        $this->assertSame('/clients/linkee', calculerPrefixe(
            '/clients/linkee/explore.php',
            '/home/u42/public_html/clients/linkee/explore.php',
            '/home/u42/public_html/clients/linkee'
        ));
    }

    public function testLeNomDuDossierNEstPasCableEnDur(): void
    {
        // Le préfixe était « /TitreRNCP » écrit en toutes lettres : renommer
        // le dossier cassait tout sans que rien ne l'indique.
        $this->assertSame('/AutreNom', calculerPrefixe(
            '/AutreNom/explore.php',
            'C:/wamp64/www/AutreNom/explore.php',
            'C:/wamp64/www/AutreNom'
        ));
    }

    public function testLesSeparateursWindowsSontAcceptes(): void
    {
        // SCRIPT_FILENAME arrive en antislashs sous Apache/Windows.
        $this->assertSame('/TitreRNCP', calculerPrefixe(
            '/TitreRNCP/explore.php',
            'C:\\wamp64\\www\\TitreRNCP\\explore.php',
            'C:\\wamp64\\www\\TitreRNCP'
        ));
    }

    public function testUneBarreFinaleSurLaRacineNeChangeRien(): void
    {
        $this->assertSame('/TitreRNCP', calculerPrefixe(
            '/TitreRNCP/explore.php',
            'C:/wamp64/www/TitreRNCP/explore.php',
            'C:/wamp64/www/TitreRNCP/'
        ));
    }

    public function testDesVariablesAbsentesDonnentLaRacine(): void
    {
        // En ligne de commande, SCRIPT_NAME et SCRIPT_FILENAME ne décrivent
        // aucune URL. Mieux vaut un préfixe vide qu'un préfixe inventé : le
        // premier ne casse que l'installation en sous-dossier, le second
        // casserait tous les liens partout.
        $this->assertSame('', calculerPrefixe('', '', ''));
        $this->assertSame('', calculerPrefixe('/explore.php', '', '/var/www'));
        $this->assertSame('', calculerPrefixe('', '/var/www/explore.php', '/var/www'));
    }

    public function testUnScriptHorsDuProjetNInventePasDePrefixe(): void
    {
        $this->assertSame('', calculerPrefixe(
            '/ailleurs/autre.php',
            '/var/www/ailleurs/autre.php',
            '/var/www/html'
        ));
    }

    public function testUnDossierVoisinAuNomProcheNEstPasConfondu(): void
    {
        // « /var/www/html2 » commence par « /var/www/html » : sans la barre
        // dans la comparaison, il passerait pour un sous-dossier du projet.
        $this->assertSame('', calculerPrefixe(
            '/autre/explore.php',
            '/var/www/html2/explore.php',
            '/var/www/html'
        ));
    }

    public function testLePrefixeNeSeTerminePasParUneBarre(): void
    {
        // baseUrl() concatène directement : « /TitreRNCP/ » + « /explore.php »
        // produirait une double barre dans chaque lien de l'application.
        foreach ([
            ['/TitreRNCP/explore.php', 'C:/w/TitreRNCP/explore.php', 'C:/w/TitreRNCP'],
            ['/explore.php', '/var/www/explore.php', '/var/www'],
        ] as [$url, $disque, $racine]) {
            $this->assertStringEndsNotWith('/', calculerPrefixe($url, $disque, $racine));
        }
    }

    public function testLeResultatSeConcateneProprementAvecUnChemin(): void
    {
        $prefixe = calculerPrefixe(
            '/TitreRNCP/explore.php',
            'C:/wamp64/www/TitreRNCP/explore.php',
            'C:/wamp64/www/TitreRNCP'
        );

        $this->assertSame('/TitreRNCP/assets/css/style.css', $prefixe . '/assets/css/style.css');
    }

    public function testLHoteNInterventPlusDansLeCalcul(): void
    {
        // Le cœur du correctif : le même script, servi au même endroit, donne
        // le même préfixe — que l'on arrive par localhost, par l'adresse IP de
        // la machine sur le réseau, ou par un nom de domaine. La fonction ne
        // reçoit d'ailleurs plus l'hôte du tout.
        $attendu = '/TitreRNCP';

        foreach (['/TitreRNCP/explore.php'] as $url) {
            $this->assertSame($attendu, calculerPrefixe(
                $url,
                'C:/wamp64/www/TitreRNCP/explore.php',
                'C:/wamp64/www/TitreRNCP'
            ));
        }

        $reflexion = new \ReflectionFunction('calculerPrefixe');
        $noms      = array_map(
            static fn (\ReflectionParameter $p): string => $p->getName(),
            $reflexion->getParameters()
        );

        $this->assertNotContains('host', $noms);
        $this->assertNotContains('hote', $noms);
    }
}
