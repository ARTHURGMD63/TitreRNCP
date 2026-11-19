<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/evenements_photos.php';

/**
 * Masquage automatique d'une photo de soirée (ETU-08) : un seuil relatif au
 * nombre de présents, borné, au lieu de trois signalements quelconques.
 */
final class PhotoSignalementTest extends TestCase
{
    public function testUnePetiteSoireeGardeLeMinimum(): void
    {
        $this->assertSame(PHOTO_SIGNALEMENTS_MIN, photoSeuilMasquage(0));
        $this->assertSame(PHOTO_SIGNALEMENTS_MIN, photoSeuilMasquage(40));
    }

    public function testLeSeuilSuitLeNombreDePresents(): void
    {
        $this->assertSame(5, photoSeuilMasquage(100));
        $this->assertSame(8, photoSeuilMasquage(151));
    }

    public function testLeSeuilEstPlafonne(): void
    {
        $this->assertSame(PHOTO_SIGNALEMENTS_MAX, photoSeuilMasquage(5000));
    }

    public function testLePointDeSignalementVerifieLAccesALaPhoto(): void
    {
        $code = (string) file_get_contents(__DIR__ . '/../../api/v1/evenement_photo_signaler.php');

        $this->assertStringContainsString('peutVoirPhotosDe(', $code);
        $this->assertStringContainsString('evenementPhotosEstCheckin(', $code);
        $this->assertStringContainsString('photoSeuilMasquage(', $code);
    }
}
