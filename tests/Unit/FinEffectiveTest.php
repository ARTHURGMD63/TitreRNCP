<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/evenements_temps.php';

/**
 * evenements.fin_effective (migration v41, DB-02) recopie en base la règle
 * de sqlFinEffectiveEvenement(). Le SQL de la migration et des fichiers
 * d'installation écrit la durée par défaut en dur : ces tests empêchent
 * qu'elle diverge de la constante PHP, et que le fil revienne à
 * l'expression non indexable.
 */
final class FinEffectiveTest extends TestCase
{
    private string $racine;

    protected function setUp(): void
    {
        $this->racine = dirname(__DIR__, 2);
    }

    public function testLeSqlDeRemplissageUtiliseLaDureeParDefautDuCode(): void
    {
        $attendu = 'COALESCE(date_fin, DATE_ADD(date_heure, INTERVAL ' . EVENEMENT_DUREE_PAR_DEFAUT_HEURES . ' HOUR))';

        $this->assertSame($attendu, sqlFinEffectiveEvenement('date_heure', 'date_fin'));
        foreach (['db_migrations_v41.sql', 'db_setup.sql', 'install_mutualise.sql'] as $fichier) {
            $sql = (string) file_get_contents($this->racine . '/' . $fichier);
            $this->assertStringContainsString($attendu, $sql, "$fichier ne remplit pas fin_effective avec la règle du code.");
        }
    }

    public function testLeFilFiltreSurLaColonneIndexee(): void
    {
        $hub = (string) file_get_contents($this->racine . '/includes/hub.php');

        $this->assertStringContainsString("\$finEff  = 'e.fin_effective';", $hub);
    }

    public function testChaqueEcritureDEvenementRecalculeLaFin(): void
    {
        // Tout fichier qui crée un événement, ou en réécrit la date, doit
        // reposer fin_effective : sinon l'événement sort du fil.
        $fautifs = [];
        $vus     = 0;
        foreach (['api', 'api/v1', 'includes', 'partenaire', 'admin', 'outils'] as $dossier) {
            foreach (glob($this->racine . '/' . $dossier . '/*.php') ?: [] as $chemin) {
                $code   = (string) file_get_contents($chemin);
                $ecrit  = preg_match('/INSERT INTO evenements\b/', $code)
                    || preg_match('/UPDATE evenements SET[^;]*date_heure/s', $code);
                if (!$ecrit) {
                    continue;
                }
                $vus++;
                if (!preg_match('/evenementMajFinEffective\(|evenementsMajFinEffectiveManquantes\(/', $code)) {
                    $fautifs[] = substr($chemin, strlen($this->racine) + 1);
                }
            }
        }

        $this->assertGreaterThanOrEqual(2, $vus, 'Aucune écriture d’événement trouvée : le test ne contrôle plus rien.');
        $this->assertSame([], $fautifs, 'Écrivent un événement sans recalculer fin_effective : ' . implode(', ', $fautifs));
    }
}
