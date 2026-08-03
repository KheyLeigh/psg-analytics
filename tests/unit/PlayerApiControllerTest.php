<?php
declare(strict_types=1);
// tests/unit/PlayerApiControllerTest.php
final class PlayerApiControllerTest extends TestCase
{
    private function controller(): PlayerApiController
    {
        $repo = new class(new PDO('sqlite::memory:')) extends PlayerRepository {
            public function paginate(int $page,int $perPage,string $sort,string $order,?string $pos): array {
                return ['items' => [], 'total' => 24];
            }
        };
        return new PlayerApiController($repo);
    }

    public function testEnveloppeContientMeta(): void
    {
        $env = $this->controller()->buildIndex(['page' => '1', 'per_page' => '20']);
        $this->assertSame(24, $env['meta']['total']);
        $this->assertSame(1, $env['meta']['page']);
        $this->assertSame(2, $env['meta']['total_pages']);
    }

    public function testPerPagePlafonneA50(): void
    {
        $env = $this->controller()->buildIndex(['per_page' => '9999']);
        $this->assertSame(50, $env['meta']['per_page']);
    }

    private function playerRow(int $id, string $lastName): array
    {
        return [
            'id' => $id, 'season_id' => 1, 'shirt_number' => $id, 'first_name' => 'Prenom',
            'last_name' => $lastName, 'position' => 'FW', 'detailed_position' => 'ST',
            'foot' => 'right', 'nationality' => 'France', 'birth_date' => null,
            'height_cm' => 180, 'is_captain' => 0,
        ];
    }

    public function testShowRetourneNullSiAbsent(): void
    {
        $repo = new class(new PDO('sqlite::memory:')) extends PlayerRepository {
            public function find(int $id): ?Player { return null; }
        };
        $controller = new PlayerApiController($repo);
        $this->assertSame(null, $controller->buildShow(999));
    }

    public function testShowRetourneLeJoueur(): void
    {
        $row = $this->playerRow(29, 'Barcola');
        $repo = new class($row, new PDO('sqlite::memory:')) extends PlayerRepository {
            public function __construct(private array $row, ?PDO $pdo = null) { parent::__construct($pdo); }
            public function find(int $id): ?Player { return Player::fromRow($this->row); }
        };
        $controller = new PlayerApiController($repo);
        $env = $controller->buildShow(29);
        $this->assertSame('Prenom Barcola', $env['data']['name']);
    }

    public function testTimelineEnveloppeLesMatches(): void
    {
        $row = $this->playerRow(29, 'Barcola');
        $repo = new class($row, new PDO('sqlite::memory:')) extends PlayerRepository {
            public function __construct(private array $row, ?PDO $pdo = null) { parent::__construct($pdo); }
            public function find(int $id): ?Player { return Player::fromRow($this->row); }
        };
        $stats = new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public function timeline(int $playerId): array {
                return [['matchId' => 1, 'playedAt' => '2025-08-10', 'goals' => 1, 'assists' => 0, 'minutes' => 90, 'rating' => 7.2]];
            }
        };
        $controller = new PlayerApiController($repo, $stats);
        $env = $controller->buildTimeline(29);
        $this->assertSame(1, count($env['data']));
        $this->assertSame(1, $env['data'][0]['goals']);
    }

    public function testCompareRenvoieLesDeuxJoueurs(): void
    {
        $rowA = $this->playerRow(1, 'Alpha');
        $rowB = $this->playerRow(2, 'Beta');
        $players = new class($rowA, $rowB, new PDO('sqlite::memory:')) extends PlayerRepository {
            public function __construct(private array $rowA, private array $rowB, ?PDO $pdo = null) { parent::__construct($pdo); }
            public function find(int $id): ?Player {
                return Player::fromRow($id === 1 ? $this->rowA : $this->rowB);
            }
        };
        $stats = new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public function seasonTotalsByPlayer(int $playerId): array {
                return ['goals' => $playerId === 1 ? 10 : 5, 'assists' => 2, 'minutes' => 900, 'shots' => 20, 'duelsWon' => 30, 'rating' => 7.0];
            }
        };
        $comparison = new ComparisonService($stats, $players);
        $controller = new PlayerApiController($players, $stats, $comparison);
        $env = $controller->buildCompare(['a' => '1', 'b' => '2']);
        $this->assertSame('Prenom Alpha', $env['data']['a']['player']['name']);
        $this->assertSame(1.0, $env['data']['a']['normalized']['goals']);
    }
}
