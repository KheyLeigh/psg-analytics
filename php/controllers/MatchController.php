<?php

declare(strict_types=1);

// Page Matchs : la saison rencontre par rencontre. La liste est rendue côté serveur
// (SSR), filtrable par compétition et par résultat (liste blanche relue par le
// contrôleur), paginée. La fiche match montre les données vérifiées d'une rencontre
// (score, possession, tirs, affluence) et le résultat aux tirs au but le cas échéant.
// Les matchs ne stockent que des identifiants d'équipe et de compétition : le
// contrôleur résout les noms via TeamRepository et CompetitionRepository.
final class MatchController extends Controller
{
    private const RESULTS = ['W', 'D', 'L'];
    private const PER_PAGE = 12;

    private int $psgId;

    public function __construct(
        private ?MatchRepository $matches = null,
        private ?TeamRepository $teams = null,
        private ?CompetitionRepository $competitions = null,
        ?int $psgId = null,
    ) {
        $this->matches ??= new MatchRepository();
        $this->teams ??= new TeamRepository();
        $this->competitions ??= new CompetitionRepository();
        $this->psgId = $psgId ?? $this->teams->psgId();
    }

    public function index(Request $r, array $params): void
    {
        $this->render('matches', $this->buildIndex($_GET));
    }

    // Assemble la liste filtrée et paginée, isolée du rendu pour rester testable.
    public function buildIndex(array $query): array
    {
        $page = Validator::int($query['page'] ?? 1, 1, 9999, 1);
        $competitionId = isset($query['competition_id'])
            ? (Validator::int($query['competition_id'], 1, PHP_INT_MAX, 0) ?: null)
            : null;
        $result = isset($query['result'])
            ? (Validator::inList($query['result'], self::RESULTS, '') ?: null)
            : null;

        $res = $this->matches->paginate($page, self::PER_PAGE, $competitionId, $result, $this->psgId);

        $names = $this->teams->namesById();
        $competitions = $this->competitions->all();
        $competitionNames = [];
        foreach ($competitions as $c) {
            $competitionNames[$c->id] = $c->name;
        }

        $items = array_map(fn (MatchGame $m): array => $this->summarize($m, $names, $competitionNames), $res['items']);

        $total = (int) $res['total'];
        $totalPages = (int) max(1, (int) ceil($total / self::PER_PAGE));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        return [
            'title'        => 'Matchs · PSG Analytics',
            'page'         => 'matches',
            'matches'      => $items,
            'competitions' => array_map(static fn ($c): array => ['id' => $c->id, 'name' => $c->name], $competitions),
            'results'      => self::RESULTS,
            'filters'      => ['competition_id' => $competitionId, 'result' => $result],
            'meta'         => [
                'page'        => $page,
                'total'       => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function show(Request $r, array $params): void
    {
        $id = Validator::int($params['id'] ?? 0, 1, PHP_INT_MAX, 0);
        $data = $this->buildDetail($id);
        if ($data === null) {
            // Match introuvable : même rendu 404 que le front controller (index.php).
            Response::html(View::render('errors/404', [], 'main'), 404);
            return;
        }
        $this->render('match_detail', $data);
    }

    // Assemble la fiche d'un match (identité, score, résultat aux t.a.b. si besoin,
    // statistiques vérifiées), null si introuvable. Les événements détaillés ne sont
    // pas disponibles en base : la vue le signale honnêtement plutôt que d'inventer.
    public function buildDetail(int $id): ?array
    {
        $match = $this->matches->find($id);
        if ($match === null) {
            return null;
        }

        $names = $this->teams->namesById();
        $competitionNames = [];
        foreach ($this->competitions->all() as $c) {
            $competitionNames[$c->id] = $c->name;
        }

        $summary = $this->summarize($match, $names, $competitionNames);

        return [
            'title' => $summary['home'] . ' ' . $summary['homeGoals'] . '-' . $summary['awayGoals'] . ' ' . $summary['away'] . ' · PSG Analytics',
            'page'  => 'match_detail',
            'match' => $summary + [
                'attendance'     => $match->attendance,
                'shots'          => $match->psgShots,
                'shotsOnTarget'  => $match->psgShotsOnTarget,
                'wentToExtra'    => $match->wentToExtra,
            ],
        ];
    }

    // Projection d'affichage d'un match : noms résolus, résultat V/N/D côté PSG, et
    // marqueur de tirs au but si la rencontre s'est décidée ainsi.
    private function summarize(MatchGame $m, array $names, array $competitionNames): array
    {
        return [
            'id'              => $m->id,
            'competition'     => $competitionNames[$m->competitionId] ?? '',
            'round'           => $m->roundLabel,
            'playedAt'        => $m->playedAt,
            'home'            => $names[$m->homeTeamId] ?? ('Équipe ' . $m->homeTeamId),
            'away'            => $names[$m->awayTeamId] ?? ('Équipe ' . $m->awayTeamId),
            'homeGoals'       => $m->homeGoals,
            'awayGoals'       => $m->awayGoals,
            'result'          => $m->result($this->psgId),
            'penaltyShootout' => $m->penaltyShootout,
            'penaltyScore'    => $m->penaltyScore,
            'possession'      => $m->psgPossession,
            'venue'           => $m->venue,
        ];
    }
}
