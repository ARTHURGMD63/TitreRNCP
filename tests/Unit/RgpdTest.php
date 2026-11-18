<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/security.php';

/**
 * La suppression d'un compte ne supprime jamais la ligne users (elle est
 * vidée) : aucune cascade ne joue. Chaque table qui pointe vers users.id doit
 * donc être traitée à la main par includes/compte.php, ou figurer ici comme
 * volontairement conservée.
 */
final class RgpdTest extends TestCase
{
    /** Tables gardées à dessein, avec la raison. */
    private const CONSERVEES = [
        'inscriptions'            => 'présences passées anonymisées (statistiques des établissements)',
        'etablissements'          => 'supprimé par compteSupprimerPartenaire() via son identifiant',
        'crm_interactions'        => 'auteur = administrateur, SET NULL',
        'admin_journal'           => 'journal d’administration, SET NULL',
        'finance_mouvements'      => 'pièce comptable',
        'culture_evenements'      => 'créé par un administrateur, SET NULL',
    ];

    public function testChaqueTableLieeAUnCompteEstTraiteeALaSuppression(): void
    {
        $schema = (string) file_get_contents(__DIR__ . '/../../db_setup.sql');
        $compte = (string) file_get_contents(__DIR__ . '/../../includes/compte.php');

        preg_match_all('/CREATE TABLE IF NOT EXISTS (\w+) \((.*?)\n\)\s*(?:ENGINE[^;]*)?;/s', $schema, $tables, PREG_SET_ORDER);
        $oubliees = [];
        foreach ($tables as [, $table, $corps]) {
            if ($table === 'users' || !preg_match('/REFERENCES users\s*\(id\)/', $corps)) {
                continue;
            }
            if (isset(self::CONSERVEES[$table])) {
                continue;
            }
            if (!preg_match('/(DELETE( \w+)? FROM|UPDATE) ' . $table . '\b/', $compte)) {
                $oubliees[] = $table;
            }
        }

        $this->assertGreaterThan(10, count($tables), 'Lecture du schéma');
        $this->assertSame([], $oubliees, 'Tables liées à users non traitées par la suppression de compte : ' . implode(', ', $oubliees));
    }

    public function testLeHachageTemoinEstValideEtAuCoutParDefaut(): void
    {
        $info = password_get_info(hachageTemoin());
        $this->assertSame('bcrypt', $info['algoName']);
        $this->assertSame(PHP_VERSION_ID >= 80400 ? 12 : 10, $info['options']['cost']);
        $this->assertFalse(password_verify('', hachageTemoin()));
    }

    public function testLeHachageDeDemonstrationEstReconnu(): void
    {
        $this->assertTrue(hachageDemonstration('$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'));
        $this->assertFalse(hachageDemonstration(password_hash('autre', PASSWORD_DEFAULT)));
    }

    public function testLesComptesDeDemoSeRouvrentEnProductionAvecCOMPTES_DEMO(): void
    {
        $demo   = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
        $avant  = [getenv('APP_ENV'), getenv('COMPTES_DEMO')];

        try {
            putenv('APP_ENV=production');
            putenv('COMPTES_DEMO');
            $this->assertTrue(hachageInterdit($demo), 'fermés par défaut en production');

            putenv('COMPTES_DEMO=1');
            $this->assertFalse(hachageInterdit($demo), 'rouverts par COMPTES_DEMO=1');

            putenv('COMPTES_DEMO=0');
            $this->assertTrue(hachageInterdit($demo), 'refermés par COMPTES_DEMO=0');

            putenv('APP_ENV=development');
            putenv('COMPTES_DEMO');
            $this->assertFalse(hachageInterdit($demo), 'toujours ouverts hors production');
        } finally {
            putenv($avant[0] === false ? 'APP_ENV' : 'APP_ENV=' . $avant[0]);
            putenv($avant[1] === false ? 'COMPTES_DEMO' : 'COMPTES_DEMO=' . $avant[1]);
        }
    }
}
