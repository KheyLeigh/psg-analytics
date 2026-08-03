<?php
declare(strict_types=1);
// tests/unit/CompetitionApiControllerTest.php
final class CompetitionApiControllerTest extends TestCase
{
    public function testBuildIndexFusionneCompetitionsEtBilan(): void
    {
        $repo = new class(new PDO('sqlite::memory:')) extends CompetitionRepository {
            public function all(): array {
                return [
                    Competition::fromRow(['id' => 1, 'name' => 'Ligue 1', 'type' => 'league', 'scope' => 'domestic']),
                    Competition::fromRow(['id' => 2, 'name' => 'Ligue des champions', 'type' => 'cup', 'scope' => 'europe']),
                ];
            }
            public function standings(int $psgTeamId): array {
                return [
                    ['competitionId' => 1, 'competitionName' => 'Ligue 1', 'wins' => 24, 'draws' => 4, 'losses' => 6, 'goalsFor' => 74, 'goalsAgainst' => 29],
                ];
            }
        };
        $controller = new CompetitionApiController($repo, 1);
        $env = $controller->buildIndex();
        $this->assertSame(24, $env['data'][0]['wins']);
        $this->assertSame(0, $env['data'][1]['wins']);
    }
}
