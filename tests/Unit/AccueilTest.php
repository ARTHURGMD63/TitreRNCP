<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Le contrat de la page d'accueil publique — le compte à rebours.
 *
 * Comme pour ApiProtectionTest, ces vérifications relisent la source plutôt
 * que d'appeler la page : en intégration continue il n'y a ni serveur web ni
 * base MySQL, et ce qu'il faut empêcher est justement une modification future
 * qui romprait le contrat sans que rien ne le signale.
 *
 * Avant le 2026-09-24, cette page basculait entre deux publics (étudiants /
 * établissements) avec un sélecteur segmenté. Depuis, elle n'a plus qu'un
 * rôle : annoncer le lancement et bloquer l'accès direct à l'application
 * (voir includes/auth_check.php, verifierGateBientotDisponible()). Les
 * anciens tests de bascule ont disparu avec la page qu'ils vérifiaient.
 */
final class AccueilTest extends TestCase
{
    private static function source(string $fichier): string
    {
        $chemin = dirname(__DIR__, 2) . '/' . $fichier;
        $contenu = file_get_contents($chemin);
        self::assertIsString($contenu, "Fichier illisible : $fichier");

        return $contenu;
    }

    /**
     * Le compte à rebours affiche une date, pas un texte figé recopié à la
     * main : sans data-lancement, le script ne peut plus calculer que « 42
     * jours » n'importe quand.
     */
    public function testLeCompteAReboursPorteUneDateCalculee(): void
    {
        $source = self::source('index.php');

        self::assertStringContainsString('data-lancement="', $source);
        self::assertStringContainsString(
            "reglage('APP_LAUNCH_DATE', 'lancement_date'",
            $source,
            'La date de lancement doit rester réglable sans modifier le code (variable ' .
            "d'environnement), sur le même principe que includes/config.php."
        );
    }

    /**
     * Le compteur « Pass Fondateur » doit venir d'une vraie requête, jamais
     * d'un chiffre écrit en dur : un compte à rebours marketing qui ment sur
     * ses propres chiffres n'inspire pas confiance à des étudiants qui, eux,
     * vont vérifier.
     */
    public function testLeCompteurFondateurLitLaVraieTable(): void
    {
        $source = self::source('index.php');

        self::assertStringContainsString('FROM liste_attente', $source);
        self::assertStringContainsString(
            'catch (Throwable $e)',
            $source,
            'La page doit rester affichable même si la base est injoignable : ' .
            "c'est désormais la porte d'entrée entière du site."
        );
    }

    /**
     * Le formulaire d'inscription poste vers le point d'API dédié, protégé
     * par jeton CSRF comme tout point d'écriture (voir ApiProtectionTest).
     */
    public function testLeFormulaireEnvoieVersLApiDedie(): void
    {
        $source = self::source('index.php');

        self::assertStringContainsString('/api/liste_attente.php', $source);
        self::assertStringContainsString('enTetesJson()', $source);
    }

    /**
     * Les liens légaux doivent rester accessibles : ce sont deux des
     * quelques pages que le mode « bientôt disponible » laisse passer (voir
     * includes/auth_check.php).
     */
    public function testLesLiensLegauxSontPresents(): void
    {
        $source = self::source('index.php');

        self::assertStringContainsString('/mentions-legales.php', $source);
        self::assertStringContainsString('/confidentialite.php', $source);
    }

    /**
     * Les appels à l'action côté établissements doivent ouvrir l'onglet
     * partenaire du formulaire : sans le paramètre, un gérant de bar atterrit
     * sur le formulaire étudiant et renseigne son école.
     *
     * auth/register.php lui-même reste inchangé par le mode « bientôt
     * disponible » : il n'est simplement plus lié depuis la page d'accueil
     * tant que le mode est actif, verifierGateBientotDisponible() le bloque
     * comme le reste de l'application.
     */
    public function testLeFormulaireAccepteLOngletDemandeParLUrl(): void
    {
        $inscription = self::source('auth/register.php');

        self::assertStringContainsString("\$_GET['type']", $inscription);
        // Et la valeur reçue est revalidée : l'URL choisit un onglet, pas un
        // type de compte.
        self::assertStringContainsString(
            "in_array(\$selectedType, ['etudiant', 'partenaire'], true)",
            $inscription
        );
    }
}
