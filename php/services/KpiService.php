<?php
declare(strict_types=1);
// Agrège les indicateurs clés du tableau de bord depuis les repositories, sans constante en dur.
final class KpiService
{
    public function __construct(
        private StatisticRepository $stats,
        private MatchRepository $matches,
        private CompetitionRepository $comps,
        private int $psgTeamId,
    ) {}

    public function dashboard(): array
    {
        $scorers = $this->stats->topScorers(1, null);
        $record = $this->matches->seasonRecord($this->psgTeamId);
        $played = max(1, (int) $record['played']);
        $topScorer = $scorers[0] ?? null;

        return [
            'top_scorer'      => $topScorer ? ['name' => $topScorer['player']->fullName(), 'goals' => $topScorer['goals']] : null,
            'top_assister'    => $this->topAssister(),
            'wins'            => (int) $record['wins'],
            'draws'           => (int) $record['draws'],
            'losses'          => (int) $record['losses'],
            'clean_sheets'    => (int) $record['clean_sheets'],
            'avg_possession'  => round((float) $record['avg_possession'], 1),
            'goals_per_match' => round((int) $record['goals_for'] / $played, 2),
        ];
    }

    private function topAssister(): ?array
    {
        $rows = $this->stats->topScorers(50, null);
        usort($rows, static fn($a, $b) => $b['assists'] <=> $a['assists']);
        $best = $rows[0] ?? null;
        return $best ? ['name' => $best['player']->fullName(), 'assists' => $best['assists']] : null;
    }
}
