<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Le contrat du site refait (2026-11) : la vitrine, la connexion, l'espace
 * établissement, et la règle « un étudiant va sur l'application ».
 *
 * Comme pour ApiProtectionTest, ces vérifications relisent la source : en
 * intégration continue il n'y a ni serveur web ni base MySQL, et ce qu'il
 * faut empêcher est une modification future qui romprait le contrat sans
 * que rien ne le signale.
 *
 * La page d'attente du lancement (compte à rebours, liste_attente) a laissé
 * place à la vitrine : ses anciens tests ont disparu avec elle.
 */
final class AccueilTest extends TestCase
{
    private static function source(string $fichier): string
    {
        $contenu = file_get_contents(dirname(__DIR__, 2) . '/' . $fichier);
        self::assertIsString($contenu, "Fichier illisible : $fichier");

        return $contenu;
    }

    /**
     * Les prix affichés aux établissements viennent de la grille facturée
     * (crmOffres, grilleCapacites), jamais d'un chiffre recopié : une vitrine
     * qui annonce un autre prix que la facture est une promesse intenable.
     */
    public function testLesTarifsDeLaVitrineViennentDeLaGrille(): void
    {
        $source = self::source('index.php');

        self::assertStringContainsString('crmOffres()', $source);
        self::assertStringContainsString('grilleCapacites()', $source);
        self::assertDoesNotMatchRegularExpression('/\b(49,99|69,99)\b/', $source, 'Prix écrit en dur dans la vitrine.');
    }

    /** Les liens des stores sont réglables sans toucher au code. */
    public function testLesLiensDesStoresSontReglables(): void
    {
        $site = self::source('includes/site.php');

        self::assertStringContainsString("reglage('APP_STORE_URL'", $site);
        self::assertStringContainsString("reglage('PLAY_STORE_URL'", $site);
    }

    /** Les pages légales restent atteignables depuis le pied de page. */
    public function testLesLiensLegauxSontPresents(): void
    {
        $site = self::source('includes/site.php');

        self::assertStringContainsString('/mentions-legales.php', $site);
        self::assertStringContainsString('/confidentialite.php', $site);
        self::assertStringContainsString('/cgu.php', $site);
        self::assertStringContainsString('piedSite()', self::source('index.php'));
    }

    /**
     * Un compte étudiant n'ouvre pas de session web : la connexion le
     * renvoie vers l'application, APRÈS la vérification du mot de passe (le
     * dire avant révélerait quelles adresses existent).
     */
    public function testUnEtudiantEstRenvoyeVersLApplication(): void
    {
        $login = self::source('auth/login.php');

        $verif   = strpos($login, 'password_verify(');
        $renvoi  = strpos($login, "/application.php?compte=etudiant");
        $session = strpos($login, 'session_regenerate_id(true)');

        self::assertNotFalse($verif);
        self::assertNotFalse($renvoi);
        self::assertNotFalse($session);
        self::assertLessThan($renvoi, $verif, 'Le renvoi doit suivre la vérification du mot de passe.');
        self::assertLessThan($session, $renvoi, 'Aucune session ne doit être ouverte pour un étudiant.');

        self::assertStringContainsString("baseUrl('/application.php')", self::source('includes/auth_check.php'));
    }

    /** Le site n'inscrit plus que des établissements. */
    public function testLInscriptionWebNeCreeQueDesEtablissements(): void
    {
        $inscription = self::source('auth/register.php');

        self::assertStringContainsString("'partenaire')", $inscription);
        self::assertStringNotContainsString("'etudiant'", $inscription);
        self::assertStringContainsString('verificationInitiale(', $inscription, 'Même vérification initiale que api/v1/inscription.php.');
    }

    /** La garde de l'espace pro commence par exiger un compte partenaire. */
    public function testLEspaceProExigeUnPartenaire(): void
    {
        $site = self::source('includes/site.php');
        $debut = strpos($site, 'function ouvrirEspacePro(');
        self::assertNotFalse($debut);

        $corps = substr($site, $debut, 400);
        self::assertMatchesRegularExpression('/\{\s*requirePartner\(\);/', $corps);
    }
}
