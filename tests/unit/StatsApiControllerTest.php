<?php
declare(strict_types=1);
// tests/unit/StatsApiControllerTest.php
final class StatsApiControllerTest extends TestCase
{
    private function heatmapService(): HeatmapService
    {
        $stats = new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public function goalsByPlayerAndMonth(): array {
                return [
                    ['playerId' => 29, 'month' => '2025-08', 'goals' => 2],
                    ['playerId' => 29, 'month' => '2025-09', 'goals' => 1],
                    ['playerId' => 7, 'month' => '2025-08', 'goals' => 3],
                ];
            }
        };
        $players = new class(new PDO('sqlite::memory:')) extends PlayerRepository {
            public function find(int $id): ?Player {
                return Player::fromRow([
                    'id' => $id, 'season_id' => 1, 'shirt_number' => $id, 'first_name' => 'Prenom',
                    'last_name' => "Joueur{$id}", 'position' => 'FW', 'detailed_position' => 'ST',
                    'foot' => 'right', 'nationality' => 'France', 'birth_date' => null,
                    'height_cm' => 180, 'is_captain' => 0,
                ]);
            }
        };
        return new HeatmapService($stats, $players);
    }

    private function kpiService(): KpiService
    {
        $stats = new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public function topScorers(int $limit, ?int $competitionId): array {
                return [['player' => Player::fromRow(['id'=>29,'season_id'=>1,'shirt_number'=>29,'first_name'=>'Bradley','last_name'=>'Barcola','position'=>'FW','detailed_position'=>'LW','foot'=>'right','nationality'=>'France','birth_date'=>null,'height_cm'=>182,'is_captain'=>0]), 'goals'=>11, 'assists'=>4, 'minutes'=>2400]];
            }
        };
        $matches = new class(new PDO('sqlite::memory:')) extends MatchRepository {
            public function seasonRecord(int $psgTeamId, int $competitionId): array {
                return ['wins'=>24,'draws'=>4,'losses'=>6,'goals_for'=>74,'goals_against'=>29,'clean_sheets'=>15,'avg_possession'=>63.2,'played'=>34];
            }
        };
        $comps = new class(new PDO('sqlite::memory:')) extends CompetitionRepository {
            public function leagueId(): ?int { return 1; }
        };
        return new KpiService($stats, $matches, $comps, 1);
    }

    public function testBuildKpisEnveloppeLeDashboard(): void
    {
        $controller = new StatsApiController($this->kpiService());
        $env = $controller->buildKpis();
        $this->assertSame('Bradley Barcola', $env['data']['top_scorer']['name']);
        $this->assertSame(24, $env['data']['wins']);
    }

    public function testBuildDistributionAgregeParMois(): void
    {
        $controller = new StatsApiController($this->kpiService(), $this->heatmapService());
        $env = $controller->buildDistribution();
        $this->assertSame(5, $env['data']['by_month']['2025-08']);
        $this->assertSame(1, $env['data']['by_month']['2025-09']);
    }

    public function testBuildHeatmapRetourneLesMoisEtLignes(): void
    {
        $controller = new StatsApiController($this->kpiService(), $this->heatmapService());
        $env = $controller->buildHeatmap();
        $this->assertSame(['2025-08', '2025-09'], $env['data']['months']);
        $this->assertSame(2, count($env['data']['rows']));
    }
}
