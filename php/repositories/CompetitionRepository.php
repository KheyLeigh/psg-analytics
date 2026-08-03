<?php
declare(strict_types=1);
// Accès aux compétitions et au bilan (V/N/D, buts) de PSG par compétition.
// Non final : les tests de services la sous-classent en doublure (idiome du plan Phase 5).
class CompetitionRepository extends Repository
{
    public function all(): array
    {
        return array_map(
            Competition::fromRow(...),
            $this->fetchAll('SELECT * FROM competitions ORDER BY name')
        );
    }

    // Identifiant de l'unique compétition de type "league" (Ligue 1), ou null si absente.
    public function leagueId(): ?int
    {
        $row = $this->fetchOne("SELECT id FROM competitions WHERE type = 'league'");
        return $row !== null ? (int) $row['id'] : null;
    }

    public function standings(int $psgTeamId): array
    {
        $rows = $this->fetchAll(
            "SELECT c.id competition_id, c.name competition_name,
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
                    SUM(CASE WHEN m.home_team_id = :psg6 THEN m.away_goals ELSE m.home_goals END) goals_against
             FROM matches m
             JOIN competitions c ON c.id = m.competition_id
             GROUP BY c.id, c.name
             ORDER BY c.name",
            [
                'psg1' => $psgTeamId, 'psg2' => $psgTeamId, 'psg3' => $psgTeamId,
                'psg4' => $psgTeamId, 'psg5' => $psgTeamId, 'psg6' => $psgTeamId,
            ]
        );

        return array_map(static function (array $r): array {
            return [
                'competitionId'   => (int) $r['competition_id'],
                'competitionName' => (string) $r['competition_name'],
                'wins'            => (int) $r['wins'],
                'draws'           => (int) $r['draws'],
                'losses'          => (int) $r['losses'],
                'goalsFor'        => (int) $r['goals_for'],
                'goalsAgainst'    => (int) $r['goals_against'],
            ];
        }, $rows);
    }
}
