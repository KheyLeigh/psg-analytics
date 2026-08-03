<?php
declare(strict_types=1);
// Vérifie que le PDF généré à la main a une structure minimale valide.
final class PdfExporterTest extends TestCase
{
    public function testGenereUnPdfMinimalValide(): void
    {
        $pdf = PdfExporter::simpleReport('Bilan PSG 2025-26', [
            ['heading' => 'Buteurs', 'lines' => ['Barcola 11', 'Dembélé 10']],
        ]);

        $this->assertTrue(str_starts_with($pdf, '%PDF-1.'), 'en-tête de version PDF');
        $this->assertTrue(str_contains($pdf, '%%EOF'), 'marqueur de fin de fichier');
    }

    public function testEchappeParenthesesEtAntiSlashDansLeTexte(): void
    {
        $pdf = PdfExporter::simpleReport('Titre (test)', [
            ['heading' => 'Section', 'lines' => ['chemin C:\\dossier (note)']],
        ]);

        $this->assertTrue(str_contains($pdf, '\\(test\\)'), 'parenthèses échappées');
        $this->assertTrue(str_contains($pdf, 'C:\\\\dossier'), 'antislash échappé');
    }

    // Reparse la table xref générée et vérifie que chaque offset déclaré pointe
    // réellement sur le début de son objet « N 0 obj », et que startxref pointe sur xref.
    public function testXrefPointeVersLeDebutReelDeChaqueObjet(): void
    {
        $pdf = PdfExporter::simpleReport('Bilan PSG 2025-26', [
            ['heading' => 'Buteurs', 'lines' => ['Barcola 11', 'Dembélé 10']],
        ]);

        $this->assertTrue(
            (bool) preg_match('/startxref\n(\d+)\n%%EOF/', $pdf, $matches),
            'startxref suivi d\'un offset numérique'
        );
        $xrefOffset = (int) $matches[1];
        $this->assertSame('xref', substr($pdf, $xrefOffset, 4), 'startxref pointe sur le mot-clé xref');

        $lines = explode("\n", substr($pdf, $xrefOffset));
        [, $total] = array_map('intval', explode(' ', trim($lines[1])));

        for ($num = 1; $num < $total; $num++) {
            $entry = $lines[2 + $num];
            $offset = (int) substr($entry, 0, 10);
            $expected = "{$num} 0 obj";
            $this->assertSame(
                $expected,
                substr($pdf, $offset, strlen($expected)),
                "l'objet {$num} commence bien à l'offset déclaré dans xref"
            );
        }
    }
}
