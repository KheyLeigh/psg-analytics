<?php
declare(strict_types=1);
// Bilan individuel d'un joueur sur une saison, toutes compétitions confondues.
final class PlayerSeasonStats
{
    public function __construct(
        public readonly int $playerId,
        public readonly int $seasonId,
        public readonly int $appearances,
        public readonly int $starts,
        public readonly int $goals,
        public readonly int $assists,
        public readonly int $yellowCards,
        public readonly int $redCards,
    ) {}

    public static function fromRow(array $r): self
    {
        return new self(
            (int) $r['player_id'],
            (int) $r['season_id'],
            (int) $r['appearances'],
            (int) $r['starts'],
            (int) $r['goals'],
            (int) $r['assists'],
            (int) $r['yellow_cards'],
            (int) $r['red_cards'],
        );
    }

    // Contribution offensive directe : buts plus passes décisives.
    public function goalContributions(): int
    {
        return $this->goals + $this->assists;
    }
}
