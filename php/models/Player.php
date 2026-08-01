<?php
declare(strict_types=1);
// Représente un joueur pour une saison donnée.
final class Player
{
    public function __construct(
        public readonly int $id,
        public readonly int $seasonId,
        public readonly ?int $shirtNumber,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $position,
        public readonly ?string $detailedPosition,
        public readonly ?string $foot,
        public readonly string $nationality,
        public readonly ?string $birthDate,
        public readonly ?int $heightCm,
        public readonly bool $isCaptain,
    ) {}

    public static function fromRow(array $r): self
    {
        return new self(
            (int) $r['id'], (int) $r['season_id'],
            $r['shirt_number'] !== null ? (int) $r['shirt_number'] : null,
            (string) $r['first_name'], (string) $r['last_name'],
            (string) $r['position'], $r['detailed_position'] ?? null, $r['foot'] ?? null,
            (string) $r['nationality'], $r['birth_date'] ?? null,
            $r['height_cm'] !== null ? (int) $r['height_cm'] : null,
            (bool) $r['is_captain'],
        );
    }

    public function fullName(): string
    {
        return trim("{$this->firstName} {$this->lastName}");
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->firstName, 0, 1) . mb_substr($this->lastName, 0, 1));
    }
}
