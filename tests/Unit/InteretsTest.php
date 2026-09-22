<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Catalogue des centres d'intérêt.
 *
 * Deux choses s'y jouent. D'abord une validation : tout ce qui arrive du
 * formulaire passe par filtrerInterets(), et la colonne `users.interests` est
 * ensuite affichée telle quelle sur le profil et l'annuaire. Ensuite une
 * synchronisation : la table indexée `user_interets` doit suivre la colonne
 * texte, sous peine de classer l'annuaire sur des goûts périmés.
 */
final class InteretsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('filtrerInterets')) {
            require_once __DIR__ . '/../../includes/interets.php';
        }
    }

    // ── Filtrage de ce qui vient du formulaire ──────────────────────────────

    public function testUnInteretHorsCatalogueEstEcarte(): void
    {
        $this->assertSame(['Running'], filtrerInterets(['Running', 'Parapente']));
    }

    public function testLeResultatSuitLOrdreDuCatalogueEtNonCeluiDuFormulaire(): void
    {
        // L'ordre vient du catalogue : deux comptes ayant coché les mêmes
        // étiquettes doivent produire la même chaîne en base, sinon toute
        // comparaison textuelle entre profils devient fausse.
        $this->assertSame(
            ['Sorties', 'Running', 'Code'],
            filtrerInterets(['Code', 'Running', 'Sorties'])
        );
    }

    public function testLesEspacesAutourSontTolerees(): void
    {
        $this->assertSame(['Running'], filtrerInterets(['  Running  ']));
    }

    public function testUneEntreeQuiNEstPasUnTableauDonneUneListeVide(): void
    {
        // « ?interests=x » plutôt que « ?interests[]=x » : PHP livre une
        // chaîne là où le code attend un tableau.
        $this->assertSame([], filtrerInterets('Running'));
        $this->assertSame([], filtrerInterets(null));
        $this->assertSame([], filtrerInterets(42));
    }

    public function testLesValeursNonTextuellesSontEcartees(): void
    {
        $this->assertSame(['Running'], filtrerInterets(['Running', ['imbriqué'], null, 7]));
    }

    public function testLeFiltrageEstSensibleALaCasse(): void
    {
        // Le catalogue est la seule orthographe admise : « running » entrerait
        // en base à côté de « Running » et compterait pour un goût différent.
        $this->assertSame([], filtrerInterets(['running', 'RUNNING']));
    }

    public function testUnDoublonNeSortQuUneFois(): void
    {
        $this->assertSame(['Running'], filtrerInterets(['Running', 'Running']));
    }

    // ── Colonne texte ───────────────────────────────────────────────────────

    public function testAllerRetourEntreTexteEtListe(): void
    {
        $liste = ['Sorties', 'Running', 'Code'];

        $this->assertSame($liste, interetsDepuisTexte(interetsVersTexte($liste)));
    }

    public function testLaLectureToleereLesEspacesDAnciennesLignes(): void
    {
        $this->assertSame(
            ['Sorties', 'Running'],
            interetsDepuisTexte('Sorties , Running')
        );
    }

    public function testUneColonneVideOuNulleDonneUneListeVide(): void
    {
        $this->assertSame([], interetsDepuisTexte(null));
        $this->assertSame([], interetsDepuisTexte(''));
        $this->assertSame([], interetsDepuisTexte('   '));
        $this->assertSame([], interetsDepuisTexte(',,,'));
    }

    // ── Ordre d'affichage du sélecteur ──────────────────────────────────────

    public function testLesInteretsCochesRemontentEnTete(): void
    {
        $tries = interetsTriesParSelection(['Code', 'Yoga']);

        $this->assertSame(['Yoga', 'Code'], array_slice($tries, 0, 2));
    }

    public function testLeSelecteurNePerdAucuneEtiquette(): void
    {
        $catalogue = interetsDisponibles();
        $tries     = interetsTriesParSelection(['Code']);

        $this->assertCount(count($catalogue), $tries);
        $this->assertSame([], array_diff($catalogue, $tries));
    }

    public function testLeCatalogueNAucunDoublon(): void
    {
        $catalogue = interetsDisponibles();

        $this->assertSame($catalogue, array_values(array_unique($catalogue)));
    }

    public function testLeSelecteurEchappeCeQuIlRecoit(): void
    {
        $html = selecteurInteretsHtml(['<script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    // ── Synchronisation de la table indexée ─────────────────────────────────

    public function testLaSynchronisationEcritLesLignesIndexees(): void
    {
        $pdo = $this->baseEnMemoire();

        synchroniserInterets($pdo, 1, ['Running', 'Code']);

        $lignes = $pdo->query('SELECT interet FROM user_interets WHERE user_id = 1 ORDER BY interet')
            ->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame(['Code', 'Running'], $lignes);
    }

    public function testLaSynchronisationRemplaceLEtatPrecedent(): void
    {
        // Le cas qui compte : un goût retiré du profil doit disparaître de la
        // table indexée, sinon l'annuaire continue de classer dessus.
        $pdo = $this->baseEnMemoire();

        synchroniserInterets($pdo, 1, ['Running', 'Code']);
        synchroniserInterets($pdo, 1, ['Yoga']);

        $lignes = $pdo->query('SELECT interet FROM user_interets WHERE user_id = 1')
            ->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame(['Yoga'], $lignes);
    }

    public function testViderLesInteretsViderLaTable(): void
    {
        $pdo = $this->baseEnMemoire();

        synchroniserInterets($pdo, 1, ['Running']);
        synchroniserInterets($pdo, 1, []);

        $this->assertSame(
            '0',
            (string) $pdo->query('SELECT COUNT(*) FROM user_interets WHERE user_id = 1')->fetchColumn()
        );
    }

    public function testUnAutreCompteNEstPasTouche(): void
    {
        $pdo = $this->baseEnMemoire();

        synchroniserInterets($pdo, 1, ['Running']);
        synchroniserInterets($pdo, 2, ['Yoga']);
        synchroniserInterets($pdo, 1, []);

        $this->assertSame(
            ['Yoga'],
            $pdo->query('SELECT interet FROM user_interets WHERE user_id = 2')->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    public function testUneTableAbsenteNEmpechePasLEnregistrementDuProfil(): void
    {
        // La migration v15 peut ne pas être passée. L'enregistrement du profil
        // ne doit pas échouer pour autant : le classement retombe sur un score
        // nul, et la migration reconstruira la table depuis la colonne texte.
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        synchroniserInterets($pdo, 1, ['Running']);

        $this->assertTrue(true, 'aucune exception ne doit remonter');
    }

    private function baseEnMemoire(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE user_interets (user_id INTEGER NOT NULL, interet TEXT NOT NULL)');

        return $pdo;
    }
}
