<?php
declare(strict_types=1);
// tests/unit/MatchApiControllerTest.php
final class MatchApiControllerTest extends TestCase
{
    private function matchRow(int $id): array
    {
        return [
            'id' => $id, 'season_id' => 1, 'competition_id' => 1, 'round_label' => 'J1',
            'played_at' => '2025-08-16', 'home_team_id' => 1, 'away_team_id' => 2,
            'home_goals' => 2, 'away_goals' => 1, 'went_to_extra' => 0, 'penalty_shootout' => 0,
            'penalty_score' => null, 'attendance' => 45000, 'psg_possession' => 61.5,
            'psg_shots' => 14, 'psg_shots_on_target' => 6, 'source_id' => 1,
        ];
    }

    public function testBuildIndexEnveloppeLaPagination(): void
    {
        $repo = new class(new PDO('sqlite::memory:')) extends MatchRepository {
            public function paginate(int $page, int $perPage, ?int $c, ?string $r, int $psg): array {
                return ['items' => [MatchGame::fromRow(['id'=>1,'season_id'=>1,'competition_id'=>1,'round_label'=>'J1','played_at'=>'2025-08-16','home_team_id'=>1,'away_team_id'=>2,'home_goals'=>2,'away_goals'=>1,'went_to_extra'=>0,'penalty_shootout'=>0,'penalty_score'=>null,'attendance'=>45000,'psg_possession'=>61.5,'psg_shots'=>14,'psg_shots_on_target'=>6,'source_id'=>1])], 'total' => 34];
            }
        };
        $controller = new MatchApiController($repo, 1);
        $env = $controller->buildIndex(['per_page' => '10']);
        $this->assertSame(34, $env['meta']['total']);
        $this->assertSame('W', $env['data'][0]['result']);
    }

    public function testBuildShowRetourneNullSiAbsent(): void
    {
        $repo = new class(new PDO('sqlite::memory:')) extends MatchRepository {
            public function find(int $id): ?MatchGame { return null; }
        };
        $controller = new MatchApiController($repo, 1);
        $this->assertSame(null, $controller->buildShow(999));
    }

    public function testBuildShowRetourneLeMatch(): void
    {
        $row = $this->matchRow(5);
        $repo = new class($row, new PDO('sqlite::memory:')) extends MatchRepository {
            public function __construct(private array $row, ?PDO $pdo = null) { parent::__construct($pdo); }
            public function find(int $id): ?MatchGame { return MatchGame::fromRow($this->row); }
        };
        $controller = new MatchApiController($repo, 1);
        $env = $controller->buildShow(5);
        $this->assertSame(5, $env['data']['id']);
        $this->assertSame('W', $env['data']['result']);
    }
}
