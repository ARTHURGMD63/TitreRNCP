<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Le contrat de sécurité des points d'API.
 *
 * Pourquoi ces tests ressemblent à une relecture de code plutôt qu'à des
 * appels HTTP : ce qu'il faut empêcher, c'est qu'un point d'écriture soit
 * AJOUTÉ demain sans sa garde. Un test qui appelle les onze points existants
 * ne dit rien du douzième ; un test qui parcourt le dossier, si. Et il tourne
 * en intégration continue, où il n'y a ni serveur web ni base MySQL.
 *
 * Les onze points existants, eux, ont été vérifiés en conditions réelles :
 * sans jeton → 403, en GET → 405, avec jeton et bonne origine → 200, avec
 * jeton et origine étrangère → 403.
 */
final class ApiProtectionTest extends TestCase
{
    /** Points d'API en lecture seule : ils n'écrivent rien, pas de jeton exigé. */
    private const LECTURE_SEULE = [
        'live.php',          // interrogation courte, la plus appelée de l'app
        'social_feed.php',
        'squad_members.php',
        'stats.php',
        'liste_attente_statut.php', // rang d'une inscription, pour un visiteur qui revient
    ];

    /**
     * Points d'API délibérément anonymes : aucune identité à vérifier, parce
     * qu'il n'y a personne — pas encore de compte. Ils écrivent quand même
     * (donc restent soumis à protegerEcritureApi() et à la garde posée avant
     * tout accès à la base), mais requireLogin() n'a pas de sens pour un
     * visiteur qui n'a justement pas encore de compte.
     */
    private const ANONYME = [
        'liste_attente.php',        // formulaire de la page d'accueil « bientôt disponible »
        'liste_attente_statut.php', // lecture du même rang, par la même personne sans compte
    ];

    /** @return list<string> chemins absolus de tous les points d'API */
    private function pointsApi(): array
    {
        $racine = dirname(__DIR__, 2);

        return array_merge(
            glob($racine . '/api/*.php') ?: [],
            // Le scan de QR code vit côté partenaire mais répond en JSON et
            // écrit en base : c'est un point d'API comme les autres.
            [$racine . '/partenaire/api_scan.php']
        );
    }

    public function testChaquePointDEcritureExigeJetonEtOrigine(): void
    {
        $sansGarde = [];

        foreach ($this->pointsApi() as $chemin) {
            if (in_array(basename($chemin), self::LECTURE_SEULE, true)) {
                continue;
            }
            $source = (string) file_get_contents($chemin);
            if (!str_contains($source, 'protegerEcritureApi()')) {
                $sansGarde[] = basename($chemin);
            }
        }

        $this->assertSame(
            [],
            $sansGarde,
            "Ces points d'API écrivent sans vérifier le jeton CSRF ni l'origine. "
            . "Ajouter protegerEcritureApi() juste après l'en-tête Content-Type, "
            . "ou inscrire le fichier dans self::LECTURE_SEULE s'il ne fait que lire."
        );
    }

    public function testLaGardeEstPoseeAvantToutTravail(): void
    {
        // Une garde placée après la première requête SQL ne protège plus
        // grand-chose : au mieux elle empêche l'écriture finale, au pire elle
        // arrive après un effet de bord déjà produit.
        foreach ($this->pointsApi() as $chemin) {
            $source = (string) file_get_contents($chemin);
            $garde  = strpos($source, 'protegerEcritureApi()');
            if ($garde === false) {
                continue;
            }

            $premiereRequete = strpos($source, '$pdo->');
            if ($premiereRequete === false) {
                continue;
            }

            $this->assertLessThan(
                $premiereRequete,
                $garde,
                basename($chemin) . " touche la base avant d'avoir vérifié le jeton."
            );
        }
    }

    public function testLesPointsEnLectureSeuleNecriventPas(): void
    {
        // La liste d'exemption ci-dessus est une affirmation : ces fichiers ne
        // font que lire. Si l'un d'eux se met à écrire, l'exemption devient un
        // trou, et c'est ce test qui le dit.
        $racine = dirname(__DIR__, 2);

        foreach (self::LECTURE_SEULE as $nom) {
            $source = (string) file_get_contents($racine . '/api/' . $nom);

            // On regarde le SQL écrit dans le fichier, pas les appels de
            // fonctions : fluxToucher() écrit un compteur de révision, ce qui
            // est de la signalisation, pas une modification de données.
            $this->assertDoesNotMatchRegularExpression(
                '/\b(INSERT\s+INTO|UPDATE\s+\w|DELETE\s+FROM)\b/i',
                $source,
                "$nom est déclaré en lecture seule mais contient une écriture SQL. "
                . "Le retirer de self::LECTURE_SEULE et lui poser protegerEcritureApi()."
            );
        }
    }

    public function testAucunPointDApiNeFaitConfianceAuTypeDeCompteSeul(): void
    {
        // Chaque point vérifie qui parle avant d'agir. Sans cette ligne, un
        // compte étudiant atteindrait le scan de check-in partenaire.
        foreach ($this->pointsApi() as $chemin) {
            if (in_array(basename($chemin), self::ANONYME, true)) {
                continue;
            }
            $source = (string) file_get_contents($chemin);

            $this->assertMatchesRegularExpression(
                '/\$_SESSION\[.user_(id|type).\]|requireLogin\(\)|requirePartner\(\)|requireStudent\(\)|sessionLectureSeule\(\)/',
                $source,
                basename($chemin) . " ne vérifie l'identité de personne."
            );
        }
    }

    public function testToutAppelDEcritureDuJavaScriptPorteLeJeton(): void
    {
        // Le pendant côté client : un fetch POST qui oublie enTetesJson()
        // recevra un 403 en production, et le bouton restera bloqué. Autant
        // l'apprendre ici.
        $racine  = dirname(__DIR__, 2);
        $sources = [
            'assets/js/app.js'         => (string) file_get_contents($racine . '/assets/js/app.js'),
            'partenaire/dashboard.php' => (string) file_get_contents($racine . '/partenaire/dashboard.php'),
        ];

        foreach ($sources as $nom => $source) {
            preg_match_all("/method:\s*'POST'/", $source, $posts);
            preg_match_all('/headers:\s*enTetesJson\(\)/', $source, $entetes);

            $this->assertSame(
                count($posts[0]),
                count($entetes[0]),
                "$nom : " . count($posts[0]) . " appels POST mais "
                . count($entetes[0]) . " passages par enTetesJson(). "
                . "Un appel d'écriture sans jeton sera refusé par le serveur."
            );
        }
    }
}
