<?php
declare(strict_types=1);
// Frontière HTTP de l'API matches : validation liste blanche, pagination filtrée, détail unitaire.
final class MatchApiController extends Controller
{
    private int $psgTeamId;

    public function __construct(
        private ?MatchRepository $matches = null,
        ?int $psgTeamId = null,
        ?TeamRepository $teams = null,
    ) {
        $this->matches ??= new MatchRepository();
        $teams ??= new TeamRepository();
        $this->psgTeamId = $psgTeamId ?? $teams->psgId();
    }

    public function index(Request $r, array $params): void
    {
        $this->json($this->buildIndex($_GET));
    }

    public function buildIndex(array $query): array
    {
        $page = Validator::int($query['page'] ?? 1, 1, 9999, 1);
        $perPage = Validator::int($query['per_page'] ?? 20, 1, 50, 20);
        $competitionId = isset($query['competition_id'])
            ? (Validator::int($query['competition_id'], 1, PHP_INT_MAX, 0) ?: null)
            : null;
        $result = isset($query['result'])
            ? Validator::inList($query['result'], ['W', 'D', 'L'], '') ?: null
            : null;

        $res = $this->matches->paginate($page, $perPage, $competitionId, $result, $this->psgTeamId);
        $items = array_map(fn(MatchGame $m) => $this->summarize($m), $res['items']);

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
            $this->json(['error' => 'match introuvable'], 404);
            return;
        }
        $this->json($env);
    }

    public function buildShow(int $id): ?array
    {
        $match = $this->matches->find($id);
        if ($match === null) {
            return null;
        }
        return Response::apiEnvelope($this->summarize($match));
    }

    private function summarize(MatchGame $m): array
    {
        return [
            'id' => $m->id,
            'competitionId' => $m->competitionId,
            'roundLabel' => $m->roundLabel,
            'playedAt' => $m->playedAt,
            'homeTeamId' => $m->homeTeamId,
            'awayTeamId' => $m->awayTeamId,
            'homeGoals' => $m->homeGoals,
            'awayGoals' => $m->awayGoals,
            'result' => $m->result($this->psgTeamId),
            'possession' => $m->psgPossession,
            'venue' => $m->venue,
            'attendance' => $m->attendance,
        ];
    }
}
