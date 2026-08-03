<?php
declare(strict_types=1);
// Vérifie l'agrégation des KPI du tableau de bord depuis des repositories bouchonnés.
final class KpiServiceTest extends TestCase
{
    public function testDashboardAgregeLesKpi(): void
    {
        $stats = new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public function topScorers(int $limit, ?int $competitionId): array {
                return [['player' => Player::fromRow(['id'=>29,'season_id'=>1,'shirt_number'=>29,'first_name'=>'Bradley','last_name'=>'Barcola','position'=>'FW','detailed_position'=>'LW','foot'=>'right','nationality'=>'France','birth_date'=>null,'height_cm'=>182,'is_captain'=>0]), 'goals'=>11, 'assists'=>4, 'minutes'=>2400]];
            }
        };
        $matches = new class(new PDO('sqlite::memory:')) extends MatchRepository {
            public function seasonRecord(int $psgTeamId, int $competitionId): array { return ['wins'=>24,'draws'=>4,'losses'=>6,'goals_for'=>74,'goals_against'=>29,'clean_sheets'=>15,'avg_possession'=>63.2,'played'=>34]; }
        };
        $comps = new class(new PDO('sqlite::memory:')) extends CompetitionRepository {
            public function leagueId(): ?int { return 1; }
        };
        $kpi = new KpiService($stats, $matches, $comps, 1);
        $d = $kpi->dashboard();
        $this->assertSame('Bradley Barcola', $d['top_scorer']['name']);
        $this->assertSame(24, $d['wins']);
        $this->assertSame(2.18, round($d['goals_per_match'], 2)); // 74/34
    }
}
