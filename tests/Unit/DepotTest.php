<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Le dépôt ne doit pas regrossir sans qu'on s'en aperçoive.
 *
 * Il portait 44 Mo de binaires — un teaser de 11 Mo, quatre diaporamas pour
 * 24 Mo, sept PDF reconstructibles — pour 7,8 Mo de code. Aucun n'était
 * utilisé par l'application. Ils ont été retirés du suivi (voir
 * docs/LIVRABLES.md), mais rien n'empêchait de recommencer : un
 * « git add . » un soir de rendu, et c'est reparti pour un historique qui ne
 * rétrécira jamais.
 *
 * Ce test lit l'index de Git, pas le disque : les fichiers présents
 * localement mais ignorés ne le concernent pas.
 */
final class DepotTest extends TestCase
{
    /**
     * Au-delà de ce poids, un fichier suivi mérite une justification.
     *
     * 2 Mo laisse passer les .docx sources (1,4 Mo au total) et refuse tout
     * ce qui relève du livrable.
     */
    private const POIDS_MAX_OCTETS = 2 * 1024 * 1024;

    /** Formats qui n'ont rien à faire dans un dépôt de code, quelle que soit leur taille. */
    private const EXTENSIONS_INTERDITES = ['mp4', 'mov', 'avi', 'mkv', 'pptx', 'ppt', 'zip', 'psd', 'ai'];

    private string $racine;

    protected function setUp(): void
    {
        $this->racine = dirname(__DIR__, 2);

        if (!is_dir($this->racine . '/.git')) {
            $this->markTestSkipped("Hors dépôt Git : rien à vérifier.");
        }
    }

    /** @return list<string> les chemins suivis par Git, relatifs à la racine */
    private function fichiersSuivis(): array
    {
        $sortie = [];
        $code   = 0;
        exec(
            'git -C ' . escapeshellarg($this->racine) . ' ls-files 2>&1',
            $sortie,
            $code
        );

        if ($code !== 0) {
            $this->markTestSkipped('git ls-files indisponible dans cet environnement.');
        }

        return array_values(array_filter(array_map('trim', $sortie), static fn($l) => $l !== ''));
    }

    public function testAucunFichierSuiviNeDepasseDeuxMega(): void
    {
        $lourds = [];

        foreach ($this->fichiersSuivis() as $relatif) {
            $chemin = $this->racine . '/' . $relatif;
            if (!is_file($chemin)) {
                continue; // supprimé dans la copie de travail, pas notre sujet
            }
            $taille = (int) filesize($chemin);
            if ($taille > self::POIDS_MAX_OCTETS) {
                $lourds[] = sprintf('%s (%.1f Mo)', $relatif, $taille / 1048576);
            }
        }

        $this->assertSame(
            [],
            $lourds,
            "Fichiers suivis trop lourds : " . implode(', ', $lourds) . ".\n"
            . "Un binaire ne se diffe pas : Git en garde une copie entière à chaque "
            . "modification, et l'historique ne rétrécit jamais. Voir docs/LIVRABLES.md."
        );
    }

    public function testAucunFormatDeLivrableNestSuivi(): void
    {
        $interdits = [];

        foreach ($this->fichiersSuivis() as $relatif) {
            $ext = strtolower(pathinfo($relatif, PATHINFO_EXTENSION));
            if (in_array($ext, self::EXTENSIONS_INTERDITES, true)) {
                $interdits[] = $relatif;
            }
        }

        $this->assertSame(
            [],
            $interdits,
            "Ces formats sont des livrables, pas des sources : " . implode(', ', $interdits) . ".\n"
            . "Les déposer dans une release ou un espace partagé. Voir docs/LIVRABLES.md."
        );
    }

    public function testLesPdfReconstructiblesNeSontPasSuivis(): void
    {
        // Un PDF dont le HTML voisin est versionné est une sortie de build.
        $suivis  = array_flip($this->fichiersSuivis());
        $doublons = [];

        foreach (array_keys($suivis) as $relatif) {
            if (strtolower(pathinfo($relatif, PATHINFO_EXTENSION)) !== 'pdf') {
                continue;
            }
            $source = substr($relatif, 0, -4) . '.html';
            if (isset($suivis[$source])) {
                $doublons[] = "$relatif (régénérable depuis $source)";
            }
        }

        $this->assertSame(
            [],
            $doublons,
            "PDF suivis alors que leur source HTML l'est aussi : "
            . implode(', ', $doublons) . "."
        );
    }

    public function testLesSourcesDesPdfRetiresSontToujoursLa(): void
    {
        // Le revers : retirer les PDF n'a de sens que si de quoi les refaire
        // reste versionné. Sans cela, on n'a pas allégé le dépôt, on a perdu
        // les documents.
        $suivis = array_flip($this->fichiersSuivis());

        foreach ([
            'docs/Affiches_Rue_StudentLink.html',
            'docs/Charte_Graphique_StudentLink.html',
            'docs/Flyer_Bars_StudentLink.html',
            'docs/Plaquette_Etudiants_StudentLink.html',
            'docs/Plaquette_Partenaires_StudentLink.html',
            'Script_Oral_StudentLink.docx',
        ] as $source) {
            $this->assertArrayHasKey(
                $source,
                $suivis,
                "$source n'est plus versionné, et le PDF qui en découlait non plus : "
                . "ce document n'existe plus nulle part dans le dépôt."
            );
        }
    }
}
