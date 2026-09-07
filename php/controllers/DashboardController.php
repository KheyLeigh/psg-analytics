<?php
declare(strict_types=1);

// Page phare du site : agrège les données RÉELLES (KpiService, repositories) et les
// prépare pour un rendu SSR "Matchday" pour la saison sélectionnée (?saison=, saison
// courante par défaut). Les charts sont hydratés côté client à partir des données
// embarquées et de l'API ; la page reste lisible sans JavaScript.
final class DashboardController extends Controller
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
        $this->render('dashboard', $this->buildViewData($season));
    }

    // Assemble les données réelles du dashboard pour une saison donnée, isolé de
    // index() (aucun rendu ni effet de bord) pour rester testable.
    public function buildViewData(Season $season): array
    {
        $seasonId = $season->id;
        $psgId = $this->teams->psgId();
        $leagueId = $this->comps->leagueId() ?? 0;

        $kpi = (new KpiService($this->stats, $this->matches, $this->comps, $psgId, $seasonId))->dashboard();
        $record = $this->matches->seasonRecord($seasonId, $psgId, $leagueId);

        // Points cumulés : reconstruits côté serveur (aucun endpoint).
        $points = $this->matches->cumulativePoints($seasonId, $psgId, $leagueId);
        $totalPoints = $points !== [] ? (int) end($points)['y'] : ($record['wins'] * 3 + $record['draws']);

        // Cinq derniers matchs (toutes compétitions) et forme lue du plus ancien au récent.
        $recent = $this->matches->recentDetailed($seasonId, $psgId, 5);
        $form = array_reverse(array_map(static fn (array $m): string => $m['result'], $recent));

        // Meilleurs buteurs de Ligue 1 de la saison sélectionnée.
        $scorers = $this->stats->topScorers($seasonId, 5, $leagueId);
        $topScorers = array_map(static fn (array $s): array => [
            'label' => $s['player']->lastName,
            'value' => $s['goals'],
        ], $scorers);

        return [
            'title'       => 'Dashboard · PSG Analytics',
            'page'        => 'dashboard',
            'kpi'         => $kpi,
            'record'      => $record,
            'totalPoints' => $totalPoints,
            'points'      => $points,
            'recent'      => $recent,
            'form'        => $form,
            'topScorers'  => $topScorers,
        ] + $this->seasons->navData($season);
    }
}
