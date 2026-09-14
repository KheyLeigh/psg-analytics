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

        // Meilleurs buteurs et passeurs de Ligue 1 de la saison sélectionnée.
        $topScorers = self::toBars($this->stats->topScorers($seasonId, 5, $leagueId), 'goals');
        $topAssists = self::toBars($this->stats->topAssists($seasonId, 5, $leagueId), 'assists');

        // Idem pour la Ligue des Champions. Peuvent être vides (aucun but/passe
        // inscrit sur la compétition pour la saison, ex. phase de groupes non
        // encore jouée) : jamais de donnée fabriquée, la carte s'efface alors
        // côté vue.
        $uclId = $this->comps->championsLeagueId();
        $topScorersLdc = $uclId !== null ? self::toBars($this->stats->topScorers($seasonId, 5, $uclId), 'goals') : [];
        $topAssistsLdc = $uclId !== null ? self::toBars($this->stats->topAssists($seasonId, 5, $uclId), 'assists') : [];

        return [
            'title'         => 'Dashboard · PSG Analytics',
            'page'          => 'dashboard',
            'kpi'           => $kpi,
            'record'        => $record,
            'totalPoints'   => $totalPoints,
            'points'        => $points,
            'recent'        => $recent,
            'form'          => $form,
            'topScorers'    => $topScorers,
            'topScorersLdc' => $topScorersLdc,
            'topAssists'    => $topAssists,
            'topAssistsLdc' => $topAssistsLdc,
        ] + $this->seasons->navData($season);
    }

    // Réduit une liste topScorers()/topAssists() en paires label/value pour un
    // bar chart : repli sur le prénom pour les joueurs à nom unique (ex.
    // Marquinhos, Vitinha), lastName vide sinon.
    private static function toBars(array $rows, string $valueKey): array
    {
        return array_map(static fn (array $r): array => [
            'label' => $r['player']->lastName !== '' ? $r['player']->lastName : $r['player']->firstName,
            'value' => $r[$valueKey],
        ], $rows);
    }
}
