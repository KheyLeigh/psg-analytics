<?php
declare(strict_types=1);
// Classements de joueurs sur des métriques vérifiées, saison complète toutes compétitions.
final class RankingService
{
    // Liste blanche : seules ces métriques peuvent être triées (jamais de tri sur clé brute).
    private const METRICS = ['goals', 'assists', 'goal_contributions', 'appearances', 'yellow_cards', 'red_cards'];

    public function __construct(
        private PlayerSeasonStatsRepository $seasonStats,
        private PlayerRepository $players,
    ) {}

    public function byMetric(string $metric, int $limit): array
    {
        if (!in_array($metric, self::METRICS, true)) {
            throw new InvalidArgumentException("métrique de classement inconnue : {$metric}");
        }

        $rows = [];
        foreach ($this->seasonStats->all() as $playerId => $bilan) {
            $player = $this->players->find($playerId);
            if ($player === null) {
                continue;
            }
            $rows[] = ['player' => $player, 'value' => $this->valueFor($bilan, $metric)];
        }

        usort($rows, static fn (array $a, array $b): int => $b['value'] <=> $a['value']);

        return array_slice($rows, 0, max(0, $limit));
    }

    private function valueFor(PlayerSeasonStats $bilan, string $metric): int
    {
        return match ($metric) {
            'goals'               => $bilan->goals,
            'assists'             => $bilan->assists,
            'goal_contributions'  => $bilan->goalContributions(),
            'appearances'         => $bilan->appearances,
            'yellow_cards'        => $bilan->yellowCards,
            'red_cards'           => $bilan->redCards,
        };
    }
}
