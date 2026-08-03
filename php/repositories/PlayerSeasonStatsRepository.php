<?php
declare(strict_types=1);
// Accès aux bilans de saison par joueur (données vérifiées, toutes compétitions).
final class PlayerSeasonStatsRepository extends Repository
{
    public function forPlayer(int $playerId): ?PlayerSeasonStats
    {
        $row = $this->fetchOne('SELECT * FROM player_season_stats WHERE player_id = ?', [$playerId]);
        return $row ? PlayerSeasonStats::fromRow($row) : null;
    }

    /** @return array<int,PlayerSeasonStats> indexé par player_id, du plus prolifique au moins */
    public function all(): array
    {
        $bilans = [];
        foreach ($this->fetchAll('SELECT * FROM player_season_stats ORDER BY goals DESC, assists DESC') as $row) {
            $bilans[(int) $row['player_id']] = PlayerSeasonStats::fromRow($row);
        }
        return $bilans;
    }
}
