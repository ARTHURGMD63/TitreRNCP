<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Numéro de téléphone (v43) : format, affichage, visibilité côté
 * établissement, signature des requêtes OVH, textes légaux.
 */
final class TelephoneTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../includes/telephone.php';
        require_once __DIR__ . '/../../includes/textes_legaux.php';
    }

    public function testLesEcrituresCourantesDUnMobileSontAcceptees(): void
    {
        foreach (['06 12 34 56 78', '0612345678', '06.12.34.56.78', '06-12-34-56-78',
                  '+33 6 12 34 56 78', '+33612345678', '0033612345678', '+33 (0)6 12 34 56 78'] as $saisie) {
            $this->assertSame('+33612345678', telephoneNormaliser($saisie), $saisie);
        }
        $this->assertSame('+33798765432', telephoneNormaliser('07 98 76 54 32'));
    }

    public function testFixesNumerosEtrangersEtSaisiesIncompletesSontRefuses(): void
    {
        // Les numéros étrangers sont refusés exprès : c'est la parade à la
        // fraude qui fait envoyer des SMS vers des numéros surtaxés.
        foreach (['', '04 73 00 00 00', '01 23 45 67 89', '+44 7700 900123', '06 12 34 56',
                  '06 12 34 56 78 9', 'abc', '612345678', '08 99 00 00 00'] as $saisie) {
            $this->assertNull(telephoneNormaliser($saisie), $saisie);
        }
    }

    public function testAffichageALaFrancaise(): void
    {
        $this->assertSame('06 12 34 56 78', telephoneAffiche('+33612345678'));
        $this->assertSame('inconnu', telephoneAffiche('inconnu'));
    }

    public function testLEtablissementVoitLesNumerosTrenteJoursApresLaFin(): void
    {
        $fin = '2026-12-01 23:00:00';
        $finTs = (int) strtotime($fin);

        $this->assertTrue(telephoneVisiblePourEtablissement($fin, '2026-12-01 20:00:00', $finTs - 3600));
        $this->assertTrue(telephoneVisiblePourEtablissement($fin, '2026-12-01 20:00:00', $finTs + 30 * 86400));
        $this->assertFalse(telephoneVisiblePourEtablissement($fin, '2026-12-01 20:00:00', $finTs + 30 * 86400 + 1));
        // Sans heure de fin, le délai court depuis le début.
        $debutTs = (int) strtotime('2026-12-01 20:00:00');
        $this->assertFalse(telephoneVisiblePourEtablissement(null, '2026-12-01 20:00:00', $debutTs + 31 * 86400));
    }

    public function testLeCodeNeSeStockeQuEnEmpreinteLieeAuNumero(): void
    {
        $empreinte = telephoneCodeEmpreinte('+33612345678', '123456');
        $this->assertSame(64, strlen($empreinte));
        $this->assertStringNotContainsString('123456', $empreinte);
        $this->assertNotSame($empreinte, telephoneCodeEmpreinte('+33698765432', '123456'));
    }

    public function testLeMessageSMSPorteLeCodeEtSaDuree(): void
    {
        $message = telephoneMessageCode('042517');
        $this->assertStringContainsString('042517', $message);
        $this->assertStringContainsString((string) TELEPHONE_CODE_VALIDITE_MIN . ' minutes', $message);
        $this->assertLessThanOrEqual(160, mb_strlen($message), 'un seul SMS');
    }

    public function testSignatureOvh(): void
    {
        // Valeur calculée à la main : « $1$ » + SHA-1 des six champs joints par « + ».
        $attendu = '$1$' . sha1('secret+client+POST+https://eu.api.ovh.com/1.0/sms/x/jobs+{}+1700000000');
        $this->assertSame($attendu, ovhSignature('secret', 'client', 'POST', 'https://eu.api.ovh.com/1.0/sms/x/jobs', '{}', 1700000000));
    }

    public function testCorpsDEnvoiSansPublicite(): void
    {
        $corps = smsCorps('+33612345678', 'code', '');
        $this->assertSame(['+33612345678'], $corps['receivers']);
        $this->assertTrue($corps['noStopClause']);
        $this->assertTrue($corps['senderForResponse']);
        $this->assertArrayNotHasKey('sender', $corps);

        $avecNom = smsCorps('+33612345678', 'code', 'Outsee');
        $this->assertSame('Outsee', $avecNom['sender']);
        $this->assertArrayNotHasKey('senderForResponse', $avecNom);
    }

    public function testLeJournalNeGardePasLeNumeroComplet(): void
    {
        $this->assertSame('+336••••5678', smsNumeroMasque('+33612345678'));
    }

    public function testLesTextesLegauxAnnoncentLeNumeroEtSonPartage(): void
    {
        $textes = textesLegaux();
        $tout = static fn (string $slug): string => (string) json_encode($textes[$slug], JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('numéro de téléphone', $tout('cgu'));
        $this->assertStringContainsString('30 jours', $tout('cgu'));
        $this->assertStringContainsString('numéro de téléphone', $tout('cgv'));
        $this->assertStringContainsString('prospection', $tout('cgv'));
        $this->assertStringContainsString('Téléphone (étudiants)', $tout('confidentialite'));
        $this->assertStringContainsString('SMS de vérification', $tout('confidentialite'));
    }
}
