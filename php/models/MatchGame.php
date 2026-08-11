<?php
declare(strict_types=1);
// Représente un match (le mot Match est réservé en PHP).
final class MatchGame
{
    public function __construct(
        public readonly int $id,
        public readonly int $seasonId,
        public readonly int $competitionId,
        public readonly ?string $roundLabel,
        public readonly string $playedAt,
        public readonly int $homeTeamId,
        public readonly int $awayTeamId,
        public readonly int $homeGoals,
        public readonly int $awayGoals,
        public readonly bool $wentToExtra,
        public readonly bool $penaltyShootout,
        public readonly ?string $penaltyScore,
        public readonly string $venue,
        public readonly ?int $attendance,
        public readonly ?float $psgPossession,
        public readonly ?int $psgShots,
        public readonly ?int $psgShotsOnTarget,
        public readonly int $sourceId,
    ) {}

    public static function fromRow(array $r): self
    {
        return new self(
            (int) $r['id'], (int) $r['season_id'], (int) $r['competition_id'],
            $r['round_label'] ?? null, (string) $r['played_at'],
            (int) $r['home_team_id'], (int) $r['away_team_id'],
            (int) $r['home_goals'], (int) $r['away_goals'],
            (bool) $r['went_to_extra'], (bool) $r['penalty_shootout'],
            $r['penalty_score'] ?? null,
            (string) ($r['venue'] ?? 'home'),
            $r['attendance'] !== null ? (int) $r['attendance'] : null,
            $r['psg_possession'] !== null ? (float) $r['psg_possession'] : null,
            $r['psg_shots'] !== null ? (int) $r['psg_shots'] : null,
            $r['psg_shots_on_target'] !== null ? (int) $r['psg_shots_on_target'] : null,
            (int) $r['source_id'],
        );
    }

    public function psgGoals(int $psgTeamId): int
    {
        return $this->homeTeamId === $psgTeamId ? $this->homeGoals : $this->awayGoals;
    }

    public function opponentGoals(int $psgTeamId): int
    {
        return $this->homeTeamId === $psgTeamId ? $this->awayGoals : $this->homeGoals;
    }

    public function result(int $psgTeamId): string
    {
        $for = $this->psgGoals($psgTeamId);
        $against = $this->opponentGoals($psgTeamId);
        if ($for > $against) {
            return 'W';
        }
        return $for === $against ? 'D' : 'L';
    }
}
