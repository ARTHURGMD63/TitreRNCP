<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Le contrat de la page d'accueil publique.
 *
 * Comme pour ApiProtectionTest, ces vérifications relisent la source plutôt
 * que d'appeler la page : en intégration continue il n'y a ni serveur web ni
 * base MySQL, et ce qu'il faut empêcher est justement une modification future.
 *
 * Le mode de panne visé est précis. La bascule entre les deux publics repose
 * sur une correspondance de noms : un onglet porte `data-pour="x"`, le bloc
 * qu'il affiche porte `data-public="x"`. Le jour où l'un des deux est renommé
 * sans l'autre, le script masque les deux blocs et le visiteur reçoit une page
 * vide — sans la moindre erreur PHP pour le signaler.
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

    /** @return list<string> */
    private static function valeurs(string $source, string $attribut): array
    {
        preg_match_all('/' . preg_quote($attribut, '/') . '="([a-z]+)"/', $source, $m);

        return array_values(array_unique($m[1]));
    }

    public function testChaqueOngletAfficheUnBlocExistant(): void
    {
        $source = self::source('index.php');

        $publics = self::valeurs($source, 'data-public');
        $onglets = self::valeurs($source, 'data-pour');

        self::assertNotEmpty($publics, 'Aucun bloc de public dans la page.');
        foreach ($onglets as $onglet) {
            self::assertContains(
                $onglet,
                $publics,
                "L'onglet « $onglet » ne correspond à aucun bloc data-public : la bascule "
                . 'masquerait les deux publics et la page s\'afficherait vide.'
            );
        }
    }

    public function testLesDeuxPublicsSontPresents(): void
    {
        $publics = self::valeurs(self::source('index.php'), 'data-public');

        self::assertEqualsCanonicalizing(['etudiants', 'etablissements'], $publics);
    }

    /**
     * Un public inconnu ne doit pas produire une page sans contenu : la valeur
     * de l'URL est ramenée à l'un des deux blocs, jamais reprise telle quelle.
     */
    public function testLePublicDeLUrlEstRameneAUneValeurConnue(): void
    {
        $source = self::source('index.php');

        self::assertStringContainsString(
            "\$pour = (\$_GET['pour'] ?? '') === 'etablissements' ? 'etablissements' : 'etudiants';",
            $source
        );
    }

    /**
     * Les appels à l'action côté établissements doivent ouvrir l'onglet
     * partenaire du formulaire : sans le paramètre, un gérant de bar atterrit
     * sur le formulaire étudiant et renseigne son école.
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
