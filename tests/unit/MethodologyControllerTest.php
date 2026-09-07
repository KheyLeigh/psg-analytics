<?php
declare(strict_types=1);

// Vérifie que la page Méthodologie assemble bien les sources et la couverture par
// table fournies par le repository pour la saison sélectionnée, sans les recalculer.
final class MethodologyControllerTest extends TestCase
{
    private function sourcesDouble(): SourceRepository
    {
        return new class(new PDO('sqlite::memory:')) extends SourceRepository {
            public array $seen = [];
            public function sources(): array
            {
                return [
                    ['label' => 'FBref', 'confidence' => 'verified', 'url' => 'https://fbref.com', 'note' => 'Scores', 'collectedAt' => '2026-08-11'],
                    ['label' => 'StatGenerator', 'confidence' => 'estimated', 'url' => null, 'note' => 'Attribution', 'collectedAt' => '2026-07-20'],
                ];
            }
            public function coverageByTable(int $seasonId): array
            {
                $this->seen[] = $seasonId;
                return [
                    ['label' => 'Matchs', 'total' => 55, 'verified' => 55, 'pct' => 100],
                    ['label' => 'Statistiques par match', 'total' => 515, 'verified' => 0, 'pct' => 0],
                ];
            }
        };
    }

    private function seasonsDouble(): SeasonRepository
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE seasons (id INTEGER PRIMARY KEY, label TEXT, start_date TEXT, end_date TEXT, is_current INT)');
        $pdo->exec("INSERT INTO seasons VALUES (1,'2025-26','2025-07-01','2026-06-30',1)");
        return new SeasonRepository($pdo);
    }

    public function testAssembleSourcesEtCouverturePourLaSaisonDemandee(): void
    {
        $sources = $this->sourcesDouble();
        $season = new Season(1, '2025-26', '2025-07-01', '2026-06-30', true);
        $data = (new MethodologyController($sources, $this->seasonsDouble()))->buildViewData($season);

        $this->assertSame('methodology', $data['page']);
        $this->assertSame(2, count($data['sources']));
        $this->assertSame('verified', $data['sources'][0]['confidence']);
        $this->assertSame('estimated', $data['sources'][1]['confidence']);

        $this->assertSame(100, $data['coverage'][0]['pct']);
        $this->assertSame(0, $data['coverage'][1]['pct']);
        $this->assertSame(515, $data['coverage'][1]['total']);

        $this->assertSame([1], $sources->seen, 'coverageByTable reçoit bien la saison demandée');
        $this->assertSame('2025-26', $data['selectedSeason']);
    }
}
