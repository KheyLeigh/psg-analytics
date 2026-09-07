<?php
declare(strict_types=1);
// Agrégats et détails de performance des joueurs (player_match_stats).
// Non final : les tests de services la sous-classent en doublure (idiome du plan Phase 5).
class StatisticRepository extends Repository
{
    public function topScorers(int $seasonId, int $limit, ?int $competitionId): array
    {
        $compFilter = $competitionId !== null ? ' AND m.competition_id = :comp' : '';
        $params = ['season' => $seasonId] + ($competitionId !== null ? ['comp' => $competitionId] : []);
        $rows = $this->fetchAll(
            "SELECT p.*, SUM(s.goals) goals, SUM(s.assists) assists, SUM(s.minutes) minutes
             FROM player_match_stats s
             JOIN players p ON p.id = s.player_id
             JOIN matches m ON m.id = s.match_id
             WHERE m.season_id = :season{$compFilter}
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
                    SUM(duels_won) duels_won, SUM(interceptions) interceptions,
                    SUM(yellow_cards) yellow_cards, SUM(red_card) red_cards,
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
            'interceptions'  => (int) ($row['interceptions'] ?? 0),
            'yellowCards'    => (int) ($row['yellow_cards'] ?? 0),
            'redCards'       => (int) ($row['red_cards'] ?? 0),
            'saves'          => (int) ($row['saves'] ?? 0),
            'goalsConceded'  => (int) ($row['goals_conceded'] ?? 0),
            'xg'             => (float) ($row['xg'] ?? 0),
            'xag'            => (float) ($row['xag'] ?? 0),
            'rating'         => $row['rating'] !== null ? (float) $row['rating'] : null,
        ];
    }

    // Maxima de l'effectif par axe du radar de profil, pour une saison donnée : chaque
    // axe est le meilleur total d'un joueur de cette saison, pour normaliser un profil
    // (valeur du joueur / meilleur total de l'axe) sur une lecture de 0 à 1.
    public function squadAxisMax(int $seasonId): array
    {
        $row = $this->fetchOne(
            'SELECT MAX(g) goals, MAX(a) assists, MAX(mn) minutes, MAX(sh) shots,
                    MAX(dw) duels_won, MAX(rt) rating
             FROM (
                 SELECT SUM(s.goals) g, SUM(s.assists) a, SUM(s.minutes) mn, SUM(s.shots) sh,
                        SUM(s.duels_won) dw, AVG(s.rating) rt
                 FROM player_match_stats s
                 JOIN matches m ON m.id = s.match_id
                 WHERE m.season_id = :season
                 GROUP BY s.player_id
             ) t',
            ['season' => $seasonId]
        ) ?? [];

        return [
            'goals'    => (float) ($row['goals'] ?? 0),
            'assists'  => (float) ($row['assists'] ?? 0),
            'minutes'  => (float) ($row['minutes'] ?? 0),
            'shots'    => (float) ($row['shots'] ?? 0),
            'duelsWon' => (float) ($row['duels_won'] ?? 0),
            'rating'   => (float) ($row['rating'] ?? 0),
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

    // Buts marqués par joueur et par mois, pour une saison donnée ; regroupement
    // portable via SUBSTR (les 7 premiers caractères de played_at, format AAAA-MM-JJ).
    public function goalsByPlayerAndMonth(int $seasonId): array
    {
        $rows = $this->fetchAll(
            "SELECT s.player_id, SUBSTR(m.played_at, 1, 7) month, SUM(s.goals) goals
             FROM player_match_stats s
             JOIN matches m ON m.id = s.match_id
             WHERE m.season_id = :season
             GROUP BY s.player_id, SUBSTR(m.played_at, 1, 7)
             HAVING SUM(s.goals) > 0
             ORDER BY month, s.player_id",
            ['season' => $seasonId]
        );
        return array_map(static function (array $r): array {
            return [
                'playerId' => (int) $r['player_id'],
                'month'    => (string) $r['month'],
                'goals'    => (int) $r['goals'],
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
