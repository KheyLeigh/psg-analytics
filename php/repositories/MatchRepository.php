<?php
declare(strict_types=1);
// Accès aux matches : derniers matches, pagination filtrée, lecture unitaire.
// Non final : les tests de services la sous-classent en doublure (idiome du plan Phase 5).
class MatchRepository extends Repository
{
    public function find(int $id): ?MatchGame
    {
        $row = $this->fetchOne('SELECT * FROM matches WHERE id = ?', [$id]);
        return $row ? MatchGame::fromRow($row) : null;
    }

    public function recent(int $limit): array
    {
        return array_map(
            MatchGame::fromRow(...),
            $this->fetchAll("SELECT * FROM matches ORDER BY played_at DESC LIMIT {$limit}")
        );
    }

    public function paginate(int $page, int $perPage, ?int $competitionId, ?string $result, int $psgTeamId): array
    {
        [$conditions, $params] = $this->buildFilters($competitionId, $result, $psgTeamId);
        $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $total = (int) $this->fetchOne("SELECT COUNT(*) c FROM matches {$where}", $params)['c'];
        $offset = ($page - 1) * $perPage;
        $rows = $this->fetchAll(
            "SELECT * FROM matches {$where} ORDER BY played_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['items' => array_map(MatchGame::fromRow(...), $rows), 'total' => $total];
    }

    // Bilan Ligue 1 de PSG en une requête portable : V/N/D, buts, clean sheets,
    // possession moyenne (NULL si non renseignée dans les données sources).
    public function seasonRecord(int $psgTeamId): array
    {
        $row = $this->fetchOne(
            "SELECT
                SUM(CASE
                    WHEN (m.home_team_id = :psg1 AND m.home_goals > m.away_goals)
                      OR (m.away_team_id = :psg2 AND m.away_goals > m.home_goals) THEN 1 ELSE 0
                END) wins,
                SUM(CASE WHEN m.home_goals = m.away_goals THEN 1 ELSE 0 END) draws,
                SUM(CASE
                    WHEN (m.home_team_id = :psg3 AND m.home_goals < m.away_goals)
                      OR (m.away_team_id = :psg4 AND m.away_goals < m.home_goals) THEN 1 ELSE 0
                END) losses,
                SUM(CASE WHEN m.home_team_id = :psg5 THEN m.home_goals ELSE m.away_goals END) goals_for,
                SUM(CASE WHEN m.home_team_id = :psg6 THEN m.away_goals ELSE m.home_goals END) goals_against,
                SUM(CASE
                    WHEN (m.home_team_id = :psg7 AND m.away_goals = 0)
                      OR (m.away_team_id = :psg8 AND m.home_goals = 0) THEN 1 ELSE 0
                END) clean_sheets,
                COALESCE(AVG(m.psg_possession), 0) avg_possession,
                COUNT(*) played
             FROM matches m
             JOIN competitions c ON c.id = m.competition_id
             WHERE c.name = 'Ligue 1' AND (m.home_team_id = :psg9 OR m.away_team_id = :psg10)",
            [
                'psg1' => $psgTeamId, 'psg2' => $psgTeamId, 'psg3' => $psgTeamId, 'psg4' => $psgTeamId,
                'psg5' => $psgTeamId, 'psg6' => $psgTeamId, 'psg7' => $psgTeamId, 'psg8' => $psgTeamId,
                'psg9' => $psgTeamId, 'psg10' => $psgTeamId,
            ]
        ) ?? [];

        return [
            'wins'           => (int) ($row['wins'] ?? 0),
            'draws'          => (int) ($row['draws'] ?? 0),
            'losses'         => (int) ($row['losses'] ?? 0),
            'goals_for'      => (int) ($row['goals_for'] ?? 0),
            'goals_against'  => (int) ($row['goals_against'] ?? 0),
            'clean_sheets'   => (int) ($row['clean_sheets'] ?? 0),
            'avg_possession' => (float) ($row['avg_possession'] ?? 0),
            'played'         => (int) ($row['played'] ?? 0),
        ];
    }

    private function buildFilters(?int $competitionId, ?string $result, int $psgTeamId): array
    {
        $conditions = [];
        $params = [];
        if ($competitionId !== null) {
            $conditions[] = 'competition_id = :competition';
            $params['competition'] = $competitionId;
        }
        if ($result !== null) {
            $conditions[] = self::resultCase() . ' = :result';
            $params['psg_home'] = $psgTeamId;
            $params['psg_away'] = $psgTeamId;
            $params['result'] = $result;
        }
        return [$conditions, $params];
    }

    // Détermine le résultat (W/D/L) du point de vue PSG, calculé directement en SQL.
    private static function resultCase(): string
    {
        return "CASE
            WHEN (home_team_id = :psg_home AND home_goals > away_goals)
              OR (away_team_id = :psg_away AND away_goals > home_goals) THEN 'W'
            WHEN home_goals = away_goals THEN 'D'
            ELSE 'L'
        END";
    }
}
