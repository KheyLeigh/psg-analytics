<?php
declare(strict_types=1);
// Représente une équipe (club).
final class Team
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $shortName,
        public readonly string $country,
        public readonly bool $isPsg,
    ) {}

    public static function fromRow(array $r): self
    {
        return new self(
            (int) $r['id'], (string) $r['name'], (string) $r['short_name'],
            (string) $r['country'], (bool) $r['is_psg'],
        );
    }
}
