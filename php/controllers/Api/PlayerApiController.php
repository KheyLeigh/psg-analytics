<?php
declare(strict_types=1);
// Frontière HTTP de l'API joueurs : validation liste blanche, délégation au repository/service, enveloppe.
final class PlayerApiController extends Controller
{
    public function __construct(
        private ?PlayerRepository $players = null,
        private ?StatisticRepository $stats = null,
        private ?ComparisonService $comparison = null,
    ) {
        $this->players ??= new PlayerRepository();
        $this->stats ??= new StatisticRepository();
        $this->comparison ??= new ComparisonService($this->stats, $this->players);
    }

    public function index(Request $r, array $params): void
    {
        $this->json($this->buildIndex($_GET));
    }

    public function buildIndex(array $query): array
    {
        $page = Validator::int($query['page'] ?? 1, 1, 9999, 1);
        $perPage = Validator::int($query['per_page'] ?? 20, 1, 50, 20);
        $sort = Validator::inList($query['sort'] ?? 'last_name', ['last_name', 'shirt_number', 'position', 'nationality'], 'last_name');
        $order = Validator::inList($query['order'] ?? 'ASC', ['ASC', 'DESC'], 'ASC');
        $position = isset($query['position'])
            ? Validator::inList($query['position'], ['GK', 'DF', 'MF', 'FW'], '') ?: null
            : null;

        $res = $this->players->paginate($page, $perPage, $sort, $order, $position);
        $items = array_map(static fn(Player $p) => [
            'id' => $p->id, 'number' => $p->shirtNumber, 'name' => $p->fullName(),
            'position' => $p->position, 'nationality' => $p->nationality,
        ], $res['items']);

        return Response::apiEnvelope($items, [
            'page' => $page, 'per_page' => $perPage, 'total' => $res['total'],
            'total_pages' => (int) ceil($res['total'] / $perPage),
        ]);
    }

    public function show(Request $r, array $params): void
    {
        $id = Validator::int($params['id'] ?? 0, 1, PHP_INT_MAX, 0);
        $env = $this->buildShow($id);
        if ($env === null) {
            Response::json(['error' => 'joueur introuvable'], 404);
            return;
        }
        Response::json($env);
    }

    public function buildShow(int $id): ?array
    {
        $player = $this->players->find($id);
        if ($player === null) {
            return null;
        }

        return Response::apiEnvelope([
            'id' => $player->id,
            'number' => $player->shirtNumber,
            'name' => $player->fullName(),
            'position' => $player->position,
            'detailedPosition' => $player->detailedPosition,
            'foot' => $player->foot,
            'nationality' => $player->nationality,
            'birthDate' => $player->birthDate,
            'heightCm' => $player->heightCm,
            'isCaptain' => $player->isCaptain,
        ]);
    }

    public function timeline(Request $r, array $params): void
    {
        $id = Validator::int($params['id'] ?? 0, 1, PHP_INT_MAX, 0);
        $env = $this->buildTimeline($id);
        if ($env === null) {
            Response::json(['error' => 'joueur introuvable'], 404);
            return;
        }
        Response::json($env);
    }

    public function buildTimeline(int $id): ?array
    {
        if ($this->players->find($id) === null) {
            return null;
        }
        return Response::apiEnvelope($this->stats->timeline($id));
    }

    public function compare(Request $r, array $params): void
    {
        $this->json($this->buildCompare($_GET));
    }

    public function buildCompare(array $query): array
    {
        $idA = Validator::int($query['a'] ?? 0, 1, PHP_INT_MAX, 0);
        $idB = Validator::int($query['b'] ?? 0, 1, PHP_INT_MAX, 0);
        $result = $this->comparison->compare($idA, $idB);

        return Response::apiEnvelope([
            'a' => $this->formatSide($result['a']),
            'b' => $this->formatSide($result['b']),
            'axes' => $result['axes'],
        ]);
    }

    private function formatSide(array $side): array
    {
        $player = $side['player'];
        return [
            'player' => $player !== null ? ['id' => $player->id, 'name' => $player->fullName()] : null,
            'totals' => $side['totals'],
            'normalized' => $side['normalized'],
        ];
    }
}
