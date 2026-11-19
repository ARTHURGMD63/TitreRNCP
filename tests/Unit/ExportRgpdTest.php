<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/rgpd.php';

/**
 * ETU-13 : l'export des données du compte couvre chaque table liée au
 * compte, et n'en rend aucun secret.
 */
final class ExportRgpdTest extends TestCase
{
    /** Tables liées à users qui ne décrivent pas la personne elle-même. */
    private const HORS_EXPORT = [
        'crm_clients'        => 'responsable = administrateur',
        'crm_interactions'   => 'auteur = administrateur',
        'finance_mouvements' => 'pièce comptable saisie par un administrateur',
        'culture_evenements' => 'créé par un administrateur',
    ];

    public function testLesSecretsSontRetires(): void
    {
        $ligne = rgpdNettoyerLigne([
            'id' => 1, 'email' => 'a@b.fr', 'password' => 'x', 'token_hash' => 'y',
            'qr_code' => 'z', 'secret' => 's', 'codes_secours' => 'c', 'jeton' => 'j', 'code_hash' => 'h',
        ]);
        $this->assertSame(['id' => 1, 'email' => 'a@b.fr'], $ligne);
    }

    public function testChaqueTableLieeAuCompteEstExportee(): void
    {
        $schema   = (string) file_get_contents(__DIR__ . '/../../db_setup.sql');
        $requetes = implode("\n", array_map(static fn (array $r): string => $r[0], rgpdRequetes(1, 1, 'a@b.fr')));

        preg_match_all('/CREATE TABLE IF NOT EXISTS (\w+) \((.*?)\n\)\s*(?:ENGINE[^;]*)?;/s', $schema, $tables, PREG_SET_ORDER);
        $oubliees = [];
        foreach ($tables as [, $table, $corps]) {
            if ($table === 'users' || isset(self::HORS_EXPORT[$table]) || !preg_match('/REFERENCES users\s*\(id\)/', $corps)) {
                continue;
            }
            if (!preg_match('/FROM ' . $table . '\b/', $requetes)) {
                $oubliees[] = $table;
            }
        }

        $this->assertSame([], $oubliees, 'Tables liées au compte absentes de l’export : ' . implode(', ', $oubliees));
    }

    public function testLeServiceExigeUnCompteEtLimiteLeDebit(): void
    {
        $code = (string) file_get_contents(__DIR__ . '/../../api/v1/mes_donnees.php');
        $this->assertStringContainsString('apiUtilisateur($pdo)', $code);
        $this->assertStringContainsString("limiteAtteinte(\$pdo, 'export'", $code);
        $this->assertStringContainsString("apiExigerMethode('GET')", $code);
    }
}
