<?php
declare(strict_types=1);
// Représente un événement de match (but, carton, changement...).
final class Event
{
    public function __construct(
        public readonly int $id,
        public readonly int $matchId,
        public readonly ?int $playerId,
        public readonly ?int $relatedPlayerId,
        public readonly string $type,
        public readonly int $minute,
        public readonly bool $stoppage,
        public readonly ?string $description,
    ) {}

    public static function fromRow(array $r): self
    {
        return new self(
            (int) $r['id'], (int) $r['match_id'],
            $r['player_id'] !== null ? (int) $r['player_id'] : null,
            $r['related_player_id'] !== null ? (int) $r['related_player_id'] : null,
            (string) $r['type'], (int) $r['minute'], (bool) $r['stoppage'],
            $r['description'] ?? null,
        );
    }
}
