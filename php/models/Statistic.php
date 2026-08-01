<?php
declare(strict_types=1);
// Représente la performance d'un joueur sur un match (player_match_stats).
final class Statistic
{
    public function __construct(
        public readonly int $id,
        public readonly int $playerId,
        public readonly int $matchId,
        public readonly bool $isStarter,
        public readonly int $minutes,
        public readonly int $goals,
        public readonly int $assists,
        public readonly int $shots,
        public readonly int $shotsOnTarget,
        public readonly int $passes,
        public readonly ?float $passAccuracy,
        public readonly int $duelsWon,
        public readonly int $yellowCards,
        public readonly bool $redCard,
        public readonly int $saves,
        public readonly int $goalsConceded,
        public readonly ?float $rating,
        public readonly float $xg,
        public readonly float $xag,
        public readonly int $sourceId,
    ) {}

    public static function fromRow(array $r): self
    {
        return new self(
            (int) $r['id'], (int) $r['player_id'], (int) $r['match_id'],
            (bool) $r['is_starter'], (int) $r['minutes'], (int) $r['goals'], (int) $r['assists'],
            (int) $r['shots'], (int) $r['shots_on_target'], (int) $r['passes'],
            $r['pass_accuracy'] !== null ? (float) $r['pass_accuracy'] : null,
            (int) $r['duels_won'], (int) $r['yellow_cards'], (bool) $r['red_card'],
            (int) $r['saves'], (int) $r['goals_conceded'],
            $r['rating'] !== null ? (float) $r['rating'] : null,
            (float) $r['xg'], (float) $r['xag'], (int) $r['source_id'],
        );
    }
}
