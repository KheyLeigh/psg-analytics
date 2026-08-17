<?php

declare(strict_types=1);

// Page phare du site : agrège les données RÉELLES (KpiService, repositories) et les
// prépare pour un rendu SSR "Matchday". Les charts sont hydratés côté client à partir
// des données embarquées et de l'API ; la page reste lisible sans JavaScript.
final class DashboardController extends Controller
{
    public function __construct(
        private ?StatisticRepository $stats = null,
        private ?MatchRepository $matches = null,
        private ?CompetitionRepository $comps = null,
        private ?TeamRepository $teams = null,
    ) {
        $this->stats ??= new StatisticRepository();
        $this->matches ??= new MatchRepository();
        $this->comps ??= new CompetitionRepository();
        $this->teams ??= new TeamRepository();
    }

    public function index(Request $r, array $params): void
    {
        $psgId = $this->teams->psgId();
        $leagueId = $this->comps->leagueId() ?? 0;

        $kpi = (new KpiService($this->stats, $this->matches, $this->comps, $psgId))->dashboard();
        $record = $this->matches->seasonRecord($psgId, $leagueId);

        // Points cumulés : reconstruits côté serveur (aucun endpoint), total attendu 76.
        $points = $this->matches->cumulativePoints($psgId, $leagueId);
        $totalPoints = $points !== [] ? (int) end($points)['y'] : ($record['wins'] * 3 + $record['draws']);

        // Cinq derniers matchs (toutes compétitions) et forme lue du plus ancien au récent.
        $recent = $this->matches->recentDetailed($psgId, 5);
        $form = array_reverse(array_map(static fn (array $m): string => $m['result'], $recent));

        // Meilleurs buteurs de Ligue 1 : totaux calibrés sur les données vérifiées FBref.
        $scorers = $this->stats->topScorers(5, $leagueId);
        $topScorers = array_map(static fn (array $s): array => [
            'label' => $s['player']->lastName,
            'value' => $s['goals'],
        ], $scorers);

        $this->render('dashboard', [
            'title'       => 'Dashboard · PSG Analytics',
            'page'        => 'dashboard',
            'kpi'         => $kpi,
            'record'      => $record,
            'totalPoints' => $totalPoints,
            'points'      => $points,
            'recent'      => $recent,
            'form'        => $form,
            'topScorers'  => $topScorers,
        ]);
    }
}
