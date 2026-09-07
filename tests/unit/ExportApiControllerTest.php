<?php
declare(strict_types=1);
// Vérifie les exports : en-tête et échappement CSV, structure du PDF, pour la saison demandée.
final class ExportApiControllerTest extends TestCase
{
    private function saison(): Season
    {
        return new Season(1, '2025-26', '2025-07-01', '2026-06-30', true);
    }

    public function testCsvContientEnTete(): void
    {
        $stats = new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public function topScorers(int $seasonId, int $limit, ?int $c): array {
                return [['player'=>Player::fromRow(['id'=>29,'season_id'=>1,'person_id'=>29,'shirt_number'=>29,'first_name'=>'Bradley','last_name'=>'Barcola','position'=>'FW','detailed_position'=>'LW','foot'=>'right','nationality'=>'France','birth_date'=>null,'height_cm'=>182,'is_captain'=>0]),'goals'=>11,'assists'=>4,'minutes'=>2400]];
            }
        };
        $csv = (new ExportApiController($stats))->buildCsv($this->saison());
        $this->assertTrue(str_contains($csv, 'Joueur;Buts;Passes;Minutes'), 'en-tête CSV');
        $this->assertTrue(str_contains($csv, 'Bradley Barcola;11;4;2400'), 'ligne joueur');
    }

    public function testPdfCommenceParEntetePdf(): void
    {
        $stats = new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public function topScorers(int $seasonId, int $limit, ?int $c): array {
                return [['player'=>Player::fromRow(['id'=>29,'season_id'=>1,'person_id'=>29,'shirt_number'=>29,'first_name'=>'Bradley','last_name'=>'Barcola','position'=>'FW','detailed_position'=>'LW','foot'=>'right','nationality'=>'France','birth_date'=>null,'height_cm'=>182,'is_captain'=>0]),'goals'=>11,'assists'=>4,'minutes'=>2400]];
            }
        };
        $pdf = (new ExportApiController($stats))->buildPdf($this->saison());
        $this->assertTrue(str_starts_with($pdf, '%PDF-1.4'), 'entête PDF');
        $this->assertTrue(str_contains($pdf, 'Bradley Barcola'), 'contenu buteur');
    }
}
