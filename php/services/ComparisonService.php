<?php
declare(strict_types=1);
// Compare deux joueurs sur un radar d'axes normalisés (0-1) pour l'affichage.
final class ComparisonService
{
    // Axes du radar ; chacun est mappé explicitement vers la clé réelle du repository.
    private const AXES = ['goals', 'assists', 'minutes', 'shots', 'duels_won', 'rating'];

    public function __construct(
        private StatisticRepository $stats,
        private PlayerRepository $players,
    ) {}

    public function compare(int $idA, int $idB): array
    {
        $valuesA = $this->extractAxes($this->stats->seasonTotalsByPlayer($idA));
        $valuesB = $this->extractAxes($this->stats->seasonTotalsByPlayer($idB));

        [$normalizedA, $normalizedB] = $this->normalize($valuesA, $valuesB);

        return [
            'a' => [
                'player'     => $this->players->find($idA),
                'totals'     => $valuesA,
                'normalized' => $normalizedA,
            ],
            'b' => [
                'player'     => $this->players->find($idB),
                'totals'     => $valuesB,
                'normalized' => $normalizedB,
            ],
            'axes' => self::AXES,
        ];
    }

    // Ramène chaque axe à sa clé réelle : seasonTotalsByPlayer expose duelsWon
    // en camelCase ; duels_won reste accepté pour compatibilité avec des doublures.
    private function extractAxes(array $totals): array
    {
        return [
            'goals'     => (float) ($totals['goals'] ?? 0),
            'assists'   => (float) ($totals['assists'] ?? 0),
            'minutes'   => (float) ($totals['minutes'] ?? 0),
            'shots'     => (float) ($totals['shots'] ?? 0),
            'duels_won' => (float) ($totals['duelsWon'] ?? $totals['duels_won'] ?? 0),
            'rating'    => (float) ($totals['rating'] ?? 0),
        ];
    }

    private function normalize(array $valuesA, array $valuesB): array
    {
        $normalizedA = [];
        $normalizedB = [];
        foreach (self::AXES as $axis) {
            $max = max($valuesA[$axis], $valuesB[$axis]);
            $normalizedA[$axis] = $max > 0 ? $valuesA[$axis] / $max : 0.0;
            $normalizedB[$axis] = $max > 0 ? $valuesB[$axis] / $max : 0.0;
        }
        return [$normalizedA, $normalizedB];
    }
}
