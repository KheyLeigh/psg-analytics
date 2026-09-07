<?php
declare(strict_types=1);

// Vérifie l'assemblage des données du Dashboard (buildViewData), isolé du rendu :
// la saison demandée est bien celle transmise aux repositories, et navData est
// fournie pour le sélecteur de saison du header.
final class DashboardControllerTest extends TestCase
{
    private function makePlayer(int $id, string $first, string $last): Player
    {
        return Player::fromRow([
            'id' => $id, 'season_id' => 1, 'person_id' => $id, 'shirt_number' => $id, 'first_name' => $first,
            'last_name' => $last, 'position' => 'FW', 'detailed_position' => 'ST',
            'foot' => 'right', 'nationality' => 'France', 'birth_date' => null,
            'height_cm' => 180, 'is_captain' => 0,
        ]);
    }

    private function controller(): array
    {
        $seen = [];
        $player = fn (int $id, string $f, string $l) => $this->makePlayer($id, $f, $l);
        $stats = new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public array $seen = [];
            public function topScorers(int $seasonId, int $limit, ?int $competitionId): array {
                $this->seen[] = ['topScorers', $seasonId];
                return [['player' => Player::fromRow(['id'=>9,'season_id'=>1,'person_id'=>9,'shirt_number'=>9,'first_name'=>'Ousmane','last_name'=>'Dembélé','position'=>'FW','detailed_position'=>'CF','foot'=>'both','nationality'=>'France','birth_date'=>null,'height_cm'=>178,'is_captain'=>0]), 'goals'=>21, 'assists'=>6, 'minutes'=>2600]];
            }
        };
        $matches = new class(new PDO('sqlite::memory:')) extends MatchRepository {
            public array $seen = [];
            public function seasonRecord(int $seasonId, int $psgTeamId, int $competitionId): array {
                $this->seen[] = ['seasonRecord', $seasonId];
                return ['wins'=>24,'draws'=>4,'losses'=>6,'goals_for'=>74,'goals_against'=>29,'clean_sheets'=>15,'avg_possession'=>63.2,'played'=>34];
            }
            public function cumulativePoints(int $seasonId, int $psgTeamId, int $competitionId): array {
                $this->seen[] = ['cumulativePoints', $seasonId];
                return [['x'=>1,'y'=>3,'result'=>'W','label'=>'J1'], ['x'=>34,'y'=>76,'result'=>'W','label'=>'J34']];
            }
            public function recentDetailed(int $seasonId, int $psgTeamId, int $limit): array {
                $this->seen[] = ['recentDetailed', $seasonId];
                return [['competition'=>'Ligue 1','opponent'=>'Nice','home'=>true,'goalsFor'=>3,'goalsAgainst'=>0,'result'=>'W']];
            }
        };
        $comps = new class(new PDO('sqlite::memory:')) extends CompetitionRepository {
            public function leagueId(): ?int { return 1; }
        };
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE teams (id INTEGER PRIMARY KEY, is_psg INTEGER)');
        $pdo->exec('INSERT INTO teams (id, is_psg) VALUES (1, 1)');
        $teams = new TeamRepository($pdo);
        $seasonsPdo = new PDO('sqlite::memory:');
        $seasonsPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $seasonsPdo->exec('CREATE TABLE seasons (id INTEGER PRIMARY KEY, label TEXT, start_date TEXT, end_date TEXT, is_current INT)');
        $seasonsPdo->exec("INSERT INTO seasons VALUES (7,'2026-27','2026-07-01','2027-06-30',1)");
        $seasons = new SeasonRepository($seasonsPdo);

        return [new DashboardController($stats, $matches, $comps, $teams, $seasons), $stats, $matches];
    }

    public function testBuildViewDataTransmetLaSaisonDemandeeAuxRepositories(): void
    {
        [$ctrl, $stats, $matches] = $this->controller();
        $season = new Season(7, '2026-27', '2026-07-01', '2027-06-30', true);

        $data = $ctrl->buildViewData($season);

        // topScorers est appelée trois fois avec la saison demandée : deux fois via
        // KpiService::dashboard() (meilleur buteur, puis meilleur passeur), une fois
        // directement dans buildViewData() pour le top 5 buteurs affiché.
        $this->assertSame(
            [['topScorers', 7], ['topScorers', 7], ['topScorers', 7]],
            $stats->seen
        );
        // seasonRecord est appelée deux fois avec la saison demandée : une fois via
        // KpiService::dashboard(), une fois directement dans buildViewData() pour
        // construire le bilan de saison affiché.
        $this->assertSame(
            [['seasonRecord', 7], ['seasonRecord', 7], ['cumulativePoints', 7], ['recentDetailed', 7]],
            $matches->seen
        );
        $this->assertSame('dashboard', $data['page']);
        $this->assertSame(76, $data['totalPoints']);
    }

    public function testBuildViewDataExposeLaNavigationDeSaison(): void
    {
        [$ctrl] = $this->controller();
        $season = new Season(7, '2026-27', '2026-07-01', '2027-06-30', true);

        $data = $ctrl->buildViewData($season);

        $this->assertSame('2026-27', $data['selectedSeason']);
        $this->assertSame(['2026-27'], array_column($data['seasons'], 'label'));
    }
}
