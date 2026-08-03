<?php
declare(strict_types=1);
// Frontière HTTP de l'API statistiques : délègue à KpiService/HeatmapService, enveloppe la réponse.
final class StatsApiController extends Controller
{
    public function __construct(
        private ?KpiService $kpi = null,
        private ?HeatmapService $heatmap = null,
    ) {
        $this->kpi ??= new KpiService(
            new StatisticRepository(),
            new MatchRepository(),
            new CompetitionRepository(),
            PsgTeamResolver::id(),
        );
        $this->heatmap ??= new HeatmapService(new StatisticRepository(), new PlayerRepository());
    }

    public function kpis(Request $r, array $params): void
    {
        Response::json($this->buildKpis());
    }

    public function buildKpis(): array
    {
        return Response::apiEnvelope($this->kpi->dashboard());
    }

    public function distribution(Request $r, array $params): void
    {
        Response::json($this->buildDistribution());
    }

    // Répartition des buts par période (mois) : agrégat de la matrice joueur x mois.
    public function buildDistribution(): array
    {
        $matrix = $this->heatmap->goalsByPlayerAndMonth();
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
        Response::json($this->buildHeatmap());
    }

    public function buildHeatmap(): array
    {
        $matrix = $this->heatmap->goalsByPlayerAndMonth();
        $rows = array_map(static fn(array $row): array => [
            'player' => ['id' => $row['player']->id, 'name' => $row['player']->fullName()],
            'cells' => $row['cells'],
        ], $matrix['rows']);

        return Response::apiEnvelope(['months' => $matrix['months'], 'rows' => $rows]);
    }
}
