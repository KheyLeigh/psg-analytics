<?php
declare(strict_types=1);
// Frontière HTTP de l'API statistiques : délègue à KpiService/HeatmapService pour la
// saison sélectionnée (?saison=, saison courante par défaut), enveloppe la réponse.
final class StatsApiController extends Controller
{
    public function __construct(
        private ?KpiService $kpi = null,
        private ?HeatmapService $heatmap = null,
        private ?TeamRepository $teams = null,
        private ?SeasonRepository $seasons = null,
    ) {
        $this->heatmap ??= new HeatmapService(new StatisticRepository(), new PlayerRepository());
        $this->seasons ??= new SeasonRepository();
    }

    private function resolveSeason(Request $r): Season
    {
        $slug = Validator::string($r->query('saison', ''), 16);
        return $this->seasons->resolve($slug !== '' ? $slug : null);
    }

    public function kpis(Request $r, array $params): void
    {
        $this->json($this->buildKpis($this->resolveSeason($r)));
    }

    public function buildKpis(Season $season): array
    {
        $kpi = $this->kpi ?? $this->buildKpiService($season);
        return Response::apiEnvelope($kpi->dashboard());
    }

    // Construction paresseuse : ne résout l'identifiant PSG que si aucune KpiService n'est injectée.
    private function buildKpiService(Season $season): KpiService
    {
        $this->teams ??= new TeamRepository();
        return new KpiService(
            new StatisticRepository(),
            new MatchRepository(),
            new CompetitionRepository(),
            $this->teams->psgId(),
            $season->id,
        );
    }

    public function distribution(Request $r, array $params): void
    {
        $this->json($this->buildDistribution($this->resolveSeason($r)));
    }

    // Répartition des buts par période (mois) : agrégat de la matrice joueur x mois.
    public function buildDistribution(Season $season): array
    {
        $matrix = $this->heatmap->goalsByPlayerAndMonth($season->id);
        $byMonth = array_fill_keys($matrix['months'], 0);
        foreach ($matrix['rows'] as $row) {
            foreach ($row['cells'] as $month => $goals) {
                $byMonth[$month] += $goals;
            }
        }
        return Response::apiEnvelope(['by_month' => $byMonth]);
    }

    public function heatmap(Request $r, array $params): void
    {
        $this->json($this->buildHeatmap($this->resolveSeason($r)));
    }

    public function buildHeatmap(Season $season): array
    {
        $matrix = $this->heatmap->goalsByPlayerAndMonth($season->id);
        $rows = array_map(static fn(array $row): array => [
            'player' => ['id' => $row['player']->id, 'name' => $row['player']->fullName()],
            'cells' => $row['cells'],
        ], $matrix['rows']);

        return Response::apiEnvelope(['months' => $matrix['months'], 'rows' => $rows]);
    }
}
