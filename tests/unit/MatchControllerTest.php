<?php
declare(strict_types=1);

// Vérifie l'assemblage de la page Matchs (buildIndex/buildDetail), isolé du rendu :
// liste blanche du filtre résultat respectée côté serveur, résolution des noms
// d'équipe et de compétition, et marqueur de tirs au but transmis à la vue.
final class MatchControllerTest extends TestCase
{
    // Ligne de match cannée : finale gagnée aux tirs au but (PSG 1-1, t.a.b. 4-3).
    private function matchRow(array $over = []): array
    {
        return array_merge([
            'id' => 55, 'season_id' => 1, 'competition_id' => 2, 'round_label' => 'Final',
            'played_at' => '2026-05-30', 'home_team_id' => 1, 'away_team_id' => 2,
            'home_goals' => 1, 'away_goals' => 1, 'went_to_extra' => 1, 'penalty_shootout' => 1,
            'penalty_score' => '4-3', 'venue' => 'neutral', 'attendance' => 70000,
            'psg_possession' => 55.0, 'psg_shots' => 12, 'psg_shots_on_target' => 5, 'source_id' => 1,
        ], $over);
    }

    private function matchesDouble(): MatchRepository
    {
        return new class(new PDO('sqlite::memory:')) extends MatchRepository {
            public array $seen = [];
            public array $rows = [];
            public ?array $findRow = null;
            public function paginate(int $page, int $perPage, ?int $competitionId, ?string $result, int $psgTeamId): array
            {
                $this->seen = compact('page', 'perPage', 'competitionId', 'result', 'psgTeamId');
                return ['items' => array_map(static fn (array $r): MatchGame => MatchGame::fromRow($r), $this->rows), 'total' => count($this->rows)];
            }
            public function find(int $id): ?MatchGame
            {
                return $this->findRow !== null ? MatchGame::fromRow($this->findRow) : null;
            }
        };
    }

    private function teamsDouble(): TeamRepository
    {
        return new class(new PDO('sqlite::memory:')) extends TeamRepository {
            public function namesById(): array { return [1 => 'PSG', 2 => 'Arsenal']; }
        };
    }

    private function competitionsDouble(): CompetitionRepository
    {
        return new class(new PDO('sqlite::memory:')) extends CompetitionRepository {
            public function all(): array
            {
                return [new class {
                    public int $id = 2;
                    public string $name = 'Ligue des Champions';
                }];
            }
        };
    }

    public function testFiltreResultatHorsListeBlancheRejete(): void
    {
        $matches = $this->matchesDouble();
        $matches->rows = [$this->matchRow()];
        $ctrl = new MatchController($matches, $this->teamsDouble(), $this->competitionsDouble(), 1);

        $data = $ctrl->buildIndex(['result' => 'X', 'competition_id' => '2']);

        // 'X' hors liste blanche W/D/L : le repository reçoit null, pas la valeur brute.
        $this->assertSame(null, $matches->seen['result']);
        $this->assertSame(2, $matches->seen['competitionId']);

        $item = $data['matches'][0];
        $this->assertSame('PSG', $item['home']);
        $this->assertSame('Arsenal', $item['away']);
        $this->assertSame('Ligue des Champions', $item['competition']);
        $this->assertSame(true, $item['penaltyShootout']);
        $this->assertSame('4-3', $item['penaltyScore']);
    }

    public function testFicheMatchIntrouvableRenvoieNull(): void
    {
        $ctrl = new MatchController($this->matchesDouble(), $this->teamsDouble(), $this->competitionsDouble(), 1);
        $this->assertSame(null, $ctrl->buildDetail(999));
    }

    public function testFicheMatchResoutNomsEtStats(): void
    {
        $matches = $this->matchesDouble();
        $matches->findRow = $this->matchRow();
        $ctrl = new MatchController($matches, $this->teamsDouble(), $this->competitionsDouble(), 1);

        $data = $ctrl->buildDetail(55);
        $this->assertSame('PSG', $data['match']['home']);
        $this->assertSame('Arsenal', $data['match']['away']);
        $this->assertSame(true, $data['match']['penaltyShootout']);
        $this->assertSame(70000, $data['match']['attendance']);
        $this->assertSame(5, $data['match']['shotsOnTarget']);
    }
}
