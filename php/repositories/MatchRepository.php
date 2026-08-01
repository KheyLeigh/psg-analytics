<?php
declare(strict_types=1);
// Accès aux matches : derniers matches, pagination filtrée, lecture unitaire.
final class MatchRepository extends Repository
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
