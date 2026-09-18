<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Règles commerciales et financières du back-office, sans toucher la BDD.
 *
 * Ce sont les seuils des documents fondateurs — grille de SL-03, indicateurs
 * de SL-07, charges de SL-13 — et ce sont eux qu'il faut protéger : une
 * erreur de calcul ici se lit chaque lundi matin comme un fait.
 */
final class CrmTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('crmOffres')) {
            require_once __DIR__ . '/../../includes/crm.php';
        }
    }

    // ─── Vocabulaire ────────────────────────────────────────────────────────

    public function testLeVocabulaireCouvreTousLesStatutsDeLEnum(): void
    {
        // L'ENUM de crm_clients et le vocabulaire PHP doivent rester alignés :
        // un statut en base sans libellé s'afficherait en brut dans la liste.
        $this->assertSame(
            ['prospect', 'contacte', 'rdv', 'essai', 'actif', 'pause', 'perdu'],
            array_keys(crmStatuts())
        );
    }

    public function testLeVocabulaireCouvreToutesLesOffresDeLaGrille(): void
    {
        $this->assertSame(
            ['aucune', 'fondateur', 'essentiel', 'premium', 'bde'],
            array_keys(crmOffres())
        );
    }

    public function testLesTarifsSuiventLaGrilleDeSl03(): void
    {
        $offres = crmOffres();
        $this->assertSame(39, $offres['fondateur']['tarif']);
        $this->assertSame(79, $offres['essentiel']['tarif']);
        $this->assertSame(149, $offres['premium']['tarif']);
        // BDE et associations : gratuit, définitivement.
        $this->assertSame(0, $offres['bde']['tarif']);
    }

    public function testUnCodeInconnuSeRabatSurLuiMeme(): void
    {
        $this->assertSame('Client actif', crmLibelleStatut('actif'));
        $this->assertSame('inexistant', crmLibelleStatut('inexistant'));
        $this->assertSame('var(--gris)', crmCouleurStatut('inexistant'));
    }

    public function testChaqueCategorieFinanciereEstDansUnSeulSens(): void
    {
        $categories = financeCategories();
        $communes = array_intersect_key($categories['recette'], $categories['depense']);
        $this->assertSame([], $communes, 'Une catégorie ne peut pas être à la fois recette et dépense.');
    }

    public function testLeLibelleDeCategorieTrouveLesDeuxSens(): void
    {
        $this->assertSame('Abonnement établissement', financeLibelleCategorie('abonnement'));
        $this->assertSame('Comptabilité', financeLibelleCategorie('comptabilite'));
        $this->assertSame('inconnue', financeLibelleCategorie('inconnue'));
    }

    // ─── North Star (SL-07) ────────────────────────────────────────────────

    public function testLObjectifNorthStarSuitLeJalonFranchi(): void
    {
        // Les jalons : 30 en octobre 2026, 150 en décembre, 400 en mars 2027.
        $this->assertSame(30,  kpiObjectifCourant(strtotime('2026-10-15')));
        $this->assertSame(150, kpiObjectifCourant(strtotime('2026-12-01')));
        $this->assertSame(150, kpiObjectifCourant(strtotime('2027-02-28')));
        $this->assertSame(400, kpiObjectifCourant(strtotime('2027-03-01')));
        $this->assertSame(1000, kpiObjectifCourant(strtotime('2028-01-01')));
    }

    public function testAvantLePremierJalonLObjectifResteCeluiDuDepart(): void
    {
        // Sans cette garde, l'objectif valait zéro et la jauge divisait par lui.
        $this->assertSame(30, kpiObjectifCourant(strtotime('2026-09-01')));
    }

    // ─── Taux de présence ──────────────────────────────────────────────────

    public function testTauxDePresence(): void
    {
        $this->assertSame(70.0, tauxPresence(10, 7));
        $this->assertSame(100.0, tauxPresence(4, 4));
    }

    public function testSansInscritLeTauxDePresenceNExistePas(): void
    {
        // Pas « 0 % » : une soirée sans réservation n'a pas de taux du tout,
        // et l'afficher à zéro ferait chuter la moyenne pour rien.
        $this->assertNull(tauxPresence(0, 0));
    }

    // ─── Churn (SL-07) ─────────────────────────────────────────────────────

    public function testChurnMensuel(): void
    {
        $this->assertSame(10.0, crmCalculChurn(1, 10));
        $this->assertSame(0.0, crmCalculChurn(0, 8));
    }

    public function testSansClientEnDebutDeMoisLeChurnNExistePas(): void
    {
        $this->assertNull(crmCalculChurn(0, 0));
    }

    // ─── Point mort (SL-03 / SL-13) ────────────────────────────────────────

    public function testPointMortSansAucunClientUtiliseLeTarifEssentiel(): void
    {
        // SL-13 : « 3 clients à 79 € couvrent la totalité des charges. »
        $pm = financeCalculPointMort(0.0, 0, 180.0);
        $this->assertSame(79.0, $pm['panier_moyen']);
        $this->assertSame(3, $pm['clients_requis']);
        $this->assertFalse($pm['atteint']);
    }

    public function testPointMortUtiliseLePanierReelDesQuIlYADesClients(): void
    {
        // Cinq fondateurs à 39 € : 195 € de MRR, panier de 39 €.
        $pm = financeCalculPointMort(195.0, 5, 180.0);
        $this->assertSame(39.0, $pm['panier_moyen']);
        $this->assertSame(5, $pm['clients_requis']);
        $this->assertTrue($pm['atteint'], '195 € de MRR couvrent 180 € de charges.');
    }

    public function testLePointMortEstAtteintALEgalite(): void
    {
        $this->assertTrue(financeCalculPointMort(180.0, 2, 180.0)['atteint']);
        $this->assertFalse(financeCalculPointMort(179.99, 2, 180.0)['atteint']);
    }

    // ─── Autonomie (SL-13) ─────────────────────────────────────────────────

    public function testAutonomieEnMois(): void
    {
        $this->assertSame(5.0, financeCalculAutonomie(900.0, 180.0));
    }

    public function testSansConsommationLAutonomieEstIllimitee(): void
    {
        $this->assertNull(financeCalculAutonomie(1000.0, 0.0));
        $this->assertNull(financeCalculAutonomie(1000.0, -50.0));
    }

    public function testUnSoldeNegatifDonneUneAutonomieNulleEtNonNegative(): void
    {
        $this->assertSame(0.0, financeCalculAutonomie(-200.0, 180.0));
    }

    // ─── Repères de SL-13 ──────────────────────────────────────────────────

    public function testLesReperesFinanciersSontCeuxDeSl13(): void
    {
        $this->assertSame(1000.00, FINANCE_CAPITAL_INITIAL);
        $this->assertSame(1000.00, FINANCE_PLANCHER);
        $this->assertSame(180.00, FINANCE_CHARGES_MENSUELLES);
    }

    // ─── Souscription (SL-03 §2 et §4) ──────────────────────────────

    public function testUnFondateurEstOffertTroisMois(): void
    {
        $depart = strtotime('2026-09-17');
        $this->assertSame('2026-12-17', finEssaiPourFormule('fondateur', $depart));
    }

    public function testLesAutresFormulesOntUnMoisDEssai(): void
    {
        // SL-03 lie la facturation à la première soirée test publiée et
        // scannée : une condition qui ne se calcule pas à la signature. On
        // pose un mois, affiché à l'établissement, que les fondateurs
        // ajustent depuis la fiche client.
        $depart = strtotime('2026-09-17');
        $this->assertSame('2026-10-17', finEssaiPourFormule('essentiel', $depart));
        $this->assertSame('2026-10-17', finEssaiPourFormule('premium', $depart));
    }

    public function testUneFicheSansOffreNaPasDAbonnement(): void
    {
        $this->assertFalse(abonnementChoisi(null));
        $this->assertFalse(abonnementChoisi(['offre' => 'aucune']));
        $this->assertTrue(abonnementChoisi(['offre' => 'essentiel']));
    }

    public function testLesPlacesFondateurEtLaDateSontCellesAnnoncees(): void
    {
        // Quinze places et une date ferme, annoncées publiquement : la
        // rareté n'a de valeur commerciale que si elle est vraie.
        $this->assertSame(15, PLACES_FONDATEUR);
        $this->assertSame('2026-12-31', FIN_OFFRE_FONDATEUR);
    }

    public function testUnMoisVideNAAucunMontant(): void
    {
        $this->assertSame(
            ['recettes' => 0.0, 'depenses' => 0.0, 'resultat' => 0.0,
             'prevu_recettes' => 0.0, 'prevu_depenses' => 0.0],
            financeMoisVide()
        );
    }
}
