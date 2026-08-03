<?php
declare(strict_types=1);
// tests/unit/CsvExporterTest.php
final class CsvExporterTest extends TestCase
{
    public function testGenereEnTeteEtLignes(): void
    {
        $csv = CsvExporter::fromRows(['Joueur', 'Buts'], [['Barcola', 11], ['Dembélé', 10]]);
        $this->assertTrue(str_contains($csv, 'Joueur;Buts'), 'en-tête');
        $this->assertTrue(str_contains($csv, 'Barcola;11'), 'ligne 1');
    }

    public function testEchappeGuillemets(): void
    {
        $csv = CsvExporter::fromRows(['A'], [['dit "oui"']]);
        $this->assertTrue(str_contains($csv, '"dit ""oui"""'), 'guillemets doublés');
    }
}
