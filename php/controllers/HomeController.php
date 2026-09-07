<?php

declare(strict_types=1);

// Accueil : la couverture éditoriale du site (récit de saison, promesse de
// traçabilité, portes d'entrée vers les pages profondes), pour la saison
// sélectionnée (?saison=, saison courante par défaut). Elle réutilise les mêmes
// accès de données réelles que le Dashboard (KpiService, repositories), mais reste
// chart-light : ici le récit et la navigation priment sur l'atelier analytique.
final class HomeController extends Controller
{
    public function __construct(
        private ?StatisticRepository $stats = null,
        private ?MatchRepository $matches = null,
        private ?CompetitionRepository $comps = null,
        private ?TeamRepository $teams = null,
        private ?SeasonRepository $seasons = null,
    ) {
        $this->stats ??= new StatisticRepository();
        $this->matches ??= new MatchRepository();
        $this->comps ??= new CompetitionRepository();
        $this->teams ??= new TeamRepository();
        $this->seasons ??= new SeasonRepository();
    }

    public function index(Request $r, array $params): void
    {
        $slug = Validator::string($r->query('saison', ''), 16);
        $season = $this->seasons->resolve($slug !== '' ? $slug : null);
        $this->render('home', $this->buildViewData($season));
    }

    // Assemble les données réelles de l'accueil pour une saison donnée. Isolé de
    // index() (aucun rendu ni effet de bord) pour rester testable, comme les
    // contrôleurs d'API du projet.
    public function buildViewData(Season $season): array
    {
        $seasonId = $season->id;
        $psgId = $this->teams->psgId();
        $leagueId = $this->comps->leagueId() ?? 0;

        $kpi = (new KpiService($this->stats, $this->matches, $this->comps, $psgId, $seasonId))->dashboard();
        $record = $this->matches->seasonRecord($seasonId, $psgId, $leagueId);

        $points = $this->matches->cumulativePoints($seasonId, $psgId, $leagueId);
        $totalPoints = $points !== [] ? (int) end($points)['y'] : ($record['wins'] * 3 + $record['draws']);

        $recent = $this->matches->recentDetailed($seasonId, $psgId, 5);
        $form = array_reverse(array_map(static fn (array $m): string => $m['result'], $recent));

        $scorers = $this->stats->topScorers($seasonId, 5, $leagueId);
        $topGoals = $scorers !== [] ? (int) $scorers[0]['goals'] : 0;
        $topScorers = array_map(static fn (array $s): array => [
            'name'  => $s['player']->lastName,
            'first' => $s['player']->firstName,
            'goals' => (int) $s['goals'],
        ], $scorers);

        return [
            'title'       => 'PSG Analytics · La saison remonte à sa source',
            'page'        => 'home',
            'record'      => $record,
            'totalPoints' => $totalPoints,
            'kpi'         => $kpi,
            'recent'      => $recent,
            'form'        => $form,
            'topScorers'  => $topScorers,
            'topGoals'    => $topGoals,
        ] + $this->seasons->navData($season);
    }
}
