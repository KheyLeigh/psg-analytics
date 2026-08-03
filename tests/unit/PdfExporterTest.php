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
}
