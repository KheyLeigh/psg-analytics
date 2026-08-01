<?php
declare(strict_types=1);
// Agrégats et détails de performance des joueurs (player_match_stats).
final class StatisticRepository extends Repository
{
    public function topScorers(int $limit, ?int $competitionId): array
    {
        $join = $competitionId !== null ? 'JOIN matches m ON m.id = s.match_id AND m.competition_id = :comp' : '';
        $params = $competitionId !== null ? ['comp' => $competitionId] : [];
        $rows = $this->fetchAll(
            "SELECT p.*, SUM(s.goals) goals, SUM(s.assists) assists, SUM(s.minutes) minutes
             FROM player_match_stats s
             JOIN players p ON p.id = s.player_id
             {$join}
             GROUP BY p.id
             HAVING SUM(s.goals) > 0
             ORDER BY goals DESC, assists DESC
             LIMIT {$limit}",
            $params
        );
        return array_map(static function (array $r): array {
            return [
                'player'  => Player::fromRow($r),
                'goals'   => (int) $r['goals'],
                'assists' => (int) $r['assists'],
                'minutes' => (int) $r['minutes'],
            ];
        }, $rows);
    }

    public function seasonTotalsByPlayer(int $playerId): array
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) matches, SUM(minutes) minutes, SUM(goals) goals, SUM(assists) assists,
                    SUM(shots) shots, SUM(shots_on_target) shots_on_target, SUM(passes) passes,
                    SUM(duels_won) duels_won, SUM(yellow_cards) yellow_cards, SUM(red_card) red_cards,
                    SUM(saves) saves, SUM(goals_conceded) goals_conceded, SUM(xg) xg, SUM(xag) xag,
                    AVG(rating) rating
             FROM player_match_stats
             WHERE player_id = ?',
            [$playerId]
        ) ?? [];

        return [
            'matches'        => (int) ($row['matches'] ?? 0),
            'minutes'        => (int) ($row['minutes'] ?? 0),
            'goals'          => (int) ($row['goals'] ?? 0),
            'assists'        => (int) ($row['assists'] ?? 0),
            'shots'          => (int) ($row['shots'] ?? 0),
            'shotsOnTarget'  => (int) ($row['shots_on_target'] ?? 0),
            'passes'         => (int) ($row['passes'] ?? 0),
            'duelsWon'       => (int) ($row['duels_won'] ?? 0),
            'yellowCards'    => (int) ($row['yellow_cards'] ?? 0),
            'redCards'       => (int) ($row['red_cards'] ?? 0),
            'saves'          => (int) ($row['saves'] ?? 0),
            'goalsConceded'  => (int) ($row['goals_conceded'] ?? 0),
            'xg'             => (float) ($row['xg'] ?? 0),
            'xag'            => (float) ($row['xag'] ?? 0),
            'rating'         => $row['rating'] !== null ? (float) $row['rating'] : null,
        ];
    }

    public function timeline(int $playerId): array
    {
        $rows = $this->fetchAll(
            'SELECT m.id match_id, m.played_at, s.goals, s.assists, s.minutes, s.rating
             FROM player_match_stats s
             JOIN matches m ON m.id = s.match_id
             WHERE s.player_id = ?
             ORDER BY m.played_at',
            [$playerId]
        );
        return array_map(static function (array $r): array {
            return [
                'matchId'  => (int) $r['match_id'],
                'playedAt' => (string) $r['played_at'],
                'goals'    => (int) $r['goals'],
                // Passes décisives (assists), pas le total des passes.
                'assists'  => (int) $r['assists'],
                'minutes'  => (int) $r['minutes'],
                'rating'   => $r['rating'] !== null ? (float) $r['rating'] : null,
            ];
        }, $rows);
    }

    public function byMatch(int $matchId): array
    {
        $stats = $this->fetchAll('SELECT * FROM player_match_stats WHERE match_id = ? ORDER BY minutes DESC', [$matchId]);
        if ($stats === []) {
            return [];
        }
        $playerIds = array_values(array_unique(array_map(
            static fn (array $r): int => (int) $r['player_id'],
            $stats
        )));
        $placeholders = implode(',', array_fill(0, count($playerIds), '?'));
        $players = $this->fetchAll("SELECT * FROM players WHERE id IN ({$placeholders})", $playerIds);

        $playersById = [];
        foreach ($players as $row) {
            $playersById[(int) $row['id']] = Player::fromRow($row);
        }

        return array_map(static function (array $r) use ($playersById): array {
            return [
                'player' => $playersById[(int) $r['player_id']],
                'stat'   => Statistic::fromRow($r),
            ];
        }, $stats);
    }
}
