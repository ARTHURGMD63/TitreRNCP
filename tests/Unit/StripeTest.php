<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use StripeErreur;

/**
 * Paiement Stripe : ce qui se vérifie sans appeler Stripe.
 *
 * La signature des webhooks est la seule barrière entre Internet et la mise à
 * jour des formules en base : un corps forgé accepté donnerait une formule
 * Premium sans paiement. L'encodage des paramètres, lui, décide de ce que
 * Stripe facture : un montant mal transmis est une facture fausse.
 */
final class StripeTest extends TestCase
{
    private const SECRET = 'whsec_test_outsee';

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../includes/stripe.php';
    }

    private function signer(string $corps, int $horodatage, string $secret = self::SECRET): string
    {
        return 't=' . $horodatage . ',v1=' . hash_hmac('sha256', $horodatage . '.' . $corps, $secret);
    }

    public function testUneSignatureValideDonneLEvenement(): void
    {
        $corps = '{"id":"evt_1","type":"invoice.paid","data":{"object":{}}}';
        $t     = 1_700_000_000;

        $evenement = stripeVerifierWebhook($corps, $this->signer($corps, $t), self::SECRET, $t + 10);

        $this->assertSame('evt_1', $evenement['id']);
        $this->assertSame('invoice.paid', $evenement['type']);
    }

    public function testUnCorpsModifieEstRefuse(): void
    {
        $corps = '{"id":"evt_1","type":"invoice.paid","data":{"object":{}}}';
        $t     = 1_700_000_000;
        $entete = $this->signer($corps, $t);

        $this->expectException(StripeErreur::class);
        stripeVerifierWebhook(str_replace('invoice.paid', 'checkout.session.completed', $corps), $entete, self::SECRET, $t);
    }

    public function testUnAutreSecretEstRefuse(): void
    {
        $corps = '{"id":"evt_1","type":"invoice.paid"}';
        $t     = 1_700_000_000;

        $this->expectException(StripeErreur::class);
        stripeVerifierWebhook($corps, $this->signer($corps, $t, 'whsec_autre'), self::SECRET, $t);
    }

    public function testUnEvenementRejoueTropTardEstRefuse(): void
    {
        $corps = '{"id":"evt_1","type":"invoice.paid"}';
        $t     = 1_700_000_000;

        $this->expectException(StripeErreur::class);
        stripeVerifierWebhook($corps, $this->signer($corps, $t), self::SECRET, $t + STRIPE_TOLERANCE_WEBHOOK + 1);
    }

    public function testUnEnteteSansSignatureEstRefuse(): void
    {
        $this->expectException(StripeErreur::class);
        stripeVerifierWebhook('{"id":"evt_1","type":"x"}', 't=1700000000', self::SECRET, 1_700_000_000);
    }

    public function testSansSecretRienNEstAccepte(): void
    {
        $corps = '{"id":"evt_1","type":"invoice.paid"}';

        $this->expectException(StripeErreur::class);
        stripeVerifierWebhook($corps, $this->signer($corps, 1_700_000_000, ''), '', 1_700_000_000);
    }

    public function testUneSignatureParmiPlusieursSuffit(): void
    {
        // Pendant une rotation du secret, Stripe envoie deux signatures v1.
        $corps  = '{"id":"evt_2","type":"invoice.paid"}';
        $t      = 1_700_000_000;
        $bonne  = hash_hmac('sha256', $t . '.' . $corps, self::SECRET);
        $entete = 't=' . $t . ',v1=' . str_repeat('0', 64) . ',v1=' . $bonne;

        $this->assertSame('evt_2', stripeVerifierWebhook($corps, $entete, self::SECRET, $t)['id']);
    }

    public function testLesParametresSontEncodesALaManiereDeStripe(): void
    {
        $encode = stripeEncoder([
            'mode'       => 'subscription',
            'line_items' => [['price_data' => ['unit_amount' => 4999, 'recurring' => ['interval' => 'month']], 'quantity' => 1]],
            'tax_id_collection' => ['enabled' => true],
            'customer'   => null,
        ]);

        $this->assertSame(
            'mode=subscription'
            . '&line_items%5B0%5D%5Bprice_data%5D%5Bunit_amount%5D=4999'
            . '&line_items%5B0%5D%5Bprice_data%5D%5Brecurring%5D%5Binterval%5D=month'
            . '&line_items%5B0%5D%5Bquantity%5D=1'
            . '&tax_id_collection%5Benabled%5D=true',
            $encode
        );
    }

    public function testLesMontantsPartentEnCentimes(): void
    {
        $this->assertSame(4999, stripeCentimes(49.99));
        $this->assertSame(6999, stripeCentimes(69.99));
        $this->assertSame(2500, stripeCentimes(25));
    }

    public function testSansCleStripeLePaiementEnLigneResteEteint(): void
    {
        $avant = getenv('STRIPE_SECRET_KEY');
        putenv('STRIPE_SECRET_KEY');
        try {
            if (trim((string) (configLocale()['stripe_secret'] ?? '')) === '') {
                $this->assertFalse(stripeActif());
            } else {
                $this->markTestSkipped('Une clé Stripe est réglée dans config.local.php.');
            }
        } finally {
            if ($avant !== false) {
                putenv('STRIPE_SECRET_KEY=' . $avant);
            }
        }
    }

    public function testUnAbonnementAnnuleNEstPlusVivant(): void
    {
        $this->assertTrue(stripeAbonnementVivant(['stripe_subscription_id' => 'sub_1', 'stripe_statut' => 'active']));
        $this->assertTrue(stripeAbonnementVivant(['stripe_subscription_id' => 'sub_1', 'stripe_statut' => 'past_due']));
        $this->assertFalse(stripeAbonnementVivant(['stripe_subscription_id' => 'sub_1', 'stripe_statut' => 'canceled']));
        $this->assertFalse(stripeAbonnementVivant(['stripe_subscription_id' => null, 'stripe_statut' => null]));
        $this->assertFalse(stripeAbonnementVivant(null));
    }
}
