<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Les en-têtes et l'encodage des messages sortants.
 *
 * C'est la partie qui décide si un e-mail arrive ou finit en indésirables, et
 * elle ne se vérifie pas en regardant sa boîte : un message peut passer chez
 * soi et être refusé ailleurs. On teste donc ce qui est vérifiable — la forme
 * — et on documente dans docs/EMAIL.md ce qui ne l'est pas : les
 * enregistrements DNS.
 *
 * Rien n'est envoyé ici.
 */
final class MailTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('entetesMail')) {
            require_once __DIR__ . '/../../includes/mail.php';
        }
        $_SERVER['HTTP_HOST'] = 'studentlink.example';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_HOST']);
    }

    /** @return array<string,string|int> */
    private function config(array $surcharge = []): array
    {
        return $surcharge + [
            'expediteur'  => 'noreply@studentlink.example',
            'nom'         => 'StudentLink',
            'retour'      => 'rebonds@studentlink.example',
            'transport'   => 'mail',
            'hote'        => '',
            'port'        => 587,
            'chiffrement' => 'tls',
            'utilisateur' => '',
            'motdepasse'  => '',
        ];
    }

    // ── Encodage du sujet ───────────────────────────────────────────────────

    public function testUnSujetAsciiResteIntact(): void
    {
        $this->assertSame('Rappel de sortie', encoderEntete('Rappel de sortie'));
    }

    public function testUnSujetAccentueEstEncodeSelonLaRfc2047(): void
    {
        // « StudentLink — Réinitialisation… » partait en UTF-8 brut. Un
        // en-tête ne transporte que de l'ASCII : selon le client, le sujet
        // s'affichait en caractères de remplacement, et plusieurs filtres
        // comptent un en-tête 8 bits comme signal négatif.
        $encode = encoderEntete('Réinitialisation de votre mot de passe');

        $this->assertStringContainsString('=?UTF-8?B?', $encode);
        $this->assertMatchesRegularExpression('/^[\x20-\x7E\r\n]*$/', $encode);
    }

    public function testLeSujetEncodeSeRelitALIdentique(): void
    {
        $original = 'StudentLink — Réinitialisation de votre mot de passe (à faire aujourd\'hui)';

        $this->assertSame($original, mb_decode_mimeheader(encoderEntete($original)));
    }

    public function testChaqueTronconEncodeResteSousLaLimite(): void
    {
        // Un mot encodé ne doit pas dépasser 75 caractères, césure comprise.
        $long   = str_repeat('Réinitialisation épatante ', 12);
        $encode = encoderEntete($long);

        foreach (explode("\r\n ", $encode) as $troncon) {
            $this->assertLessThanOrEqual(75, strlen($troncon));
        }
        $this->assertSame(trim($long), mb_decode_mimeheader($encode));
    }

    public function testLaCesureNeCoupePasUnCaractereUtf8(): void
    {
        // Couper au milieu d'un « é » afficherait un losange à la jointure.
        $encode = encoderEntete(str_repeat('é', 200));

        $this->assertSame(str_repeat('é', 200), mb_decode_mimeheader($encode));
    }

    // ── Injection d'en-têtes ────────────────────────────────────────────────

    public function testUnSautDeLigneDansLeSujetNeCreePasDEntete(): void
    {
        // Sans ce nettoyage, un sujet contenant « \r\nBcc: » enverrait une
        // copie du message à un tiers.
        $sale = "Rappel\r\nBcc: espion@evil.example";

        $this->assertStringNotContainsString("\r", nettoyerEntete($sale));
        $this->assertStringNotContainsString("\n", nettoyerEntete($sale));
        $this->assertStringNotContainsString("\r", encoderEntete($sale));
    }

    public function testLOctetNulEstRetireAussi(): void
    {
        $this->assertStringNotContainsString("\0", nettoyerEntete("Rappel\0suite"));
    }

    // ── Jeu d'en-têtes ──────────────────────────────────────────────────────

    public function testLesEntetesEssentielsSontTousPresents(): void
    {
        // Chacun de ces en-têtes manquant compte comme signal négatif chez au
        // moins un grand fournisseur. L'ancienne version n'en posait que deux.
        $entetes = entetesMail($this->config(), 'Rappel');

        foreach ([
            'Date', 'From', 'Reply-To', 'Return-Path', 'Message-ID',
            'MIME-Version', 'Content-Type', 'Content-Transfer-Encoding',
        ] as $attendu) {
            $this->assertArrayHasKey($attendu, $entetes, "En-tête $attendu absent.");
            $this->assertNotSame('', trim($entetes[$attendu]));
        }
    }

    public function testLIdentifiantDeMessageEstRattacheAuDomaineExpediteur(): void
    {
        // DMARC juge l'alignement sur le domaine de l'expéditeur, pas sur
        // celui de la machine.
        $id = entetesMail($this->config(), 'Rappel')['Message-ID'];

        $this->assertMatchesRegularExpression('/^<[^@>]+@studentlink\.example>$/', $id);
    }

    public function testDeuxMessagesNOntJamaisLeMemeIdentifiant(): void
    {
        $this->assertNotSame(
            identifiantMessage('noreply@studentlink.example'),
            identifiantMessage('noreply@studentlink.example')
        );
    }

    public function testLAdresseDeRetourPorteLesRebonds(): void
    {
        // Return-Path est ce qui aligne SPF et ramène les rebonds. Sans lui,
        // une adresse morte reste sollicitée indéfiniment.
        $entetes = entetesMail($this->config(), 'Rappel');

        $this->assertSame('<rebonds@studentlink.example>', $entetes['Return-Path']);
    }

    public function testLeNomAffichéAccentuéEstEncodé(): void
    {
        $entetes = entetesMail($this->config(['nom' => 'StudentLink Équipe']), 'Rappel');

        $this->assertMatchesRegularExpression('/^[\x20-\x7E\r\n]*$/', $entetes['From']);
        $this->assertStringContainsString('<noreply@studentlink.example>', $entetes['From']);
    }

    public function testLaDateEstAuFormatRfc(): void
    {
        $date = entetesMail($this->config(), 'Rappel')['Date'];

        $this->assertNotFalse(strtotime($date), "Date illisible : $date");
    }

    // ── Corps ───────────────────────────────────────────────────────────────

    public function testLeCorpsEstEncodeEnQuotedPrintable(): void
    {
        $encode = encoderCorps("Bonjour,\nRéinitialisation.");

        $this->assertMatchesRegularExpression('/^[\x20-\x7E\r\n=]*$/', $encode);
        $this->assertSame("Bonjour,\r\nRéinitialisation.", quoted_printable_decode($encode));
    }

    public function testLesFinsDeLigneSontNormalisees(): void
    {
        // Un mélange de \n et \r\n dans un message est un signal négatif
        // classique, et certains relais tronquent sur le format inattendu.
        $encode = encoderCorps("a\nb\r\nc\rd");

        $this->assertSame("a\r\nb\r\nc\r\nd", quoted_printable_decode($encode));
    }

    // ── Configuration ───────────────────────────────────────────────────────

    public function testLExpediteurParDefautSuitLeDomaineServi(): void
    {
        // Un « noreply@studentlink.app » écrit en dur mentait dès que le site
        // tournait ailleurs — et c'est exactement ce que SPF sanctionne.
        $config = configurationMail();

        $this->assertSame('noreply@studentlink.example', $config['expediteur']);
    }

    public function testLePortDuDomaineNeFinitPasDansLAdresse(): void
    {
        $_SERVER['HTTP_HOST'] = 'studentlink.example:8443';

        $this->assertSame('noreply@studentlink.example', configurationMail()['expediteur']);
    }

    public function testLeTransportParDefautEstLaFonctionMail(): void
    {
        // SMTP ne s'active que si on l'a configuré : une valeur par défaut
        // qui tenterait une connexion réseau ferait échouer tous les envois
        // là où mail() fonctionnait.
        $this->assertSame('mail', configurationMail()['transport']);
    }
}
