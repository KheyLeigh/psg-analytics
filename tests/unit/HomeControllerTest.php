<?php

declare(strict_types=1);

// tests/unit/HomeControllerTest.php
// Vérifie l'assemblage des données de l'Accueil (buildViewData), isolé du rendu :
// total de points lu du cumul, forme remise dans l'ordre chronologique, top buteurs
// mis au format compact avec le meilleur total pour l'échelle des jauges.
final class HomeControllerTest extends TestCase
{
    private function makePlayer(int $id, string $first, string $last): Player
    {
        return Player::fromRow([
            'id' => $id, 'season_id' => 1, 'shirt_number' => $id, 'first_name' => $first,
            'last_name' => $last, 'position' => 'FW', 'detailed_position' => 'ST',
            'foot' => 'right', 'nationality' => 'France', 'birth_date' => null,
            'height_cm' => 180, 'is_captain' => 0,
        ]);
    }

    private function controller(): HomeController
    {
        $player = fn (int $id, string $f, string $l) => $this->makePlayer($id, $f, $l);
        $stats = new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public array $rows = [];
            public function topScorers(int $limit, ?int $competitionId): array
            {
                return array_slice($this->rows, 0, $limit);
            }
        };
        $stats->rows = [
            ['player' => $player(9, 'Ousmane', 'Dembélé'), 'goals' => 21, 'assists' => 6, 'minutes' => 2600],
            ['player' => $player(29, 'Bradley', 'Barcola'), 'goals' => 13, 'assists' => 8, 'minutes' => 2500],
        ];

        $matches = new class(new PDO('sqlite::memory:')) extends MatchRepository {
            public function seasonRecord(int $psgTeamId, int $competitionId): array
            {
                return ['wins' => 24, 'draws' => 4, 'losses' => 6, 'goals_for' => 74,
                    'goals_against' => 29, 'clean_sheets' => 15, 'avg_possession' => 63.2, 'played' => 34];
            }
            public function cumulativePoints(int $psgTeamId, int $competitionId): array
            {
                return [['x' => 1, 'y' => 3, 'result' => 'W', 'label' => 'J1'],
                    ['x' => 2, 'y' => 6, 'result' => 'W', 'label' => 'J2'],
                    ['x' => 3, 'y' => 76, 'result' => 'W', 'label' => 'J34']];
            }
            public function recentDetailed(int $psgTeamId, int $limit): array
            {
                // Du plus récent au plus ancien, comme la vraie requête (ORDER BY DESC).
                return [
                    ['competition' => 'Ligue 1', 'opponent' => 'Nice', 'home' => true, 'goalsFor' => 3, 'goalsAgainst' => 0, 'result' => 'W'],
                    ['competition' => 'Ligue 1', 'opponent' => 'Lens', 'home' => false, 'goalsFor' => 1, 'goalsAgainst' => 1, 'result' => 'D'],
                    ['competition' => 'Ligue 1', 'opponent' => 'Lyon', 'home' => true, 'goalsFor' => 2, 'goalsAgainst' => 1, 'result' => 'W'],
                ];
            }
        };

        $comps = new class(new PDO('sqlite::memory:')) extends CompetitionRepository {
            public function leagueId(): ?int { return 1; }
        };

        // TeamRepository est final (pas de doublure par héritage) : on l'adosse à un
        // PDO en mémoire portant une table teams minimale pour résoudre psgId().
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE teams (id INTEGER PRIMARY KEY, is_psg INTEGER)');
        $pdo->exec('INSERT INTO teams (id, is_psg) VALUES (1, 1)');
        $teams = new TeamRepository($pdo);

        return new HomeController($stats, $matches, $comps, $teams);
    }

    public function testTotalPointsVientDuCumul(): void
    {
        $data = $this->controller()->buildViewData();
        $this->assertSame(76, $data['totalPoints']);
        $this->assertSame('home', $data['page']);
    }

    public function testFormeRemiseDansLordreChronologique(): void
    {
        $data = $this->controller()->buildViewData();
        // recentDetailed renvoie du plus récent au plus ancien : la forme est inversée.
        $this->assertSame(['W', 'D', 'W'], $data['form']);
    }

    public function testTopButeursCompactsAvecMeilleurTotal(): void
    {
        $data = $this->controller()->buildViewData();
        $this->assertSame(21, $data['topGoals']);
        $this->assertSame('Dembélé', $data['topScorers'][0]['name']);
        $this->assertSame('Bradley', $data['topScorers'][1]['first']);
        $this->assertSame(13, $data['topScorers'][1]['goals']);
    }
}
