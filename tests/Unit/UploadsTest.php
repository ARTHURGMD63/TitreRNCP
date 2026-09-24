<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Traitement des images reçues.
 *
 * C'est la surface la plus exposée de l'application : un fichier arrive
 * d'ailleurs, avec un nom et un type que l'expéditeur choisit. Le code s'en
 * méfiait déjà — nom régénéré, type relu dans le contenu, bornes de taille —
 * mais rien ne le vérifiait, et une garde qu'aucun test ne tient se retire
 * un jour par inadvertance.
 *
 * storeUploadedImage() elle-même ne se teste pas ici : elle appelle
 * is_uploaded_file() et move_uploaded_file(), qui ne répondent vrai que pour
 * un fichier réellement reçu par une requête HTTP. Ce sont ses gardes
 * périphériques — celles qu'on peut atteindre — qui sont couvertes.
 */
final class UploadsTest extends TestCase
{
    private string $dossier = '';

    protected function setUp(): void
    {
        if (!function_exists('deleteStoredImage')) {
            require_once __DIR__ . '/../../includes/auth_check.php';
            require_once __DIR__ . '/../../includes/uploads.php';
        }

        $this->dossier = sys_get_temp_dir() . '/linkee_uploads_' . bin2hex(random_bytes(6));
        mkdir($this->dossier, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dossier . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dossier);
        @unlink(dirname($this->dossier) . '/temoin_a_ne_pas_supprimer.txt');
    }

    // ── Traversée de répertoire ─────────────────────────────────────────────

    /**
     * Le cas qui compte. Le nom de fichier vient de la base, où il a été
     * écrit par storeUploadedImage() — mais une page qui le passerait
     * directement depuis un POST donnerait le droit d'effacer n'importe quel
     * fichier lisible par le serveur.
     *
     * @dataProvider nomsHostiles
     */
    public function testUnNomQuiSortDuDossierEstRefuse(string $nom): void
    {
        $temoin = dirname($this->dossier) . '/temoin_a_ne_pas_supprimer.txt';
        file_put_contents($temoin, 'ce fichier doit survivre');

        $resultat = deleteStoredImage($nom, $this->dossier);

        $this->assertFalse($resultat, "« $nom » a été accepté comme nom de fichier");
        $this->assertFileExists($temoin, "« $nom » a supprimé un fichier hors du dossier");
    }

    /** @return array<string,array{string}> */
    public static function nomsHostiles(): array
    {
        return [
            'remontée simple'      => ['../temoin_a_ne_pas_supprimer.txt'],
            'remontée double'      => ['../../temoin_a_ne_pas_supprimer.txt'],
            'remontée déguisée'    => ['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/../../temoin_a_ne_pas_supprimer.txt'],
            'séparateur Windows'   => ['..\\temoin_a_ne_pas_supprimer.txt'],
            'chemin absolu'        => ['/etc/passwd'],
            'octet nul'            => ["aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg\0.txt"],
            'extension interdite'  => ['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.php'],
            'double extension'     => ['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg.php'],
            'nom vide'             => [''],
            'majuscules'           => ['AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA.jpg'],
            'nom trop court'       => ['abc.jpg'],
        ];
    }

    public function testUnNomLegitimeSupprimeBienLeFichier(): void
    {
        $nom = str_repeat('a', 32) . '.jpg';
        file_put_contents($this->dossier . '/' . $nom, 'image');

        $this->assertTrue(deleteStoredImage($nom, $this->dossier));
        $this->assertFileDoesNotExist($this->dossier . '/' . $nom);
    }

    public function testSupprimerUnFichierDejaAbsentNEstPasUnEchec(): void
    {
        // Le disque et la base peuvent diverger — une photo effacée à la main,
        // une restauration partielle. La ligne doit pouvoir partir de la base
        // quand même : c'est l'état voulu qui compte, pas le geste.
        $this->assertTrue(deleteStoredImage(str_repeat('b', 32) . '.png', $this->dossier));
    }

    // ── Avatar ──────────────────────────────────────────────────────────────

    public function testLAvatarEchappeLePrenom(): void
    {
        // Le prénom vient du formulaire d'inscription, et l'avatar s'affiche
        // sur huit écrans dont l'annuaire, où il porte le prénom d'un autre.
        $html = avatarHtml(null, '<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testLAvatarEchappeLePrenomDansLAttributAlt(): void
    {
        $html = avatarHtml(null, 'Guillemet " et chevron >');

        $this->assertStringNotContainsString('alert', $html);
        $this->assertStringNotContainsString('" et chevron >', $html);
    }

    public function testLInitialeTientCompteDesAccents(): void
    {
        // mb_substr et mb_strtoupper, pas leurs équivalents ASCII : « é » fait
        // deux octets, et strtoupper() n'en connaît aucun.
        $this->assertStringContainsString('É', avatarHtml(null, 'élodie'));
    }

    public function testUnPrenomVideNeCasseRien(): void
    {
        $html = avatarHtml(null, '');

        $this->assertNotSame('', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    public function testUnePhotoAbsenteDuDisqueRetombeSurLInitiale(): void
    {
        // La base peut porter un nom de fichier que le disque n'a plus. Une
        // balise <img> cassée est pire qu'une initiale.
        $html = avatarHtml('fichier_qui_nexiste_pas.jpg', 'Arthur');

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('A', $html);
    }

    // ── Messages d'erreur ───────────────────────────────────────────────────

    public function testChaqueCodeDErreurDonneUnMessageUtilisable(): void
    {
        foreach ([UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE, UPLOAD_ERR_PARTIAL,
                  UPLOAD_ERR_NO_FILE, UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE,
                  UPLOAD_ERR_EXTENSION, 99] as $code) {
            $message = uploadErrorMessage($code);

            $this->assertNotSame('', $message);
            // Aucun message ne doit renvoyer un code brut à l'utilisateur.
            $this->assertStringNotContainsString('UPLOAD_ERR', $message);
        }
    }

    public function testUnEchecDEnvoiNeRefuseJamaisSilencieusement(): void
    {
        $resultat = storeUploadedImage(['error' => UPLOAD_ERR_NO_FILE], $this->dossier);

        $this->assertFalse($resultat['ok']);
        $this->assertArrayHasKey('error', $resultat);
    }

    public function testUnCheminTemporaireForgeEstRefuse(): void
    {
        // Sans le contrôle is_uploaded_file(), passer un chemin arbitraire
        // dans tmp_name ferait recopier n'importe quel fichier du serveur
        // dans un dossier servi publiquement.
        $piege = $this->dossier . '/piege.txt';
        file_put_contents($piege, 'contenu');

        $resultat = storeUploadedImage(
            ['error' => UPLOAD_ERR_OK, 'tmp_name' => $piege, 'size' => 7],
            $this->dossier
        );

        $this->assertFalse($resultat['ok']);
        $this->assertArrayNotHasKey('filename', $resultat);
    }

    public function testLesBornesDeTailleSontCoherentes(): void
    {
        $this->assertLessThan(UPLOAD_MAX_PIXELS, UPLOAD_MIN_PIXELS);
        $this->assertGreaterThan(0, UPLOAD_MAX_BYTES);
        $this->assertSame(['image/jpeg', 'image/png', 'image/webp'], array_keys(UPLOAD_ALLOWED_TYPES));
    }
}
