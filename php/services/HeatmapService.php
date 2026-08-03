<?php
declare(strict_types=1);
// Construit une matrice buts marqués x (joueur, mois) pour affichage en heatmap.
final class HeatmapService
{
    public function __construct(
        private StatisticRepository $stats,
        private PlayerRepository $players,
    ) {}

    public function goalsByPlayerAndMonth(): array
    {
        $months = [];
        $cellsByPlayer = [];
        foreach ($this->stats->goalsByPlayerAndMonth() as $row) {
            if (!in_array($row['month'], $months, true)) {
                $months[] = $row['month'];
            }
            $cellsByPlayer[$row['playerId']][$row['month']] = $row['goals'];
        }
        sort($months);

        $rows = [];
        foreach ($cellsByPlayer as $playerId => $cells) {
            $player = $this->players->find($playerId);
            if ($player === null) {
                continue;
            }
            $filled = [];
            foreach ($months as $month) {
                $filled[$month] = $cells[$month] ?? 0;
            }
            $rows[] = ['player' => $player, 'cells' => $filled];
        }

        return ['months' => $months, 'rows' => $rows];
    }
}
