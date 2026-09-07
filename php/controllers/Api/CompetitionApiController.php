<?php
declare(strict_types=1);
// Frontière HTTP de l'API compétitions : liste et bilan (V/N/D, buts) de PSG par compétition.
final class CompetitionApiController extends Controller
{
    private int $psgTeamId;

    public function __construct(
        private ?CompetitionRepository $competitions = null,
        ?int $psgTeamId = null,
        ?TeamRepository $teams = null,
        private ?SeasonRepository $seasons = null,
    ) {
        $this->competitions ??= new CompetitionRepository();
        $teams ??= new TeamRepository();
        $this->psgTeamId = $psgTeamId ?? $teams->psgId();
        $this->seasons ??= new SeasonRepository();
    }

    public function index(Request $r, array $params): void
    {
        $slug = Validator::string($r->query('saison', ''), 16);
        $season = $this->seasons->resolve($slug !== '' ? $slug : null);
        $this->json($this->buildIndex($season));
    }

    public function buildIndex(Season $season): array
    {
        $standingsByCompetition = [];
        foreach ($this->competitions->standings($season->id, $this->psgTeamId) as $standing) {
            $standingsByCompetition[$standing['competitionId']] = $standing;
        }

        $items = array_map(static function (Competition $c) use ($standingsByCompetition): array {
            $s = $standingsByCompetition[$c->id] ?? null;
            return [
                'id' => $c->id,
                'name' => $c->name,
                'type' => $c->type,
                'scope' => $c->scope,
                'wins' => $s['wins'] ?? 0,
                'draws' => $s['draws'] ?? 0,
                'losses' => $s['losses'] ?? 0,
                'goalsFor' => $s['goalsFor'] ?? 0,
                'goalsAgainst' => $s['goalsAgainst'] ?? 0,
            ];
        }, $this->competitions->all());

        return Response::apiEnvelope($items);
    }
}
